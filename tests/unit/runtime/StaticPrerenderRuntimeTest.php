<?php
/**
 * Static prerender runtime tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Runtime_Cache;
use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Static_Prerender_Content_Hasher;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\TestCase;

/**
 * Tests request safety and incremental dependency-graph behavior.
 */
class StaticPrerenderRuntimeTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$this->root = trailingslashit( get_temp_dir() ) . 'blockstudio-prerender-runtime-' . wp_generate_uuid4();
		wp_mkdir_p( $this->root . '/fixtures' );
		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		Settings::reset();
		Runtime_Settings::reset();
		Runtime_Cache::reset_diagnostics();
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Runtime::reset_request_cache();
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		remove_all_filters( 'blockstudio/performance/staticPrerender/dynamicPaths' );
		remove_all_filters( 'blockstudio/performance/staticPrerender/serveLoggedIn' );
		remove_all_filters( 'blockstudio/static_prerender/public_urls' );
		Settings::reset();
		Runtime_Settings::reset();
		Runtime_Cache::reset_diagnostics();
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Runtime::reset_request_cache();
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root . '/cache';
	}

	public function filter_site_key(): string {
		return 'unit-runtime';
	}

	public function test_request_safety_rejects_personalized_dynamic_and_control_requests(): void {
		$safe = array(
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/public/',
			'HTTP_HOST'      => 'example.test',
		);
		$this->assertFalse( Static_Prerender_Runtime::should_bypass_request( $safe, array() ) );
		$this->assertTrue(
			Static_Prerender_Runtime::should_bypass_request(
				array_merge( $safe, array( 'REQUEST_METHOD' => 'POST' ) ),
				array()
			)
		);
		$this->assertTrue(
			Static_Prerender_Runtime::should_bypass_request(
				array_merge( $safe, array( 'REQUEST_URI' => '/public/?preview=1' ) ),
				array()
			)
		);
		$this->assertTrue(
			Static_Prerender_Runtime::should_bypass_request(
				array_merge( $safe, array( 'HTTP_X_BLOCKSTUDIO_PREVIEW' => '1' ) ),
				array()
			)
		);
		$this->assertTrue(
			Static_Prerender_Runtime::should_bypass_request(
				array_merge( $safe, array( 'REQUEST_URI' => '/wp-admin/edit.php' ) ),
				array()
			)
		);
		$this->assertTrue(
			Static_Prerender_Runtime::should_bypass_request(
				$safe,
				array( 'wordpress_logged_in_hash' => 'user' )
			)
		);
		$this->assertTrue(
			Static_Prerender_Runtime::request_belongs_to_user(
				array( 'comment_author_email_hash' => 'person@example.test' )
			)
		);
	}

	public function test_dynamic_prefixes_match_path_boundaries_only(): void {
		$dynamic = static fn(): array => array( '/private', '/account/orders' );
		add_filter( 'blockstudio/performance/staticPrerender/dynamicPaths', $dynamic );
		Runtime_Settings::reset();

		$this->assertTrue( Static_Prerender_Runtime::is_dynamic_path( '/private' ) );
		$this->assertTrue( Static_Prerender_Runtime::is_dynamic_path( '/private/child/' ) );
		$this->assertTrue( Static_Prerender_Runtime::is_dynamic_path( '/account/orders/42/' ) );
		$this->assertFalse( Static_Prerender_Runtime::is_dynamic_path( '/privateer/' ) );
		$this->assertFalse( Static_Prerender_Runtime::is_dynamic_path( '/account/order/' ) );

		remove_filter( 'blockstudio/performance/staticPrerender/dynamicPaths', $dynamic );
		Runtime_Settings::reset();
	}

	public function test_filtered_public_urls_still_enforce_dynamic_and_control_boundaries(): void {
		$dynamic = static fn(): array => array( '/private' );
		$urls    = static fn(): array => array(
			home_url( '/private/' ),
			home_url( '/wp-json/' ),
			home_url( '/wp-jsonfoo/' ),
		);
		add_filter( 'blockstudio/performance/staticPrerender/dynamicPaths', $dynamic );
		add_filter( 'blockstudio/static_prerender/public_urls', $urls );
		Runtime_Settings::reset();

		$this->assertSame( array( home_url( '/wp-jsonfoo/' ) ), Static_Prerender_Runtime::public_urls() );

		remove_filter( 'blockstudio/performance/staticPrerender/dynamicPaths', $dynamic );
		remove_filter( 'blockstudio/static_prerender/public_urls', $urls );
		Runtime_Settings::reset();
	}

	public function test_cache_keys_are_stable_and_query_strings_are_never_keys(): void {
		$url = 'https://Example.test:8443/Products/Card/';
		$key = Static_Prerender_Runtime::cache_key_for_url( $url );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', (string) $key );
		$this->assertSame(
			$key,
			Static_Prerender_Runtime::cache_key_for_request(
				array(
					'HTTP_HOST'   => 'example.test:8443',
					'REQUEST_URI' => '/Products/Card/',
				)
			)
		);
		$this->assertNull(
			Static_Prerender_Runtime::cache_key_for_request(
				array(
					'HTTP_HOST'   => 'example.test',
					'REQUEST_URI' => '/products/card/?color=blue',
				)
			)
		);
	}

	public function test_cacheability_requires_a_complete_opted_in_html_document(): void {
		$this->assertTrue(
			Static_Prerender_Runtime::cacheable_html(
				'<!doctype html><html><body>Ready</body></html>'
			)
		);
		$this->assertFalse( Static_Prerender_Runtime::cacheable_html( '<main>Fragment</main>' ) );
		$this->assertFalse(
			Static_Prerender_Runtime::cacheable_html(
				'<html><body><!-- blockstudio:no-cache -->Private</body></html>'
			)
		);
	}

	public function test_target_rewrite_preserves_relative_routes_without_using_the_first_page_as_home(): void {
		$source = home_url( '/products/card/' );

		$this->assertSame(
			'https://cdn.example/preview/products/card/',
			Static_Prerender_Runtime::target_url( $source, 'cdn.example', '/preview' )
		);
		$this->assertSame(
			'https://cdn.example/products/card/',
			Static_Prerender_Runtime::target_url( $source, 'https://cdn.example', null )
		);
	}

	public function test_changed_only_graph_build_does_not_invalidate_an_unrelated_page(): void {
		$alpha_dependency = $this->root . '/fixtures/alpha.php';
		$beta_dependency  = $this->root . '/fixtures/beta.php';
		file_put_contents( $alpha_dependency, 'alpha-v1' );
		file_put_contents( $beta_dependency, 'beta-v1' );

		$alpha = home_url( '/alpha/' );
		$beta  = home_url( '/beta/' );
		$html  = '<!doctype html><html><body>Rendered</body></html>';

		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response(
				$alpha,
				$html,
				array( $alpha_dependency ),
				array( 'post' => 'alpha' )
			)
		);
		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response(
				$beta,
				$html,
				array( $beta_dependency ),
				array( 'post' => 'beta' )
			)
		);

		$warm = Static_Prerender_Runtime::build_plan( array( $alpha, $beta ) );
		$this->assertSame( 0, $warm['affected'] );
		$this->assertSame( 2, $warm['unchanged'] );

		file_put_contents( $alpha_dependency, 'alpha-v2' );
		Static_Prerender_Content_Hasher::reset();
		$changed = Static_Prerender_Runtime::build_plan( array( $alpha, $beta ) );

		$this->assertSame( array( $alpha ), $changed['affectedUrls'] );
		$this->assertSame( array( $beta ), $changed['unchangedUrls'] );
		$this->assertContains( 'external/alpha.php', $changed['changedFiles'] );

		$routes = Static_Prerender_Runtime::route_map( array( $alpha, $beta ) );
		$this->assertSame( array( '/alpha/', '/beta/' ), array_keys( $routes ) );
		$this->assertSame( 2, count( glob( Static_Prerender_Runtime::cache_root_path() . '/*.html' ) ) );

		$collected = Static_Prerender_Runtime::garbage_collect( array( $alpha ) );
		$this->assertSame( 1, $collected['deletedRecords'] );
		$this->assertSame( 1, $collected['deletedFiles'] );
		$this->assertSame( 1, $collected['liveFiles'] );
	}

	public function test_runtime_status_reports_shared_storage_and_diagnostics(): void {
		Runtime_Cache::write( 'diagnostic', 'sample', 'value' );
		$status = Static_Prerender_Runtime::status();

		$this->assertSame( Static_Prerender_Runtime::cache_root_path(), $status['cacheRoot'] );
		$this->assertArrayHasKey( 'queue', $status );
		$this->assertSame( 1, $status['diagnostics']['diagnostic']['write'] ?? 0 );
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $directory );
	}
}

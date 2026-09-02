<?php
/**
 * Static prerender early-serve tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Static_Prerender_Early_Serve;
use Blockstudio\Static_Prerender_Identity;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\TestCase;

/**
 * Tests safe map/drop-in ownership and atomic graph activation.
 */
class StaticPrerenderEarlyServeTest extends TestCase {

	private const CONFIG_SOURCE = "<?php\n// Test configuration.\n";

	private string $root;

	private string $cache;

	private string $config;

	protected function setUp(): void {
		$this->root   = trailingslashit( get_temp_dir() ) . 'blockstudio-early-serve-' . wp_generate_uuid4();
		$this->cache  = $this->root . '/cache';
		$this->config = $this->root . '/wp-config.php';
		wp_mkdir_p( $this->cache );
		file_put_contents( $this->config, self::CONFIG_SOURCE );

		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		$this->enable_early_serve();
		Static_Prerender_Identity::reset();
		Static_Prerender_Early_Serve::override_content_dir_for_testing( $this->root );
		Static_Prerender_Early_Serve::override_config_path_for_testing( $this->config );
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		$this->disable_early_serve();
		Static_Prerender_Identity::reset();
		Static_Prerender_Early_Serve::override_content_dir_for_testing( null );
		Static_Prerender_Early_Serve::override_config_path_for_testing( null );
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root . '/runtime-cache';
	}

	public function filter_site_key(): string {
		return 'unit-early-serve';
	}

	public function test_graph_install_validates_every_route_and_collects_obsolete_html(): void {
		$site = Static_Prerender_Early_Serve::current_site_identity();
		$this->assertIsArray( $site );

		$live_key  = str_repeat( 'a', 64 );
		$stale_key = str_repeat( 'f', 64 );
		file_put_contents( $this->cache . '/' . $live_key . '.html', '<html>Live</html>' );
		file_put_contents( $this->cache . '/' . $stale_key . '.html', '<html>Stale</html>' );

		$route = $site['home_path'];
		$entry = array(
			'host'            => $site['host'],
			'home_path'       => $site['home_path'],
			'site_id'         => get_current_blog_id(),
			'mode'            => 'graph',
			'build_id'        => str_repeat( 'b', 32 ),
			'signature'       => str_repeat( 'c', 32 ),
			'ttl'             => 3600,
			'serve_logged_in' => false,
			'dynamic'         => array(),
			'routes'          => array( $route => $live_key ),
		);

		$artifact_dir = \Blockstudio\Runtime_Cache::directory( 'static-prerender-artifact' );
		wp_mkdir_p( $artifact_dir );
		file_put_contents( $artifact_dir . '/' . $stale_key . '.html', '<html>Stale artifact</html>' );

		$this->assertTrue( Static_Prerender_Early_Serve::install_artifact_entry( $entry, $this->cache ) );
		$this->assertFileExists( Static_Prerender_Early_Serve::map_path() );
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );
		$this->assertFileExists( $this->cache . '/' . $live_key . '.html' );
		$this->assertFileExists( $artifact_dir . '/' . $live_key . '.html' );
		$this->assertFileDoesNotExist( $artifact_dir . '/' . $stale_key . '.html' );

		$entries = Static_Prerender_Early_Serve::installed_map_entries();
		$this->assertSame( $live_key, $entries[ $site['key'] ]['routes'][ $route ] );
		$this->assertSame( rtrim( wp_normalize_path( $artifact_dir ), '/' ), $entries[ $site['key'] ]['dir'] );
		$this->assertStringContainsString(
			'Blockstudio static prerender early serve',
			(string) file_get_contents( Static_Prerender_Early_Serve::dropin_path() )
		);
	}

	public function test_prune_never_evicts_files_the_installed_map_references(): void {
		$site     = Static_Prerender_Early_Serve::current_site_identity();
		$live_key = str_repeat( '1', 64 );
		file_put_contents( $this->cache . '/' . $live_key . '.html', '<html>Mapped</html>' );

		$this->assertTrue(
			Static_Prerender_Early_Serve::install_artifact_entry(
				array(
					'host'      => $site['host'],
					'home_path' => $site['home_path'],
					'build_id'  => str_repeat( '2', 32 ),
					'routes'    => array( $site['home_path'] => $live_key ),
				),
				$this->cache
			)
		);

		$artifact_dir = \Blockstudio\Runtime_Cache::directory( 'static-prerender-artifact' );
		$protected    = Static_Prerender_Early_Serve::protected_cache_paths( $artifact_dir );
		$this->assertContains(
			rtrim( wp_normalize_path( $artifact_dir ), '/' ) . '/' . $live_key . '.html',
			$protected
		);

		$mapped = $artifact_dir . '/' . $live_key . '.html';
		touch( $mapped, time() - WEEK_IN_SECONDS );
		for ( $i = 0; $i < 5; $i++ ) {
			file_put_contents( $artifact_dir . '/' . hash( 'sha256', 'filler-' . $i ) . '.html', '<html>Filler</html>' );
		}

		$cap = static fn (): int => 2;
		add_filter( 'blockstudio/cache/max_files_per_scope', $cap );
		\Blockstudio\Runtime_Cache::prune( 'static-prerender-artifact' );
		remove_filter( 'blockstudio/cache/max_files_per_scope', $cap );

		$this->assertFileExists( $mapped );
	}

	public function test_incomplete_graph_never_replaces_the_active_map(): void {
		$site     = Static_Prerender_Early_Serve::current_site_identity();
		$good_key = str_repeat( '1', 64 );
		$bad_key  = str_repeat( '2', 64 );
		file_put_contents( $this->cache . '/' . $good_key . '.html', '<html>Ready</html>' );

		$entry = array(
			'host'      => $site['host'],
			'home_path' => $site['home_path'],
			'site_id'   => get_current_blog_id(),
			'mode'      => 'graph',
			'build_id'  => str_repeat( '3', 32 ),
			'signature' => str_repeat( '4', 32 ),
			'routes'    => array( $site['home_path'] => $good_key ),
		);
		$this->assertTrue( Static_Prerender_Early_Serve::install_artifact_entry( $entry, $this->cache ) );
		$before = (string) file_get_contents( Static_Prerender_Early_Serve::map_path() );

		$entry['routes'] = array( $site['home_path'] => $bad_key );
		$this->assertFalse( Static_Prerender_Early_Serve::install_artifact_entry( $entry, $this->cache ) );
		$this->assertSame( $before, file_get_contents( Static_Prerender_Early_Serve::map_path() ) );
	}

	public function test_deactivation_cleanup_removes_only_the_current_multisite_entry(): void {
		$site        = Static_Prerender_Early_Serve::current_site_identity();
		$current_key = str_repeat( '5', 64 );
		$other_key   = str_repeat( '6', 64 );
		file_put_contents( $this->cache . '/' . $current_key . '.html', '<html>Current</html>' );

		$current = array(
			'host'      => $site['host'],
			'home_path' => $site['home_path'],
			'site_id'   => get_current_blog_id(),
			'mode'      => 'graph',
			'build_id'  => str_repeat( '7', 32 ),
			'signature' => str_repeat( '8', 32 ),
			'routes'    => array( $site['home_path'] => $current_key ),
		);
		$this->assertTrue( Static_Prerender_Early_Serve::install_artifact_entry( $current, $this->cache ) );

		file_put_contents( $this->cache . '/' . $other_key . '.html', '<html>Other</html>' );
		$other = array(
			'host'      => 'other.example',
			'home_path' => '/tenant/',
			'site_id'   => get_current_blog_id() + 100,
			'mode'      => 'graph',
			'build_id'  => str_repeat( '9', 32 ),
			'signature' => str_repeat( 'a', 32 ),
			'routes'    => array( '/tenant/' => $other_key ),
		);
		$this->assertTrue( Static_Prerender_Early_Serve::install_artifact_entry( $other, $this->cache ) );

		Static_Prerender_Early_Serve::remove_current_site();
		$entries = Static_Prerender_Early_Serve::installed_map_entries();

		$this->assertArrayNotHasKey( $site['key'], $entries );
		$this->assertArrayHasKey( 'other.example|/tenant/', $entries );
		$this->assertFileExists( Static_Prerender_Early_Serve::map_path() );
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );
	}

	public function test_foreign_map_and_dropin_are_never_overwritten(): void {
		$foreign_map    = "<?php\nreturn array( 'foreign' => true );\n";
		$foreign_dropin = "<?php\n// Foreign cache owner.\n";
		file_put_contents( Static_Prerender_Early_Serve::map_path(), $foreign_map );
		file_put_contents( Static_Prerender_Early_Serve::dropin_path(), $foreign_dropin );

		$key = str_repeat( 'b', 64 );
		file_put_contents( $this->cache . '/' . $key . '.html', '<html>Ready</html>' );
		$this->assertFalse(
			Static_Prerender_Early_Serve::install_artifact_entry(
				array(
					'host'      => 'example.test',
					'home_path' => '/',
					'build_id'  => str_repeat( 'c', 32 ),
					'routes'    => array( '/' => $key ),
				),
				$this->cache
			)
		);

		$this->assertSame( $foreign_map, file_get_contents( Static_Prerender_Early_Serve::map_path() ) );
		$this->assertSame( $foreign_dropin, file_get_contents( Static_Prerender_Early_Serve::dropin_path() ) );
	}

	public function test_disabled_early_serve_creates_no_files_and_reads_no_configuration(): void {
		$this->disable_early_serve();
		$before = $this->files();
		error_clear_last();

		Static_Prerender_Early_Serve::maybe_sync();

		$this->assertNull( error_get_last() );
		$this->assertSame( $before, $this->files() );
		$this->assertFileDoesNotExist( Static_Prerender_Early_Serve::map_path() . '.lock' );
		$this->assertSame( self::CONFIG_SOURCE, file_get_contents( $this->config ) );
	}

	public function test_enabled_early_serve_installs_the_map_dropin_and_wp_cache_declaration(): void {
		Static_Prerender_Early_Serve::maybe_sync();

		$site    = Static_Prerender_Early_Serve::current_site_identity();
		$entries = Static_Prerender_Early_Serve::installed_map_entries();

		$this->assertFileExists( Static_Prerender_Early_Serve::map_path() );
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );
		$this->assertSame( 'signature', $entries[ $site['key'] ]['mode'] );
		$this->assertSame( Static_Prerender_Identity::current(), $entries[ $site['key'] ]['signature'] );
		$this->assertStringContainsString(
			"define( 'WP_CACHE', true ); // Blockstudio static prerender early serve.",
			(string) file_get_contents( $this->config )
		);
	}

	public function test_explicit_cleanup_removes_owned_state_after_early_serve_is_disabled(): void {
		Static_Prerender_Early_Serve::maybe_sync();
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );

		$this->disable_early_serve();
		Static_Prerender_Early_Serve::remove_current_site();

		$this->assertFileDoesNotExist( Static_Prerender_Early_Serve::map_path() );
		$this->assertFileDoesNotExist( Static_Prerender_Early_Serve::dropin_path() );
		$this->assertSame( self::CONFIG_SOURCE, file_get_contents( $this->config ) );
	}

	public function test_a_foreign_wp_cache_declaration_is_never_added_to_or_removed_from(): void {
		$foreign = "<?php\ndefine( 'WP_CACHE', true ); // Other cache plugin.\n";
		file_put_contents( $this->config, $foreign );

		Static_Prerender_Early_Serve::maybe_sync();
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );
		$this->assertSame( $foreign, file_get_contents( $this->config ) );

		$this->disable_early_serve();
		Static_Prerender_Early_Serve::remove_current_site();

		$this->assertFileDoesNotExist( Static_Prerender_Early_Serve::dropin_path() );
		$this->assertSame( $foreign, file_get_contents( $this->config ) );
	}

	public function test_a_foreign_map_owner_is_never_cleaned_up(): void {
		Static_Prerender_Early_Serve::maybe_sync();
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );

		$foreign = "<?php\nreturn array( 'entries' => array() );\n";
		file_put_contents( Static_Prerender_Early_Serve::map_path(), $foreign );

		Static_Prerender_Early_Serve::remove_current_site();

		$this->assertSame( $foreign, file_get_contents( Static_Prerender_Early_Serve::map_path() ) );
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );
		$this->assertStringContainsString(
			"define( 'WP_CACHE', true ); // Blockstudio static prerender early serve.",
			(string) file_get_contents( $this->config )
		);
	}

	public function test_artifact_install_requires_early_serve_to_be_enabled(): void {
		$key = str_repeat( 'd', 64 );
		file_put_contents( $this->cache . '/' . $key . '.html', '<html>Ready</html>' );
		$site  = Static_Prerender_Early_Serve::current_site_identity();
		$entry = array(
			'host'      => $site['host'],
			'home_path' => $site['home_path'],
			'build_id'  => str_repeat( 'e', 32 ),
			'routes'    => array( $site['home_path'] => $key ),
		);

		$this->disable_early_serve();

		$this->assertFalse( Static_Prerender_Early_Serve::install_artifact_entry( $entry, $this->cache ) );
		$this->assertFileDoesNotExist( Static_Prerender_Early_Serve::map_path() );
		$this->assertFileDoesNotExist( Static_Prerender_Early_Serve::dropin_path() );

		$this->enable_early_serve();

		$this->assertTrue( Static_Prerender_Early_Serve::install_artifact_entry( $entry, $this->cache ) );
	}

	public function test_a_failed_map_publish_leaves_the_active_identity_unchanged(): void {
		$key = str_repeat( 'a', 64 );
		file_put_contents( $this->cache . '/' . $key . '.html', '<html>Ready</html>' );
		$site     = Static_Prerender_Early_Serve::current_site_identity();
		$build_id = str_repeat( 'b', 32 );
		$identity = Static_Prerender_Identity::current();
		wp_mkdir_p( Static_Prerender_Early_Serve::map_path() );

		set_error_handler( function () { return true; } );
		try {
			$installed = Static_Prerender_Early_Serve::install_artifact_entry(
				array(
					'host'      => $site['host'],
					'home_path' => $site['home_path'],
					'build_id'  => $build_id,
					'routes'    => array( $site['home_path'] => $key ),
				),
				$this->cache
			);
		} finally {
			restore_error_handler();
		}

		Static_Prerender_Identity::reset();

		$this->assertFalse( $installed );
		$this->assertNotSame( $build_id, Static_Prerender_Identity::current() );
		$this->assertSame( $identity, Static_Prerender_Identity::current() );
	}

	public function test_generated_dropin_resolves_the_same_signature_key_as_the_runtime(): void {
		$html = '<!doctype html><html><body>Early</body></html>';
		Static_Prerender_Early_Serve::maybe_sync();

		$key = Static_Prerender_Runtime::cache_key_for_request(
			array(
				'HTTP_HOST'   => 'localhost:8888',
				'REQUEST_URI' => '/Products/Card/',
			)
		);
		$this->assertIsString( $key );
		wp_mkdir_p( Static_Prerender_Runtime::cache_root_path() );
		file_put_contents( Static_Prerender_Runtime::cache_root_path() . '/' . $key . '.html', $html );

		$this->assertSame( $html, $this->serve_through_dropin( '/Products/Card/' ) );
		$this->assertSame( $html, $this->serve_through_dropin( '/products//card/' ) );
		$this->assertSame( '', $this->serve_through_dropin( '/products/other/' ) );
	}

	public function test_graph_route_miss_falls_back_to_the_identity_cache(): void {
		$site         = Static_Prerender_Early_Serve::current_site_identity();
		$artifact_key = str_repeat( 'd', 64 );
		$build_id     = str_repeat( 'e', 32 );
		$html         = '<!doctype html><html><body>Identity fallback</body></html>';

		file_put_contents( $this->cache . '/' . $artifact_key . '.html', '<html>Graph home</html>' );
		$this->assertTrue(
			Static_Prerender_Early_Serve::install_artifact_entry(
				array(
					'host'      => $site['host'],
					'home_path' => $site['home_path'],
					'build_id'  => $build_id,
					'ttl'       => 3600,
					'routes'    => array( $site['home_path'] => $artifact_key ),
				),
				$this->cache
			)
		);

		$key = Static_Prerender_Runtime::cache_key_for_request(
			array(
				'HTTP_HOST'   => 'localhost:8888',
				'REQUEST_URI' => '/live-only/',
			)
		);
		$this->assertIsString( $key );
		wp_mkdir_p( Static_Prerender_Runtime::cache_root_path() );
		file_put_contents( Static_Prerender_Runtime::cache_root_path() . '/' . $key . '.html', $html );

		$entries = Static_Prerender_Early_Serve::installed_map_entries();
		$this->assertSame( $build_id, $entries[ $site['key'] ]['identity'] ?? null );
		$this->assertSame(
			rtrim( wp_normalize_path( Static_Prerender_Runtime::cache_root_path() ), '/' ),
			rtrim( wp_normalize_path( $entries[ $site['key'] ]['runtime_dir'] ?? '' ), '/' )
		);
		$this->assertSame( $html, $this->serve_through_dropin( '/live-only/' ) );
	}

	public function test_generated_dropin_contains_the_same_anonymous_safety_boundaries(): void {
		$source = Static_Prerender_Early_Serve::dropin_source();

		$this->assertStringContainsString( 'HTTP_X_BLOCKSTUDIO_STATIC_PRERENDER_WARM', $source );
		$this->assertStringContainsString( 'HTTP_X_BLOCKSTUDIO_PREVIEW', $source );
		$this->assertStringContainsString( 'HTTP_X_BLOCKSTUDIO_CANVAS', $source );
		$this->assertStringContainsString( 'wordpress_logged_in_', $source );
		$this->assertStringContainsString( 'wp-postpass_', $source );
		$this->assertStringContainsString( 'comment_author_email_', $source );
		$this->assertStringContainsString( 'str_contains( $uri, \'?\' )', $source );
		$this->assertStringContainsString( 'str_starts_with( $path . \'/\', $prefix . \'/\' )', $source );
	}

	private function serve_through_dropin( string $request_uri ): string {
		$code = sprintf(
			'$_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["HTTP_HOST"] = "localhost:8888";'
				. ' $_SERVER["REQUEST_URI"] = %s; require %s;'
				. ' blockstudio_early_serve_static_prerender();',
			var_export( $request_uri, true ),
			var_export( Static_Prerender_Early_Serve::dropin_path(), true )
		);

		return (string) shell_exec(
			escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1'
		);
	}

	private function enable_early_serve(): void {
		add_filter( 'blockstudio/performance/staticPrerender/enabled', '__return_true' );
		add_filter( 'blockstudio/performance/staticPrerender/earlyServe', '__return_true' );
		Settings::reset();
		Runtime_Settings::reset();
	}

	private function disable_early_serve(): void {
		remove_filter( 'blockstudio/performance/staticPrerender/enabled', '__return_true' );
		remove_filter( 'blockstudio/performance/staticPrerender/earlyServe', '__return_true' );
		Settings::reset();
		Runtime_Settings::reset();
	}

	/**
	 * @return string[] Relative content-directory file paths.
	 */
	private function files(): array {
		$files = array();
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $items as $item ) {
			if ( $item->isFile() ) {
				$files[] = substr( $item->getPathname(), strlen( $this->root ) + 1 );
			}
		}
		sort( $files );

		return $files;
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

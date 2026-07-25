<?php
/**
 * Static prerender early-serve tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Static_Prerender_Early_Serve;
use Blockstudio\Static_Prerender_Identity;
use PHPUnit\Framework\TestCase;

/**
 * Tests safe map/drop-in ownership and atomic graph activation.
 */
class StaticPrerenderEarlyServeTest extends TestCase {

	private string $root;

	private string $cache;

	private string $config;

	protected function setUp(): void {
		$this->root   = trailingslashit( get_temp_dir() ) . 'blockstudio-early-serve-' . wp_generate_uuid4();
		$this->cache  = $this->root . '/cache';
		$this->config = $this->root . '/wp-config.php';
		wp_mkdir_p( $this->cache );
		file_put_contents( $this->config, "<?php\n// Test configuration.\n" );

		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		Static_Prerender_Identity::reset();
		Static_Prerender_Early_Serve::override_content_dir_for_testing( $this->root );
		Static_Prerender_Early_Serve::override_config_path_for_testing( $this->config );
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
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

		$this->assertTrue( Static_Prerender_Early_Serve::install_artifact_entry( $entry, $this->cache ) );
		$this->assertFileExists( Static_Prerender_Early_Serve::map_path() );
		$this->assertFileExists( Static_Prerender_Early_Serve::dropin_path() );
		$this->assertFileExists( $this->cache . '/' . $live_key . '.html' );
		$this->assertFileDoesNotExist( $this->cache . '/' . $stale_key . '.html' );

		$entries = Static_Prerender_Early_Serve::installed_map_entries();
		$this->assertSame( $live_key, $entries[ $site['key'] ]['routes'][ $route ] );
		$this->assertStringContainsString(
			'Blockstudio static prerender early serve',
			(string) file_get_contents( Static_Prerender_Early_Serve::dropin_path() )
		);
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

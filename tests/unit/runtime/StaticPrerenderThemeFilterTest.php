<?php
/**
 * Static prerender settings filters added by the active theme.
 *
 * @package Blockstudio
 */

use Blockstudio\Runtime_Cache;
use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Static_Prerender_Early_Serve;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\TestCase;

/**
 * Blockstudio boots from a plugin or from the theme's Composer autoload, so its
 * runtime is initialized before the theme's functions.php adds settings
 * filters. A theme that switches static prerendering off, for example on
 * local installs, must still win.
 */
class StaticPrerenderThemeFilterTest extends TestCase {

	private const ENABLED = 'blockstudio/settings/performance/staticPrerender/enabled';

	private const EARLY_SERVE = 'blockstudio/settings/performance/staticPrerender/earlyServe';

	private string $root;

	private int $after_setup_theme = 0;

	protected function setUp(): void {
		global $wp_actions;

		$this->root = trailingslashit( get_temp_dir() ) . 'blockstudio-prerender-theme-' . wp_generate_uuid4();
		wp_mkdir_p( $this->root . '/content' );
		file_put_contents( $this->root . '/content/wp-config.php', "<?php\n" );
		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		Static_Prerender_Early_Serve::override_content_dir_for_testing( $this->root . '/content' );
		Static_Prerender_Early_Serve::override_config_path_for_testing( $this->root . '/content/wp-config.php' );
		Static_Prerender_Runtime::deactivate();
		Settings::reset();
		Runtime_Settings::reset();
		delete_option( 'blockstudio_static_prerender_feature_state' );

		// Configuration enables the feature, as blockstudio.json would.
		add_filter( self::ENABLED, '__return_true' );
		add_filter( self::EARLY_SERVE, '__return_true' );

		// Rewind to the boot phase: plugins and the theme's Composer autoload
		// have run, the theme's functions.php filters do not exist yet.
		$this->after_setup_theme = (int) ( $wp_actions['after_setup_theme'] ?? 0 );
		unset( $wp_actions['after_setup_theme'] );
	}

	protected function tearDown(): void {
		global $wp_actions;

		if ( $this->after_setup_theme > 0 ) {
			$wp_actions['after_setup_theme'] = $this->after_setup_theme;
		}

		Static_Prerender_Runtime::deactivate();
		remove_all_filters( self::ENABLED );
		remove_all_filters( self::EARLY_SERVE );
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		Static_Prerender_Early_Serve::override_content_dir_for_testing( null );
		Static_Prerender_Early_Serve::override_config_path_for_testing( null );
		Settings::reset();
		Runtime_Settings::reset();
		Static_Prerender_Runtime::reset_request_cache();
		delete_option( 'blockstudio_static_prerender_feature_state' );
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root . '/cache';
	}

	public function filter_site_key(): string {
		return 'unit-theme-filter';
	}

	/**
	 * The theme's switch, as it would test wp_get_environment_type().
	 */
	public static function off_locally( mixed $value ): bool {
		return false;
	}

	private function finish_theme_setup(): void {
		global $wp_actions;

		$wp_actions['after_setup_theme'] = max( 1, $this->after_setup_theme );
		$this->after_setup_theme         = 0;

		$priority = has_action( 'after_setup_theme', array( Static_Prerender_Runtime::class, 'register_features' ) );
		$this->assertSame( PHP_INT_MAX, $priority, 'Static prerender must decide its hooks after the theme has loaded.' );

		Static_Prerender_Runtime::register_features();
	}

	public function test_a_theme_filter_switches_static_prerender_off_after_boot(): void {
		Static_Prerender_Runtime::register();

		$this->assertTrue( Runtime_Settings::current()->enabled( 'staticPrerender/enabled' ) );

		// The theme's functions.php runs after Blockstudio booted.
		add_filter( self::ENABLED, array( self::class, 'off_locally' ), 20 );
		add_filter( self::EARLY_SERVE, array( self::class, 'off_locally' ), 20 );

		$this->finish_theme_setup();

		$this->assertFalse( Runtime_Settings::current()->enabled( 'staticPrerender/enabled' ) );
		$this->assertFalse(
			has_action( 'template_redirect', array( Static_Prerender_Runtime::class, 'maybe_serve_or_buffer' ) ),
			'A front-end request must neither serve nor buffer.'
		);
		$this->assertFalse( has_filter( 'status_header', array( Static_Prerender_Runtime::class, 'record_status_header' ) ) );
		$this->assertFalse( has_action( 'save_post', array( Static_Prerender_Runtime::class, 'handle_content_changed' ) ) );
		$this->assertFalse( has_action( 'deleted_post', array( Static_Prerender_Runtime::class, 'handle_content_changed' ) ) );
		$this->assertFalse( has_action( 'switch_theme', array( Static_Prerender_Runtime::class, 'handle_site_changed' ) ) );
		$this->assertFalse( has_action( 'blockstudio/runtime/changed', array( Static_Prerender_Runtime::class, 'handle_site_changed' ) ) );
		$this->assertFalse( has_action( 'admin_init', array( Static_Prerender_Early_Serve::class, 'maybe_sync' ) ) );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Theme filter',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		wp_delete_post( (int) $post_id, true );

		$this->assertFalse( wp_next_scheduled( 'blockstudio_static_prerender_warm' ) );
		$this->assertSame( array(), $this->files( Static_Prerender_Runtime::cache_root_path() ), 'No static prerender HTML may be written.' );
		$this->assertSame( array(), $this->files( Runtime_Cache::directory( 'static-prerender-state' ) ), 'No static prerender state may be written.' );
		$this->assertFileDoesNotExist( $this->root . '/content/blockstudio-static-prerender-map.php' );
		$this->assertFileDoesNotExist( $this->root . '/content/advanced-cache.php' );
		$this->assertSame( "<?php\n", file_get_contents( $this->root . '/content/wp-config.php' ) );
		$this->assertFalse( get_option( 'blockstudio_static_prerender_feature_state', false ) );
	}

	public function test_a_theme_switch_off_removes_the_sites_early_serve_entry_once(): void {
		update_option(
			'blockstudio_static_prerender_feature_state',
			array(
				'earlyServe'   => true,
				'warm'         => false,
				'warmInterval' => 0,
			)
		);
		file_put_contents(
			$this->root . '/content/blockstudio-static-prerender-map.php',
			"<?php\n// Generated by Blockstudio. Static prerender early-serve map; do not edit.\nreturn array( 'entries' => array() );\n"
		);
		file_put_contents( $this->root . '/content/advanced-cache.php', "<?php\n// Blockstudio static prerender early serve\n" );

		Static_Prerender_Runtime::register();

		$this->assertFileExists( $this->root . '/content/advanced-cache.php', 'Nothing may be decided before the theme loads.' );

		add_filter( self::ENABLED, array( self::class, 'off_locally' ), 20 );
		add_filter( self::EARLY_SERVE, array( self::class, 'off_locally' ), 20 );

		$this->finish_theme_setup();

		$this->assertFileDoesNotExist( $this->root . '/content/blockstudio-static-prerender-map.php' );
		$this->assertFileDoesNotExist( $this->root . '/content/advanced-cache.php' );
		$this->assertSame( array(), get_option( 'blockstudio_static_prerender_feature_state', false ) );
	}

	public function test_without_theme_filters_the_configured_hooks_are_registered_after_setup(): void {
		Static_Prerender_Runtime::register();

		$this->assertFalse( has_action( 'template_redirect', array( Static_Prerender_Runtime::class, 'maybe_serve_or_buffer' ) ) );

		$this->finish_theme_setup();

		$this->assertSame( 0, has_action( 'template_redirect', array( Static_Prerender_Runtime::class, 'maybe_serve_or_buffer' ) ) );
		$this->assertSame( 10, has_action( 'save_post', array( Static_Prerender_Runtime::class, 'handle_content_changed' ) ) );
		$this->assertSame( 50, has_action( 'admin_init', array( Static_Prerender_Early_Serve::class, 'maybe_sync' ) ) );
	}

	public function test_registering_after_theme_setup_decides_immediately(): void {
		global $wp_actions;

		$wp_actions['after_setup_theme'] = max( 1, $this->after_setup_theme );
		$this->after_setup_theme         = 0;

		Static_Prerender_Runtime::register();

		$this->assertFalse( has_action( 'after_setup_theme', array( Static_Prerender_Runtime::class, 'register_features' ) ) );
		$this->assertSame( 0, has_action( 'template_redirect', array( Static_Prerender_Runtime::class, 'maybe_serve_or_buffer' ) ) );
	}

	/**
	 * @return string[] Files beneath a directory.
	 */
	private function files( string $directory ): array {
		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$files = array();
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $items as $item ) {
			if ( $item->isFile() ) {
				$files[] = $item->getPathname();
			}
		}

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

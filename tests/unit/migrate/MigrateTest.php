<?php

use Blockstudio\Migrate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MigrateTest extends TestCase {

	protected function setUp(): void {
		delete_option( 'blockstudio_version' );
		delete_option( 'blockstudio_options' );
		delete_option( 'blockstudio_settings' );
	}

	protected function tearDown(): void {
		delete_option( 'blockstudio_version' );
		delete_option( 'blockstudio_options' );
		delete_option( 'blockstudio_settings' );
	}

	private function make_migrator( ?string $stored_version = null ): Migrate {
		if ( null !== $stored_version ) {
			update_option( 'blockstudio_version', $stored_version );
		}

		return new Migrate();
	}

	#[DataProvider( 'migration_boot_phases' )]
	public function test_registration_handles_plugin_and_theme_boot_phases( int $plugins_loaded, int $theme_loaded, ?string $hook ): void {
		$actions = $GLOBALS['wp_actions'];
		$callback = array( Migrate::class, 'maybe_update' );
		$priorities = array();
		foreach ( array( 'plugins_loaded', 'after_setup_theme' ) as $name ) {
			$priorities[ $name ] = has_action( $name, $callback );
			if ( false !== $priorities[ $name ] ) {
				remove_action( $name, $callback, $priorities[ $name ] );
			}
		}
		update_option( 'blockstudio_version', '7.7.13' );

		try {
			$GLOBALS['wp_actions']['plugins_loaded'] = $plugins_loaded;
			$GLOBALS['wp_actions']['after_setup_theme'] = $theme_loaded;
			Migrate::register();

			if ( null !== $hook ) {
				$this->assertSame( 'plugins_loaded' === $hook ? 10 : PHP_INT_MAX, has_action( $hook, $callback ) );
				$this->assertSame( '7.7.13', get_option( 'blockstudio_version' ) );
				Migrate::maybe_update();
			}
			$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
			Migrate::maybe_update();
			$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
		} finally {
			remove_action( 'plugins_loaded', $callback );
			remove_action( 'after_setup_theme', $callback, PHP_INT_MAX );
			foreach ( $priorities as $name => $priority ) {
				if ( false !== $priority ) {
					add_action( $name, $callback, $priority );
				}
			}
			$GLOBALS['wp_actions'] = $actions;
		}
	}

	public static function migration_boot_phases(): array {
		return array(
			'plugin' => array( 0, 0, 'plugins_loaded' ),
			'theme Composer' => array( 1, 0, 'after_setup_theme' ),
			'late bootstrap' => array( 1, 1, null ),
		);
	}

	// check_and_update() dispatching

	public function test_check_and_update_first_activation_no_legacy(): void {
		$migrator = $this->make_migrator();
		$migrator->check_and_update();

		$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
	}

	public function test_check_and_update_first_activation_with_legacy_options(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->check_and_update();

		$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['editor']['formatOnSave'] );
	}

	public function test_check_and_update_upgrade_from_older_version(): void {
		$migrator = $this->make_migrator( '5.0.0' );
		$migrator->check_and_update();

		$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
	}

	public function test_check_and_update_same_version_does_not_update(): void {
		update_option( 'blockstudio_version', BLOCKSTUDIO_VERSION );
		$original = get_option( 'blockstudio_version' );

		$migrator = new Migrate();
		$migrator->check_and_update();

		$this->assertSame( $original, get_option( 'blockstudio_version' ) );
	}

	// handle_first_activation()

	public function test_first_activation_sets_version(): void {
		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
	}

	public function test_first_activation_without_legacy_stores_empty_settings(): void {
		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$this->assertSame( array(), get_option( 'blockstudio_settings' ) );
	}

	public function test_first_activation_with_legacy_runs_migrations(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['editor']['formatOnSave'] );
	}

	public function test_first_activation_with_legacy_deletes_old_option(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$this->assertFalse( get_option( 'blockstudio_options' ) );
	}

	// handle_updates() via check_and_update()

	public function test_upgrade_from_pre_520_runs_520_migration(): void {
		$legacy = new \stdClass();
		$legacy->processorScss = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator( '5.0.0' );
		$migrator->check_and_update();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['assets']['minify']['css'] );
		$this->assertTrue( $settings['assets']['process']['scss'] );
	}

	public function test_upgrade_from_post_520_skips_520_migration(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator( '6.0.0' );
		$migrator->check_and_update();

		// The 5.2.0 migration should not have run since stored version is 6.0.0.
		$this->assertSame( array(), get_option( 'blockstudio_settings' ) );
	}

	public function test_upgrade_updates_stored_version(): void {
		$migrator = $this->make_migrator( '1.0.0' );
		$migrator->check_and_update();

		$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
	}

	public function test_no_upgrade_when_version_matches(): void {
		$migrator = $this->make_migrator( BLOCKSTUDIO_VERSION );
		$migrator->check_and_update();

		$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_version' ) );
	}

	// 5.2.0 migration: formatOnSave

	public function test_520_migrates_format_on_save(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertTrue( $settings['editor']['formatOnSave'] );
	}

	public function test_520_skips_format_on_save_when_false(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = false;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertArrayNotHasKey( 'editor', $settings );
	}

	public function test_520_skips_format_on_save_when_not_set(): void {
		$legacy = new \stdClass();
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertArrayNotHasKey( 'editor', $settings );
	}

	// 5.2.0 migration: processorScss

	public function test_520_migrates_processor_scss(): void {
		$legacy = new \stdClass();
		$legacy->processorScss = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertTrue( $settings['assets']['minify']['css'] );
		$this->assertTrue( $settings['assets']['process']['scss'] );
	}

	public function test_520_skips_processor_scss_when_false(): void {
		$legacy = new \stdClass();
		$legacy->processorScss = false;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertArrayNotHasKey( 'assets', $settings );
	}

	// 5.2.0 migration: processorEsbuild

	public function test_520_migrates_processor_esbuild(): void {
		$legacy = new \stdClass();
		$legacy->processorEsbuild = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertTrue( $settings['assets']['minify']['js'] );
	}

	public function test_520_skips_processor_esbuild_when_false(): void {
		$legacy = new \stdClass();
		$legacy->processorEsbuild = false;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertArrayNotHasKey( 'assets', $settings );
	}

	// 5.2.0 migration: all options combined

	public function test_520_migrates_all_options_together(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave    = true;
		$legacy->processorScss   = true;
		$legacy->processorEsbuild = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertTrue( $settings['editor']['formatOnSave'] );
		$this->assertTrue( $settings['assets']['minify']['css'] );
		$this->assertTrue( $settings['assets']['minify']['js'] );
		$this->assertTrue( $settings['assets']['process']['scss'] );
	}

	public function test_520_deletes_legacy_options_after_migration(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$this->assertFalse( get_option( 'blockstudio_options' ) );
	}

	public function test_520_no_migration_when_legacy_options_empty(): void {
		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$this->assertSame( array(), get_option( 'blockstudio_settings' ) );
		$this->assertFalse( get_option( 'blockstudio_options' ) );
	}

	// Edge cases

	public function test_520_empty_legacy_object_creates_empty_settings(): void {
		$legacy = new \stdClass();
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertEmpty( $settings );
	}

	public function test_constructor_reads_current_version_from_database(): void {
		update_option( 'blockstudio_version', '4.0.0' );

		$migrator = new Migrate();
		$ref      = new ReflectionClass( $migrator );

		$prop = $ref->getProperty( 'current_version' );
		$prop->setAccessible( true );

		$this->assertSame( '4.0.0', $prop->getValue( $migrator ) );
	}

	public function test_constructor_reads_new_version_from_constant(): void {
		$migrator = new Migrate();
		$ref      = new ReflectionClass( $migrator );

		$prop = $ref->getProperty( 'new_version' );
		$prop->setAccessible( true );

		$this->assertSame( BLOCKSTUDIO_VERSION, $prop->getValue( $migrator ) );
	}

	public function test_constructor_with_no_stored_version_returns_false(): void {
		$migrator = new Migrate();
		$ref      = new ReflectionClass( $migrator );

		$prop = $ref->getProperty( 'current_version' );
		$prop->setAccessible( true );

		$this->assertFalse( $prop->getValue( $migrator ) );
	}

	public function test_migrations_array_contains_registered_versions(): void {
		$migrator = new Migrate();
		$ref      = new ReflectionClass( $migrator );

		$prop = $ref->getProperty( 'migrations' );
		$prop->setAccessible( true );
		$migrations = $prop->getValue( $migrator );

		$this->assertArrayHasKey( '5.2.0', $migrations );
		$this->assertIsArray( $migrations['5.2.0'] );
		$this->assertSame( $migrator, $migrations['5.2.0'][0] );
		$this->assertSame( 'migrate_to_520', $migrations['5.2.0'][1] );
		$this->assertArrayHasKey( '7.6.6', $migrations );
		$this->assertSame( $migrator, $migrations['7.6.6'][0] );
		$this->assertSame( 'migrate_to_766', $migrations['7.6.6'][1] );
		$this->assertArrayHasKey( '7.7.14', $migrations );
		$this->assertSame( $migrator, $migrations['7.7.14'][0] );
		$this->assertSame( 'migrate_to_7714', $migrations['7.7.14'][1] );
	}

	// 7.7.14 migration: the options read on every request are stored and autoloaded

	public function test_7714_autoloads_the_small_request_options(): void {
		$options  = array(
			'blockstudio_build_lock_cleanup_version'      => '7.7.0',
			'blockstudio_collection_post_types_signature' => 'signature',
			'blockstudio_populate_cache_version'          => array(
				'version' => 'v',
				'rotated' => 1,
				'dirty'   => 0,
			),
		);
		$previous = $this->replace_options( $options );

		try {
			$this->assertSame( array(), array_intersect_key( $options, $this->autoloaded_options() ) );

			$this->make_migrator( '7.7.0' )->check_and_update();

			$this->assertSame( array(), array_diff_key( $options, wp_load_alloptions() ), 'The caches of the running request know the change.' );
			$this->assertSame( array(), array_diff_key( $options, $this->autoloaded_options() ) );

			foreach ( $options as $option => $value ) {
				$this->assertSame( $value, get_option( $option ) );
			}
		} finally {
			$this->restore_options( $previous );
		}
	}

	public function test_7714_leaves_the_populate_state_to_a_persistent_object_cache(): void {
		$options  = array(
			'blockstudio_populate_cache_version' => array(
				'version' => 'v',
				'rotated' => 1,
				'dirty'   => 0,
			),
		);
		$previous = $this->replace_options( $options );
		$using    = wp_using_ext_object_cache( true );

		try {
			$this->make_migrator( '7.7.0' )->check_and_update();
		} finally {
			wp_using_ext_object_cache( (bool) $using );
		}

		try {
			$this->assertArrayNotHasKey( 'blockstudio_populate_cache_version', $this->autoloaded_options() );
			$this->assertSame( $options['blockstudio_populate_cache_version'], get_option( 'blockstudio_populate_cache_version' ) );
		} finally {
			$this->restore_options( $previous );
		}
	}

	public function test_7714_autoloads_page_options_by_size(): void {
		$options  = array(
			'blockstudio_pages_collection_manifests' => array( 'collections' => array() ),
			'blockstudio_pages_template_for'         => array( 'post' => str_repeat( 'a', 200000 ) ),
		);
		$previous = $this->replace_options( $options );

		try {
			$this->make_migrator( '7.7.0' )->check_and_update();

			$this->assertArrayHasKey( 'blockstudio_pages_collection_manifests', wp_load_alloptions() );
			$this->assertArrayNotHasKey( 'blockstudio_pages_template_for', wp_load_alloptions() );

			$autoloaded = $this->autoloaded_options();
			$this->assertArrayHasKey( 'blockstudio_pages_collection_manifests', $autoloaded );
			$this->assertArrayNotHasKey( 'blockstudio_pages_template_for', $autoloaded );

			foreach ( $options as $option => $value ) {
				$this->assertSame( $value, get_option( $option ) );
			}
		} finally {
			$this->restore_options( $previous );
		}
	}

	public function test_7714_changes_page_autoload_without_deleting_values(): void {
		$options = array(
			'blockstudio_pages_collection_manifests' => array( 'collections' => array() ),
			'blockstudio_pages_template_for' => array( 'post' => array( 'template' => array( array( 'core/paragraph' ) ) ) ),
		);
		$previous = $this->replace_options( $options );
		$deleted = array();
		$record = static function ( string $name ) use ( &$deleted, $options ): void {
			if ( array_key_exists( $name, $options ) ) {
				$deleted[] = $name;
			}
		};
		add_action( 'delete_option', $record );

		try {
			$this->make_migrator( '7.7.13' )->check_and_update();
			$this->assertSame( array(), $deleted );
			foreach ( $options as $name => $value ) {
				$this->assertSame( $value, get_option( $name ) );
			}
		} finally {
			remove_action( 'delete_option', $record );
			$this->restore_options( $previous );
		}
	}

	public function test_7714_stores_missing_request_options_as_their_read_defaults(): void {
		$defaults = array(
			'blockstudio_settings'                        => array(),
			'blockstudio_static_prerender_feature_state'  => array(),
			'blockstudio_build_lock_cleanup_version'      => '',
			'blockstudio_collection_post_types_signature' => '',
			'blockstudio_populate_cache_version'          => '0',
			'blockstudio_pages_collection_manifests'      => array(),
			'blockstudio_pages_template_for'              => array(),
		);
		$previous = $this->replace_options( array_fill_keys( array_keys( $defaults ), null ) );

		try {
			$this->make_migrator( '7.7.0' )->check_and_update();

			foreach ( $defaults as $option => $value ) {
				$this->assertSame( $value, get_option( $option, null ), $option );
			}

			$this->assertSame( array(), array_diff_key( $defaults, wp_load_alloptions() ), 'The caches of the running request know the new rows.' );
			$this->assertSame( array(), array_diff_key( $defaults, $this->autoloaded_options() ) );
		} finally {
			$this->restore_options( $previous );
		}
	}

	public function test_first_activation_stores_the_request_options(): void {
		$options  = array(
			'blockstudio_static_prerender_feature_state',
			'blockstudio_build_lock_cleanup_version',
			'blockstudio_collection_post_types_signature',
			'blockstudio_populate_cache_version',
			'blockstudio_pages_collection_manifests',
			'blockstudio_pages_template_for',
		);
		$previous = $this->replace_options( array_fill_keys( $options, null ) );

		try {
			$this->make_migrator()->handle_first_activation();

			$this->assertSame( array(), array_diff( array( 'blockstudio_settings', ...$options ), array_keys( $this->autoloaded_options() ) ) );
		} finally {
			$this->restore_options( $previous );
		}
	}

	public function test_7714_keeps_existing_settings(): void {
		$settings = array( 'editor' => array( 'formatOnSave' => true ) );
		update_option( 'blockstudio_settings', $settings );

		$this->make_migrator( '7.7.0' )->check_and_update();

		$this->assertSame( $settings, get_option( 'blockstudio_settings' ) );
	}

	// Stores the given values with autoload off, as releases before 7.7.14 did; null removes the option.
	private function replace_options( array $options ): array {
		$previous = array();

		foreach ( $options as $option => $value ) {
			$previous[ $option ] = get_option( $option, null );
			delete_option( $option );

			if ( null !== $value ) {
				add_option( $option, $value, '', false );
			}
		}

		return $previous;
	}

	private function restore_options( array $previous ): void {
		foreach ( $previous as $option => $value ) {
			delete_option( $option );

			if ( null !== $value ) {
				add_option( $option, $value );
			}
		}
	}

	// What a new request without an object cache loads.
	private function autoloaded_options(): array {
		wp_cache_delete( 'alloptions', 'options' );

		return wp_load_alloptions();
	}

	// Idempotency

	public function test_running_check_and_update_twice_is_safe(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator();
		$migrator->check_and_update();

		$settings_first = get_option( 'blockstudio_settings' );

		// Second run: version is now current, so nothing should change.
		$migrator2 = new Migrate();
		$migrator2->check_and_update();

		$settings_second = get_option( 'blockstudio_settings' );
		$this->assertSame( $settings_first, $settings_second );
	}

	// Version comparison edge cases

	public function test_upgrade_from_520_does_not_rerun_520(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator( '5.2.0' );
		$migrator->check_and_update();

		// The 5.2.0 migration should not run since stored version equals the migration version.
		$this->assertSame( array(), get_option( 'blockstudio_settings' ) );
	}

	public function test_upgrade_from_519_runs_520(): void {
		$legacy = new \stdClass();
		$legacy->formatOnSave = true;
		update_option( 'blockstudio_options', $legacy );

		$migrator = $this->make_migrator( '5.1.9' );
		$migrator->check_and_update();

		$settings = get_option( 'blockstudio_settings' );
		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['editor']['formatOnSave'] );
	}
}

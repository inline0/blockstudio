<?php

use Blockstudio\Migrate;
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

	public function test_first_activation_without_legacy_does_not_create_settings(): void {
		$migrator = $this->make_migrator();
		$migrator->handle_first_activation();

		$this->assertFalse( get_option( 'blockstudio_settings' ) );
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
		$this->assertFalse( get_option( 'blockstudio_settings' ) );
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

		$this->assertFalse( get_option( 'blockstudio_settings' ) );
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

	public function test_migrations_array_contains_520(): void {
		$migrator = new Migrate();
		$ref      = new ReflectionClass( $migrator );

		$prop = $ref->getProperty( 'migrations' );
		$prop->setAccessible( true );
		$migrations = $prop->getValue( $migrator );

		$this->assertArrayHasKey( '5.2.0', $migrations );
		$this->assertIsArray( $migrations['5.2.0'] );
		$this->assertSame( $migrator, $migrations['5.2.0'][0] );
		$this->assertSame( 'migrate_to_520', $migrations['5.2.0'][1] );
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
		$this->assertFalse( get_option( 'blockstudio_settings' ) );
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

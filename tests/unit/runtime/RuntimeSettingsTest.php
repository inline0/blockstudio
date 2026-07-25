<?php

use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Tests for product-neutral runtime settings.
 */
class RuntimeSettingsTest extends TestCase {

	private string $path;

	protected function setUp(): void {
		$path = wp_tempnam( 'blockstudio-runtime-settings' );
		$this->assertIsString( $path );
		$this->path = $path;

		add_filter( 'blockstudio/settings/path', array( $this, 'settings_path' ) );
		$this->write( array() );
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/settings/path', array( $this, 'settings_path' ) );
		Settings::reset();
		Runtime_Settings::reset();

		if ( is_file( $this->path ) ) {
			unlink( $this->path );
		}
	}

	public function settings_path(): string {
		return $this->path;
	}

	public function test_compat_profile_preserves_wordpress_defaults(): void {
		$config = Runtime_Settings::current();

		$this->assertSame( 'compat', $config->value( 'profile' ) );
		$this->assertFalse( $config->enabled( 'wordpress/headNoise' ) );
		$this->assertSame( 'off', $config->value( 'preload.links' ) );
		$this->assertSame( array(), $config->errors() );
	}

	public function test_speed_profile_enables_generic_defaults_but_explicit_values_win(): void {
		$this->write(
			array(
				'performance' => array(
					'profile'   => 'speed',
					'wordpress' => array(
						'xmlrpc' => false,
					),
					'media'     => array(
						'rootMargin' => '450px',
					),
				),
			)
		);

		$config = Runtime_Settings::current();
		$this->assertSame( 'speed', $config->value( 'profile' ) );
		$this->assertTrue( $config->enabled( 'wordpress/headNoise' ) );
		$this->assertFalse( $config->enabled( 'wordpress/xmlrpc' ) );
		$this->assertSame( 'intent', $config->value( 'preload/links' ) );
		$this->assertTrue( $config->enabled( 'media/lazy' ) );
		$this->assertSame( '450px', $config->value( 'media/rootMargin' ) );
	}

	public function test_invalid_raw_configuration_falls_back_and_reports_errors(): void {
		$this->write(
			array(
				'performance' => array(
					'profile'   => 'turbo',
					'unknown'   => true,
					'wordpress' => array(
						'xmlrpc' => 'yes',
					),
					'preload'   => null,
				),
			)
		);

		$config = Runtime_Settings::current();
		$this->assertSame( 'compat', $config->value( 'profile' ) );
		$this->assertNotEmpty( $config->errors() );
		$errors = implode( "\n", $config->errors() );
		$this->assertStringContainsString( 'not supported', $errors );
		$this->assertStringContainsString( 'performance.wordpress.xmlrpc must be true or false', $errors );
		$this->assertStringContainsString( 'performance.preload must be an object', $errors );
	}

	public function test_runtime_value_filters_are_applied_before_validation(): void {
		add_filter( 'blockstudio/performance/wordpress/xmlrpc', '__return_true' );
		Runtime_Settings::reset();

		try {
			$this->assertTrue( Runtime_Settings::current()->enabled( 'wordpress/xmlrpc' ) );
		} finally {
			remove_filter( 'blockstudio/performance/wordpress/xmlrpc', '__return_true' );
		}
	}

	public function test_configuration_hash_changes_after_source_invalidation(): void {
		$before = Runtime_Settings::current()->hash();
		$this->write(
			array(
				'performance' => array(
					'measurement' => array(
						'enabled' => true,
					),
				),
			)
		);

		$after = Runtime_Settings::current();
		$this->assertNotSame( $before, $after->hash() );
		$this->assertTrue( $after->enabled( 'measurement/enabled' ) );
	}

	/**
	 * @param array<string, mixed> $settings Settings payload.
	 */
	private function write( array $settings ): void {
		$payload = array() === $settings ? (object) array() : $settings;

		file_put_contents(
			$this->path,
			wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
		);
		Settings::reload();
		Runtime_Settings::reset();
	}
}

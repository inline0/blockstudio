<?php

use Blockstudio\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Parity between the published JSON Schema and the PHP defaults.
 *
 * The admin notice reports a configuration key as an unknown setting whenever
 * it is absent from Settings::$defaults, which the validator walks. The JSON
 * Schema is what authors and editors read, so a setting documented there but
 * missing from the defaults is reported as invalid while the runtime honours
 * it. Walking the schema the way the validator walks a configuration file
 * keeps the two from drifting apart again.
 */
class SettingsSchemaParityTest extends TestCase {

	/**
	 * Read the protected defaults array.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		$property = new ReflectionProperty( Settings::class, 'defaults' );
		$property->setAccessible( true );

		return (array) $property->getValue();
	}

	/**
	 * Read the published settings schema.
	 *
	 * @return array<string, mixed>
	 */
	private function schema(): array {
		$path = dirname( __DIR__, 3 ) . '/schemas/blockstudio.json';

		$this->assertFileExists( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a repository fixture.
		return (array) json_decode( (string) file_get_contents( $path ), true );
	}

	/**
	 * Collect schema paths that the defaults array does not declare.
	 *
	 * Descent mirrors Settings::report_unknown_keys(): a branch is only walked
	 * when the schema describes named properties and the defaults hold a
	 * non-empty array, so free-form maps stay leaves in both.
	 *
	 * @param array<string, mixed> $schema   Schema subtree.
	 * @param array<string, mixed> $defaults Defaults subtree.
	 * @param string               $path     Slash-delimited path so far.
	 *
	 * @return string[]
	 */
	private function missing_paths( array $schema, array $defaults, string $path = '' ): array {
		$missing = array();

		foreach ( $schema['properties'] ?? array() as $key => $child ) {
			if ( '$schema' === $key ) {
				continue;
			}

			$current = '' === $path ? $key : $path . '/' . $key;

			if ( ! array_key_exists( $key, $defaults ) ) {
				$missing[] = $current;

				continue;
			}

			if ( is_array( $child['properties'] ?? null ) && is_array( $defaults[ $key ] ) && array() !== $defaults[ $key ] ) {
				$missing = array_merge( $missing, $this->missing_paths( $child, $defaults[ $key ], $current ) );
			}
		}

		return $missing;
	}

	public function test_every_documented_setting_exists_in_the_php_defaults(): void {
		$missing = $this->missing_paths( $this->schema(), $this->defaults() );

		$this->assertSame(
			array(),
			$missing,
			"Documented settings missing from Settings::\$defaults, so the validator reports them as unknown:\n"
				. implode( "\n", $missing )
		);
	}

	public function test_tailwind_output_is_accepted_by_the_validator(): void {
		$defaults = $this->defaults();

		$this->assertArrayHasKey( 'output', $defaults['tailwind'] ?? array() );
		$this->assertSame( 'inline', $defaults['tailwind']['output'] );
	}
}

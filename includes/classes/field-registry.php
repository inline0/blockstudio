<?php
/**
 * Field Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Centralized singleton registry for custom field definitions.
 *
 * This class provides a single source of truth for reusable custom field
 * definitions that can be referenced via "custom/{name}" in block.json
 * attributes. Fields can be registered via file system discovery (field.json)
 * or programmatically through the 'blockstudio/fields' filter.
 *
 * @since 7.0.0
 */
final class Field_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Field_Registry|null
	 */
	private static ?Field_Registry $instance = null;

	/**
	 * Registered field definitions indexed by name.
	 *
	 * @var array<string, array>
	 */
	private array $fields = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Field_Registry The singleton instance.
	 */
	public static function instance(): Field_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Singleton pattern.
	}

	/**
	 * Reset the registry (mainly for testing).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->fields = array();
	}

	/**
	 * Register a custom field definition.
	 *
	 * @param string $name       The field name.
	 * @param array  $definition The field definition (must contain 'attributes' array).
	 *
	 * @return void
	 */
	public function register( string $name, array $definition ): void {
		$this->fields[ $name ] = $definition;
	}

	/**
	 * Get a field definition by name.
	 *
	 * @param string $name The field name.
	 *
	 * @return array|null The field definition or null.
	 */
	public function get( string $name ): ?array {
		return $this->fields[ $name ] ?? null;
	}

	/**
	 * Check if a field exists.
	 *
	 * @param string $name The field name.
	 *
	 * @return bool Whether the field exists.
	 */
	public function has( string $name ): bool {
		return isset( $this->fields[ $name ] );
	}

	/**
	 * Get all registered field definitions.
	 *
	 * @return array<string, array> The field definitions.
	 */
	public function all(): array {
		return $this->fields;
	}
}

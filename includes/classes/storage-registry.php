<?php
/**
 * Storage Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Blockstudio\Interfaces\Storage_Handler_Interface;

/**
 * Central registry for storage handlers.
 *
 * This class manages storage handlers and provides methods to process
 * fields for storage registration. It supports multiple storage types
 * (block, postMeta, option) and normalizes storage configuration.
 *
 * @since 7.0.0
 */
class Storage_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Storage_Registry|null
	 */
	private static ?Storage_Registry $instance = null;

	/**
	 * Registered storage handlers.
	 *
	 * @var array<string, Storage_Handler_Interface>
	 */
	private array $handlers = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return Storage_Registry
	 */
	public static function instance(): Storage_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {
	}

	/**
	 * Register a storage handler.
	 *
	 * @param Storage_Handler_Interface $handler The storage handler.
	 *
	 * @return void
	 */
	public function register_handler( Storage_Handler_Interface $handler ): void {
		$this->handlers[ $handler->get_type() ] = $handler;
	}

	/**
	 * Get a storage handler by type.
	 *
	 * @param string $type The storage type.
	 *
	 * @return Storage_Handler_Interface|null The handler or null if not found.
	 */
	public function get_handler( string $type ): ?Storage_Handler_Interface {
		return $this->handlers[ $type ] ?? null;
	}

	/**
	 * Get all registered handlers.
	 *
	 * @return array<string, Storage_Handler_Interface>
	 */
	public function get_handlers(): array {
		return $this->handlers;
	}

	/**
	 * Get storage types for a field.
	 *
	 * Normalizes the storage configuration to always return an array.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return array<string> The storage types.
	 */
	public function get_storage_types( array $field ): array {
		if ( ! isset( $field['storage'] ) ) {
			return array( 'block' );
		}

		$types = $field['storage']['type'] ?? array( 'block' );
		return is_array( $types ) ? $types : array( $types );
	}

	/**
	 * Check if a field has a specific storage type.
	 *
	 * @param array  $field The field configuration.
	 * @param string $type  The storage type to check.
	 *
	 * @return bool Whether the field has the storage type.
	 */
	public function has_storage_type( array $field, string $type ): bool {
		return in_array( $type, $this->get_storage_types( $field ), true );
	}

	/**
	 * Process a field for storage registration.
	 *
	 * Registers all storage types for the field.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return void
	 */
	public function process_field( string $block_name, array $field ): void {
		$types = $this->get_storage_types( $field );

		foreach ( $types as $type ) {
			$handler = $this->get_handler( $type );
			if ( $handler ) {
				$handler->register( $block_name, $field );
			}
		}
	}

	/**
	 * Process all fields in a block for storage registration.
	 *
	 * @param string $block_name The block name.
	 * @param array  $fields     The block fields.
	 *
	 * @return void
	 */
	public function process_block_fields( string $block_name, array $fields ): void {
		foreach ( $fields as $field ) {
			if ( ! isset( $field['id'] ) ) {
				continue;
			}

			$this->process_field( $block_name, $field );

			// Process nested fields in container types.
			if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$this->process_block_fields( $block_name, $field['fields'] );
			}
		}
	}
}

<?php
/**
 * Option Storage Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Storage_Handlers;

use Blockstudio\Interfaces\Storage_Handler_Interface;
use Blockstudio\Field_Type_Config;

/**
 * Option storage handler.
 *
 * Handles storage in wp_options table. Registers settings with
 * REST API support for Gutenberg integration.
 *
 * @since 7.0.0
 */
class Option_Storage implements Storage_Handler_Interface {

	/**
	 * Get the storage type identifier.
	 *
	 * @return string The storage type.
	 */
	public function get_type(): string {
		return 'option';
	}

	/**
	 * Register storage for a field.
	 *
	 * Registers option with REST API support.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return void
	 */
	public function register( string $block_name, array $field ): void {
		$option_key = $this->get_key( $block_name, $field );
		$type       = $field['type'] ?? 'text';

		register_setting(
			'blockstudio',
			$option_key,
			array(
				'type'         => $this->get_meta_type( $type ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Get the storage key for a field.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return string The option key.
	 */
	public function get_key( string $block_name, array $field ): string {
		if ( isset( $field['storage']['optionKey'] ) ) {
			return $field['storage']['optionKey'];
		}

		return sanitize_key( $block_name . '_' . $field['id'] );
	}

	/**
	 * Get the meta type for a field type.
	 *
	 * Maps Blockstudio field types to WordPress setting types.
	 *
	 * @param string $field_type The field type.
	 *
	 * @return string The setting type.
	 */
	private function get_meta_type( string $field_type ): string {
		if ( Field_Type_Config::is_string_type( $field_type ) ) {
			return 'string';
		}

		if ( Field_Type_Config::is_number_type( $field_type ) ) {
			return 'number';
		}

		if ( Field_Type_Config::is_boolean_type( $field_type ) ) {
			return 'boolean';
		}

		if ( Field_Type_Config::is_array_type( $field_type ) ) {
			return 'array';
		}

		// Default to string for object types and unknown types.
		return 'string';
	}
}

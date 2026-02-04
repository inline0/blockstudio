<?php
/**
 * Post Meta Storage Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Storage_Handlers;

use Blockstudio\Interfaces\Storage_Handler_Interface;
use Blockstudio\Field_Type_Config;

/**
 * Post meta storage handler.
 *
 * Handles storage in wp_postmeta table. Registers post meta with
 * REST API support for Gutenberg integration.
 *
 * @since 7.0.0
 */
class Post_Meta_Storage implements Storage_Handler_Interface {

	/**
	 * Get the storage type identifier.
	 *
	 * @return string The storage type.
	 */
	public function get_type(): string {
		return 'postMeta';
	}

	/**
	 * Register storage for a field.
	 *
	 * Registers post meta with REST API support.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return void
	 */
	public function register( string $block_name, array $field ): void {
		$meta_key = $this->get_key( $block_name, $field );
		$type     = $field['type'] ?? 'text';

		register_post_meta(
			'',
			$meta_key,
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => $this->get_meta_type( $type ),
			)
		);
	}

	/**
	 * Get the storage key for a field.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return string The meta key.
	 */
	public function get_key( string $block_name, array $field ): string {
		if ( isset( $field['storage']['postMetaKey'] ) ) {
			return $field['storage']['postMetaKey'];
		}

		return sanitize_key( $block_name . '_' . $field['id'] );
	}

	/**
	 * Get the meta type for a field type.
	 *
	 * Maps Blockstudio field types to WordPress meta types.
	 *
	 * @param string $field_type The field type.
	 *
	 * @return string The meta type.
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

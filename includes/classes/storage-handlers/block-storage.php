<?php
/**
 * Block Storage Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Storage_Handlers;

use Blockstudio\Interfaces\Storage_Handler_Interface;

/**
 * Block storage handler.
 *
 * Handles storage in block attributes (default WordPress behavior).
 * This handler doesn't need to register anything extra since block
 * attributes are handled by the Attribute_Builder.
 *
 * @since 7.0.0
 */
class Block_Storage implements Storage_Handler_Interface {

	/**
	 * Get the storage type identifier.
	 *
	 * @return string The storage type.
	 */
	public function get_type(): string {
		return 'block';
	}

	/**
	 * Register storage for a field.
	 *
	 * Block attributes are registered via Attribute_Builder, so this is a no-op.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return void
	 */
	public function register( string $block_name, array $field ): void {
		// No-op - block attributes are registered via Attribute_Builder.
	}

	/**
	 * Get the storage key for a field.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return string The storage key (field ID).
	 */
	public function get_key( string $block_name, array $field ): string {
		return $field['id'];
	}
}

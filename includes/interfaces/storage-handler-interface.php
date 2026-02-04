<?php
/**
 * Storage Handler Interface.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Interfaces;

/**
 * Interface for storage handlers in the storage registry.
 *
 * Each handler is responsible for storing field values in a specific
 * location (block attributes, post meta, options, etc.).
 *
 * @since 7.0.0
 */
interface Storage_Handler_Interface {

	/**
	 * Get the storage type identifier.
	 *
	 * @return string The storage type (e.g., 'block', 'postMeta', 'option').
	 */
	public function get_type(): string;

	/**
	 * Register storage for a field.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return void
	 */
	public function register( string $block_name, array $field ): void;

	/**
	 * Get the storage key for a field.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return string The storage key.
	 */
	public function get_key( string $block_name, array $field ): string;
}

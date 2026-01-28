<?php
/**
 * Field Handler Interface.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Interfaces;

/**
 * Interface for field handlers in the attribute builder.
 *
 * Each handler is responsible for building attributes for a specific
 * field type or group of related field types.
 *
 * @since 7.0.0
 */
interface Field_Handler_Interface {

	/**
	 * Check if this handler supports the given field type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether this handler supports the type.
	 */
	public function supports( string $type ): bool;

	/**
	 * Build attribute data for a field.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 *
	 * @return void
	 */
	public function build( array $field, array &$attributes, string $prefix = '' ): void;

	/**
	 * Get the default value for a field of this type.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return mixed The default value.
	 */
	public function get_default_value( array $field ): mixed;
}

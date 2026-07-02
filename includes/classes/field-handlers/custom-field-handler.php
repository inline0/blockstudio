<?php
/**
 * Custom Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

use Blockstudio\Field_Type_Registry;

/**
 * Handler for registered custom field types.
 *
 * @since 7.5.0
 */
class Custom_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Check if this handler supports the given field type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether this handler supports the type.
	 */
	public function supports( string $type ): bool {
		return Field_Type_Registry::instance()->is_custom_type( $type );
	}

	/**
	 * Build attribute data for a custom field.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 *
	 * @return void
	 */
	public function build( array $field, array &$attributes, string $prefix = '' ): void {
		$field_id     = $this->get_field_id( $field, $prefix );
		$attribute_id = $this->get_attribute_id( $field, $prefix );
		$attribute    = Field_Type_Registry::instance()->build_attribute( $field, $attribute_id );

		if ( null === $attribute ) {
			return;
		}

		$this->apply_block_field_metadata( $field, $attribute );

		$attributes[ $field_id ] = $attribute;
	}
}

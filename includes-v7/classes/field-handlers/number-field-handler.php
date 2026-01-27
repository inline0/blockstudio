<?php
/**
 * Number Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

/**
 * Handler for number-based field types.
 *
 * Handles: number, range.
 *
 * @since 7.0.0
 */
class Number_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Supported field types.
	 *
	 * @var array<string>
	 */
	protected array $supported_types = array(
		'number',
		'range',
	);

	/**
	 * Build attribute data for a number field.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 *
	 * @return void
	 */
	public function build( array $field, array &$attributes, string $prefix = '' ): void {
		$type     = $field['type'] ?? '';
		$field_id = $this->get_field_id( $field, $prefix );

		if ( '' === $field_id ) {
			return;
		}

		$attribute = $this->create_base_attribute( $type, 'number' );

		// Apply defaults with special handling for zero.
		foreach ( array( 'default', 'fallback' ) as $item ) {
			if ( isset( $field[ $item ] ) ) {
				// Convert zero to string '0' for proper handling.
				$attribute[ $item ] = 0 === $field[ $item ] ? '0' : $field[ $item ];
			}
		}

		$attribute['id'] = $field_id;

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Get the default value for a number field.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return int|float The default value.
	 */
	public function get_default_value( array $field ): mixed {
		return $field['default'] ?? 0;
	}
}

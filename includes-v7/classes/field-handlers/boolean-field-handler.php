<?php
/**
 * Boolean Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

/**
 * Handler for boolean field types.
 *
 * Handles: toggle.
 *
 * @since 7.0.0
 */
class Boolean_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Supported field types.
	 *
	 * @var array<string>
	 */
	protected array $supported_types = array(
		'toggle',
	);

	/**
	 * Build attribute data for a boolean field.
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

		$attribute = $this->create_base_attribute( $type, 'boolean' );

		$this->apply_defaults( $field, $attribute );
		$attribute['id'] = $field_id;

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Get the default value for a boolean field.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return bool The default value.
	 */
	public function get_default_value( array $field ): mixed {
		return $field['default'] ?? false;
	}
}

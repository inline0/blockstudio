<?php
/**
 * Media Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

/**
 * Handler for media/file field types.
 *
 * Handles: files.
 *
 * @since 7.0.0
 */
class Media_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Supported field types.
	 *
	 * @var array<string>
	 */
	protected array $supported_types = array(
		'files',
	);

	/**
	 * Build attribute data for a media field.
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

		$attribute = array(
			'blockstudio' => true,
			'type'        => array( 'number', 'object', 'array' ),
			'field'       => $type,
			'multiple'    => $field['multiple'] ?? false,
			'returnSize'  => $field['returnSize'] ?? 'full',
		);

		// Handle return format.
		if ( isset( $field['returnFormat'] ) ) {
			$attribute['returnFormat'] = $field['returnFormat'] ?? 'value';
		}

		$this->apply_defaults( $field, $attribute );
		$this->apply_storage( $field, $attribute );
		$attribute['id'] = $field_id;

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Get the default value for a media field.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return mixed The default value.
	 */
	public function get_default_value( array $field ): mixed {
		$is_multiple = $field['multiple'] ?? false;
		return $field['default'] ?? ( $is_multiple ? array() : null );
	}
}

<?php
/**
 * Text Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

/**
 * Handler for text-based field types.
 *
 * Handles: text, textarea, code, date, datetime, unit, classes, richtext, wysiwyg.
 *
 * @since 7.0.0
 */
class Text_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Supported field types.
	 *
	 * @var array<string>
	 */
	protected array $supported_types = array(
		'code',
		'date',
		'datetime',
		'text',
		'textarea',
		'unit',
		'classes',
		'richtext',
		'wysiwyg',
	);

	/**
	 * Build attribute data for a text field.
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

		$attribute = $this->create_base_attribute( $type, 'string' );

		// Rich text types have HTML source.
		if ( 'richtext' === $type || 'wysiwyg' === $type ) {
			$attribute['source'] = 'html';
		}

		// Code fields have additional properties.
		if ( 'code' === $type ) {
			$attribute['language'] = $field['language'] ?? 'html';
			$attribute['asset']    = $field['asset'] ?? false;
		}

		// Handle Tailwind for classes type.
		if ( 'classes' === $type && ( $field['tailwind'] ?? false ) ) {
			$attribute['tailwind'] = true;
		}

		// Apply defaults, storage, and set ID.
		$this->apply_defaults( $field, $attribute );
		$this->apply_storage( $field, $attribute );
		$attribute['id'] = $field_id;

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Get the default value for a text field.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return string The default value.
	 */
	public function get_default_value( array $field ): mixed {
		return $field['default'] ?? '';
	}
}

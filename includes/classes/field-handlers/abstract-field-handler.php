<?php
/**
 * Abstract Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

use Blockstudio\Interfaces\Field_Handler_Interface;
use Blockstudio\Field_Type_Config;

/**
 * Abstract base class for field type handlers.
 *
 * Field handlers convert Blockstudio field definitions into WordPress
 * block attributes. Each handler specializes in one category of fields.
 *
 * Implementation Requirements:
 * Subclasses must:
 * 1. Set $supported_types array with field types they handle
 * 2. Implement build() method to create attributes
 *
 * Provided Methods:
 * - supports(): Checks if handler can process a field type
 * - get_field_id(): Builds prefixed field ID for nested fields
 * - create_base_attribute(): Creates base attribute structure
 * - apply_defaults(): Adds default/fallback values
 * - get_default_value(): Gets type-appropriate default
 *
 * Example Handler:
 * ```php
 * class My_Field_Handler extends Abstract_Field_Handler {
 *     protected array $supported_types = ['myfield'];
 *
 *     public function build(array $field, array &$attributes, string $prefix): void {
 *         $id = $this->get_field_id($field, $prefix);
 *         $attribute = $this->create_base_attribute('myfield', 'string');
 *         $this->apply_defaults($field, $attribute);
 *         $attributes[$id] = $attribute;
 *     }
 * }
 * ```
 *
 * Existing Handlers:
 * - Text_Field_Handler: text, textarea, code, classes, richtext
 * - Number_Field_Handler: number, range
 * - Boolean_Field_Handler: toggle
 * - Select_Field_Handler: select, radio, checkbox, color, gradient
 * - Media_Field_Handler: files (images, attachments)
 * - Container_Field_Handler: group, tabs, repeater
 *
 * @since 7.0.0
 */
abstract class Abstract_Field_Handler implements Field_Handler_Interface {

	/**
	 * Field types supported by this handler.
	 *
	 * @var array<string>
	 */
	protected array $supported_types = array();

	/**
	 * Check if this handler supports the given field type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether this handler supports the type.
	 */
	public function supports( string $type ): bool {
		return in_array( $type, $this->supported_types, true );
	}

	/**
	 * Get the field ID with prefix.
	 *
	 * @param array  $field  The field configuration.
	 * @param string $prefix The prefix.
	 *
	 * @return string The field ID.
	 */
	protected function get_field_id( array $field, string $prefix ): string {
		$id = $field['id'] ?? '';
		return '' === $prefix ? $id : $prefix . '_' . $id;
	}

	/**
	 * Create base attribute structure.
	 *
	 * @param string $type          The field type.
	 * @param string $attribute_type The attribute type.
	 *
	 * @return array The base attribute.
	 */
	protected function create_base_attribute( string $type, string|array $attribute_type ): array {
		return array(
			'blockstudio' => true,
			'type'        => $attribute_type,
			'field'       => $type,
		);
	}

	/**
	 * Apply default and fallback values to attribute.
	 *
	 * @param array $field     The field configuration.
	 * @param array $attribute The attribute array (passed by reference).
	 *
	 * @return void
	 */
	protected function apply_defaults( array $field, array &$attribute ): void {
		foreach ( array( 'default', 'fallback' ) as $item ) {
			if ( isset( $field[ $item ] ) ) {
				$attribute[ $item ] = $field[ $item ];
			}
		}
	}

	/**
	 * Get the default value for a field of this type.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return mixed The default value.
	 */
	public function get_default_value( array $field ): mixed {
		$type = $field['type'] ?? '';
		return Field_Type_Config::get_default_value( $type );
	}
}

<?php
/**
 * Field Type Configuration class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Centralized field type configuration and type-checking utilities.
 *
 * This class defines all supported Blockstudio field types and their
 * properties. It's used by Attribute_Builder and field handlers to
 * determine how fields should be converted to WordPress attributes.
 *
 * Field Type Categories:
 * - STRING_TYPES: text, textarea, code, date, classes, richtext, wysiwyg
 * - NUMBER_TYPES: number, range
 * - BOOLEAN_TYPES: toggle
 * - OBJECT_TYPES: color, gradient, icon, link, radio, select, group
 * - ARRAY_TYPES: repeater, checkbox, token, attributes
 * - CONTAINER_TYPES: group, tabs, repeater (contain other fields)
 * - NON_ATTRIBUTE_TYPES: message (display only, no attribute)
 *
 * Type Configuration (TYPES constant):
 * Each type defines:
 * - attribute: The WordPress attribute type (string|number|boolean|object|array)
 * - default: The default value for the attribute
 * - source: (optional) For richtext - 'html' source from inner content
 *
 * Usage Examples:
 * ```php
 * // Get attribute type for a field
 * $type = Field_Type_Config::get_attribute_type('text'); // 'string'
 *
 * // Check field category
 * Field_Type_Config::is_string_type('textarea'); // true
 * Field_Type_Config::is_container_type('repeater'); // true
 *
 * // Check if field supports options
 * Field_Type_Config::has_options('select'); // true
 * Field_Type_Config::is_multiple_options('checkbox', $field); // true
 * ```
 *
 * @since 7.0.0
 */
final class Field_Type_Config {

	/**
	 * Field type configurations.
	 *
	 * Maps field types to their attribute type and default value.
	 *
	 * @var array<string, array{attribute: string|array, default: mixed}>
	 */
	public const TYPES = array(
		// String types.
		'code'       => array(
			'attribute' => 'string',
			'default'   => '',
		),
		'date'       => array(
			'attribute' => 'string',
			'default'   => '',
		),
		'datetime'   => array(
			'attribute' => 'string',
			'default'   => '',
		),
		'text'       => array(
			'attribute' => 'string',
			'default'   => '',
		),
		'textarea'   => array(
			'attribute' => 'string',
			'default'   => '',
		),
		'unit'       => array(
			'attribute' => 'string',
			'default'   => '',
		),
		'classes'    => array(
			'attribute' => 'string',
			'default'   => '',
		),
		'richtext'   => array(
			'attribute' => 'string',
			'default'   => '',
			'source'    => 'html',
		),
		'wysiwyg'    => array(
			'attribute' => 'string',
			'default'   => '',
			'source'    => 'html',
		),

		// Number types.
		'number'     => array(
			'attribute' => 'number',
			'default'   => 0,
		),
		'range'      => array(
			'attribute' => 'number',
			'default'   => 0,
		),

		// Boolean types.
		'toggle'     => array(
			'attribute' => 'boolean',
			'default'   => false,
		),

		// Object types.
		'color'      => array(
			'attribute' => 'object',
			'default'   => null,
		),
		'gradient'   => array(
			'attribute' => 'object',
			'default'   => null,
		),
		'icon'       => array(
			'attribute' => 'object',
			'default'   => null,
		),
		'link'       => array(
			'attribute' => 'object',
			'default'   => null,
		),
		'radio'      => array(
			'attribute' => 'object',
			'default'   => null,
		),
		'select'     => array(
			'attribute' => 'object',
			'default'   => null,
		),

		// Array types.
		'repeater'   => array(
			'attribute' => 'array',
			'default'   => array(),
		),
		'checkbox'   => array(
			'attribute' => 'array',
			'default'   => array(),
		),
		'token'      => array(
			'attribute' => 'array',
			'default'   => array(),
		),
		'attributes' => array(
			'attribute' => 'array',
			'default'   => array(),
		),

		// Mixed/special types.
		'files'      => array(
			'attribute' => array( 'number', 'object', 'array' ),
			'default'   => null,
		),
		'group'      => array(
			'attribute' => 'object',
			'default'   => null,
		),
		'tabs'       => array(
			'attribute' => null,
			'default'   => null,
		),
		'message'    => array(
			'attribute' => null,
			'default'   => null,
		),
	);

	/**
	 * String types (output as string attribute).
	 *
	 * @var array<string>
	 */
	public const STRING_TYPES = array(
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
	 * Number types (output as number attribute).
	 *
	 * @var array<string>
	 */
	public const NUMBER_TYPES = array(
		'number',
		'range',
	);

	/**
	 * Boolean types (output as boolean attribute).
	 *
	 * @var array<string>
	 */
	public const BOOLEAN_TYPES = array(
		'toggle',
	);

	/**
	 * Object types (output as object attribute).
	 *
	 * @var array<string>
	 */
	public const OBJECT_TYPES = array(
		'color',
		'gradient',
		'icon',
		'link',
		'radio',
		'select',
		'group',
	);

	/**
	 * Array types (output as array attribute).
	 *
	 * @var array<string>
	 */
	public const ARRAY_TYPES = array(
		'repeater',
		'checkbox',
		'token',
		'attributes',
	);

	/**
	 * Types that support options.
	 *
	 * @var array<string>
	 */
	public const OPTION_TYPES = array(
		'select',
		'radio',
		'checkbox',
		'color',
		'gradient',
	);

	/**
	 * Types that support multiple selection.
	 *
	 * @var array<string>
	 */
	public const MULTIPLE_OPTION_TYPES = array(
		'checkbox',
		'token',
	);

	/**
	 * Container types (group/tabs/repeater).
	 *
	 * @var array<string>
	 */
	public const CONTAINER_TYPES = array(
		'group',
		'tabs',
		'repeater',
	);

	/**
	 * Types with no attribute output.
	 *
	 * @var array<string>
	 */
	public const NON_ATTRIBUTE_TYPES = array(
		'message',
	);

	/**
	 * Get the attribute type for a field type.
	 *
	 * @param string $type The field type.
	 *
	 * @return string|array|null The attribute type or null if not found.
	 */
	public static function get_attribute_type( string $type ): string|array|null {
		return self::TYPES[ $type ]['attribute'] ?? null;
	}

	/**
	 * Get the default value for a field type.
	 *
	 * @param string $type The field type.
	 *
	 * @return mixed The default value.
	 */
	public static function get_default_value( string $type ): mixed {
		return self::TYPES[ $type ]['default'] ?? null;
	}

	/**
	 * Check if the type is a string type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it's a string type.
	 */
	public static function is_string_type( string $type ): bool {
		return in_array( $type, self::STRING_TYPES, true );
	}

	/**
	 * Check if the type is a number type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it's a number type.
	 */
	public static function is_number_type( string $type ): bool {
		return in_array( $type, self::NUMBER_TYPES, true );
	}

	/**
	 * Check if the type is a boolean type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it's a boolean type.
	 */
	public static function is_boolean_type( string $type ): bool {
		return in_array( $type, self::BOOLEAN_TYPES, true );
	}

	/**
	 * Check if the type is an object type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it's an object type.
	 */
	public static function is_object_type( string $type ): bool {
		return in_array( $type, self::OBJECT_TYPES, true );
	}

	/**
	 * Check if the type is an array type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it's an array type.
	 */
	public static function is_array_type( string $type ): bool {
		return in_array( $type, self::ARRAY_TYPES, true );
	}

	/**
	 * Check if the type supports options.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it supports options.
	 */
	public static function has_options( string $type ): bool {
		return in_array( $type, self::OPTION_TYPES, true );
	}

	/**
	 * Check if the type supports multiple selection.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it supports multiple selection.
	 */
	public static function is_multiple_option_type( string $type ): bool {
		return in_array( $type, self::MULTIPLE_OPTION_TYPES, true );
	}

	/**
	 * Check if the type is a container type.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it's a container type.
	 */
	public static function is_container_type( string $type ): bool {
		return in_array( $type, self::CONTAINER_TYPES, true );
	}

	/**
	 * Check if the type produces an attribute.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool Whether it produces an attribute.
	 */
	public static function produces_attribute( string $type ): bool {
		return ! in_array( $type, self::NON_ATTRIBUTE_TYPES, true );
	}

	/**
	 * Check if select/checkbox should be multiple.
	 *
	 * @param string $type     The field type.
	 * @param array  $field    The field configuration.
	 *
	 * @return bool Whether the field is multiple.
	 */
	public static function is_multiple_options( string $type, array $field ): bool {
		return 'checkbox' === $type ||
			'token' === $type ||
			( 'select' === $type && ( $field['multiple'] ?? false ) );
	}
}

<?php
/**
 * Explicit validation of stored Blockstudio field values.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Type;
use WP_Block_Type_Registry;

/**
 * Reports stored-value problems without rendering or changing content.
 *
 * @since 7.7.5
 */
final class Block_Validator {

	/**
	 * Keys Blockstudio reads from the `blockstudio` attribute.
	 *
	 * @var string[]
	 */
	private const CONTAINER_KEYS = array( 'attributes', 'disabled', 'name', 'editor' );

	/**
	 * Validate one block's complete parsed attrs object.
	 *
	 * @param string $name  Registered block name.
	 * @param array  $attrs Attributes as returned by parse_blocks().
	 * @return array|null Findings, or null when Blockstudio has no fields for this block.
	 */
	public static function validate( string $name, array $attrs ): ?array {
		$fields     = \bs_block_fields( $name );
		$extensions = \bs_block_extension_fields( $name );
		if ( null === $fields && array() === $extensions ) {
			return null;
		}

		$block    = WP_Block_Type_Registry::get_instance()->get_registered( $name );
		$own      = self::compiled( Field_Definitions::latest( Field_Definitions::collect( $fields ?? array(), 'block' ) ), $block->attributes ?? array() );
		$extended = array();
		foreach ( Extensions::get_matches( $name, Build::extensions() ) as $extension ) {
			$extended = array_replace( $extended, $extension->attributes ?? array() );
		}
		$extended    = self::compiled( Field_Definitions::latest( Field_Definitions::collect( $extensions, 'extension' ) ), $extended );
		$definitions = Field_Definitions::combine( $own, $extended );
		$source      = null === $fields ? 'extension' : 'block';
		$findings    = null === $fields ? array() : self::unknown_attributes( $name, $attrs, array_merge( array_keys( ( $block->attributes ?? array() ) + WP_Block_Type::GLOBAL_ATTRIBUTES ), array_keys( $definitions ) ) );
		$settings    = array();
		$values      = array();

		if ( array_key_exists( 'blockstudio', $attrs ) ) {
			$settings = self::object_values( $attrs['blockstudio'] );
			if ( null === $settings ) {
				$findings[] = self::finding( 'invalid_container', 'blockstudio', $source, $attrs['blockstudio'], 'object' );
				return self::filter( $findings, $name, $attrs );
			}
		}
		if ( array_key_exists( 'attributes', $settings ) ) {
			$values = self::object_values( $settings['attributes'] );
			if ( null === $values ) {
				$findings[] = self::finding( 'invalid_container', 'blockstudio/attributes', $source, $settings['attributes'], 'object' );
				return self::filter( $findings, $name, $attrs );
			}
		}

		$disabled = $settings['disabled'] ?? array();
		if ( ! is_array( $disabled ) || ! array_is_list( $disabled ) ) {
			$findings[] = self::finding( 'wrong_type', 'blockstudio/disabled', $source, $disabled, 'list' );
			$disabled   = array();
		}

		// Only a Blockstudio render reads declared top-level fields. An
		// extension of a core/third-party block reads the nested object only.
		$top = null === $fields ? array() : array_intersect_key( $attrs, $definitions );
		foreach ( $top as $key => $value ) {
			if ( array_key_exists( $key, $values ) ) {
				if ( $value !== $values[ $key ] ) {
					$findings[] = self::finding( 'shadowed_value', self::path( '', $key ), $definitions[ $key ][0]['source'], $value, $values[ $key ] );
				}
				continue;
			}
			if ( in_array( $key, $disabled, true ) ) {
				continue;
			}
			$entry = $definitions[ $key ][0];
			$empty = null === $value || false === $value || '' === $value || array() === $value ||
				( 0 === $value && ! Field_Type_Config::is_multiple_options( $entry['field']['type'], $entry['field'] ) );
			// WordPress checks registered top-level attributes before Blockstudio
			// sees them. The nested object is not subject to those field schemas.
			if ( isset( $own[ $key ] ) && is_wp_error( rest_validate_value_from_schema( $value, $own[ $key ][0]['compiled'], (string) $key ) ) ) {
				if ( ! $empty || ! empty( $own[ $key ][0]['compiled']['default'] ) ) {
					$findings[] = self::finding( 'wrong_type', self::path( '', $key ), 'block', $value, $own[ $key ][0]['compiled']['type'] ?? 'value' );
				}
				unset( $top[ $key ] );
				continue;
			}
			if ( isset( $extended[ $key ] ) && ! $empty ) {
				$findings[] = self::finding( 'extension_top_level', self::path( '', $key ), 'extension', $value, self::path( 'blockstudio/attributes', $key ) );
			}
		}
		$values = array_replace( $top, $values );
		if ( null !== $fields ) {
			$unknown   = array_diff_key( $values, $definitions );
			$unhandled = array_fill_keys( Block::unhandled_attributes( $name, $unknown ), true );
			foreach ( $unknown as $key => $value ) {
				if ( ! isset( $unhandled[ $key ] ) ) {
					unset( $values[ $key ] );
				}
			}
		}
		self::values( $values, $definitions, '', $source, $disabled, $findings );

		return self::filter( $findings, $name, $attrs );
	}

	/**
	 * Report top-level keys that a Blockstudio block neither reads nor forwards.
	 *
	 * A template receives the block's registered attributes, which include the
	 * ones WordPress adds for `supports` (`className`, `anchor`, `style`) and
	 * for every block (`lock`, `metadata`), plus the fields of matching
	 * extensions. Any other key is dropped, so a mistyped field ID or a values
	 * object stored without its `blockstudio` wrapper renders as an empty block.
	 *
	 * @param string   $name  Registered block name.
	 * @param array    $attrs Attributes as returned by parse_blocks().
	 * @param string[] $known Attribute names the render path reads.
	 * @return array Findings.
	 */
	private static function unknown_attributes( string $name, array $attrs, array $known ): array {
		$known     = array_values( array_unique( array_merge( array_map( 'strval', $known ), array( 'blockstudio' ) ) ) );
		$unknown   = array_diff_key( $attrs, array_flip( $known ) );
		$unhandled = array_fill_keys( Block::unhandled_attributes( $name, $unknown ), true );
		$findings  = array();
		foreach ( $unknown as $key => $value ) {
			if ( ! isset( $unhandled[ $key ] ) ) {
				continue;
			}
			// A container member next to a missing wrapper is the likely cause.
			if ( ! array_key_exists( 'blockstudio', $attrs ) && in_array( (string) $key, self::CONTAINER_KEYS, true ) ) {
				$findings[] = self::finding( 'missing_container', self::path( '', $key ), 'block', $value, self::path( 'blockstudio', $key ) );
				continue;
			}
			$findings[] = self::finding( 'unknown_attribute', self::path( '', $key ), 'block', $value, $known );
		}
		return $findings;
	}

	/**
	 * Validate parsed blocks and their children without expanding references.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $path   Parent block path.
	 * @return array Findings with block_path and block_name for CLI consumers.
	 */
	public static function blocks( array $blocks, string $path = '' ): array {
		$findings = array();
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$block_path = self::path( $path, $index );
			$name       = $block['blockName'] ?? null;
			if ( is_string( $name ) ) {
				foreach ( self::validate( $name, is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array() ) ?? array() as $finding ) {
					$findings[] = array(
						'block_path' => $block_path,
						'block_name' => $name,
					) + $finding;
				}
			}
			$findings = array_merge( $findings, self::blocks( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array(), $block_path ) );
		}
		return $findings;
	}

	/**
	 * Attach existing built definitions; never build fields or run populate.
	 *
	 * @param array $definitions Storage definitions.
	 * @param array $attributes  Already registered render attributes.
	 * @return array Definitions with their actual render metadata.
	 */
	private static function compiled( array $definitions, array $attributes ): array {
		foreach ( $definitions as $key => &$entries ) {
			$attribute = $attributes[ $key ] ?? null;
			if ( ! is_array( $attribute ) || empty( $attribute['blockstudio'] ) ) {
				unset( $definitions[ $key ] );
				continue;
			}
			foreach ( $entries as &$entry ) {
				$entry['compiled'] = $attribute;
			}
			unset( $entry );
		}
		unset( $entries );
		return $definitions;
	}

	/**
	 * Validate one storage level, retaining every applicable definition.
	 *
	 * @param array  $values      Stored values.
	 * @param array  $definitions Definitions by key.
	 * @param string $path        Parent storage path.
	 * @param string $source      Source for unknown fields.
	 * @param array  $disabled    Disabled root field IDs.
	 * @param array  $findings    Findings, passed by reference.
	 * @return void
	 */
	private static function values( array $values, array $definitions, string $path, string $source, array $disabled, array &$findings ): void {
		$known = array_fill_keys( array_keys( $definitions ), true );
		foreach ( $definitions as $key => $entries ) {
			foreach ( $entries as $entry ) {
				if ( 'files' === ( $entry['field']['type'] ?? '' ) ) {
					$known[ $key . '__size' ] = true;
				}
			}
		}
		foreach ( $values as $key => $value ) {
			if ( ! isset( $known[ $key ] ) ) {
				$findings[] = self::finding( 'unknown_field', self::path( $path, $key ), $source, $value, array_keys( $known ) );
			}
		}
		foreach ( $definitions as $key => $entries ) {
			$field_path = self::path( $path, $key );
			$shapes     = array_unique( array_map( static fn( array $entry ): string => self::shape( $entry['field'] ), $entries ) );
			if ( count( $shapes ) > 1 ) {
				$expected   = array_map( static fn( array $entry ): string => $entry['source'] . ':' . $entry['field']['type'], $entries );
				$findings[] = self::finding( 'definition_conflict', $field_path, $entries[0]['source'], $values[ $key ] ?? null, $expected );
				continue;
			}
			if ( ! array_key_exists( $key, $values ) || in_array( $key, $disabled, true ) ) {
				continue;
			}
			// The renderer reads the first matching row field in each source.
			// Retain every definition above for conflict diagnostics only.
			$effective = array();
			foreach ( $entries as $entry ) {
				$effective[ $entry['source'] ] ??= $entry;
			}
			$entries = array_values( $effective );
			$value   = $values[ $key ];
			foreach ( $entries as $entry ) {
				self::value( $value, $entry, $field_path, $findings );
			}
			if ( 'repeater' !== ( $entries[0]['field']['type'] ?? '' ) || ! is_array( $value ) || ! array_is_list( $value ) ) {
				continue;
			}
			$children = array();
			foreach ( $entries as $entry ) {
				$inner = Field_Definitions::collect( Field_Definitions::children( $entry['field']['attributes'] ?? array() ), $entry['source'] );
				$built = array();
				foreach ( $entry['compiled']['attributes'] ?? array() as $attribute ) {
					if ( is_array( $attribute ) && isset( $attribute['id'] ) && ! empty( $attribute['blockstudio'] ) ) {
						$built[ $attribute['id'] ] ??= $attribute;
					}
				}
				$children = Field_Definitions::combine( $children, self::compiled( $inner, $built ) );
			}
			foreach ( $value as $index => $row ) {
				$row_values = self::object_values( $row );
				if ( null === $row_values ) {
					$findings[] = self::finding( 'wrong_type', self::path( $field_path, $index ), $entries[0]['source'], $row, 'object' );
					continue;
				}
				self::values( $row_values, $children, self::path( $field_path, $index ), $entries[0]['source'], array(), $findings );
			}
		}
	}

	/**
	 * Validate a present value against one applicable field.
	 *
	 * @param mixed  $value    Stored value.
	 * @param array  $entry    Field and source metadata.
	 * @param string $path     Storage path.
	 * @param array  $findings Findings, passed by reference.
	 * @return void
	 */
	private static function value( mixed $value, array $entry, string $path, array &$findings ): void {
		$field = $entry['field'];
		$type  = $field['type'];
		if ( null === $value || false === $value || '' === $value || ( array() === $value && 'repeater' !== $type ) ) {
			return;
		}
		$valid = match ( $type ) {
			'repeater', 'token', 'attributes' => is_array( $value ) && array_is_list( $value ),
			'select', 'checkbox' => self::option_shape( $value, Field_Type_Config::is_multiple_options( $type, $field ) ),
			'radio' => self::option_shape( $value, false ),
			'number', 'range' => is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ),
			'toggle' => is_bool( $value ),
			'files' => self::file_shape( $value ),
			'link', 'icon' => null !== self::object_values( $value ),
			'color', 'gradient', 'html-tag', 'block' => is_string( $value ),
			default => in_array( $type, Field_Type_Config::STRING_TYPES, true )
				? is_string( $value ) || is_int( $value ) || is_float( $value )
				: self::declared_shape( $value, Field_Type_Config::get_attribute_type( $type ) ),
		};
		if ( ! $valid ) {
			$findings[] = self::finding( 'wrong_type', $path, $entry['source'], $value, self::shape( $field ) );
			return;
		}
		if ( 'repeater' === $type ) {
			if ( isset( $field['min'] ) && count( $value ) < $field['min'] ) {
				$findings[] = self::finding( 'too_few_rows', $path, $entry['source'], $value, (int) $field['min'] );
			}
			if ( isset( $field['max'] ) && count( $value ) > $field['max'] ) {
				$findings[] = self::finding( 'too_many_rows', $path, $entry['source'], $value, (int) $field['max'] );
			}
			return;
		}
		if ( 'token' === $type ) {
			foreach ( $value as $index => $token ) {
				if ( ! self::option_shape( $token, false ) ) {
					$findings[] = self::finding( 'wrong_type', self::path( $path, $index ), $entry['source'], $token, 'option' );
				}
			}
			return;
		}
		if ( 'attributes' === $type ) {
			foreach ( $value as $index => $pair ) {
				$object = self::object_values( $pair );
				if ( null === $object || ! is_string( $object['attribute'] ?? null ) || '' === $object['attribute'] || ! is_scalar( $object['value'] ?? null ) ) {
					$findings[] = self::finding( 'wrong_type', self::path( $path, $index ), $entry['source'], $pair, 'attribute' );
				}
			}
			return;
		}
		self::options( $value, $entry, $path, $findings );
	}

	/**
	 * Check only option sets that registration already resolved.
	 *
	 * @param mixed  $value    Stored option value.
	 * @param array  $entry    Field and compiled metadata.
	 * @param string $path     Storage path.
	 * @param array  $findings Findings, passed by reference.
	 * @return void
	 */
	private static function options( mixed $value, array $entry, string $path, array &$findings ): void {
		$field = $entry['field'];
		$type  = $field['type'];
		if ( ! in_array( $type, Field_Type_Config::OPTION_TYPES, true ) ) {
			return;
		}
		// Palette pickers accept custom values unless the control disables them.
		if ( ( 'color' === $type && empty( $field['disableCustomColors'] ) ) || ( 'gradient' === $type && empty( $field['disableCustomGradients'] ) ) ) {
			return;
		}
		$populate = $field['populate'] ?? array();
		if ( 'fetch' === ( $populate['type'] ?? '' ) || ! empty( $populate['fetch'] ) || Populate::has_dynamic_arguments( $populate ) ) {
			return;
		}
		$options  = array() !== $populate
			? ( $entry['compiled']['options'] ?? $field['options'] ?? array() )
			: ( $field['options'] ?? $entry['compiled']['options'] ?? array() );
		$multiple = 'checkbox' === $type || ( 'select' === $type && ! empty( $field['multiple'] ) );
		if ( 0 === $value && ! $multiple ) {
			return;
		}
		$stored     = $value;
		$is_list    = $multiple && is_array( $stored ) && array_is_list( $stored );
		$value      = is_object( $value ) ? get_object_vars( $value ) : $value;
		$selections = $multiple ? Field_Type_Config::multiple_option_values( $value ) : array( $value );
		$expected   = array_map( static fn( $option ) => is_array( $option ) ? ( $option['value'] ?? null ) : $option, $options );
		foreach ( $selections as $index => $selection ) {
			$selection = is_object( $selection ) ? get_object_vars( $selection ) : $selection;
			if ( false === Block::get_option_value( array( 'options' => $options ), 'both', $selection ) ) {
				$item_path  = $is_list ? self::path( $path, $index ) : $path;
				$findings[] = self::finding( 'unknown_option', $item_path, $entry['source'], $is_list ? $stored[ $index ] : $stored, $expected );
			}
		}
	}

	/**
	 * Compare storage families, not merely field names or translation policy.
	 *
	 * @param array $field Field definition.
	 * @return string Storage family.
	 */
	private static function shape( array $field ): string {
		$type = $field['type'];
		if ( in_array( $type, Field_Type_Config::STRING_TYPES, true ) || in_array( $type, array( 'color', 'gradient', 'html-tag', 'block' ), true ) ) {
			return 'string';
		}
		return match ( $type ) {
			'select', 'checkbox', 'radio' => Field_Type_Config::is_multiple_options( $type, $field ) ? 'options' : 'option',
			'files', 'link', 'icon', 'repeater', 'token' => $type,
			default => implode( '|', (array) Field_Type_Config::get_attribute_type( $type ) ),
		};
	}

	/**
	 * Read JSON object-like values without modifying the caller's data.
	 *
	 * @param mixed $value Value.
	 * @return array|null Object members, or null for a non-object shape.
	 */
	private static function object_values( mixed $value ): ?array {
		if ( is_object( $value ) ) {
			return get_object_vars( $value );
		}
		return is_array( $value ) && ( array() === $value || ! array_is_list( $value ) ) ? $value : null;
	}

	/**
	 * Accept the scalar and option-object forms supported by option rendering.
	 *
	 * @param mixed $value    Value.
	 * @param bool  $multiple Whether imported single selections are supported.
	 * @return bool Whether the shape is supported.
	 */
	private static function option_shape( mixed $value, bool $multiple ): bool {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( $multiple && is_array( $value ) && ! array_key_exists( 'value', $value ) ) {
			return array_is_list( $value ) && array() === array_filter( $value, static fn( $item ): bool => ! self::option_shape( $item, false ) );
		}
		if ( is_array( $value ) ) {
			return array_key_exists( 'value', $value ) && is_scalar( $value['value'] );
		}
		return is_string( $value ) || is_int( $value ) || is_float( $value ) || ( ! $multiple && is_bool( $value ) );
	}

	/**
	 * File IDs and references do not need a database or media lookup to validate.
	 *
	 * @param mixed $value Stored file value.
	 * @return bool Whether the value is an ID, reference, object, or list thereof.
	 */
	private static function file_shape( mixed $value ): bool {
		if ( is_string( $value ) ) {
			return ! is_numeric( $value ) || ctype_digit( $value );
		}
		if ( is_int( $value ) ) {
			return $value >= 0;
		}
		$object = self::object_values( $value );
		if ( null !== $object && ( isset( $object['id'] ) || isset( $object['ID'] ) ) ) {
			return self::file_shape( $object['id'] ?? $object['ID'] );
		}
		return is_array( $value ) && array_is_list( $value ) && array() === array_filter( $value, static fn( $item ): bool => ! self::file_shape( $item ) );
	}

	/**
	 * Validate custom field types using their declared WordPress attribute type.
	 *
	 * @param mixed             $value Value.
	 * @param string|array|null $types Declared types.
	 * @return bool Whether a declared type accepts the value.
	 */
	private static function declared_shape( mixed $value, string|array|null $types ): bool {
		foreach ( (array) $types as $type ) {
			$valid = match ( $type ) {
				'string' => is_string( $value ),
				'integer' => is_int( $value ),
				'number' => is_int( $value ) || is_float( $value ),
				'boolean' => is_bool( $value ),
				'object' => null !== self::object_values( $value ),
				'array' => is_array( $value ),
				default => true,
			};
			if ( $valid ) {
				return true;
			}
		}
		return null === $types;
	}

	/**
	 * Append a JSON Pointer escaped storage segment, without a leading slash.
	 *
	 * @param string     $parent Parent path.
	 * @param int|string $key    Storage key or row index.
	 * @return string Path.
	 */
	private static function path( string $parent, int|string $key ): string {
		$segment = str_replace( array( '~', '/' ), array( '~0', '~1' ), (string) $key );
		return '' === $parent ? $segment : $parent . '/' . $segment;
	}

	/**
	 * Create a stable machine-readable finding with a translatable message.
	 *
	 * @param string $code     Finding code.
	 * @param string $path     Storage path.
	 * @param string $source   Definition source.
	 * @param mixed  $value    Stored value.
	 * @param mixed  $expected Allowed values, type, bound, or winning value.
	 * @return array Finding.
	 */
	private static function finding( string $code, string $path, string $source, mixed $value, mixed $expected ): array {
		$message = match ( $code ) {
			'unknown_field' => __( 'This storage key is not declared by the block or its extensions.', 'blockstudio' ),
			'unknown_attribute' => __( 'This top-level key is not an attribute of the block, so it is ignored.', 'blockstudio' ),
			'missing_container' => __( 'This key is only read inside the blockstudio object, which is missing.', 'blockstudio' ),
			'unknown_option' => __( 'This value is not among the field options.', 'blockstudio' ),
			'wrong_type' => __( 'This value has an unsupported shape for the field.', 'blockstudio' ),
			'too_few_rows' => __( 'This repeater has fewer rows than its minimum.', 'blockstudio' ),
			'too_many_rows' => __( 'This repeater has more rows than its maximum.', 'blockstudio' ),
			'definition_conflict' => __( 'This storage key has conflicting field definitions.', 'blockstudio' ),
			'shadowed_value' => __( 'The nested field value overrides this top-level value.', 'blockstudio' ),
			'extension_top_level' => __( 'Extension bindings read this value only from blockstudio.attributes.', 'blockstudio' ),
			'invalid_container' => __( 'Blockstudio field values must be stored in an object.', 'blockstudio' ),
		};
		return compact( 'code', 'path', 'source', 'message', 'value', 'expected' );
	}

	/**
	 * Allow integrations to adapt findings to their render-time value resolvers.
	 *
	 * @param array  $findings Findings.
	 * @param string $name     Block name.
	 * @param array  $attrs    Original attributes.
	 * @return array Findings.
	 */
	private static function filter( array $findings, string $name, array $attrs ): array {
		/**
		 * Filter findings from explicit stored-value validation.
		 *
		 * @since 7.7.5
		 * @param array  $findings Findings; an empty list means valid.
		 * @param string $name     Block name.
		 * @param array  $attrs    Original parsed attrs object.
		 */
		return apply_filters( 'blockstudio/blocks/attributes/validate', $findings, $name, $attrs );
	}
}

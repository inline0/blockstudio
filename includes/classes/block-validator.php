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

		$block       = WP_Block_Type_Registry::get_instance()->get_registered( $name );
		$sets        = self::definition_sets( $name, $block, $fields, $extensions );
		$own         = $sets[0];
		$extended    = $sets[1];
		$definitions = $sets[2];
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
	 * Describe the values a block stores in blockstudio.attributes as a JSON Schema.
	 *
	 * Built from the definitions validate() uses. Each field is described in the
	 * simplest stored form validate() accepts: an option value instead of an
	 * option object, attachment IDs instead of file objects. Only keywords that
	 * rest_validate_value_from_schema() checks are used for constraints, so the
	 * schema works as a REST or ability input schema as it is.
	 *
	 * @param string $name Registered block name.
	 * @return array|null Schema, or null when Blockstudio has no fields for this block.
	 */
	public static function schema( string $name ): ?array {
		$fields     = \bs_block_fields( $name );
		$extensions = \bs_block_extension_fields( $name );
		if ( null === $fields && array() === $extensions ) {
			return null;
		}

		$block  = WP_Block_Type_Registry::get_instance()->get_registered( $name );
		$schema = array( '$schema' => 'https://json-schema.org/draft/2020-12/schema' )
			+ ( is_string( $block->title ?? null ) && '' !== $block->title ? array( 'title' => $block->title ) : array() )
			+ self::object_schema( self::definition_sets( $name, $block, $fields, $extensions )[2] );

		/**
		 * Filter the schema of a block's stored field values.
		 *
		 * @since 7.7.13
		 * @param array  $schema JSON Schema of blockstudio.attributes.
		 * @param string $name   Block name.
		 */
		return apply_filters( 'blockstudio/blocks/attributes/schema', $schema, $name );
	}

	/**
	 * Compile the block's own and its extensions' storage definitions.
	 *
	 * @param string              $name       Block name.
	 * @param \WP_Block_Type|null $block      Registered block type.
	 * @param array|null          $fields     Own field definitions.
	 * @param array               $extensions Extension field definitions.
	 * @return array{0: array, 1: array, 2: array} Own, extension and combined definitions.
	 */
	private static function definition_sets( string $name, ?WP_Block_Type $block, ?array $fields, array $extensions ): array {
		$own      = self::compiled( Field_Definitions::latest( Field_Definitions::collect( $fields ?? array(), 'block' ) ), $block->attributes ?? array() );
		$extended = array();
		foreach ( Extensions::get_matches( $name, Build::extensions() ) as $extension ) {
			$extended = array_replace( $extended, $extension->attributes ?? array() );
		}
		$extended = self::compiled( Field_Definitions::latest( Field_Definitions::collect( $extensions, 'extension' ) ), $extended );
		return array( $own, $extended, Field_Definitions::combine( $own, $extended ) );
	}

	/**
	 * Schema of one storage level: a block's values or one repeater row.
	 *
	 * Keys with conflicting definitions are left out, because validate()
	 * reports every value stored under them.
	 *
	 * @param array $definitions Compiled definitions by storage key.
	 * @return array Object schema.
	 */
	private static function object_schema( array $definitions ): array {
		$properties = array();
		foreach ( $definitions as $key => $entries ) {
			if ( count( array_unique( array_map( static fn( array $entry ): string => self::shape( $entry['field'] ), $entries ) ) ) > 1 ) {
				continue;
			}
			// Only the first definition of each source is read, as in values().
			$effective = array();
			foreach ( $entries as $entry ) {
				$effective[ $entry['source'] ] ??= $entry;
			}
			$schemas = array_map( array( self::class, 'field_schema' ), array_values( $effective ) );
			$schema  = array_shift( $schemas );
			foreach ( $schemas as $other ) {
				$schema = self::narrow( $schema, $other );
			}
			if ( 'repeater' === $entries[0]['field']['type'] && count( $effective ) > 1 ) {
				$schema['items'] = self::object_schema( self::repeater_definitions( array_values( $effective ) ) );
			}
			$properties[ (string) $key ] = $schema;
			if ( 'files' === $entries[0]['field']['type'] && ! isset( $definitions[ $key . '__size' ] ) ) {
				$properties[ $key . '__size' ] = array( 'type' => 'string' );
			}
		}
		// Without fields there is no properties keyword: an empty PHP array would
		// encode as a JSON list, and WordPress reads properties as an array.
		return array( 'type' => 'object' )
			+ ( array() === $properties ? array() : array( 'properties' => $properties ) )
			+ array( 'additionalProperties' => false );
	}

	/**
	 * Intersect option sets and bounds from two definitions of one key.
	 *
	 * @param array $schema First field schema.
	 * @param array $other  Second field schema of the same storage shape.
	 * @return array Schema accepted by both.
	 */
	private static function narrow( array $schema, array $other ): array {
		if ( 'null' === ( $schema['type'] ?? null ) || 'null' === ( $other['type'] ?? null ) ) {
			unset( $schema['enum'] );
			$schema['type'] = 'null';
			return $schema;
		}
		if ( isset( $schema['enum'], $other['enum'] ) ) {
			$schema['enum'] = array_values( array_filter( $schema['enum'], static fn( $value ): bool => false !== Block::get_option_value( array( 'options' => $other['enum'] ), 'both', $value ) ) );
		} elseif ( isset( $other['enum'] ) ) {
			$schema['enum'] = $other['enum'];
		}
		if ( isset( $schema['items'], $other['items'] ) ) {
			$schema['items'] = self::narrow( $schema['items'], $other['items'] );
		}
		foreach ( array( 'minimum', 'minItems' ) as $bound ) {
			if ( isset( $other[ $bound ] ) ) {
				$schema[ $bound ] = isset( $schema[ $bound ] ) ? max( $schema[ $bound ], $other[ $bound ] ) : $other[ $bound ];
			}
		}
		foreach ( array( 'maximum', 'maxItems' ) as $bound ) {
			if ( isset( $other[ $bound ] ) ) {
				$schema[ $bound ] = isset( $schema[ $bound ] ) ? min( $schema[ $bound ], $other[ $bound ] ) : $other[ $bound ];
			}
		}
		// WordPress ignores an empty enum. Limit unavailable choices to a clear.
		if ( isset( $schema['enum'] ) && array() === $schema['enum'] ) {
			unset( $schema['enum'] );
			$schema['type'] = 'null';
			return $schema;
		}
		if ( 'array' === ( $schema['type'] ?? null ) && 'null' === ( $schema['items']['type'] ?? null ) ) {
			$schema['maxItems'] = 0;
		}
		return $schema;
	}

	/**
	 * Schema of one field's stored value in its simplest accepted form.
	 *
	 * @param array $entry Compiled definition.
	 * @return array Field schema.
	 */
	private static function field_schema( array $entry ): array {
		$field  = $entry['field'];
		$type   = $field['type'];
		$schema = match ( true ) {
			in_array( $type, Field_Type_Config::STRING_TYPES, true ), in_array( $type, array( 'html-tag', 'block' ), true ) => array( 'type' => 'string' ),
			in_array( $type, array( 'number', 'range' ), true ) => array( 'type' => 'number' ) + array_filter(
				array(
					'minimum' => $field['min'] ?? null,
					'maximum' => $field['max'] ?? null,
				),
				'is_numeric'
			),
			'toggle' === $type => array( 'type' => 'boolean' ),
			in_array( $type, array( 'select', 'radio', 'checkbox', 'color', 'gradient' ), true ) => self::option_schema( $entry ),
			'token' === $type => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'files' === $type => empty( $field['multiple'] ) ? array(
				'type'    => 'integer',
				'minimum' => 0,
			) : array(
				'type'  => 'array',
				'items' => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
			),
			'link' === $type => array(
				'type'       => 'object',
				'properties' => array(
					'url'           => array( 'type' => 'string' ),
					'title'         => array( 'type' => 'string' ),
					'opensInNewTab' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'url' ),
			),
			'icon' === $type => array(
				'type'       => 'object',
				'properties' => array(
					'set'    => array( 'type' => 'string' ),
					'subSet' => array( 'type' => 'string' ),
					'icon'   => array( 'type' => 'string' ),
				),
				'required'   => array( 'set', 'icon' ),
			),
			'attributes' === $type => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'attribute' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'value'     => array( 'type' => array( 'string', 'number', 'boolean' ) ),
					),
					'required'   => array( 'attribute', 'value' ),
				),
			),
			'repeater' === $type => self::repeater_schema( $entry ),
			default => array( 'type' => self::json_types( Field_Type_Config::get_attribute_type( $type ) ) ),
		};

		$annotations = array(
			'label' => 'title',
			'help'  => 'description',
		);
		foreach ( $annotations as $property => $keyword ) {
			if ( is_string( $field[ $property ] ?? null ) && '' !== trim( $field[ $property ] ) ) {
				$schema[ $keyword ] = trim( $field[ $property ] );
			}
		}
		if ( array_key_exists( 'default', $field ) ) {
			$schema['default'] = $field['default'];
		}
		return $schema;
	}

	/**
	 * Option values of a select, radio, checkbox, color, or gradient field.
	 *
	 * Uses the options validate() checks against. Remote and attribute-dependent
	 * populate options, and palettes that allow custom values, have no fixed list.
	 *
	 * @param array $entry Compiled definition.
	 * @return array Field schema.
	 */
	private static function option_schema( array $entry ): array {
		$field    = $entry['field'];
		$type     = $field['type'];
		$populate = $field['populate'] ?? array();
		$multiple = Field_Type_Config::is_multiple_options( $type, $field );
		$value    = array( 'type' => in_array( $type, array( 'color', 'gradient' ), true ) ? 'string' : array( 'string', 'number' ) );

		$open = ( 'color' === $type && empty( $field['disableCustomColors'] ) ) ||
			( 'gradient' === $type && empty( $field['disableCustomGradients'] ) ) ||
			'fetch' === ( $populate['type'] ?? '' ) || ! empty( $populate['fetch'] ) || Populate::has_dynamic_arguments( $populate );
		if ( ! $open ) {
			$options = array() !== $populate
				? ( $entry['compiled']['options'] ?? $field['options'] ?? array() )
				: ( $field['options'] ?? $entry['compiled']['options'] ?? array() );
			$values  = array_values(
				array_filter(
					array_map( static fn( $option ) => is_array( $option ) ? ( $option['value'] ?? null ) : $option, $options ),
					'is_scalar'
				)
			);
			$types   = array_values( array_unique( array_map( static fn( $option ): string => is_bool( $option ) ? 'boolean' : ( is_string( $option ) ? 'string' : 'number' ), $values ) ) );
			$value   = array(
				'type' => array() === $types ? $value['type'] : self::json_types( $types ),
				'enum' => $values,
			);
		}

		if ( isset( $value['enum'] ) && array() === $value['enum'] ) {
			return $multiple
				? array(
					'type'     => 'array',
					'items'    => array( 'type' => 'null' ),
					'maxItems' => 0,
				)
				: array( 'type' => 'null' );
		}

		return $multiple
			? array(
				'type'  => 'array',
				'items' => $value,
			)
			: $value;
	}

	/**
	 * Rows of a repeater: an object per row, within the field's bounds.
	 *
	 * @param array $entry Compiled definition.
	 * @return array Field schema.
	 */
	private static function repeater_schema( array $entry ): array {
		$field = $entry['field'];

		return array(
			'type'  => 'array',
			'items' => self::object_schema( self::repeater_definitions( array( $entry ) ) ),
		) + array_filter(
			array(
				'minItems' => isset( $field['min'] ) ? (int) $field['min'] : null,
				'maxItems' => isset( $field['max'] ) ? (int) $field['max'] : null,
			),
			'is_int'
		);
	}

	/**
	 * Combine the compiled row definitions read by every applicable render path.
	 *
	 * @param array $entries Effective repeater definitions.
	 * @return array Row definitions by storage key.
	 */
	private static function repeater_definitions( array $entries ): array {
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
		return $children;
	}

	/**
	 * JSON types for a declared WordPress attribute type.
	 *
	 * @param string|array|null $types Declared type or types; null accepts any type.
	 * @return string|array JSON type or types.
	 */
	private static function json_types( string|array|null $types ): string|array {
		$types = null === $types ? array( 'string', 'number', 'boolean', 'object', 'array', 'null' ) : array_values( (array) $types );
		return 1 === count( $types ) ? $types[0] : $types;
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
				$finding    = self::finding( 'unknown_field', self::path( $path, $key ), $source, $value, array_keys( $known ) );
				$suggestion = self::closest_key( (string) $key, array_keys( $known ) );
				if ( null !== $suggestion ) {
					/* translators: %s: Closest storage key the block declares. */
					$finding['message'] .= ' ' . sprintf( __( 'Did you mean "%s"?', 'blockstudio' ), $suggestion );
				}
				$findings[] = $finding;
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
			$children = self::repeater_definitions( $entries );
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
	 * Closest known storage key to a misspelled one, the rule Settings uses for unknown settings.
	 *
	 * @param string                 $key   Unknown storage key.
	 * @param array<int, int|string> $known Known storage keys at the same level.
	 * @return string|null Suggestion, or null when nothing is within three edits.
	 */
	private static function closest_key( string $key, array $known ): ?string {
		$best     = null;
		$distance = PHP_INT_MAX;
		$key      = strtolower( $key );
		foreach ( $known as $candidate ) {
			$candidate = (string) $candidate;
			if ( abs( strlen( $key ) - strlen( $candidate ) ) > 3 ) {
				continue;
			}
			$current = levenshtein( $key, strtolower( $candidate ) );
			if ( $current < $distance ) {
				$distance = $current;
				$best     = $candidate;
				if ( 0 === $distance ) {
					break;
				}
			}
		}
		return $distance <= 3 ? $best : null;
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

<?php
/**
 * Field Type Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Registry for custom Blockstudio field type definitions.
 *
 * Built-in field types remain defined by Field_Type_Config. This registry adds
 * validated namespaced custom field types and exposes the combined type map to
 * attribute builders, storage handlers, editor asset loading, and tests.
 *
 * @since 7.5.0
 */
final class Field_Type_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Field_Type_Registry|null
	 */
	private static ?Field_Type_Registry $instance = null;

	/**
	 * Manually registered custom field types.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $registered = array();

	/**
	 * Filter-registered custom field types.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $filtered = array();

	/**
	 * Whether filter registrations have been loaded.
	 *
	 * @var bool
	 */
	private bool $filters_loaded = false;

	/**
	 * Custom field types used by currently registered blocks.
	 *
	 * @var array<string, bool>
	 */
	private array $used = array();

	/**
	 * Valid WordPress attribute/storage types.
	 *
	 * @var array<string>
	 */
	private const ATTRIBUTE_TYPES = array(
		'string',
		'number',
		'boolean',
		'object',
		'array',
	);

	/**
	 * Get the singleton instance.
	 *
	 * @return Field_Type_Registry Registry instance.
	 */
	public static function instance(): Field_Type_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Validate a custom field type name.
	 *
	 * @param string $name Field type name.
	 *
	 * @return bool Whether the name is valid.
	 */
	public static function is_valid_custom_name( string $name ): bool {
		if ( ! preg_match( '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', $name ) ) {
			return false;
		}

		$namespace = explode( '/', $name, 2 )[0];

		return ! in_array( $namespace, array( 'custom', 'blockstudio' ), true );
	}

	/**
	 * Check whether a type name belongs to a built-in field.
	 *
	 * @param string $name Field type name.
	 *
	 * @return bool Whether the field type is built in.
	 */
	public static function is_builtin_type( string $name ): bool {
		return array_key_exists( $name, Field_Type_Config::TYPES );
	}

	/**
	 * Register a custom field type.
	 *
	 * @param string $name       Field type name.
	 * @param array  $definition Field type definition.
	 *
	 * @return bool Whether registration succeeded.
	 */
	public function register( string $name, array $definition ): bool {
		if (
			! self::is_valid_custom_name( $name ) ||
			self::is_builtin_type( $name ) ||
			isset( $this->registered[ $name ] ) ||
			! $this->is_valid_definition( $definition )
		) {
			return false;
		}

		$this->registered[ $name ] = $this->normalize_definition( $definition );
		$this->filters_loaded      = false;

		return true;
	}

	/**
	 * Unregister a manually registered custom field type.
	 *
	 * @param string $name Field type name.
	 *
	 * @return bool Whether the field type existed.
	 */
	public function unregister( string $name ): bool {
		if ( ! isset( $this->registered[ $name ] ) ) {
			return false;
		}

		unset( $this->registered[ $name ], $this->used[ $name ] );
		$this->filters_loaded = false;

		return true;
	}

	/**
	 * Get all built-in and custom field type definitions.
	 *
	 * @return array<string, array<string, mixed>> Field type definitions.
	 */
	public function all(): array {
		$this->load_filters();

		return array_merge( Field_Type_Config::TYPES, $this->registered, $this->filtered );
	}

	/**
	 * Get a field type definition.
	 *
	 * @param string $name Field type name.
	 *
	 * @return array<string, mixed>|null Field type definition.
	 */
	public function get( string $name ): ?array {
		$types = $this->all();

		return $types[ $name ] ?? null;
	}

	/**
	 * Check whether a field type exists.
	 *
	 * @param string $name Field type name.
	 *
	 * @return bool Whether the field type is registered.
	 */
	public function has( string $name ): bool {
		return null !== $this->get( $name );
	}

	/**
	 * Check whether a field type is a registered custom type.
	 *
	 * @param string $name Field type name.
	 *
	 * @return bool Whether this is a custom field type.
	 */
	public function is_custom_type( string $name ): bool {
		return ! self::is_builtin_type( $name ) && null !== $this->get( $name );
	}

	/**
	 * Check whether a field type produces a WordPress attribute.
	 *
	 * @param string $name Field type name.
	 *
	 * @return bool Whether the type produces an attribute.
	 */
	public function produces_attribute( string $name ): bool {
		$definition = $this->get( $name );

		if ( ! $definition ) {
			return true;
		}

		return false !== ( $definition['produces_attribute'] ?? true ) &&
			null !== ( $definition['attribute'] ?? null );
	}

	/**
	 * Build a generic WordPress attribute for a custom field type.
	 *
	 * @param array  $field    Field configuration.
	 * @param string $field_id Attribute ID.
	 *
	 * @return array<string, mixed>|null Built attribute.
	 */
	public function build_attribute( array $field, string $field_id ): ?array {
		$type       = $field['type'] ?? '';
		$definition = is_string( $type ) ? $this->get( $type ) : null;

		if ( '' === $field_id || ! $definition || ! $this->produces_attribute( $type ) ) {
			return null;
		}

		$attribute = array(
			'blockstudio' => true,
			'type'        => $definition['attribute'],
			'field'       => $type,
			'id'          => $field_id,
		);

		if ( array_key_exists( 'source', $field ) ) {
			$attribute['source'] = $field['source'];
		} elseif ( array_key_exists( 'source', $definition ) ) {
			$attribute['source'] = $definition['source'];
		}

		if ( array_key_exists( 'default', $field ) ) {
			$attribute['default'] = $field['default'];
		} elseif ( array_key_exists( 'default', $definition ) ) {
			$attribute['default'] = $definition['default'];
		}

		foreach ( array( 'fallback', 'storage', 'set' ) as $key ) {
			if ( array_key_exists( $key, $field ) ) {
				$attribute[ $key ] = $field[ $key ];
			}
		}

		return $attribute;
	}

	/**
	 * Get the storage value type for a field.
	 *
	 * @param array $field Field configuration.
	 *
	 * @return string|null Storage type or null when unknown.
	 */
	public function get_storage_value_type( array $field ): ?string {
		$type = $field['type'] ?? '';

		if ( isset( $field['__blockstudio_storage_value_type'] ) ) {
			return $this->normalize_storage_type( $field['__blockstudio_storage_value_type'] );
		}

		if ( ! is_string( $type ) || ! $this->is_custom_type( $type ) ) {
			return null;
		}

		$definition = $this->get( $type );
		$storage    = $definition['storage'] ?? array();

		if ( is_array( $storage ) && isset( $storage['type'] ) ) {
			return $this->normalize_storage_type( $storage['type'] );
		}

		return $this->normalize_storage_type( $definition['attribute'] ?? null );
	}

	/**
	 * Get a REST schema for a storage-backed field.
	 *
	 * @param array  $field      Field configuration.
	 * @param string $value_type Storage value type.
	 *
	 * @return array<string, mixed>|null REST schema.
	 */
	public function get_storage_rest_schema( array $field, string $value_type ): ?array {
		if ( isset( $field['storage']['rest_schema'] ) && is_array( $field['storage']['rest_schema'] ) ) {
			return $this->normalize_storage_rest_schema( $field['storage']['rest_schema'], $value_type );
		}

		$type = $field['type'] ?? '';
		if ( is_string( $type ) && $this->is_custom_type( $type ) ) {
			$definition = $this->get( $type );
			$storage    = $definition['storage'] ?? array();

			if ( is_array( $storage ) && isset( $storage['rest_schema'] ) && is_array( $storage['rest_schema'] ) ) {
				return $this->normalize_storage_rest_schema( $storage['rest_schema'], $value_type );
			}
		}

		if ( 'array' === $value_type ) {
			return array(
				'type'  => 'array',
				'items' => $this->array_item_schema( $field ),
			);
		}

		if ( 'object' === $value_type ) {
			return array(
				'type'                 => 'object',
				'additionalProperties' => true,
			);
		}

		return null;
	}

	/**
	 * Normalize a custom storage REST schema for the resolved storage value type.
	 *
	 * @param array  $schema     REST schema.
	 * @param string $value_type Storage value type.
	 *
	 * @return array<string, mixed>
	 */
	private function normalize_storage_rest_schema( array $schema, string $value_type ): array {
		if ( 'array' === $value_type && 'array' !== ( $schema['type'] ?? '' ) ) {
			return array(
				'type'  => 'array',
				'items' => $schema,
			);
		}

		if ( 'array' === $value_type && ! isset( $schema['items'] ) ) {
			$schema['items'] = array( 'type' => self::ATTRIBUTE_TYPES );
		}

		return $schema;
	}

	/**
	 * Build a permissive REST schema for array item values.
	 *
	 * @param array $field Field configuration.
	 *
	 * @return array<string, mixed> Item schema.
	 */
	private function array_item_schema( array $field ): array {
		$type      = $field['type'] ?? '';
		$item_type = is_string( $type ) ? Field_Type_Config::get_attribute_type( $type ) : null;

		if ( in_array( $item_type, self::ATTRIBUTE_TYPES, true ) && 'array' !== $item_type ) {
			return 'object' === $item_type
				? array(
					'type'                 => 'object',
					'additionalProperties' => true,
				)
				: array( 'type' => $item_type );
		}

		return array( 'type' => self::ATTRIBUTE_TYPES );
	}

	/**
	 * Mark all custom field types used in a field tree.
	 *
	 * @param array $fields Field definitions.
	 *
	 * @return void
	 */
	public function mark_used_fields( array $fields ): void {
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = $field['type'] ?? '';
			if ( is_string( $type ) && $this->is_custom_type( $type ) ) {
				$this->used[ $type ] = true;
			}

			foreach ( array( 'attributes', 'fields' ) as $key ) {
				if ( isset( $field[ $key ] ) && is_array( $field[ $key ] ) ) {
					$this->mark_used_fields( $field[ $key ] );
				}
			}

			if ( isset( $field['tabs'] ) && is_array( $field['tabs'] ) ) {
				foreach ( $field['tabs'] as $tab ) {
					if ( isset( $tab['attributes'] ) && is_array( $tab['attributes'] ) ) {
						$this->mark_used_fields( $tab['attributes'] );
					}
				}
			}
		}
	}

	/**
	 * Enqueue editor assets for used custom field types.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		foreach ( array_keys( $this->used ) as $type ) {
			$definition = $this->get( $type );
			if ( ! $definition ) {
				continue;
			}

			$this->enqueue_handles( $definition['editor_script'] ?? array(), 'script', $type );
			$this->enqueue_handles( $definition['editor_style'] ?? array(), 'style', $type );
		}
	}

	/**
	 * Get a fingerprint of all field type definitions.
	 *
	 * @return string Field type fingerprint.
	 */
	public function fingerprint(): string {
		return md5( wp_json_encode( $this->all() ) );
	}

	/**
	 * Reset registry state for tests.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->registered     = array();
		$this->filtered       = array();
		$this->filters_loaded = false;
		$this->used           = array();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

	/**
	 * Load filter-registered field types once per request.
	 *
	 * @return void
	 */
	private function load_filters(): void {
		if ( $this->filters_loaded ) {
			return;
		}

		$this->filtered = array();

		/**
		 * Register custom Blockstudio field types.
		 *
		 * @param array<string, array<string, mixed>> $types Custom field types keyed by namespaced type name.
		 */
		$types = apply_filters( 'blockstudio/field_types', $this->registered );

		if ( is_array( $types ) ) {
			foreach ( $types as $name => $definition ) {
				if (
					! is_string( $name ) ||
					! is_array( $definition ) ||
					! self::is_valid_custom_name( $name ) ||
					! $this->is_valid_definition( $definition )
				) {
					continue;
				}

				$this->filtered[ $name ] = $this->normalize_definition( $definition );
			}
		}

		$this->filters_loaded = true;
	}

	/**
	 * Validate a field type definition.
	 *
	 * @param array $definition Field type definition.
	 *
	 * @return bool Whether the definition is valid.
	 */
	private function is_valid_definition( array $definition ): bool {
		if ( ! array_key_exists( 'attribute', $definition ) ) {
			return false;
		}

		if ( ! $this->is_valid_attribute_type( $definition['attribute'] ) ) {
			return false;
		}

		foreach ( array( 'editor_script', 'editor_style' ) as $key ) {
			if ( isset( $definition[ $key ] ) && ! $this->is_valid_handle_list( $definition[ $key ] ) ) {
				return false;
			}
		}

		if ( isset( $definition['storage'] ) && ! is_array( $definition['storage'] ) ) {
			return false;
		}

		if ( isset( $definition['storage']['type'] ) && ! $this->normalize_storage_type( $definition['storage']['type'] ) ) {
			return false;
		}

		if ( isset( $definition['storage']['rest_schema'] ) && ! is_array( $definition['storage']['rest_schema'] ) ) {
			return false;
		}

		if ( isset( $definition['supports'] ) && ! is_array( $definition['supports'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize a field type definition.
	 *
	 * @param array $definition Field type definition.
	 *
	 * @return array<string, mixed> Normalized definition.
	 */
	private function normalize_definition( array $definition ): array {
		$definition['produces_attribute'] = $definition['produces_attribute'] ?? true;

		return $definition;
	}

	/**
	 * Validate an attribute type value.
	 *
	 * @param mixed $attribute Attribute type.
	 *
	 * @return bool Whether the attribute type is valid.
	 */
	private function is_valid_attribute_type( mixed $attribute ): bool {
		if ( null === $attribute ) {
			return true;
		}

		if ( is_string( $attribute ) ) {
			return in_array( $attribute, self::ATTRIBUTE_TYPES, true );
		}

		if ( is_array( $attribute ) ) {
			foreach ( $attribute as $type ) {
				if ( ! is_string( $type ) || ! in_array( $type, self::ATTRIBUTE_TYPES, true ) ) {
					return false;
				}
			}

			return count( $attribute ) > 0;
		}

		return false;
	}

	/**
	 * Validate a WordPress script/style handle list.
	 *
	 * @param mixed $handles Handles.
	 *
	 * @return bool Whether the value is valid.
	 */
	private function is_valid_handle_list( mixed $handles ): bool {
		if ( is_string( $handles ) ) {
			return '' !== $handles;
		}

		if ( ! is_array( $handles ) ) {
			return false;
		}

		foreach ( $handles as $handle ) {
			if ( ! is_string( $handle ) || '' === $handle ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize a storage type.
	 *
	 * @param mixed $type Storage type.
	 *
	 * @return string|null Normalized storage type.
	 */
	private function normalize_storage_type( mixed $type ): ?string {
		if ( is_array( $type ) ) {
			$type = reset( $type );
		}

		if ( ! is_string( $type ) || ! in_array( $type, self::ATTRIBUTE_TYPES, true ) ) {
			return null;
		}

		return $type;
	}

	/**
	 * Enqueue script or style handles.
	 *
	 * @param string|array $handles Handles.
	 * @param string       $asset_type Asset type.
	 * @param string       $field_type Field type name.
	 *
	 * @return void
	 */
	private function enqueue_handles( string|array $handles, string $asset_type, string $field_type ): void {
		foreach ( (array) $handles as $handle ) {
			if ( ! is_string( $handle ) || '' === $handle ) {
				continue;
			}

			if ( 'style' === $asset_type ) {
				if ( wp_style_is( $handle, 'registered' ) ) {
					wp_enqueue_style( $handle );
					continue;
				}
			} elseif ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
				continue;
			}

			Error_Handler::warning(
				sprintf( 'Custom field type "%s" references missing editor %s handle "%s".', $field_type, $asset_type, $handle ),
				array(
					'field_type' => $field_type,
					'asset_type' => $asset_type,
					'handle'     => $handle,
				)
			);
		}
	}
}

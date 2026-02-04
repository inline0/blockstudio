<?php
/**
 * Select Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

use Blockstudio\Populate;
use Blockstudio\Option_Value_Resolver;

/**
 * Handler for select/choice field types.
 *
 * Handles: select, radio, checkbox, token, color, gradient.
 *
 * @since 7.0.0
 */
class Select_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Supported field types.
	 *
	 * @var array<string>
	 */
	protected array $supported_types = array(
		'select',
		'radio',
		'checkbox',
		'token',
		'color',
		'gradient',
		'icon',
		'link',
	);

	/**
	 * Types that always output as array.
	 *
	 * @var array<string>
	 */
	private array $array_types = array(
		'checkbox',
		'token',
	);

	/**
	 * Types that always output as object.
	 *
	 * @var array<string>
	 */
	private array $object_types = array(
		'color',
		'gradient',
		'icon',
		'link',
		'radio',
	);

	/**
	 * Types that support options.
	 *
	 * @var array<string>
	 */
	private array $option_types = array(
		'select',
		'radio',
		'checkbox',
		'color',
		'gradient',
	);

	/**
	 * Build attribute data for a select field.
	 *
	 * @param array  $field       The field configuration.
	 * @param array  $attributes  The attributes array (passed by reference).
	 * @param string $prefix      The attribute ID prefix.
	 *
	 * @return void
	 */
	public function build( array $field, array &$attributes, string $prefix = '' ): void {
		$type     = $field['type'] ?? '';
		$field_id = $this->get_field_id( $field, $prefix );

		if ( '' === $field_id ) {
			return;
		}

		$is_multiple = $this->is_multiple_options( $type, $field );
		$attr_type   = $this->get_attribute_type( $type, $is_multiple );

		$attribute = $this->create_base_attribute( $type, $attr_type );

		// Handle multiple select.
		if ( 'select' === $type && $is_multiple ) {
			$attribute['multiple'] = true;
		}

		// Handle options.
		if ( in_array( $type, $this->option_types, true ) ) {
			$this->build_options( $field, $attribute );
		}

		// Handle populate.
		if ( isset( $field['populate'] ) ) {
			$attribute['populate'] = $field['populate'];
		}

		// Handle return format.
		if ( isset( $field['returnFormat'] ) ) {
			$attribute['returnFormat'] = $field['returnFormat'] ?? 'value';
		}

		// Apply defaults with special handling for options.
		$this->apply_option_defaults( $field, $attribute, $is_multiple );
		$this->apply_storage( $field, $attribute );

		$attribute['id'] = $field_id;

		// Handle set property for extensions.
		if ( $field['set'] ?? false ) {
			$attribute['set'] = $field['set'];
		}

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Check if field should be treated as multiple.
	 *
	 * @param string $type  The field type.
	 * @param array  $field The field configuration.
	 *
	 * @return bool Whether field is multiple.
	 */
	private function is_multiple_options( string $type, array $field ): bool {
		return in_array( $type, $this->array_types, true ) ||
			( 'select' === $type && ( $field['multiple'] ?? false ) );
	}

	/**
	 * Get the attribute type based on field type.
	 *
	 * @param string $type        The field type.
	 * @param bool   $is_multiple Whether field is multiple.
	 *
	 * @return string The attribute type.
	 */
	private function get_attribute_type( string $type, bool $is_multiple ): string {
		if ( $is_multiple ) {
			return 'array';
		}

		if ( in_array( $type, $this->object_types, true ) ) {
			return 'object';
		}

		// Single select also returns object.
		if ( 'select' === $type ) {
			return 'object';
		}

		return 'string';
	}

	/**
	 * Build options for the attribute.
	 *
	 * @param array $field     The field configuration.
	 * @param array $attribute The attribute array (passed by reference).
	 *
	 * @return void
	 */
	private function build_options( array $field, array &$attribute ): void {
		$options = $field['options'] ?? array();

		// Handle populate.
		if ( isset( $field['populate'] ) ) {
			$populate_type = $field['populate']['type'] ?? false;

			if (
				'query' === $populate_type ||
				'custom' === $populate_type ||
				'function' === $populate_type
			) {
				$options_addons        = Populate::init(
					$field['populate'],
					$field['default'] ?? false
				);
				$options_transformed   = array();
				$options_populate      = array();
				$options_populate_full = array();

				if ( 'query' === $field['populate']['type'] ) {
					$q                = $field['populate']['query'];
					$return_map_value = array(
						'posts' => 'ID',
						'users' => 'ID',
						'terms' => 'term_id',
					);
					$return_map_label = array(
						'posts' => 'post_title',
						'users' => 'display_name',
						'terms' => 'name',
					);

					foreach ( $options_addons as $opt ) {
						$val = $opt->{$return_map_value[ $q ]};

						$options_populate[]            = $val;
						$options_transformed[]         = array(
							'value' => $val,
							'label' => $opt->{$field['populate']['returnFormat']['label']
								?? $return_map_label[ $q ]},
						);
						$options_populate_full[ $val ] = $opt;
					}
				}

				if ( 'function' === $field['populate']['type'] ) {
					$val   = $field['populate']['returnFormat']['value'] ?? false;
					$label = $field['populate']['returnFormat']['label'] ?? false;

					if ( ! $val && ! $label ) {
						$options_addons = array_values( $options_addons );
					}

					foreach ( $options_addons as $opt ) {
						$opt = (array) $opt;

						$val                   = $opt[ $val ]
							?? ( $opt['value'] ?? ( array_values( $opt )[0] ?? $opt ) );
						$options_populate[]    = $val;
						$options_transformed[] = array(
							'value' => $val,
							'label' => $opt[ $label ] ?? ( $opt['label'] ?? $val ),
						);
					}
				}

				if ( count( $options_populate ) >= 1 ) {
					$attribute['optionsPopulate']     = $options_populate;
					$attribute['optionsPopulateFull'] = $options_populate_full;
				}

				$is_transform =
					'query' === $field['populate']['type'] ||
					'function' === $field['populate']['type'];

				$options = isset( $field['populate']['position'] ) &&
					'before' === $field['populate']['position']
						? array_merge(
							$is_transform ? $options_transformed : $options_addons,
							$options
						)
						: array_merge(
							$options,
							$is_transform ? $options_transformed : $options_addons
						);
			}
		}

		$attribute['options'] = $options;
	}

	/**
	 * Apply default values with option mapping.
	 *
	 * @param array $field       The field configuration.
	 * @param array $attribute   The attribute array (passed by reference).
	 * @param bool  $is_multiple Whether field is multiple.
	 *
	 * @return void
	 */
	private function apply_option_defaults( array $field, array &$attribute, bool $is_multiple ): void {
		$type = $field['type'] ?? '';

		foreach ( array( 'default', 'fallback' ) as $item ) {
			if ( ! isset( $field[ $item ] ) ) {
				continue;
			}

			if ( 'color' === $type || 'gradient' === $type ) {
				// Find matching option.
				foreach ( $field['options'] ?? array() as $value ) {
					if ( $value['value'] === $field[ $item ] ) {
						$attribute[ $item ] = $value;
					}
				}
			} elseif ( in_array( $type, array( 'checkbox', 'radio', 'select', 'token' ), true ) ) {
				$default_select = array();
				$default_values = is_array( $field[ $item ] )
					? $field[ $item ]
					: array( $field[ $item ] );

				foreach ( $default_values as $value ) {
					$option_value = Option_Value_Resolver::get_option_value(
						array( 'options' => $attribute['options'] ?? $field['options'] ),
						'value',
						array( 'value' => $value )
					);
					$option_label = Option_Value_Resolver::get_option_value(
						array( 'options' => $attribute['options'] ?? $field['options'] ),
						'label',
						array( 'value' => $value )
					);

					$default_select[] = array(
						'value' => $option_value,
						'label' => $option_label,
					);
				}

				$attribute[ $item ] = $is_multiple
					? $default_select
					: ( $default_select[0] ?? null );
			} elseif ( in_array( $type, array( 'icon', 'link' ), true ) ) {
				$attribute[ $item ] = $field[ $item ];
			}
		}
	}

	/**
	 * Get the default value for a select field.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return mixed The default value.
	 */
	public function get_default_value( array $field ): mixed {
		$type = $field['type'] ?? '';

		if ( in_array( $type, $this->array_types, true ) ) {
			return $field['default'] ?? array();
		}

		return $field['default'] ?? null;
	}
}

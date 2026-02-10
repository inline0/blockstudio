<?php
/**
 * Container Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

use Blockstudio\Build;

/**
 * Handler for container field types.
 *
 * Handles: group, tabs, repeater, attributes.
 *
 * @since 7.0.0
 */
class Container_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Supported field types.
	 *
	 * @var array<string>
	 */
	protected array $supported_types = array(
		'group',
		'tabs',
		'repeater',
		'attributes',
	);

	/**
	 * Callback for recursive attribute building.
	 *
	 * @var callable|null
	 */
	private $build_callback = null;

	/**
	 * Set the callback for recursive attribute building.
	 *
	 * @param callable $callback The callback function.
	 *
	 * @return void
	 */
	public function set_build_callback( callable $callback ): void {
		$this->build_callback = $callback;
	}

	/**
	 * Build attribute data for a container field.
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

		switch ( $type ) {
			case 'tabs':
				$this->build_tabs( $field, $attributes, $prefix );
				break;

			case 'group':
				$this->build_group( $field, $attributes, $prefix, $field_id );
				break;

			case 'repeater':
				$this->build_repeater( $field, $attributes, $prefix, $field_id );
				break;

			case 'attributes':
				$this->build_attributes( $field, $attributes, $prefix, $field_id );
				break;
		}
	}

	/**
	 * Build tabs container.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 *
	 * @return void
	 */
	private function build_tabs( array $field, array &$attributes, string $prefix ): void {
		if ( ! isset( $field['tabs'] ) || ! $this->build_callback ) {
			return;
		}

		foreach ( $field['tabs'] as $tab ) {
			if ( isset( $tab['attributes'] ) ) {
				call_user_func_array(
					$this->build_callback,
					array(
						array_values( $tab['attributes'] ),
						&$attributes,
						$prefix,
						false, // from_group.
						false, // from_repeater.
						false, // is_override.
					)
				);
			}
		}
	}

	/**
	 * Build group container.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 * @param string $field_id   The field ID.
	 *
	 * @return void
	 */
	private function build_group( array $field, array &$attributes, string $prefix, string $field_id ): void {
		if ( ! isset( $field['attributes'] ) || ! $this->build_callback ) {
			return;
		}

		// Filter out nested groups.
		$filtered_attributes = $field['attributes'];
		Build::filter_not_key( $filtered_attributes, 'type', 'group' );

		$new_prefix = '' === $prefix
			? ( $field['id'] ?? '' )
			: $prefix . '_' . ( $field['id'] ?? '' );

		call_user_func_array(
			$this->build_callback,
			array(
				array_values( $filtered_attributes ),
				&$attributes,
				$new_prefix,
				true,  // from_group.
				false, // from_repeater.
				false, // is_override.
			)
		);
	}

	/**
	 * Build repeater container.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 * @param string $field_id   The field ID.
	 *
	 * @return void
	 */
	private function build_repeater( array $field, array &$attributes, string $prefix, string $field_id ): void {
		if ( '' === $field_id ) {
			return;
		}

		// Filter out nested groups from attributes.
		$inner_attributes = $field['attributes'] ?? array();
		if ( count( $inner_attributes ) >= 1 ) {
			$inner_attributes = array_values(
				array_filter(
					$inner_attributes,
					fn( $val ) => 'group' !== ( $val['type'] ?? '' )
				)
			);
		}

		$attribute = array(
			'blockstudio' => true,
			'type'        => 'array',
			'field'       => 'repeater',
			'attributes'  => $inner_attributes,
		);

		// Recursively build inner attributes.
		if ( count( $inner_attributes ) >= 1 && $this->build_callback ) {
			call_user_func_array(
				$this->build_callback,
				array(
					$inner_attributes,
					&$attribute['attributes'],
					'',
					false, // from_group.
					true,  // from_repeater.
					false, // is_override.
				)
			);
		}

		$this->apply_defaults( $field, $attribute );

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Build attributes field.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 * @param string $field_id   The field ID.
	 *
	 * @return void
	 */
	private function build_attributes( array $field, array &$attributes, string $prefix, string $field_id ): void {
		if ( '' === $field_id ) {
			return;
		}

		$attribute = array(
			'blockstudio' => true,
			'type'        => 'array',
			'field'       => 'attributes',
		);

		$attribute['id'] = $field_id;

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Get the default value for a container field.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return mixed The default value.
	 */
	public function get_default_value( array $field ): mixed {
		$type = $field['type'] ?? '';

		if ( 'repeater' === $type || 'attributes' === $type ) {
			return array();
		}

		return null;
	}
}

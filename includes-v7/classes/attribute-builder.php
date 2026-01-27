<?php
/**
 * Attribute Builder class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Blockstudio\Interfaces\Field_Handler_Interface;
use Blockstudio\Field_Handlers\Text_Field_Handler;
use Blockstudio\Field_Handlers\Number_Field_Handler;
use Blockstudio\Field_Handlers\Boolean_Field_Handler;
use Blockstudio\Field_Handlers\Select_Field_Handler;
use Blockstudio\Field_Handlers\Media_Field_Handler;
use Blockstudio\Field_Handlers\Container_Field_Handler;

/**
 * Builds WordPress block attributes from Blockstudio field configurations.
 *
 * This class converts the field definitions in block.json into the
 * WordPress block attributes format using a strategy pattern with
 * specialized handlers for each field type.
 *
 * Field to Attribute Conversion:
 * Blockstudio fields in block.json define UI controls and data types.
 * This class converts them to WordPress block attribute definitions
 * that Gutenberg understands.
 *
 * Example Conversion:
 * ```json
 * // block.json field
 * "title": {
 *   "type": "string",
 *   "field": "text",
 *   "default": "Hello"
 * }
 *
 * // Becomes WordPress attribute
 * "title": {
 *   "type": "string",
 *   "default": "Hello"
 * }
 * ```
 *
 * Strategy Pattern:
 * Each field type has a dedicated handler class:
 * - Text_Field_Handler: text, textarea, code, classes, etc.
 * - Number_Field_Handler: number, range
 * - Boolean_Field_Handler: toggle, checkbox
 * - Select_Field_Handler: select, radio, checkbox (multi)
 * - Media_Field_Handler: image, file, gallery
 * - Container_Field_Handler: group, repeater, tabs
 *
 * Handler Interface:
 * ```php
 * interface Field_Handler_Interface {
 *     public function supports(string $type): bool;
 *     public function build(array $field, array &$attributes, string $prefix): void;
 * }
 * ```
 *
 * Custom Handlers:
 * Register custom handlers via register_handler() for plugin-specific
 * field types or to override default behavior.
 *
 * @since 7.0.0
 */
class Attribute_Builder {

	/**
	 * Registered field handlers.
	 *
	 * @var array<Field_Handler_Interface>
	 */
	private array $handlers = array();

	/**
	 * Container handler reference for recursive building.
	 *
	 * @var Container_Field_Handler|null
	 */
	private ?Container_Field_Handler $container_handler = null;

	/**
	 * Whether Tailwind is active.
	 *
	 * @var bool
	 */
	private bool $tailwind_active = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_default_handlers();
	}

	/**
	 * Register the default field handlers.
	 *
	 * @return void
	 */
	private function register_default_handlers(): void {
		// Register handlers in order of priority.
		$this->register_handler( new Text_Field_Handler() );
		$this->register_handler( new Number_Field_Handler() );
		$this->register_handler( new Boolean_Field_Handler() );
		$this->register_handler( new Select_Field_Handler() );
		$this->register_handler( new Media_Field_Handler() );

		// Container handler needs special setup for recursive building.
		$this->container_handler = new Container_Field_Handler();
		$this->container_handler->set_build_callback( array( $this, 'build_attributes_recursive' ) );
		$this->register_handler( $this->container_handler );
	}

	/**
	 * Register a field handler.
	 *
	 * @param Field_Handler_Interface $handler The handler to register.
	 *
	 * @return void
	 */
	public function register_handler( Field_Handler_Interface $handler ): void {
		$this->handlers[] = $handler;
	}

	/**
	 * Build attributes from fields configuration.
	 *
	 * @param array $fields      The fields configuration.
	 * @param bool  $is_override Whether building for an override.
	 * @param bool  $is_extend   Whether building for an extension.
	 *
	 * @return array The built attributes.
	 */
	public function build( array $fields, bool $is_override = false, bool $is_extend = false ): array {
		$attributes = array();

		$this->build_attributes_recursive(
			$fields,
			$attributes,
			'',
			false,
			false,
			$is_override,
			$is_extend
		);

		return $attributes;
	}

	/**
	 * Build attributes recursively.
	 *
	 * This method is called by the container handler for nested fields.
	 *
	 * @param array  $attrs         The attributes to build.
	 * @param array  $attributes    The attributes array (passed by reference).
	 * @param string $prefix        The ID prefix.
	 * @param bool   $from_group    Whether from a group.
	 * @param bool   $from_repeater Whether from a repeater.
	 * @param bool   $is_override   Whether an override.
	 * @param bool   $is_extend     Whether an extension.
	 *
	 * @return void
	 */
	public function build_attributes_recursive(
		array $attrs,
		array &$attributes,
		string $prefix = '',
		bool $from_group = false,
		bool $from_repeater = false,
		bool $is_override = false,
		bool $is_extend = false
	): void {
		$index = 0;

		foreach ( $attrs as $data ) {
			$field = array( 'attributes' => $data );

			foreach ( $field as $v ) {
				$field_id = $from_repeater ? (string) $index : $this->get_field_id( $v, $prefix );
				++$index;

				$type = $v['type'] ?? '';

				// Skip message type.
				if ( 'message' === $type ) {
					continue;
				}

				// Must have ID or be a container type.
				if ( ! isset( $v['id'] ) && ! in_array( $type, array( 'group', 'tabs' ), true ) ) {
					continue;
				}

				// Handle tabs at top level.
				if ( 'tabs' === $type && ! $from_group && ! $from_repeater ) {
					$handler = $this->get_handler_for_type( $type );
					if ( $handler ) {
						$handler->build( $v, $attributes, $prefix );
					}
					continue;
				}

				// Handle group/repeater at top level.
				if ( ( 'group' === $type && ! $from_group ) || 'repeater' === $type ) {
					$handler = $this->get_handler_for_type( $type );
					if ( $handler ) {
						$handler->build( $v, $attributes, $prefix );
					}
					continue;
				}

				// Check for Tailwind activation.
				if ( 'classes' === $type && ( $v['tailwind'] ?? false ) ) {
					$this->tailwind_active = true;
				}

				// Get handler and build attribute.
				$handler = $this->get_handler_for_type( $type );
				if ( $handler ) {
					$handler->build( $v, $attributes, $prefix );
				}
			}
		}
	}

	/**
	 * Get the field ID with prefix.
	 *
	 * @param array  $field  The field configuration.
	 * @param string $prefix The prefix.
	 *
	 * @return string The field ID.
	 */
	private function get_field_id( array $field, string $prefix ): string {
		$id         = $field['id'] ?? '';
		$prefix_str = '' === $prefix ? '' : $prefix . '_';
		return $prefix_str . $id;
	}

	/**
	 * Get the handler for a field type.
	 *
	 * @param string $type The field type.
	 *
	 * @return Field_Handler_Interface|null The handler or null.
	 */
	private function get_handler_for_type( string $type ): ?Field_Handler_Interface {
		foreach ( $this->handlers as $handler ) {
			if ( $handler->supports( $type ) ) {
				return $handler;
			}
		}
		return null;
	}

	/**
	 * Check if Tailwind is active.
	 *
	 * @return bool Whether Tailwind is active.
	 */
	public function is_tailwind_active(): bool {
		return $this->tailwind_active;
	}

	/**
	 * Reset the Tailwind active flag.
	 *
	 * @return void
	 */
	public function reset_tailwind_active(): void {
		$this->tailwind_active = false;
	}
}

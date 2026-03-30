<?php
/**
 * Storage Sync class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Syncs block attribute values to additional storage locations.
 *
 * When a post is saved, this class extracts block data and syncs
 * field values to their configured storage locations (post meta, options).
 *
 * @since 7.0.0
 */
class Storage_Sync {

	/**
	 * Storage Registry instance.
	 *
	 * @var Storage_Registry
	 */
	private Storage_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Storage_Registry|null $registry Optional storage registry.
	 */
	public function __construct( ?Storage_Registry $registry = null ) {
		$this->registry = $registry ?? Storage_Registry::instance();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'save_post', array( $this, 'sync_post_blocks' ), 10, 2 );
	}

	/**
	 * Sync block values when a post is saved.
	 *
	 * @param int|string $post_id The post ID.
	 * @param \WP_Post   $post    The post object.
	 *
	 * @return void
	 */
	public function sync_post_blocks( $post_id, \WP_Post $post ): void {
		$post_id = (int) $post_id;
		// Skip autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Parse blocks from content.
		$blocks = parse_blocks( $post->post_content );

		if ( empty( $blocks ) ) {
			return;
		}

		$this->sync_blocks( $post_id, $blocks );
	}

	/**
	 * Sync an array of blocks.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $blocks  The blocks to sync.
	 *
	 * @return void
	 */
	private function sync_blocks( int $post_id, array $blocks ): void {
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$this->sync_block( $post_id, $block );

			// Process inner blocks recursively.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->sync_blocks( $post_id, $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Sync a single block's values to storage.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $block   The block data.
	 *
	 * @return void
	 */
	private function sync_block( int $post_id, array $block ): void {
		$block_name = $block['blockName'];

		// Block attributes are nested in blockstudio.attributes.
		$attrs = $block['attrs']['blockstudio']['attributes'] ?? array();

		// Get block configuration from Build::blocks() (returns WP_Block_Type objects).
		$block_type = Build::blocks()[ $block_name ] ?? null;
		if ( ! $block_type || ! isset( $block_type->blockstudio ) ) {
			return;
		}

		$fields = $block_type->blockstudio['attributes'] ?? array();

		if ( empty( $fields ) ) {
			return;
		}

		$this->sync_fields( $post_id, $block_name, $fields, $attrs );
	}

	/**
	 * Sync field values to storage.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $block_name The block name.
	 * @param array  $fields     The field configurations.
	 * @param array  $attrs      The current attribute scope.
	 * @param string $prefix     The attribute prefix for nested fields.
	 * @param bool   $from_repeater Whether the current scope contains repeater rows.
	 *
	 * @return void
	 */
	private function sync_fields( int $post_id, string $block_name, array $fields, array $attrs, string $prefix = '', bool $from_repeater = false ): void {
		foreach ( $fields as $field ) {
			$attr_key = '';
			$value    = null;

			if ( isset( $field['id'] ) ) {
				$attr_key = $this->get_attribute_key( (string) $field['id'], $prefix );

				if ( isset( $field['storage'] ) ) {
					$value = $this->get_attribute_value( $attrs, $attr_key, $from_repeater );
					$this->sync_field_value( $post_id, $block_name, $field, $value );
				}

				if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
					$this->sync_fields( $post_id, $block_name, $field['fields'], $attrs, $attr_key, $from_repeater );
				}
			}

			if ( isset( $field['attributes'] ) && is_array( $field['attributes'] ) ) {
				if ( 'repeater' === ( $field['type'] ?? '' ) ) {
					$repeater_attrs = $this->get_attribute_value( $attrs, $attr_key, $from_repeater );
					$repeater_attrs = is_array( $repeater_attrs ) ? $repeater_attrs : array();
					$this->sync_fields( $post_id, $block_name, $field['attributes'], $repeater_attrs, '', true );
				} else {
					$child_prefix = ( 'group' === ( $field['type'] ?? '' ) && '' !== $attr_key ) ? $attr_key : $prefix;
					$this->sync_fields( $post_id, $block_name, $field['attributes'], $attrs, $child_prefix, $from_repeater );
				}
			}

			if ( isset( $field['tabs'] ) && is_array( $field['tabs'] ) ) {
				foreach ( $field['tabs'] as $tab ) {
					if ( isset( $tab['attributes'] ) && is_array( $tab['attributes'] ) ) {
						$this->sync_fields( $post_id, $block_name, $tab['attributes'], $attrs, $prefix, $from_repeater );
					}
				}
			}
		}
	}

	/**
	 * Build an attribute key from the current prefix and field ID.
	 *
	 * @param string $field_id The field ID.
	 * @param string $prefix   The current prefix.
	 *
	 * @return string
	 */
	private function get_attribute_key( string $field_id, string $prefix ): string {
		return '' === $prefix ? $field_id : $prefix . '_' . $field_id;
	}

	/**
	 * Get an attribute value from the current scope.
	 *
	 * Repeater scopes contain lists of row arrays, so nested values are
	 * collected recursively to preserve row structure.
	 *
	 * @param array  $attrs         The current attribute scope.
	 * @param string $attr_key      The attribute key to read.
	 * @param bool   $from_repeater Whether the current scope contains repeater rows.
	 *
	 * @return mixed
	 */
	private function get_attribute_value( array $attrs, string $attr_key, bool $from_repeater = false ) {
		if ( ! $from_repeater ) {
			return $attrs[ $attr_key ] ?? null;
		}

		return array_map(
			function ( $row ) use ( $attr_key ) {
				if ( ! is_array( $row ) ) {
					return null;
				}

				return $this->get_attribute_value( $row, $attr_key, array_is_list( $row ) );
			},
			$attrs
		);
	}

	/**
	 * Sync a field value to its configured storage locations.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 * @param mixed  $value      The field value.
	 *
	 * @return void
	 */
	private function sync_field_value( int $post_id, string $block_name, array $field, $value ): void {
		$types = $this->registry->get_storage_types( $field );

		foreach ( $types as $type ) {
			// Skip block storage - that's handled by WordPress.
			if ( 'block' === $type ) {
				continue;
			}

			$handler = $this->registry->get_handler( $type );
			if ( ! $handler ) {
				continue;
			}

			$key = $handler->get_key( $block_name, $field );

			if ( 'postMeta' === $type ) {
				if ( null === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
			} elseif ( 'option' === $type ) {
				if ( null === $value ) {
					delete_option( $key );
				} else {
					update_option( $key, $value );
				}
			}
		}
	}
}

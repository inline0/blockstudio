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
	 * @param array  $attrs      The block attributes.
	 * @param string $prefix     The attribute prefix for nested fields.
	 *
	 * @return void
	 */
	private function sync_fields( int $post_id, string $block_name, array $fields, array $attrs, string $prefix = '' ): void {
		foreach ( $fields as $field ) {
			if ( ! isset( $field['id'] ) ) {
				continue;
			}

			$attr_key = '' === $prefix ? $field['id'] : $prefix . '_' . $field['id'];

			// Sync this field if it has storage configuration.
			if ( isset( $field['storage'] ) ) {
				$value = $attrs[ $attr_key ] ?? null;
				$this->sync_field_value( $post_id, $block_name, $field, $value );
			}

			// Process nested fields in container types.
			if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$this->sync_fields( $post_id, $block_name, $field['fields'], $attrs, $attr_key );
			}
		}
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

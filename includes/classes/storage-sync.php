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
		add_action( 'wp_after_insert_post', array( $this, 'sync_post_blocks' ), 10, 4 );
	}

	/**
	 * Sync block values when a post is saved.
	 *
	 * @param int|string    $post_id The post ID.
	 * @param \WP_Post      $post    The post object.
	 * @param bool          $update  Whether this is an update.
	 * @param \WP_Post|null $post_before The post before this save.
	 *
	 * @return void
	 */
	public function sync_post_blocks( $post_id, \WP_Post $post, bool $update = false, ?\WP_Post $post_before = null ): void {
		$post_id = (int) $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );

		if ( empty( $blocks ) ) {
			return;
		}

		$previous = $post_before && 'publish' === $post_before->post_status
			? $this->index_block_attributes( parse_blocks( $post_before->post_content ) )
			: array();
		$this->sync_blocks( $post_id, $blocks, $previous, 'publish' === $post->post_status );
	}

	/**
	 * Match saved blocks across edits, preferring their persistent identity.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $path Parent position for legacy blocks without a key.
	 * @return array Attributes indexed by block identity.
	 */
	private function index_block_attributes( array $blocks, string $path = '' ): array {
		$indexed = array();
		foreach ( $blocks as $index => $block ) {
			$position             = $path . '/' . $index;
			$identity             = $this->block_identity( $block, $position );
			$indexed[ $identity ] = $block['attrs']['blockstudio']['attributes'] ?? array();
			$indexed              = array_replace( $indexed, $this->index_block_attributes( $block['innerBlocks'] ?? array(), $position ) );
		}
		return $indexed;
	}

	/**
	 * Get a block's identity within a saved document.
	 *
	 * @param array  $block Parsed block.
	 * @param string $position Position in the document.
	 * @return string Identity.
	 */
	private function block_identity( array $block, string $position ): string {
		$key = $block['attrs']['__BLOCKSTUDIO_KEY'] ?? null;
		return ( $block['blockName'] ?? '' ) . ':' . ( is_string( $key ) && '' !== $key ? 'key:' . $key : 'path:' . $position );
	}

	/**
	 * Sync an array of blocks.
	 *
	 * @param int    $post_id The post ID.
	 * @param array  $blocks  The blocks to sync.
	 * @param array  $previous Previous attributes by block identity.
	 * @param bool   $sync_options Whether this save may write site options.
	 * @param string $path Parent position.
	 *
	 * @return void
	 */
	private function sync_blocks( int $post_id, array $blocks, array $previous = array(), bool $sync_options = true, string $path = '' ): void {
		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$position = $path . '/' . $index;
			$this->sync_block( $post_id, $block, $previous[ $this->block_identity( $block, $position ) ] ?? null, $sync_options );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->sync_blocks( $post_id, $block['innerBlocks'], $previous, $sync_options, $position );
			}
		}
	}

	/**
	 * Sync a single block's values to storage.
	 *
	 * @param int        $post_id The post ID.
	 * @param array      $block   The block data.
	 * @param array|null $previous Previous field values, if this block already existed.
	 * @param bool       $sync_options Whether this save may write site options.
	 *
	 * @return void
	 */
	private function sync_block( int $post_id, array $block, ?array $previous = null, bool $sync_options = true ): void {
		$block_name = $block['blockName'];

		$attrs = $block['attrs']['blockstudio']['attributes'] ?? array();

		$block_type = Build::blocks()[ $block_name ] ?? null;
		if ( ! $block_type || ! isset( $block_type->blockstudio ) ) {
			return;
		}

		$fields = $block_type->blockstudio['attributes'] ?? array();

		if ( empty( $fields ) ) {
			return;
		}

		$this->sync_fields( $post_id, $block_name, $fields, $attrs, '', false, $previous, $sync_options );
	}

	/**
	 * Sync field values to storage.
	 *
	 * @param int        $post_id    The post ID.
	 * @param string     $block_name The block name.
	 * @param array      $fields     The field configurations.
	 * @param array      $attrs      The current attribute scope.
	 * @param string     $prefix     The attribute prefix for nested fields.
	 * @param bool       $from_repeater Whether the current scope contains repeater rows.
	 * @param array|null $previous Previous values in this scope.
	 * @param bool       $sync_options Whether this save may write site options.
	 *
	 * @return void
	 */
	private function sync_fields( int $post_id, string $block_name, array $fields, array $attrs, string $prefix = '', bool $from_repeater = false, ?array $previous = null, bool $sync_options = true ): void {
		foreach ( $fields as $field ) {
			$attr_key = '';
			$value    = null;

			if ( isset( $field['id'] ) ) {
				$attr_key = $this->get_attribute_key( (string) $field['id'], $prefix );

				if ( isset( $field['storage'] ) ) {
					$value        = $this->get_attribute_value( $attrs, $attr_key, $from_repeater );
					$write_option = $sync_options && $this->has_attribute( $attrs, $attr_key, $from_repeater ) && (
						null === $previous ||
						! $this->has_attribute( $previous, $attr_key, $from_repeater ) ||
						$value !== $this->get_attribute_value( $previous, $attr_key, $from_repeater )
					);
					$this->sync_field_value( $post_id, $block_name, $field, $value, $write_option );
				}

				if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
					$this->sync_fields( $post_id, $block_name, $field['fields'], $attrs, $attr_key, $from_repeater, $previous, $sync_options );
				}
			}

			if ( isset( $field['attributes'] ) && is_array( $field['attributes'] ) ) {
				if ( 'repeater' === ( $field['type'] ?? '' ) ) {
					$repeater_attrs = $this->get_attribute_value( $attrs, $attr_key, $from_repeater );
					$repeater_attrs = is_array( $repeater_attrs ) ? $repeater_attrs : array();
					$old_rows       = null === $previous ? null : $this->get_attribute_value( $previous, $attr_key, $from_repeater );
					$previous_rows  = null !== $previous && $this->has_attribute( $previous, $attr_key, $from_repeater )
						? ( is_array( $old_rows ) ? $old_rows : array() )
						: null;
					$this->sync_fields( $post_id, $block_name, $field['attributes'], $repeater_attrs, '', true, $previous_rows, $sync_options && $this->has_attribute( $attrs, $attr_key, $from_repeater ) );
				} else {
					$child_prefix = ( 'group' === ( $field['type'] ?? '' ) && '' !== $attr_key ) ? $attr_key : $prefix;
					$this->sync_fields( $post_id, $block_name, $field['attributes'], $attrs, $child_prefix, $from_repeater, $previous, $sync_options );
				}
			}

			if ( isset( $field['tabs'] ) && is_array( $field['tabs'] ) ) {
				foreach ( $field['tabs'] as $tab ) {
					if ( isset( $tab['attributes'] ) && is_array( $tab['attributes'] ) ) {
						$this->sync_fields( $post_id, $block_name, $tab['attributes'], $attrs, $prefix, $from_repeater, $previous, $sync_options );
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
	 * Distinguish an omitted field from an explicitly cleared field.
	 *
	 * @param array  $attrs Current scope.
	 * @param string $key Field key.
	 * @param bool   $from_repeater Whether this scope is a list of rows.
	 * @return bool Whether the field was supplied.
	 */
	private function has_attribute( array $attrs, string $key, bool $from_repeater ): bool {
		if ( ! $from_repeater ) {
			return array_key_exists( $key, $attrs );
		}
		if ( array() === $attrs ) {
			return true;
		}
		foreach ( $attrs as $row ) {
			if ( is_array( $row ) && $this->has_attribute( $row, $key, array_is_list( $row ) ) ) {
				return true;
			}
		}
		return false;
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
	 * @param bool   $write_option Whether the option value changed in a published save.
	 *
	 * @return void
	 */
	private function sync_field_value( int $post_id, string $block_name, array $field, $value, bool $write_option = true ): void {
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
					update_post_meta( $post_id, $key, wp_slash( $value ) );
				}
			} elseif ( 'option' === $type && $write_option ) {
				if ( null === $value ) {
					delete_option( $key );
				} else {
					update_option( $key, $value );
				}
			}
		}
	}
}

<?php
/**
 * Block Registrar class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Type;

/**
 * Registers blocks with WordPress.
 *
 * This class extracts block registration logic from the Build class.
 *
 * @since 7.0.0
 */
class Block_Registrar {

	/**
	 * Block Registry instance.
	 *
	 * @var Block_Registry
	 */
	private Block_Registry $registry;

	/**
	 * Attribute Builder instance.
	 *
	 * @var Attribute_Builder
	 */
	private Attribute_Builder $attribute_builder;

	/**
	 * Constructor.
	 *
	 * @param Block_Registry|null    $registry          Optional block registry.
	 * @param Attribute_Builder|null $attribute_builder Optional attribute builder.
	 */
	public function __construct(
		?Block_Registry $registry = null,
		?Attribute_Builder $attribute_builder = null
	) {
		$this->registry          = $registry ?? Block_Registry::instance();
		$this->attribute_builder = $attribute_builder ?? new Attribute_Builder();
	}

	/**
	 * Register a block with WordPress.
	 *
	 * @param array $block_data The block data.
	 * @param array $block_json The block.json data.
	 *
	 * @return WP_Block_Type|null The registered block or null.
	 */
	public function register( array $block_data, array $block_json ): ?WP_Block_Type {
		$classification = $block_data['classification'] ?? array();
		$is_extend      = $classification['is_extend'] ?? false;
		$is_override    = $classification['is_override'] ?? false;

		$native_path = $is_override && ! ( $classification['is_block'] ?? false )
			? $block_data['path']
			: Files::get_render_template( $block_data['path'] );

		// Build attributes.
		$attributes          = array();
		$filtered_attributes = array();

		if ( isset( $block_json['blockstudio']['attributes'] ) ) {
			if ( ! $is_override ) {
				Build::filter_attributes(
					$block_json,
					$block_json['blockstudio']['attributes'],
					$filtered_attributes
				);
			}

			$attributes = $this->attribute_builder->build(
				$block_json['blockstudio']['attributes'],
				$is_override,
				$is_extend
			);
		}

		// Add standard attributes.
		$attributes = $this->add_standard_attributes( $attributes, $block_json, $is_extend );

		// Create the block type.
		$block = $this->create_block_type( $block_json, $attributes, $native_path );

		// Set blockstudio metadata.
		$block->blockstudio = $this->build_blockstudio_metadata(
			$block,
			$filtered_attributes,
			$block_json
		);

		return $block;
	}

	/**
	 * Add standard attributes to a block.
	 *
	 * @param array $attributes The current attributes.
	 * @param array $block_json The block.json data.
	 * @param bool  $is_extend  Whether this is an extension.
	 *
	 * @return array The updated attributes.
	 */
	private function add_standard_attributes( array $attributes, array $block_json, bool $is_extend ): array {
		$attributes['blockstudio'] = array(
			'type'    => 'object',
			'default' => array(
				'name' => $block_json['name'],
			),
		);

		$attributes['anchor'] = $is_extend
			? array(
				'type'      => 'string',
				'source'    => 'attribute',
				'attribute' => 'id',
				'selector'  => '*',
			)
			: array(
				'type' => 'string',
			);

		$attributes['className'] = array(
			'type' => 'string',
		);

		return $attributes;
	}

	/**
	 * Create a WP_Block_Type instance.
	 *
	 * @param array  $block_json  The block.json data.
	 * @param array  $attributes  The block attributes.
	 * @param string $native_path The native render path.
	 *
	 * @return WP_Block_Type The block type.
	 */
	private function create_block_type( array $block_json, array $attributes, string $native_path ): WP_Block_Type {
		$block              = new WP_Block_Type( $block_json['name'], $block_json );
		$block->api_version = Constants::BLOCK_API_VERSION;

		$block->render_callback = array( 'Blockstudio\Block', 'render' );

		$block->attributes = array_merge(
			$block_json['attributes'] ?? array(),
			$attributes
		);

		$block->uses_context = array_merge(
			array( 'postId', 'postType' ),
			$block_json['usesContext'] ?? array()
		);

		$block->provides_context = array_merge(
			array( $block_json['name'] => 'blockstudio' ),
			$block_json['providesContext'] ?? array()
		);

		$block->path = $native_path;

		// Handle variations.
		if ( isset( $block_json['variations'] ) ) {
			$block->variations = $this->process_variations( $block_json['variations'] );
		}

		return $block;
	}

	/**
	 * Process block variations.
	 *
	 * @param array $variations The variations from block.json.
	 *
	 * @return array The processed variations.
	 */
	private function process_variations( array $variations ): array {
		$processed = array();

		foreach ( $variations as $variation ) {
			$processed[] = array(
				'attributes' => array(
					'blockstudio' => array(
						'attributes' => $variation['attributes'],
					),
				),
			) + $variation;
		}

		return $processed;
	}

	/**
	 * Build blockstudio metadata for a block.
	 *
	 * @param WP_Block_Type $block               The block type.
	 * @param array         $filtered_attributes The filtered attributes.
	 * @param array         $block_json          The block.json data.
	 *
	 * @return array The blockstudio metadata.
	 */
	private function build_blockstudio_metadata(
		WP_Block_Type $block,
		array $filtered_attributes,
		array $block_json
	): array {
		$disable_loading = $block_json['blockstudio']['blockEditor']['disableLoading']
			?? ( Settings::get( 'blockEditor/disableLoading' ) ?? false );

		return array(
			'attributes'  => $filtered_attributes,
			'blockEditor' => array(
				'disableLoading' => $disable_loading,
			),
			'conditions'  => $block->blockstudio['conditions'] ?? true,
			'editor'      => $block->blockstudio['editor'] ?? false,
			'extend'      => $block->blockstudio['extend'] ?? false,
			'group'       => $block->blockstudio['group'] ?? false,
			'icon'        => $block->blockstudio['icon'] ?? null,
			'refreshOn'   => $block->blockstudio['refreshOn'] ?? false,
			'transforms'  => $block->blockstudio['transforms'] ?? false,
			'variations'  => $block->variations ?? false,
		);
	}

	/**
	 * Register an override for a block.
	 *
	 * @param array         $override_data The override data.
	 * @param array         $override_json The override block.json.
	 * @param WP_Block_Type $original_block The original block.
	 *
	 * @return void
	 */
	public function apply_override( array $override_data, array $override_json, WP_Block_Type $original_block ): void {
		foreach ( $override_json as $key => $value ) {
			if ( 'blockstudio' === $key ) {
				$this->apply_blockstudio_override( $original_block, $value, $override_data );
				continue;
			}

			$original_block->{$key} = $value;
		}
	}

	/**
	 * Apply blockstudio-specific overrides.
	 *
	 * @param WP_Block_Type $block         The block to override.
	 * @param array         $blockstudio   The blockstudio override data.
	 * @param array         $override_data The full override data.
	 *
	 * @return void
	 */
	private function apply_blockstudio_override(
		WP_Block_Type $block,
		array $blockstudio,
		array $override_data
	): void {
		$override_attributes = $blockstudio['attributes'] ?? array();

		// Merge attributes.
		Build::merge_attributes(
			$block->blockstudio['attributes'],
			$override_attributes
		);

		// Build override attributes.
		$override_built_attributes = $this->attribute_builder->build(
			$override_attributes,
			true,
			false
		);

		// Merge built attributes.
		Build::merge_attributes(
			$block->attributes,
			$override_built_attributes
		);

		// Map attributes by ID.
		$mapped_attributes = array();
		foreach ( $block->attributes as $name => $attribute ) {
			if ( isset( $attribute['id'] ) ) {
				$mapped_attributes[ $attribute['id'] ] = $attribute;
			} else {
				$mapped_attributes[ $name ] = $attribute;
			}
		}

		$block->attributes = $mapped_attributes;
	}

	/**
	 * Register an extension block.
	 *
	 * @param array $extension_data The extension data.
	 * @param array $extension_json The extension block.json.
	 *
	 * @return WP_Block_Type|null The registered extension block or null.
	 */
	public function register_extension( array $extension_data, array $extension_json ): ?WP_Block_Type {
		return $this->register( $extension_data, $extension_json );
	}

	/**
	 * Register blocks with WordPress Block Registry.
	 *
	 * @param array $blocks Array of blocks to register.
	 *
	 * @return void
	 */
	public function register_with_wordpress( array $blocks ): void {
		foreach ( $blocks as $name => $block ) {
			if ( $block instanceof WP_Block_Type ) {
				$this->registry->register_block( $name, $block );
			}
		}
	}
}

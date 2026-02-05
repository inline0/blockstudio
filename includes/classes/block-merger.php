<?php
/**
 * Block Merger class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Merges template blocks with existing post blocks using keys.
 *
 * When blocks have a `__BLOCKSTUDIO_KEY` attribute, the merger preserves
 * user-edited content from the existing post while applying structural
 * changes from the updated template.
 *
 * @since 7.0.0
 */
class Block_Merger {

	/**
	 * Flat key map built from the entire old block tree.
	 *
	 * @var array<string, array>
	 */
	private array $old_key_map = array();

	/**
	 * Merge new template blocks with existing post blocks.
	 *
	 * Builds a flat key map from the entire old block tree, then walks
	 * the new template tree matching blocks by `__BLOCKSTUDIO_KEY` at
	 * any nesting level.
	 *
	 * @param array $new_blocks Blocks parsed from the updated template.
	 * @param array $old_blocks Blocks parsed from the existing post content.
	 *
	 * @return array The merged block array.
	 */
	public function merge( array $new_blocks, array $old_blocks ): array {
		$this->old_key_map = $this->build_key_map( $old_blocks );

		return $this->merge_blocks( $new_blocks );
	}

	/**
	 * Recursively merge blocks against the key map.
	 *
	 * @param array $new_blocks Blocks from the template.
	 *
	 * @return array The merged block array.
	 */
	private function merge_blocks( array $new_blocks ): array {
		$merged = array();

		foreach ( $new_blocks as $new_block ) {
			$key = $new_block['attrs']['__BLOCKSTUDIO_KEY'] ?? null;

			if ( null !== $key && isset( $this->old_key_map[ $key ] ) ) {
				$merged[] = $this->merge_block( $new_block, $this->old_key_map[ $key ] );
			} else {
				if ( ! empty( $new_block['innerBlocks'] ) ) {
					$new_block['innerBlocks'] = $this->merge_blocks( $new_block['innerBlocks'] );
				}
				$merged[] = $new_block;
			}
		}

		return $merged;
	}

	/**
	 * Merge a single keyed block pair.
	 *
	 * Preserves the user's content (innerHTML, innerContent, innerBlocks)
	 * while applying the template's attrs. If the block type changed,
	 * the template wins entirely.
	 *
	 * @param array $new_block The block from the updated template.
	 * @param array $old_block The block from the existing post content.
	 *
	 * @return array The merged block.
	 */
	private function merge_block( array $new_block, array $old_block ): array {
		if ( $new_block['blockName'] !== $old_block['blockName'] ) {
			return $new_block;
		}

		$new_block['innerHTML']    = $old_block['innerHTML'];
		$new_block['innerContent'] = $old_block['innerContent'];
		$new_block['innerBlocks']  = $old_block['innerBlocks'];

		return $new_block;
	}

	/**
	 * Build a flat key map from the entire block tree.
	 *
	 * Recursively walks all blocks and their innerBlocks, mapping
	 * `__BLOCKSTUDIO_KEY` values to block arrays. Keys must be globally
	 * unique across the entire tree.
	 *
	 * @param array $blocks The blocks to map.
	 *
	 * @return array<string, array> Key-to-block mapping.
	 */
	private function build_key_map( array $blocks ): array {
		$map = array();

		foreach ( $blocks as $block ) {
			$key = $block['attrs']['__BLOCKSTUDIO_KEY'] ?? null;

			if ( null !== $key ) {
				if ( isset( $map[ $key ] ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- Intentional warning for duplicate keys.
					trigger_error(
						sprintf( 'Blockstudio: Duplicate block key "%s" found. Second occurrence treated as unkeyed.', esc_html( $key ) ),
						E_USER_WARNING
					);
				} else {
					$map[ $key ] = $block;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_map = $this->build_key_map( $block['innerBlocks'] );

				foreach ( $inner_map as $inner_key => $inner_block ) {
					if ( ! isset( $map[ $inner_key ] ) ) {
						$map[ $inner_key ] = $inner_block;
					}
				}
			}
		}

		return $map;
	}
}

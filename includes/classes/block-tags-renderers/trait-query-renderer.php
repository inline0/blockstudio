<?php
/**
 * Query renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/query and core/comments blocks.
 */
trait Query_Renderer {

	/**
	 * Render core/query block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_query( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		$content = array( '<div class="wp-block-query">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/query',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-query"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/comments block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_comments( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		$content = array( '<div class="wp-block-comments">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/comments',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-comments"></div>',
			'innerContent' => $content,
		);
	}
}

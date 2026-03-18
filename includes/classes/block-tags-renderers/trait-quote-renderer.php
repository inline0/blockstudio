<?php
/**
 * Quote renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/quote blocks.
 */
trait Quote_Renderer {

	/**
	 * Render core/quote block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_quote( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		$content = array( '<blockquote class="wp-block-quote">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</blockquote>';

		return array(
			'blockName'    => 'core/quote',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<blockquote class="wp-block-quote"></blockquote>',
			'innerContent' => $content,
		);
	}
}

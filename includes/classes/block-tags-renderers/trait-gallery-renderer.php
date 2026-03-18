<?php
/**
 * Gallery renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/gallery blocks.
 */
trait Gallery_Renderer {

	/**
	 * Render core/gallery block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_gallery( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		$content = array( '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</figure>';

		return array(
			'blockName'    => 'core/gallery',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<figure class="wp-block-gallery has-nested-images columns-default is-cropped"></figure>',
			'innerContent' => $content,
		);
	}
}

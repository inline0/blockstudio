<?php
/**
 * Social links renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/social-links and core/social-link blocks.
 */
trait Social_Links_Renderer {

	/**
	 * Render core/social-links block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_social_links( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		$content = array( '<ul class="wp-block-social-links">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</ul>';

		return array(
			'blockName'    => 'core/social-links',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<ul class="wp-block-social-links"></ul>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/social-link block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_social_link( array $attrs, string $inner_content ): array {
		return array(
			'blockName'    => 'core/social-link',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
}

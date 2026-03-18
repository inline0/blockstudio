<?php
/**
 * Paragraph renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/paragraph blocks.
 */
trait Paragraph_Renderer {

	/**
	 * Render core/paragraph block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_paragraph( array $attrs, string $inner_content ): array {
		$html = '<p>' . $inner_content . '</p>';

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

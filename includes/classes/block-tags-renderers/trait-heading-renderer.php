<?php
/**
 * Heading renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/heading blocks.
 */
trait Heading_Renderer {

	/**
	 * Render core/heading block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_heading( array $attrs, string $inner_content ): array {
		$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
		if ( $level < 1 || $level > 6 ) {
			$level = 2;
		}

		$tag  = 'h' . $level;
		$html = "<{$tag} class=\"wp-block-heading\">{$inner_content}</{$tag}>";

		$attrs['level'] = $level;

		return array(
			'blockName'    => 'core/heading',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

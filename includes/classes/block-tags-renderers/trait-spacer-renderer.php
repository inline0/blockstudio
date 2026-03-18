<?php
/**
 * Spacer renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/spacer blocks.
 */
trait Spacer_Renderer {

	/**
	 * Render core/spacer block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_spacer( array $attrs, string $inner_content ): array {
		$height = isset( $attrs['height'] ) ? $attrs['height'] : '100px';

		$html = '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>';

		return array(
			'blockName'    => 'core/spacer',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

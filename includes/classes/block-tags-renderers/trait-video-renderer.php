<?php
/**
 * Video renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/video blocks.
 */
trait Video_Renderer {

	/**
	 * Render core/video block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_video( array $attrs, string $inner_content ): array {
		$src = isset( $attrs['src'] ) ? $attrs['src'] : '';

		$html = '<figure class="wp-block-video"><video controls src="' . esc_attr( $src ) . '"></video></figure>';

		return array(
			'blockName'    => 'core/video',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

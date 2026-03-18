<?php
/**
 * Audio renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/audio blocks.
 */
trait Audio_Renderer {

	/**
	 * Render core/audio block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_audio( array $attrs, string $inner_content ): array {
		$src = isset( $attrs['src'] ) ? $attrs['src'] : '';

		$html = '<figure class="wp-block-audio"><audio controls src="' . esc_attr( $src ) . '"></audio></figure>';

		return array(
			'blockName'    => 'core/audio',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

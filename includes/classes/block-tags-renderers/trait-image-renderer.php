<?php
/**
 * Image renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/image blocks.
 */
trait Image_Renderer {

	/**
	 * Render core/image block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_image( array $attrs, string $inner_content ): array {
		$src = isset( $attrs['src'] ) ? $attrs['src'] : ( isset( $attrs['url'] ) ? $attrs['url'] : '' );
		$alt = isset( $attrs['alt'] ) ? $attrs['alt'] : '';

		if ( ! empty( $src ) ) {
			$attrs['url'] = $src;
		}
		unset( $attrs['src'] );

		$html = '<figure class="wp-block-image"><img src="' . esc_attr( $src ) . '" alt="' . esc_attr( $alt ) . '"/></figure>';

		return array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

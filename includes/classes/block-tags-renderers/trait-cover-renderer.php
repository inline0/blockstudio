<?php
/**
 * Cover renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/cover blocks.
 */
trait Cover_Renderer {

	/**
	 * Render core/cover block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_cover( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		$url = isset( $attrs['url'] ) ? $attrs['url'] : ( isset( $attrs['src'] ) ? $attrs['src'] : '' );
		unset( $attrs['src'] );

		if ( ! empty( $url ) ) {
			$attrs['url'] = $url;
		}

		if ( ! isset( $attrs['dimRatio'] ) ) {
			$attrs['dimRatio'] = 50;
		}

		$img = '';
		if ( ! empty( $url ) ) {
			$img = '<img class="wp-block-cover__image-background" alt="" src="' . esc_attr( $url ) . '" data-object-fit="cover"/>';
		}

		$content = array( '<div class="wp-block-cover">' . $img . '<span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div></div>';

		return array(
			'blockName'    => 'core/cover',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-cover">' . $img . '<span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container"></div></div>',
			'innerContent' => $content,
		);
	}
}

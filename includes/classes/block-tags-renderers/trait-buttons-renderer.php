<?php
/**
 * Buttons renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/buttons and core/button blocks.
 */
trait Buttons_Renderer {

	/**
	 * Render core/buttons block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_buttons( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		$content = array( '<div class="wp-block-buttons">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/buttons',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-buttons"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/button block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_button( array $attrs, string $inner_content ): array {
		$text = $inner_content;
		$url  = isset( $attrs['url'] ) ? $attrs['url'] : ( isset( $attrs['href'] ) ? $attrs['href'] : '#' );
		unset( $attrs['href'] );
		$attrs['url'] = $url;

		$html = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_attr( $url ) . '">' . $text . '</a></div>';

		return array(
			'blockName'    => 'core/button',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

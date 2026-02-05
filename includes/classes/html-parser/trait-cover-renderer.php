<?php
/**
 * Cover renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/cover blocks.
 */
trait Cover_Renderer {

	/**
	 * Render core/cover block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_cover( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

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

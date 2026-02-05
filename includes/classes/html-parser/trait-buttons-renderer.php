<?php
/**
 * Buttons renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/buttons and core/button blocks.
 */
trait Buttons_Renderer {

	/**
	 * Render core/buttons block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_buttons( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

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
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_button( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$text = $parser->get_inner_html( $element );
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

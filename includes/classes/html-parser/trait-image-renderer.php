<?php
/**
 * Image renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/image blocks.
 */
trait Image_Renderer {

	/**
	 * Render core/image block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_image( DOMElement $element, array $attrs, Html_Parser $parser ): array {
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

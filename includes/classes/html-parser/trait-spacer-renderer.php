<?php
/**
 * Spacer renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/spacer blocks.
 */
trait Spacer_Renderer {

	/**
	 * Render core/spacer block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_spacer( DOMElement $element, array $attrs, Html_Parser $parser ): array {
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

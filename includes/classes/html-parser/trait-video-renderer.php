<?php
/**
 * Video renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/video blocks.
 */
trait Video_Renderer {

	/**
	 * Render core/video block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_video( DOMElement $element, array $attrs, Html_Parser $parser ): array {
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

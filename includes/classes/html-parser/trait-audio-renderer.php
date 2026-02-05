<?php
/**
 * Audio renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/audio blocks.
 */
trait Audio_Renderer {

	/**
	 * Render core/audio block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_audio( DOMElement $element, array $attrs, Html_Parser $parser ): array {
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

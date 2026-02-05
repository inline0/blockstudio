<?php
/**
 * Paragraph renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/paragraph blocks.
 */
trait Paragraph_Renderer {

	/**
	 * Render core/paragraph block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_paragraph( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content = $parser->get_inner_html( $element );
		$html    = '<p>' . $content . '</p>';

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

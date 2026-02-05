<?php
/**
 * Verse renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/verse blocks.
 */
trait Verse_Renderer {

	/**
	 * Render core/verse block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_verse( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content = $parser->get_inner_html( $element );
		$html    = '<pre class="wp-block-verse">' . $content . '</pre>';

		return array(
			'blockName'    => 'core/verse',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

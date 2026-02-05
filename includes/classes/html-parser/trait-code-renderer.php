<?php
/**
 * Code renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/code blocks.
 */
trait Code_Renderer {

	/**
	 * Render core/code block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_code( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content = $parser->get_inner_html( $element );
		$html    = '<pre class="wp-block-code"><code>' . $content . '</code></pre>';

		return array(
			'blockName'    => 'core/code',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

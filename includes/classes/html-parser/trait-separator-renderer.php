<?php
/**
 * Separator renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/separator blocks.
 */
trait Separator_Renderer {

	/**
	 * Render core/separator block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_separator( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$html = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';

		return array(
			'blockName'    => 'core/separator',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

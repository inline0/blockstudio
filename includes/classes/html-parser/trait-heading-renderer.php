<?php
/**
 * Heading renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/heading blocks.
 */
trait Heading_Renderer {

	/**
	 * Render core/heading block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_heading( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
		if ( $level < 1 || $level > 6 ) {
			$level = 2;
		}

		$content = $parser->get_inner_html( $element );
		$tag     = 'h' . $level;
		$html    = "<{$tag} class=\"wp-block-heading\">{$content}</{$tag}>";

		$attrs['level'] = $level;

		return array(
			'blockName'    => 'core/heading',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

<?php
/**
 * Quote renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/quote blocks.
 */
trait Quote_Renderer {

	/**
	 * Render core/quote block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_quote( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<blockquote class="wp-block-quote">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</blockquote>';

		return array(
			'blockName'    => 'core/quote',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<blockquote class="wp-block-quote"></blockquote>',
			'innerContent' => $content,
		);
	}
}

<?php
/**
 * Columns renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/columns and core/column blocks.
 */
trait Columns_Renderer {

	/**
	 * Render core/columns block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_columns( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div class="wp-block-columns">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/columns',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-columns"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/column block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_column( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div class="wp-block-column">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/column',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-column"></div>',
			'innerContent' => $content,
		);
	}
}

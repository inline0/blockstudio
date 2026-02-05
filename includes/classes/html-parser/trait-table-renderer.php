<?php
/**
 * Table renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/table blocks.
 */
trait Table_Renderer {

	/**
	 * Render core/table block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_table( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$table_html = $parser->get_outer_html( $element );
		$html       = '<figure class="wp-block-table">' . $table_html . '</figure>';

		return array(
			'blockName'    => 'core/table',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

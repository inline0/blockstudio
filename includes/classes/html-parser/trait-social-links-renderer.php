<?php
/**
 * Social links renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/social-links and core/social-link blocks.
 */
trait Social_Links_Renderer {

	/**
	 * Render core/social-links block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_social_links( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<ul class="wp-block-social-links">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</ul>';

		return array(
			'blockName'    => 'core/social-links',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<ul class="wp-block-social-links"></ul>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/social-link block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_social_link( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		return array(
			'blockName'    => 'core/social-link',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
}

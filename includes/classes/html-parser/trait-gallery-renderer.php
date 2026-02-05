<?php
/**
 * Gallery renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/gallery blocks.
 */
trait Gallery_Renderer {

	/**
	 * Render core/gallery block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_gallery( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$tag = strtolower( $child->tagName );
				if ( 'img' === $tag || 'block' === $tag ) {
					$block = $parser->parse_element_public( $child );
					if ( $block ) {
						$inner_blocks[] = $block;
					}
				}
			}
		}

		$content = array( '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</figure>';

		return array(
			'blockName'    => 'core/gallery',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<figure class="wp-block-gallery has-nested-images columns-default is-cropped"></figure>',
			'innerContent' => $content,
		);
	}
}

<?php
/**
 * Details renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/details blocks.
 */
trait Details_Renderer {

	/**
	 * Render core/details block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_details( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$summary      = isset( $attrs['summary'] ) ? $attrs['summary'] : '';
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				if ( 'summary' === strtolower( $child->tagName ) ) {
					$summary = $parser->get_inner_html( $child );
				} else {
					$block = $parser->parse_element_public( $child );
					if ( $block ) {
						$inner_blocks[] = $block;
					}
				}
			}
		}

		if ( ! empty( $summary ) ) {
			$attrs['summary'] = $summary;
		}

		$content = array( '<details class="wp-block-details"><summary>' . esc_html( $summary ) . '</summary>' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</details>';

		return array(
			'blockName'    => 'core/details',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<details class="wp-block-details"><summary>' . esc_html( $summary ) . '</summary></details>',
			'innerContent' => $content,
		);
	}
}

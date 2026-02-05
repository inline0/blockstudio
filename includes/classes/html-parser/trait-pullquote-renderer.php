<?php
/**
 * Pullquote renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;
use DOMText;

/**
 * Renders core/pullquote blocks.
 */
trait Pullquote_Renderer {

	/**
	 * Render core/pullquote block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_pullquote( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content  = '';
		$citation = isset( $attrs['citation'] ) ? $attrs['citation'] : '';

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$tag = strtolower( $child->tagName );
				if ( 'cite' === $tag ) {
					$citation = $parser->get_inner_html( $child );
				} else {
					$content .= $parser->get_outer_html( $child );
				}
			} elseif ( $child instanceof DOMText ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$text = trim( $child->textContent );
				if ( ! empty( $text ) ) {
					$content .= '<p>' . esc_html( $text ) . '</p>';
				}
			}
		}

		$html = '<figure class="wp-block-pullquote"><blockquote>' . $content;
		if ( ! empty( $citation ) ) {
			$html             .= '<cite>' . esc_html( $citation ) . '</cite>';
			$attrs['citation'] = $citation;
		}
		$html .= '</blockquote></figure>';

		return array(
			'blockName'    => 'core/pullquote',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

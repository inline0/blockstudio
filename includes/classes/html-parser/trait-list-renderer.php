<?php
/**
 * List renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;
use DOMText;

/**
 * Renders core/list blocks.
 */
trait List_Renderer {

	/**
	 * Render core/list block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_list( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$ordered      = ! empty( $attrs['ordered'] );
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				$content        = $parser->get_inner_html( $child );
				$inner_blocks[] = array(
					'blockName'    => 'core/list-item',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '<li>' . $content . '</li>',
					'innerContent' => array( '<li>' . $content . '</li>' ),
				);
			} elseif ( $child instanceof DOMText ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$text = trim( $child->textContent );
				if ( ! empty( $text ) ) {
					$inner_blocks[] = array(
						'blockName'    => 'core/list-item',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<li>' . esc_html( $text ) . '</li>',
						'innerContent' => array( '<li>' . esc_html( $text ) . '</li>' ),
					);
				}
			}
		}

		$tag     = $ordered ? 'ol' : 'ul';
		$content = array( "<{$tag} class=\"wp-block-list\">" );
		foreach ( $inner_blocks as $item ) {
			$content[] = null;
		}
		$content[] = "</{$tag}>";

		return array(
			'blockName'    => 'core/list',
			'attrs'        => array( 'ordered' => $ordered ),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => "<{$tag} class=\"wp-block-list\"></{$tag}>",
			'innerContent' => $content,
		);
	}
}

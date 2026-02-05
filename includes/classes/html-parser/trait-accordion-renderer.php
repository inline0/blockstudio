<?php
/**
 * Accordion renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/accordion, core/accordion-item, core/accordion-heading,
 * and core/accordion-panel blocks.
 */
trait Accordion_Renderer {

	/**
	 * Render core/accordion block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_accordion( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div role="group" class="wp-block-accordion">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/accordion',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div role="group" class="wp-block-accordion"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/accordion-item block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_accordion_item( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div class="wp-block-accordion-item">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/accordion-item',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-accordion-item"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/accordion-heading block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_accordion_heading( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 3;
		$tag   = 'h' . $level;
		$title = $parser->get_inner_html( $element );

		$show_icon     = isset( $attrs['showIcon'] ) ? $attrs['showIcon'] : true;
		$icon_position = isset( $attrs['iconPosition'] ) ? $attrs['iconPosition'] : 'right';

		// DOMDocument lowercases attribute names.
		if ( isset( $attrs['showicon'] ) ) {
			$show_icon         = $attrs['showicon'];
			$attrs['showIcon'] = $show_icon;
			unset( $attrs['showicon'] );
		}
		if ( isset( $attrs['iconposition'] ) ) {
			$icon_position         = $attrs['iconposition'];
			$attrs['iconPosition'] = $icon_position;
			unset( $attrs['iconposition'] );
		}

		$icon_html = '';
		if ( $show_icon ) {
			$icon_html = '<span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span>';
		}

		$button_inner = '';
		if ( 'left' === $icon_position && $show_icon ) {
			$button_inner = $icon_html;
		}
		$button_inner .= '<span class="wp-block-accordion-heading__toggle-title">' . $title . '</span>';
		if ( 'right' === $icon_position && $show_icon ) {
			$button_inner .= $icon_html;
		}

		$html = '<' . $tag . ' class="wp-block-accordion-heading"><button class="wp-block-accordion-heading__toggle">' . $button_inner . '</button></' . $tag . '>';

		return array(
			'blockName'    => 'core/accordion-heading',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/accordion-panel block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_accordion_panel( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div role="region" class="wp-block-accordion-panel">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/accordion-panel',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div role="region" class="wp-block-accordion-panel"></div>',
			'innerContent' => $content,
		);
	}
}

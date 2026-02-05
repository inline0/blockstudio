<?php
/**
 * More renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/more and core/nextpage blocks.
 */
trait More_Renderer {

	/**
	 * Render core/more block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_more( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$custom_text = isset( $attrs['customText'] ) ? $attrs['customText'] : '';

		// DOMDocument lowercases attribute names.
		if ( empty( $custom_text ) && isset( $attrs['customtext'] ) ) {
			$custom_text         = $attrs['customtext'];
			$attrs['customText'] = $custom_text;
			unset( $attrs['customtext'] );
		}

		$no_teaser = isset( $attrs['noTeaser'] ) ? $attrs['noTeaser'] : false;
		if ( ! $no_teaser && isset( $attrs['noteaser'] ) ) {
			$no_teaser         = $attrs['noteaser'];
			$attrs['noTeaser'] = $no_teaser;
			unset( $attrs['noteaser'] );
		}

		$html = '<!--more';
		if ( ! empty( $custom_text ) ) {
			$html .= ' ' . $custom_text;
		}
		$html .= '-->';

		if ( $no_teaser ) {
			$html .= "\n<!--noteaser-->";
		}

		return array(
			'blockName'    => 'core/more',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/nextpage block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_nextpage( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$html = '<!--nextpage-->';

		return array(
			'blockName'    => 'core/nextpage',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

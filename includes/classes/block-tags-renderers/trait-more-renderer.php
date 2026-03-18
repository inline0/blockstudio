<?php
/**
 * More renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/more and core/nextpage blocks.
 */
trait More_Renderer {

	/**
	 * Render core/more block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_more( array $attrs, string $inner_content ): array {
		$custom_text = isset( $attrs['customText'] ) ? $attrs['customText'] : '';

		// Handle lowercased attribute names.
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
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_nextpage( array $attrs, string $inner_content ): array {
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

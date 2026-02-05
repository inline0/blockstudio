<?php
/**
 * Embed renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/embed blocks.
 */
trait Embed_Renderer {

	/**
	 * Render core/embed block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_embed( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$url      = isset( $attrs['url'] ) ? $attrs['url'] : '';
		$provider = isset( $attrs['providerNameSlug'] ) ? $attrs['providerNameSlug'] : 'youtube';

		$html = '<figure class="wp-block-embed is-type-video is-provider-' . esc_attr( $provider ) . ' wp-block-embed-' . esc_attr( $provider ) . '"><div class="wp-block-embed__wrapper">' . esc_url( $url ) . '</div></figure>';

		return array(
			'blockName'    => 'core/embed',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

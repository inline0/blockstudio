<?php
/**
 * Pullquote renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/pullquote blocks.
 */
trait Pullquote_Renderer {

	/**
	 * Render core/pullquote block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_pullquote( array $attrs, string $inner_content ): array {
		$citation = isset( $attrs['citation'] ) ? $attrs['citation'] : '';

		// Extract <cite> from inner content if present.
		$body = $inner_content;
		if ( preg_match( '/<cite>(.*?)<\/cite>/si', $inner_content, $cite_match ) ) {
			$citation = $cite_match[1];
			$body     = preg_replace( '/<cite>.*?<\/cite>/si', '', $inner_content );
		}

		$body = trim( $body );
		if ( ! empty( $body ) && ! preg_match( '/^</', $body ) ) {
			$body = '<p>' . esc_html( $body ) . '</p>';
		}

		$html = '<figure class="wp-block-pullquote"><blockquote>' . $body;
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

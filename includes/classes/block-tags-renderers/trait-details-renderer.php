<?php
/**
 * Details renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/details blocks.
 */
trait Details_Renderer {

	/**
	 * Render core/details block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_details( array $attrs, string $inner_content ): array {
		$summary = isset( $attrs['summary'] ) ? $attrs['summary'] : '';

		// Extract <summary> from inner content if present.
		$remaining = $inner_content;
		if ( preg_match( '/<summary>(.*?)<\/summary>/si', $inner_content, $summary_match ) ) {
			$summary   = $summary_match[1];
			$remaining = preg_replace( '/<summary>.*?<\/summary>/si', '', $inner_content );
		}

		$inner_blocks = Block_Tags::parse_inner_blocks( $remaining );

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

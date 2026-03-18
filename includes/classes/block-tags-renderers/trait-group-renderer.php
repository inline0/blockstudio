<?php
/**
 * Group renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/group blocks.
 */
trait Group_Renderer {

	/**
	 * Render core/group block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_group( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );

		// Handle layout attribute (for row/stack variants).
		$class = 'wp-block-group';
		if ( isset( $attrs['layout'] ) ) {
			if ( is_string( $attrs['layout'] ) ) {
				$attrs['layout'] = json_decode( $attrs['layout'], true );
			}
			if ( isset( $attrs['layout']['type'] ) && 'flex' === $attrs['layout']['type'] ) {
				$class .= ' is-layout-flex';
				if ( isset( $attrs['layout']['orientation'] ) && 'vertical' === $attrs['layout']['orientation'] ) {
					$class .= ' is-vertical';
				}
			}
		}

		$content = array( '<div class="' . $class . '">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/group',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="' . $class . '"></div>',
			'innerContent' => $content,
		);
	}
}

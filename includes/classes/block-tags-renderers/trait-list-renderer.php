<?php
/**
 * List renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/list blocks.
 */
trait List_Renderer {

	/**
	 * Render core/list block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_list( array $attrs, string $inner_content ): array {
		$ordered      = ! empty( $attrs['ordered'] );
		$inner_blocks = array();

		preg_match_all( '/<li>(.*?)<\/li>/si', $inner_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $item_content ) {
				$inner_blocks[] = array(
					'blockName'    => 'core/list-item',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '<li>' . $item_content . '</li>',
					'innerContent' => array( '<li>' . $item_content . '</li>' ),
				);
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

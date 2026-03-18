<?php
/**
 * Verse renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/verse blocks.
 */
trait Verse_Renderer {

	/**
	 * Render core/verse block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_verse( array $attrs, string $inner_content ): array {
		$html = '<pre class="wp-block-verse">' . $inner_content . '</pre>';

		return array(
			'blockName'    => 'core/verse',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

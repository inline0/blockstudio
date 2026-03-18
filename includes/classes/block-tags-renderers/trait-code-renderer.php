<?php
/**
 * Code renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/code blocks.
 */
trait Code_Renderer {

	/**
	 * Render core/code block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_code( array $attrs, string $inner_content ): array {
		$html = '<pre class="wp-block-code"><code>' . $inner_content . '</code></pre>';

		return array(
			'blockName'    => 'core/code',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

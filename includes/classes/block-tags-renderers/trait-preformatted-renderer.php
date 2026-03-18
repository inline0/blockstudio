<?php
/**
 * Preformatted renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/preformatted blocks.
 */
trait Preformatted_Renderer {

	/**
	 * Render core/preformatted block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_preformatted( array $attrs, string $inner_content ): array {
		$html = '<pre class="wp-block-preformatted">' . $inner_content . '</pre>';

		return array(
			'blockName'    => 'core/preformatted',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

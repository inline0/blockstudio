<?php
/**
 * Separator renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/separator blocks.
 */
trait Separator_Renderer {

	/**
	 * Render core/separator block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_separator( array $attrs, string $inner_content ): array {
		$html = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';

		return array(
			'blockName'    => 'core/separator',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

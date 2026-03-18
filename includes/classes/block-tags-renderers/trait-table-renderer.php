<?php
/**
 * Table renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/table blocks.
 */
trait Table_Renderer {

	/**
	 * Render core/table block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_table( array $attrs, string $inner_content ): array {
		$html = '<figure class="wp-block-table"><table>' . $inner_content . '</table></figure>';

		return array(
			'blockName'    => 'core/table',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}

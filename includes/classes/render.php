<?php
/**
 * Render class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Programmatic block rendering utility for theme developers.
 *
 * This class provides a simple API for rendering Blockstudio blocks
 * directly from PHP code, useful for:
 *
 * - Including blocks in theme templates
 * - Rendering blocks in custom page builders
 * - Generating block output for AJAX/REST responses
 * - Testing block output during development
 *
 * Usage Examples:
 *
 * ```php
 * // Render by block name (echoes output)
 * Render::block('blockstudio/hero');
 *
 * // Render with custom attributes
 * Render::block([
 *     'name' => 'blockstudio/card',
 *     'data' => [
 *         'title' => 'My Card',
 *         'image' => 123  // Attachment ID
 *     ]
 * ]);
 *
 * // Render with inner content
 * Render::block([
 *     'name' => 'blockstudio/section',
 *     'data' => ['background' => 'dark'],
 *     'content' => '<p>Inner HTML content</p>'
 * ]);
 * ```
 *
 * Helper Function:
 * The blockstudio_render_block() function wraps this class:
 * ```php
 * blockstudio_render_block(['name' => 'blockstudio/hero']);
 * ```
 *
 * @since 1.0.0
 */
class Render {

	/**
	 * Render a block by name or configuration.
	 *
	 * @param string|array $value Block name or configuration array.
	 *
	 * @return false|string|void Returns HTML string, false on failure, or void when echoing.
	 */
	public static function block( $value ) {
		$data    = array();
		$content = false;

		if ( is_array( $value ) ) {
			$name    = $value['name'] ?? $value['id'];
			$data    = $value['data'] ?? array();
			$content = $value['content'] ?? false;
		} else {
			$name = $value;
		}

		$blocks = Build::data();

		if (
			! isset( $blocks[ $name ]['path'] ) &&
			! isset( $data['_BLOCKSTUDIO_EDITOR_STRING'] )
		) {
			return false;
		}

		$editor = $data['_BLOCKSTUDIO_EDITOR_STRING'] ?? false;
		unset( $data['_BLOCKSTUDIO_EDITOR_STRING'] );

		if ( $editor ) {
			return Block::render(
				array(
					'blockstudio' => array(
						'editor'     => $editor,
						'name'       => $name,
						'attributes' => $data,
					),
				)
			);
		} else {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Block render handles escaping.
			echo Block::render(
				array(
					'blockstudio' => array(
						'name'       => $name,
						'attributes' => $data,
					),
				),
				'',
				'',
				$content
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

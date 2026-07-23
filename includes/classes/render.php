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
	 * Programmatic embeds render frontend-resolved HTML even when called from
	 * another block's editor preview.
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

		$parent = \WP_Block_Supports::$block_to_render;

		\WP_Block_Supports::$block_to_render = array(
			'blockName' => $name,
			'attrs'     => $data,
		);

		if ( $editor ) {
			try {
				$result = Block::render(
					array(
						'blockstudio' => array(
							'editor'     => $editor,
							'name'       => $name,
							'attributes' => $data,
						),
					)
				);
			} finally {
				\WP_Block_Supports::$block_to_render = $parent;
			}

			return $result;
		} else {
			$mode = isset( $_GET['blockstudioMode'] ) ? sanitize_text_field( wp_unslash( $_GET['blockstudioMode'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['blockstudioMode'] );

			try {
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
			} finally {
				if ( null !== $mode ) {
					$_GET['blockstudioMode'] = $mode; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}

				\WP_Block_Supports::$block_to_render = $parent;
			}
		}
	}
}

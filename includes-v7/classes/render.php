<?php
/**
 * Render class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles block rendering.
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
		}
	}
}

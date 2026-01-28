<?php
/**
 * Builder class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles the deprecated Blockstudio builder blocks.
 */
class Builder {

	/**
	 * Builder block names.
	 *
	 * @var array
	 */
	private static array $blocks = array(
		'blockstudio/container',
		'blockstudio/element',
		'blockstudio/text',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'render_block', array( __CLASS__, 'render_blocks' ), 10, 2 );
	}

	/**
	 * Enqueue builder editor assets.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		if ( ! Settings::get( 'builderDeprecated/enabled' ) ) {
			return;
		}

		$block_scripts = include BLOCKSTUDIO_DIR . '/includes/admin/assets/builder/index.tsx.asset.php';
		wp_enqueue_script(
			'blockstudio-builder',
			plugin_dir_url( __FILE__ ) . '../admin/assets/builder/index.tsx.js',
			$block_scripts['dependencies'],
			$block_scripts['version'],
			true
		);
		wp_localize_script(
			'blockstudio-builder',
			'blockstudioAdmin',
			Admin::data( false )
		);
	}

	/**
	 * Render builder blocks.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string The rendered block HTML.
	 */
	public static function render_blocks( $block_content, $block ): string {
		if ( ! in_array( $block['blockName'], self::$blocks, true ) ) {
			return $block_content;
		}

		$name = $block['blockName'];
		$data = $block['attrs']['blockstudio']['data'] ?? array();

		if ( 'blockstudio/container' === $block['blockName'] ) {
			$tag     = $data['tag'] ?? 'div';
			$content = $block_content ?? '';
		} elseif ( 'blockstudio/element' === $block['blockName'] ) {
			$tag     = $data['tag'] ?? 'img';
			$content = '';
		} else {
			$tag     = $data['tag'] ?? 'p';
			$content = $data['content'] ?? '';
		}

		$class_name = $data['className'] ?? '';

		$class = '';
		if ( ! is_array( $class_name ) && '' !== trim( $class_name ?? '' ) ) {
			$class = "class='" . esc_attr( $class_name ) . "'";
		}

		$attribute = Utils::data_attributes( $data['attributes'] ?? array() );

		$attribute_blockstudio = "data-blockstudio='" . esc_attr( $name ) . "'";

		if ( 'blockstudio/element' === $block['blockName'] ) {
			return "<$tag $class $attribute_blockstudio $attribute />";
		}

		return "<$tag $class $attribute_blockstudio $attribute>$content</$tag>";
	}
}

new Builder();

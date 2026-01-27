<?php
/**
 * Blocks class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Parser;

/**
 * Handles block editor integration.
 */
class Blocks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'admin_footer', array( $this, 'output_admin_footer_scripts' ) );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		global $post;

		$block_scripts = include BLOCKSTUDIO_DIR . '/includes-v7/admin/assets/blocks/index.tsx.asset.php';
		wp_enqueue_script(
			'blockstudio-blocks',
			plugin_dir_url( __FILE__ ) . '../admin/assets/blocks/index.tsx.js',
			$block_scripts['dependencies'],
			$block_scripts['version'],
			true
		);

		$blockstudio_blocks = array();
		$blocks             = Build::blocks();
		$block_names        = array_keys( $blocks );

		$parser = new WP_Block_Parser();

		$content       = $this->get_content( $post );
		$parsed_blocks = $parser->parse( $content );

		$block_renderer = function ( $block ) use (
			&$block_renderer,
			&$blockstudio_blocks,
			$block_names,
			$blocks
		) {
			if ( in_array( $block['blockName'], $block_names, true ) ) {
				$block_attrs = $block['attrs'];
				Block::transform(
					$block_attrs,
					$block,
					$block['blockName'],
					false,
					false,
					$blocks[ $block['blockName'] ]->attributes
				);
				$block_obj = array(
					'blockName' => $block['blockName'],
					'attrs'     => $block_attrs,
				);
				$id        = str_replace(
					array( '{', '}', '[', ']', '"', '/', ' ', ':', ',', '\\' ),
					'_',
					wp_json_encode( $block_obj )
				);
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Setting mode for rendering.
				$_GET['blockstudioMode']     = 'editor';
				$blockstudio_blocks[ $id ] = array(
					'rendered' => render_block( $block ),
					'block'    => $block_obj,
				);
			}
			if ( count( $block['innerBlocks'] ) > 0 ) {
				foreach ( $block['innerBlocks'] as $inner_block ) {
					$block_renderer( $inner_block );
				}
			}
		};

		foreach ( $parsed_blocks as $block ) {
			$block_renderer( $block );
		}

		$localize_array = array(
			'nonce'             => wp_create_nonce( 'ajax-nonce' ),
			'nonceRest'         => wp_create_nonce( 'wp_rest' ),
			'rest'              => esc_url_raw( rest_url() ),
			'blockstudioBlocks' => $blockstudio_blocks,
		);

		wp_localize_script(
			'blockstudio-blocks',
			'blockstudio',
			$localize_array
		);

		wp_localize_script(
			'blockstudio-blocks',
			'blockstudioAdmin',
			array_merge(
				Admin::data( false ),
				apply_filters( 'blockstudio/blocks/conditions', array() )
			)
		);
	}

	/**
	 * Output admin footer scripts for block editor.
	 *
	 * @return void
	 */
	public function output_admin_footer_scripts(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen->is_block_editor() ) {
			return;
		}

		$all_assets               = Admin::get_all_assets();
		$chosen_css_class_styles  = Settings::get( 'blockEditor/cssClasses' );
		$css_classes              = array();
		$chosen_css_vars_styles   = Settings::get( 'blockEditor/cssVariables' );
		$css_variables            = array();

		$styles = $all_assets['styles'];

		if ( count( $chosen_css_class_styles ) > 0 ) {
			foreach ( $styles as $key => $style ) {
				if ( ! in_array( $key, $chosen_css_class_styles, true ) ) {
					continue;
				}

				$css_classes[] = $key;
			}
		}

		if ( count( $chosen_css_vars_styles ) > 0 ) {
			foreach ( $styles as $key => $style ) {
				if ( ! in_array( $key, $chosen_css_vars_styles, true ) ) {
					continue;
				}

				$css_variables[] = $key;
			}
		}

		echo '<script>';
		echo 'window.blockstudioAdmin.styles = ' . wp_json_encode( $all_assets['styles'] ) . ';';
		echo 'window.blockstudioAdmin.cssClasses = ' . wp_json_encode( $css_classes ) . ';';
		echo 'window.blockstudioAdmin.cssVariables = ' . wp_json_encode( $css_variables ) . ';';
		echo '</script>';
	}

	/**
	 * Get content for parsing blocks.
	 *
	 * @param object|null $post The post object.
	 *
	 * @return string The post content.
	 */
	private function get_content( ?object $post ): string {
		if ( $post && ! empty( $post->post_content ) ) {
			return $post->post_content;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading query params for template detection.
		if (
			isset( $_GET['p'] ) &&
			isset( $_GET['canvas'] ) &&
			'edit' === $_GET['canvas']
		) {
			$template_path = sanitize_text_field( wp_unslash( $_GET['p'] ) );

			if ( str_starts_with( $template_path, '/wp_template/' ) ) {
				$template_id = substr( $template_path, strlen( '/wp_template/' ) );
				$template    = get_block_template( $template_id );
				if ( $template && ! empty( $template->content ) ) {
					return $template->content;
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return '';
	}
}

new Blocks();

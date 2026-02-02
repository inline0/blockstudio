<?php
/**
 * Blocks class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Parser;

/**
 * Gutenberg block editor integration and script enqueuing.
 *
 * This class handles the JavaScript side of Blockstudio in the block editor,
 * enqueuing scripts and providing initial block render data.
 *
 * Responsibilities:
 *
 * 1. Script Enqueuing:
 *    - Enqueues the main blocks/index.tsx.js script
 *    - Provides blockstudio and blockstudioAdmin global objects
 *    - Includes nonces, REST URLs, and admin data
 *
 * 2. Initial Block Rendering:
 *    - Parses current post content to find Blockstudio blocks
 *    - Pre-renders each block server-side for instant display
 *    - Provides blockstudioBlocks object with { rendered, block } data
 *
 * 3. CSS Class/Variable Injection:
 *    - Reads cssClasses and cssVariables settings
 *    - Provides style handles to the editor for class autocomplete
 *
 * Global Objects Provided:
 *
 * window.blockstudio:
 * - nonce: AJAX nonce
 * - nonceRest: REST API nonce
 * - rest: REST API base URL
 * - blockstudioBlocks: Pre-rendered block data keyed by serialized attrs
 *
 * window.blockstudioAdmin:
 * - All data from Admin::data(false)
 * - styles: All enqueued stylesheets
 * - cssClasses: Handles for class extraction
 * - cssVariables: Handles for CSS variable extraction
 *
 * @since 1.0.0
 */
class Blocks {

	/**
	 * Flag to prevent recursive calls when getting assets.
	 *
	 * @var bool
	 */
	private static bool $getting_assets = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		global $post;

		$block_scripts = include BLOCKSTUDIO_DIR . '/includes/admin/assets/blocks/index.tsx.asset.php';
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
				$_GET['blockstudioMode']   = 'editor';
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

		// Build styles data for CSS classes and variables autocomplete.
		// Use flag to prevent infinite recursion (get_all_assets triggers enqueue_block_editor_assets).
		$styles        = array();
		$css_classes   = array();
		$css_variables = array();

		if ( ! self::$getting_assets ) {
			self::$getting_assets = true;

			$all_assets              = Admin::get_all_assets();
			$chosen_css_class_styles = Settings::get( 'blockEditor/cssClasses' );
			$chosen_css_vars_styles  = Settings::get( 'blockEditor/cssVariables' );

			$styles = $all_assets['styles'];

			if ( is_array( $chosen_css_class_styles ) && count( $chosen_css_class_styles ) > 0 ) {
				foreach ( $styles as $key => $style ) {
					if ( ! in_array( $key, $chosen_css_class_styles, true ) ) {
						continue;
					}

					$css_classes[] = $key;
				}
			}

			if ( is_array( $chosen_css_vars_styles ) && count( $chosen_css_vars_styles ) > 0 ) {
				foreach ( $styles as $key => $style ) {
					if ( ! in_array( $key, $chosen_css_vars_styles, true ) ) {
						continue;
					}

					$css_variables[] = $key;
				}
			}

			self::$getting_assets = false;
		}

		wp_localize_script(
			'blockstudio-blocks',
			'blockstudioAdmin',
			array_merge(
				Admin::data( false ),
				apply_filters( 'blockstudio/blocks/conditions', array() ),
				array(
					'styles'       => $styles,
					'cssClasses'   => $css_classes,
					'cssVariables' => $css_variables,
				)
			)
		);
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

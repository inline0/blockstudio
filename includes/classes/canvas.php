<?php
/**
 * Canvas class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Editor_Context;
use WP_Block_Parser;

/**
 * Figma-like canvas view showing all Blockstudio pages using BlockPreview.
 *
 * Registers a hidden admin page that renders blocks client-side via
 * BlockPreview from @wordpress/block-editor, with pre-rendered HTML
 * for Blockstudio blocks.
 *
 * @since 7.0.0
 */
class Canvas {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register hidden admin page for the canvas.
	 *
	 * @return void
	 */
	public function register_admin_page(): void {
		if ( ! Settings::get( 'dev/canvas/enabled' ) ) {
			return;
		}

		add_submenu_page(
			null,
			'Blockstudio Canvas',
			'',
			'edit_posts',
			'blockstudio-canvas',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the admin page HTML.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		echo '<style>'
			. 'html, body, #wpwrap { background: #2c2c2c !important; }'
			. 'html { margin-top: 0 !important; }'
			. '#adminmenumain, #adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter { display: none !important; }'
			. '#wpcontent { margin-left: 0 !important; padding: 0 !important; }'
			. '.notice, .update-nag, .updated, .error, .is-dismissible { display: none !important; }'
			. '#blockstudio-canvas { position: fixed; inset: 0; z-index: 999999; }'
			. '</style>';
		echo '<div id="blockstudio-canvas"></div>';
	}

	/**
	 * Enqueue canvas assets on the admin page.
	 *
	 * @param string $hook The admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'admin_page_blockstudio-canvas' !== $hook ) {
			return;
		}

		if ( ! Settings::get( 'dev/canvas/enabled' ) ) {
			return;
		}

		$asset_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/canvas/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_script( 'wp-block-library' );

		do_action( 'enqueue_block_editor_assets' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook.

		$asset = require $asset_file;

		$pages              = $this->get_pages_with_content();
		$blockstudio_blocks = $this->preload_all_blocks( $pages );

		wp_add_inline_script(
			'blockstudio-blocks',
			'window.blockstudio.blockstudioBlocks = Object.assign(window.blockstudio.blockstudioBlocks || {}, ' . wp_json_encode( $blockstudio_blocks ) . ');',
			'after'
		);

		wp_enqueue_script(
			'blockstudio-canvas',
			plugins_url( 'includes/admin/assets/canvas/index.js', BLOCKSTUDIO_FILE ),
			array_merge( $asset['dependencies'] ?? array(), array( 'blockstudio-blocks' ) ),
			$asset['version'] ?? BLOCKSTUDIO_VERSION,
			true
		);

		$editor_settings = get_block_editor_settings(
			array(),
			new WP_Block_Editor_Context( array( 'name' => 'core/edit-post' ) )
		);

		wp_localize_script(
			'blockstudio-canvas',
			'blockstudioCanvas',
			array(
				'pages'    => $pages,
				'settings' => $editor_settings,
			)
		);
	}

	/**
	 * Get all Blockstudio-managed pages with their post content.
	 *
	 * @return array<int, array{title: string, slug: string, name: string, content: string}>
	 */
	private function get_pages_with_content(): array {
		$posts = get_posts(
			array(
				'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$pages = array();

		foreach ( $posts as $post ) {
			$name = get_post_meta( $post->ID, '_blockstudio_page_name', true );

			$pages[] = array(
				'title'   => $post->post_title,
				'slug'    => $post->post_name,
				'name'    => $name ? $name : $post->post_name,
				'content' => $post->post_content,
			);
		}

		return $pages;
	}

	/**
	 * Pre-render all Blockstudio blocks across all pages.
	 *
	 * @param array $pages Pages with content from get_pages_with_content().
	 * @return array<string, array{rendered: string, block: array}> Preloaded block data keyed by hash.
	 */
	private function preload_all_blocks( array $pages ): array {
		$blockstudio_blocks = array();
		$blocks             = Build::blocks();
		$block_names        = array_keys( $blocks );
		$parser             = new WP_Block_Parser();

		$block_renderer = function ( $block ) use (
			&$block_renderer,
			&$blockstudio_blocks,
			$block_names,
			$blocks
		) {
			if ( in_array( $block['blockName'], $block_names, true ) ) {
				$raw_inner = $block['attrs']['blockstudio']['attributes'] ?? array();

				if ( is_array( $raw_inner ) && ! empty( $raw_inner ) ) {
					array_walk_recursive(
						$raw_inner,
						function ( &$value ) {
							if ( '' === $value ) {
								$value = false;
							}
						}
					);
				}

				$block_obj = array(
					'blockName' => $block['blockName'],
					'attrs'     => (object) $raw_inner,
				);

				$id = str_replace(
					array( '{', '}', '[', ']', '"', '/', ' ', ':', ',', '\\' ),
					'_',
					wp_json_encode( $block_obj )
				);

				if ( ! isset( $blockstudio_blocks[ $id ] ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Setting mode for rendering.
					$_GET['blockstudioMode']   = 'editor';
					$blockstudio_blocks[ $id ] = array(
						'rendered' => render_block( $block ),
						'block'    => $block_obj,
					);
				}
			}

			if ( count( $block['innerBlocks'] ) > 0 ) {
				foreach ( $block['innerBlocks'] as $inner_block ) {
					$block_renderer( $inner_block );
				}
			}
		};

		foreach ( $pages as $page ) {
			$parsed_blocks = $parser->parse( $page['content'] );

			foreach ( $parsed_blocks as $block ) {
				$block_renderer( $block );
			}
		}

		return $blockstudio_blocks;
	}
}

new Canvas();

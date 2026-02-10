<?php
/**
 * Canvas class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Block_Editor_Context;
use WP_Block_Parser;
use WP_REST_Response;

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
	 * File extensions to monitor for changes.
	 *
	 * @var array<string>
	 */
	private const WATCHED_EXTENSIONS = array( 'php', 'json', 'css', 'scss', 'js', 'twig', 'html' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
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
		echo '<script>document.title = "Blockstudio Canvas";</script>';
		echo '<style>'
			. 'html, body, #wpwrap { background: #2c2c2c !important; overflow: hidden !important; }'
			. 'html { margin-top: 0 !important; }'
			. '#adminmenumain, #adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter { display: none !important; }'
			. '#wpcontent { margin-left: 0 !important; padding: 0 !important; }'
			. '.notice, .update-nag, .updated, .error, .is-dismissible { display: none !important; }'
			. '#blockstudio-canvas { position: fixed; inset: 0; z-index: 999999; overflow: hidden; }'
			. '@keyframes blockstudio-canvas-spin { to { transform: rotate(360deg); } }'
			. '#blockstudio-canvas-loader { position: fixed; inset: 0; z-index: 9999999; display: flex; align-items: center; justify-content: center; pointer-events: none; }'
			. '#blockstudio-canvas-loader > div { width: 32px; height: 32px; border: 3px solid rgba(255,255,255,0.1); border-top-color: rgba(255,255,255,0.4); border-radius: 50%; animation: blockstudio-canvas-spin 0.8s linear infinite; }'
			. '</style>';
		echo '<div id="blockstudio-canvas-loader"><div></div></div>';
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
		$blocks             = $this->get_blocks_with_content();
		$blockstudio_blocks = $this->preload_all_blocks( $pages );
		$block_preloads     = $this->preload_block_items( $blocks );
		$blockstudio_blocks = array_merge( $blockstudio_blocks, $block_preloads );

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

		Assets::$force_editor_screen = true;

		$editor_settings = get_block_editor_settings(
			array(),
			new WP_Block_Editor_Context( array( 'name' => 'core/edit-post' ) )
		);

		Assets::$force_editor_screen = false;

		wp_localize_script(
			'blockstudio-canvas',
			'blockstudioCanvas',
			array(
				'pages'    => $pages,
				'blocks'   => $blocks,
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
	 * Get all registered Blockstudio blocks with default attribute content.
	 *
	 * @return array<int, array{title: string, name: string, content: string}>
	 */
	private function get_blocks_with_content(): array {
		$blocks = Build::blocks();
		$items  = array();

		foreach ( $blocks as $name => $block ) {
			$attributes = $block->blockstudio['attributes'] ?? array();
			$defaults   = array();

			foreach ( $attributes as $id => $attr ) {
				if ( ! isset( $attr['default'] ) ) {
					continue;
				}

				$defaults[ $id ] = $attr['default'];
			}

			$attrs_json = wp_json_encode(
				array(
					'blockstudio' => array(
						'attributes' => $defaults,
					),
				)
			);

			$content = '<!-- wp:' . $name . ' ' . $attrs_json . ' /-->';

			$items[] = array(
				'title'   => $block->title ?? $name,
				'name'    => $name,
				'content' => $content,
			);
		}

		usort(
			$items,
			function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		return $items;
	}

	/**
	 * Pre-render blocks from the blocks view items.
	 *
	 * @param array $block_items Block items from get_blocks_with_content().
	 * @return array<string, array{rendered: string, block: array}> Preloaded block data keyed by hash.
	 */
	private function preload_block_items( array $block_items ): array {
		$blockstudio_blocks = array();
		$blocks             = Build::blocks();
		$block_names        = array_keys( $blocks );
		$parser             = new WP_Block_Parser();

		foreach ( $block_items as $item ) {
			$parsed_blocks = $parser->parse( $item['content'] );

			foreach ( $parsed_blocks as $block ) {
				if ( ! in_array( $block['blockName'], $block_names, true ) ) {
					continue;
				}

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
					$_GET['blockstudioMode'] = 'editor';

					try {
						$rendered = render_block( $block );
					} catch ( \Throwable $e ) {
						$rendered = '';
					}

					$blockstudio_blocks[ $id ] = array(
						'rendered' => $rendered,
						'block'    => $block_obj,
					);
				}
			}
		}

		return $blockstudio_blocks;
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

	/**
	 * Register REST API endpoints for live mode.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		$permission = function () {
			return current_user_can( 'edit_posts' );
		};

		register_rest_route(
			'blockstudio/v1',
			'/canvas/poll',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'poll' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			'blockstudio/v1',
			'/canvas/refresh',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Lightweight endpoint returning a fingerprint of all watched files.
	 *
	 * @return WP_REST_Response
	 */
	public function poll(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'fingerprint' => $this->compute_fingerprint(),
			)
		);
	}

	/**
	 * Re-sync pages and return fresh page content with preloaded blocks.
	 *
	 * @return WP_REST_Response
	 */
	public function refresh(): WP_REST_Response {
		$page_paths = Pages::get_paths();
		$discovery  = new Page_Discovery();
		$sync       = new Page_Sync();

		foreach ( $page_paths as $path ) {
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$discovered = $discovery->discover( $path );

			foreach ( $discovered as $page_data ) {
				$sync->sync( $page_data );
			}
		}

		$pages              = $this->get_pages_with_content();
		$blocks             = $this->get_blocks_with_content();
		$blockstudio_blocks = $this->preload_all_blocks( $pages );
		$block_preloads     = $this->preload_block_items( $blocks );
		$blockstudio_blocks = array_merge( $blockstudio_blocks, $block_preloads );

		return new WP_REST_Response(
			array(
				'pages'             => $pages,
				'blocks'            => $blocks,
				'blockstudioBlocks' => $blockstudio_blocks,
			)
		);
	}

	/**
	 * Compute an MD5 fingerprint of all watched file modification times.
	 *
	 * @return string The fingerprint hash, or empty string if no files found.
	 */
	private function compute_fingerprint(): string {
		$mtimes = array();

		foreach ( Build::paths() as $path_info ) {
			$this->collect_file_mtimes( $path_info['path'], $mtimes );
		}

		foreach ( Pages::get_paths() as $path ) {
			$this->collect_file_mtimes( $path, $mtimes );
		}

		if ( empty( $mtimes ) ) {
			return '';
		}

		ksort( $mtimes );

		return md5( wp_json_encode( $mtimes ) );
	}

	/**
	 * Recursively collect file modification times from a directory.
	 *
	 * @param string                   $directory The directory to scan.
	 * @param array<string, int|false> $mtimes    Reference to the mtimes array.
	 * @return void
	 */
	private function collect_file_mtimes( string $directory, array &$mtimes ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$pathname = $file->getPathname();

			if ( str_contains( $pathname, '/_dist/' ) || str_contains( $pathname, '/node_modules/' ) ) {
				continue;
			}

			if ( ! in_array( $file->getExtension(), self::WATCHED_EXTENSIONS, true ) ) {
				continue;
			}

			$mtimes[ $pathname ] = $file->getMTime();
		}
	}
}

new Canvas();

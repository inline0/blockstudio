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

		Build::refresh_blocks();

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
			'window.blockstudio.blockstudioBlocks = (window.blockstudio.blockstudioBlocks || []).concat(' . wp_json_encode( $blockstudio_blocks ) . ');',
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

		$reset = Settings::get( 'assets/reset' );

		if ( true === $reset || Settings::get( 'assets/reset/enabled' ) ) {
			if ( isset( $editor_settings['__unstableResolvedAssets']['styles'] ) ) {
				$editor_settings['__unstableResolvedAssets']['styles'] = preg_replace(
					array(
						'/<link[^>]+(?:classic|content)\.css[^>]*>/',
						'/<link[^>]+id="wp-block-[^"]*-css"[^>]*>/',
						'/<style[^>]+id="global-styles-inline-css"[^>]*>.*?<\/style>/s',
					),
					'',
					$editor_settings['__unstableResolvedAssets']['styles']
				);
			}
		}

		wp_localize_script(
			'blockstudio-canvas',
			'blockstudioCanvas',
			array(
				'pages'     => $pages,
				'blocks'    => $blocks,
				'settings'  => $editor_settings,
				'restRoot'  => esc_url_raw( rest_url() ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Get Blockstudio-managed pages with their post content.
	 *
	 * @param array<string> $only_sources If non-empty, only return pages matching these source paths.
	 * @return array<int, array{title: string, slug: string, name: string, content: string}>
	 */
	private function get_pages_with_content( array $only_sources = array() ): array {
		$args = array(
			'post_type'      => 'any',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $only_sources ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_blockstudio_page_source',
					'value'   => $only_sources,
					'compare' => 'IN',
				),
			);
		} else {
			$args['meta_key'] = '_blockstudio_page_source'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}

		$posts = get_posts( $args );
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
	 * @param array         $block_items  Block items from get_blocks_with_content().
	 * @param array<string> $only_blocks  If non-empty, only render these block names.
	 * @return array<int, array{rendered: string, blockName: string}> Preloaded block data as ordered array.
	 */
	private function preload_block_items( array $block_items, array $only_blocks = array() ): array {
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

				if ( ! empty( $only_blocks ) && ! in_array( $block['blockName'], $only_blocks, true ) ) {
					continue;
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Setting mode for rendering.
				$_GET['blockstudioMode'] = 'editor';

				try {
					$rendered = render_block( $block );
				} catch ( \Throwable $e ) {
					$rendered = '';
				}

				$blockstudio_blocks[] = array(
					'rendered'  => $rendered,
					'blockName' => $block['blockName'],
				);
			}
		}

		return $blockstudio_blocks;
	}

	/**
	 * Pre-render all Blockstudio blocks across all pages.
	 *
	 * @param array         $pages        Pages with content from get_pages_with_content().
	 * @param array<string> $only_blocks  If non-empty, only render these block names.
	 * @return array<int, array{rendered: string, blockName: string}> Preloaded block data as ordered array.
	 */
	private function preload_all_blocks( array $pages, array $only_blocks = array() ): array {
		$blockstudio_blocks = array();
		$blocks             = Build::blocks();
		$block_names        = array_keys( $blocks );
		$parser             = new WP_Block_Parser();

		$block_renderer = function ( $block ) use (
			&$block_renderer,
			&$blockstudio_blocks,
			$block_names,
			$only_blocks
		) {
			if ( in_array( $block['blockName'], $block_names, true ) ) {
				if ( empty( $only_blocks ) || in_array( $block['blockName'], $only_blocks, true ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Setting mode for rendering.
					$_GET['blockstudioMode'] = 'editor';

					$blockstudio_blocks[] = array(
						'rendered'  => render_block( $block ),
						'blockName' => $block['blockName'],
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
			'/canvas/refresh',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => $permission,
				'args'                => array(
					'blocks' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'pages'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'blockstudio/v1',
			'/canvas/stream',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stream' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Re-sync pages and return fresh page content with preloaded blocks.
	 *
	 * Accepts an optional `blocks` query param (comma-separated block names)
	 * to only re-render specific blocks instead of everything.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function refresh( \WP_REST_Request $request ): WP_REST_Response {
		$query_params    = $request->get_query_params();
		$blocks_targeted = array_key_exists( 'blocks', $query_params );
		$blocks_param    = $blocks_targeted ? $request->get_param( 'blocks' ) : '';
		$only_blocks     = ! empty( $blocks_param ) ? explode( ',', $blocks_param ) : array();
		$pages_targeted  = array_key_exists( 'pages', $query_params );
		$pages_param     = $pages_targeted ? $request->get_param( 'pages' ) : '';
		$only_pages      = ! empty( $pages_param ) ? explode( ',', $pages_param ) : array();

		Build::refresh_blocks();

		if ( ! $pages_targeted || ! empty( $only_pages ) ) {
			$page_paths = Pages::get_paths();
			$discovery  = new Page_Discovery();
			$sync       = new Page_Sync();

			foreach ( $page_paths as $path ) {
				if ( ! is_dir( $path ) ) {
					continue;
				}

				$discovered = $discovery->discover( $path );

				foreach ( $discovered as $page_data ) {
					if ( empty( $only_pages ) || in_array( $page_data['source_path'], $only_pages, true ) ) {
						$sync->sync( $page_data );
					}
				}
			}
		}

		if ( $pages_targeted && empty( $only_pages ) ) {
			$response_pages = array();
		} elseif ( ! empty( $only_pages ) ) {
			$response_pages = $this->get_pages_with_content( $only_pages );
		} else {
			$response_pages = $this->get_pages_with_content();
		}

		if ( $blocks_targeted && empty( $only_blocks ) ) {
			return new WP_REST_Response(
				array(
					'pages'             => $response_pages,
					'blocks'            => array(),
					'blockstudioBlocks' => array(),
					'changedBlocks'     => array(),
				)
			);
		}

		$all_blocks = $this->get_blocks_with_content();

		if ( $blocks_targeted ) {
			$blocks = array_values(
				array_filter(
					$all_blocks,
					function ( $block ) use ( $only_blocks ) {
						return in_array( $block['name'], $only_blocks, true );
					}
				)
			);
		} else {
			$blocks = $all_blocks;
		}

		// Preloading needs all pages to find block usage across all content.
		$preload_pages      = $pages_targeted ? $this->get_pages_with_content() : $response_pages;
		$blockstudio_blocks = $this->preload_all_blocks( $preload_pages, $only_blocks );
		$block_preloads     = $this->preload_block_items( $all_blocks, $only_blocks );
		$blockstudio_blocks = array_merge( $blockstudio_blocks, $block_preloads );

		return new WP_REST_Response(
			array(
				'pages'             => $response_pages,
				'blocks'            => $blocks,
				'blockstudioBlocks' => $blockstudio_blocks,
				'changedBlocks'     => $only_blocks,
				'blocksNative'      => Build::blocks(),
			)
		);
	}

	/**
	 * SSE stream that pushes fingerprint changes to the client.
	 *
	 * Tracks file modification times to detect which specific blocks
	 * and pages changed, so the client can request a targeted refresh.
	 *
	 * @return void
	 */
	public function stream(): void {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );

		set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		ignore_user_abort( false );

		// Release session lock so subsequent requests are not blocked.
		if ( function_exists( 'session_write_close' ) && session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$prev_mtimes = array();
		$fingerprint = $this->compute_fingerprint_with_mtimes( $prev_mtimes );

		$dir_to_blocks = $this->build_dir_to_blocks_map();
		$dir_to_pages  = $this->build_dir_to_pages_map();

		echo "event: fingerprint\n";
		echo 'data: ' . wp_json_encode( array( 'fingerprint' => $fingerprint ) ) . "\n\n";
		flush();

		$interval = 1;

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( ! connection_aborted() ) {
			sleep( $interval );

			clearstatcache();

			$new_mtimes      = array();
			$new_fingerprint = $this->compute_fingerprint_with_mtimes( $new_mtimes );

			if ( $new_fingerprint !== $fingerprint ) {
				Build::refresh_blocks();

				$new_dir_to_blocks = $this->build_dir_to_blocks_map();
				$new_dir_to_pages  = $this->build_dir_to_pages_map();

				$changed_blocks = array_values( array_unique( $this->detect_changed_blocks( $prev_mtimes, $new_mtimes, $dir_to_blocks ) ) );
				$changed_pages  = $this->detect_changed_pages( $prev_mtimes, $new_mtimes, $dir_to_pages );

				foreach ( $new_dir_to_blocks as $dir => $name ) {
					if ( ! isset( $dir_to_blocks[ $dir ] ) ) {
						$changed_blocks[] = $name;
					}
				}
				$changed_blocks = array_values( array_unique( $changed_blocks ) );

				foreach ( $new_dir_to_pages as $dir => $source ) {
					if ( ! isset( $dir_to_pages[ $dir ] ) ) {
						$changed_pages[] = $source;
					}
				}
				$changed_pages = array_values( array_unique( $changed_pages ) );

				$fingerprint   = $new_fingerprint;
				$prev_mtimes   = $new_mtimes;
				$dir_to_blocks = $new_dir_to_blocks;
				$dir_to_pages  = $new_dir_to_pages;

				$refresh = $this->compute_refresh_data( $changed_blocks, $changed_pages );

				$data = array(
					'fingerprint'       => $fingerprint,
					'changedBlocks'     => $changed_blocks,
					'changedPages'      => $changed_pages,
					'pages'             => $refresh['pages'],
					'blocks'            => $refresh['blocks'],
					'blockstudioBlocks' => $refresh['blockstudioBlocks'],
					'blocksNative'      => $refresh['blocksNative'] ?? array(),
					'tailwindCss'       => $refresh['tailwindCss'] ?? '',
				);

				echo "event: changed\n";
				echo 'data: ' . wp_json_encode( $data ) . "\n\n";
				flush();
			} else {
				echo ": heartbeat\n\n";
				flush();
			}
		}

		exit;
	}

	/**
	 * Build a mapping from block directories to block names.
	 *
	 * @return array<string, string> Directory path => block name.
	 */
	private function build_dir_to_blocks_map(): array {
		$map = array();

		foreach ( Build::blocks() as $name => $block ) {
			if ( ! empty( $block->path ) ) {
				$map[ dirname( $block->path ) ] = $name;
			}
		}

		return $map;
	}

	/**
	 * Build a mapping from page directories to source paths.
	 *
	 * @return array<string, string> Directory path => page source path.
	 */
	private function build_dir_to_pages_map(): array {
		$map       = array();
		$discovery = new Page_Discovery();

		foreach ( Pages::get_paths() as $path ) {
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$discovered = $discovery->discover( $path );

			foreach ( $discovered as $page_data ) {
				if ( ! empty( $page_data['directory'] ) && isset( $page_data['source_path'] ) ) {
					$map[ $page_data['directory'] ] = $page_data['source_path'];
				}
			}
		}

		return $map;
	}

	/**
	 * Detect which block names were affected by file changes.
	 *
	 * @param array<string, int|false> $old_mtimes     Previous mtimes.
	 * @param array<string, int|false> $new_mtimes     Current mtimes.
	 * @param array<string, string>    $dir_to_blocks  Directory-to-block-name map.
	 * @return array<string> Affected block names.
	 */
	private function detect_changed_blocks( array $old_mtimes, array $new_mtimes, array $dir_to_blocks ): array {
		$changed_files = $this->diff_mtimes( $old_mtimes, $new_mtimes );
		$blocks        = array();

		foreach ( $changed_files as $file ) {
			foreach ( $dir_to_blocks as $dir => $name ) {
				if ( str_starts_with( $file, $dir . '/' ) ) {
					$blocks[] = $name;
					break;
				}
			}
		}

		return $blocks;
	}

	/**
	 * Detect which page source paths were affected by file changes.
	 *
	 * @param array<string, int|false> $old_mtimes     Previous mtimes.
	 * @param array<string, int|false> $new_mtimes     Current mtimes.
	 * @param array<string, string>    $dir_to_pages   Directory-to-source-path map.
	 * @return array<string> Affected page source paths.
	 */
	private function detect_changed_pages( array $old_mtimes, array $new_mtimes, array $dir_to_pages ): array {
		$changed_files = $this->diff_mtimes( $old_mtimes, $new_mtimes );
		$pages         = array();

		foreach ( $changed_files as $file ) {
			foreach ( $dir_to_pages as $dir => $source_path ) {
				if ( str_starts_with( $file, $dir . '/' ) ) {
					$pages[] = $source_path;
					break;
				}
			}
		}

		return array_values( array_unique( $pages ) );
	}

	/**
	 * Compute refresh data for changed blocks and pages.
	 *
	 * Used by the SSE stream to include refresh data inline,
	 * avoiding a separate HTTP request and its bootstrap overhead.
	 *
	 * @param array<string> $changed_blocks Block names that changed.
	 * @param array<string> $changed_pages  Page source paths that changed.
	 * @return array{pages: array, blocks: array, blockstudioBlocks: array, changedBlocks: array<string>, tailwindCss: string}
	 */
	private function compute_refresh_data( array $changed_blocks, array $changed_pages ): array {
		Build::refresh_blocks();

		if ( ! empty( $changed_pages ) ) {
			$page_paths = Pages::get_paths();
			$discovery  = new Page_Discovery();
			$sync       = new Page_Sync();

			foreach ( $page_paths as $path ) {
				if ( ! is_dir( $path ) ) {
					continue;
				}

				$discovered = $discovery->discover( $path );

				foreach ( $discovered as $page_data ) {
					if ( in_array( $page_data['source_path'], $changed_pages, true ) ) {
						$sync->sync( $page_data );
					}
				}
			}
		}

		$response_pages = ! empty( $changed_pages )
			? $this->get_pages_with_content( $changed_pages )
			: array();

		$all_block_types = Build::blocks();
		$blocks_native   = $all_block_types;

		if ( empty( $changed_blocks ) ) {
			$blockstudio_blocks = ! empty( $response_pages )
				? $this->preload_all_blocks( $response_pages )
				: array();

			return array(
				'pages'             => $response_pages,
				'blocks'            => array(),
				'blockstudioBlocks' => $blockstudio_blocks,
				'changedBlocks'     => array(),
				'blocksNative'      => $blocks_native,
				'tailwindCss'       => '',
			);
		}

		$all_blocks = $this->get_blocks_with_content();
		$blocks     = array_values(
			array_filter(
				$all_blocks,
				function ( $block ) use ( $changed_blocks ) {
					return in_array( $block['name'], $changed_blocks, true );
				}
			)
		);

		$preload_pages      = $this->get_pages_with_content();
		$blockstudio_blocks = $this->preload_all_blocks( $preload_pages, $changed_blocks );
		$block_preloads     = $this->preload_block_items( $all_blocks, $changed_blocks );
		$blockstudio_blocks = array_merge( $blockstudio_blocks, $block_preloads );

		return array(
			'pages'             => $response_pages,
			'blocks'            => $blocks,
			'blockstudioBlocks' => $blockstudio_blocks,
			'changedBlocks'     => $changed_blocks,
			'blocksNative'      => $blocks_native,
			'tailwindCss'       => Tailwind::compile_editor_css(),
		);
	}

	/**
	 * Get file paths that changed between two mtime snapshots.
	 *
	 * @param array<string, int|false> $old_mtimes Previous mtimes.
	 * @param array<string, int|false> $new_mtimes Current mtimes.
	 * @return array<string> Changed file paths.
	 */
	private function diff_mtimes( array $old_mtimes, array $new_mtimes ): array {
		$changed = array();

		foreach ( $new_mtimes as $file => $mtime ) {
			if ( ! isset( $old_mtimes[ $file ] ) || $old_mtimes[ $file ] !== $mtime ) {
				$changed[] = $file;
			}
		}

		foreach ( $old_mtimes as $file => $mtime ) {
			if ( ! isset( $new_mtimes[ $file ] ) ) {
				$changed[] = $file;
			}
		}

		return $changed;
	}

	/**
	 * Compute fingerprint and populate the mtimes array for diffing.
	 *
	 * @param array<string, int|false> $mtimes Reference to populate with file mtimes.
	 * @return string The fingerprint hash, or empty string if no files found.
	 */
	private function compute_fingerprint_with_mtimes( array &$mtimes ): string {
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

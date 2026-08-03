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
	 * Public inventory and document schema version.
	 */
	public const SCHEMA_VERSION = Canvas_Data::SCHEMA_VERSION;

	/**
	 * Return the public canvas inventory for all or selected content.
	 *
	 * @since 7.6.0
	 *
	 * @param array $selection Type-keyed IDs, names, paths, or source paths.
	 * @param array $options   Optional ordering and consumer metadata.
	 *
	 * @return array Canvas inventory DTO.
	 */
	public static function inventory( array $selection = array(), array $options = array() ): array {
		return Canvas_Data::inventory( $selection, $options );
	}

	/**
	 * Render public canvas documents for all or selected content.
	 *
	 * @since 7.6.0
	 *
	 * @param array $selection Type-keyed IDs, names, paths, or source paths.
	 * @param array $options   Inventory and document options.
	 *
	 * @return array Canvas document DTO.
	 */
	public static function documents( array $selection = array(), array $options = array() ): array {
		return Canvas_Data::documents( $selection, $options );
	}

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
		if ( ! Settings::get_bool( 'dev/canvas/enabled', false ) ) {
			return;
		}

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
			'',
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
			BLOCKSTUDIO_URL . 'includes/admin/assets/canvas/index.js',
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
				'pages'         => $pages,
				'blocks'        => $blocks,
				'settings'      => $editor_settings,
				'restRoot'      => esc_url_raw( rest_url() ),
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'canvasVersion' => $asset['version'] ?? BLOCKSTUDIO_VERSION,
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
	 * @param array<string>|null $only_blocks Optional canonical block names, null for all.
	 *
	 * @return array<int, array{title: string, name: string, content: string}>
	 */
	private function get_blocks_with_content( ?array $only_blocks = null ): array {
		$blocks = Build::blocks();
		$items  = array();

		foreach ( $blocks as $name => $block ) {
			if ( null !== $only_blocks && ! in_array( $name, $only_blocks, true ) ) {
				continue;
			}

			if ( Ui::is_bundled_block( $block ) ) {
				continue;
			}

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

				$rendered = $this->render_editor_block( $block );

				$blockstudio_blocks[] = array(
					'rendered'   => $rendered,
					'blockName'  => $block['blockName'],
					'attributes' => $block['attrs'],
					'mode'       => 'editor',
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
					$blockstudio_blocks[] = array(
						'rendered'   => $this->render_editor_block( $block ),
						'blockName'  => $block['blockName'],
						'attributes' => $block['attrs'],
						'mode'       => 'editor',
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
	 * Render one editor-mode block without leaking request state.
	 *
	 * @param array $block Parsed block.
	 *
	 * @return string Rendered HTML or an empty string on failure.
	 */
	private function render_editor_block( array $block ): string {
		$buffer_level = ob_get_level();
		$had_mode     = array_key_exists( 'blockstudioMode', $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque snapshot is restored, never interpreted.
		$mode = $had_mode ? wp_unslash( $_GET['blockstudioMode'] ) : null;

		$_GET['blockstudioMode'] = 'editor'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		try {
			$rendered = render_block( $block );

			return is_string( $rendered ) ? $rendered : '';
		} catch ( \Throwable ) {
			return '';
		} finally {
			if ( $had_mode ) {
				$_GET['blockstudioMode'] = $mode; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				unset( $_GET['blockstudioMode'] );
			}

			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
		}
	}

	/**
	 * Register REST API endpoints for live mode.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		if ( ! Settings::get( 'dev/canvas/enabled' ) ) {
			return;
		}

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
		if ( ! Settings::get( 'dev/canvas/enabled' ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Canvas is disabled.', 'blockstudio' ),
				),
				404
			);
		}

		$query_params     = $request->get_query_params();
		$has_blocks_param = array_key_exists( 'blocks', $query_params );
		$has_pages_param  = array_key_exists( 'pages', $query_params );
		$targeted         = $has_blocks_param || $has_pages_param;
		$blocks_targeted  = $targeted;
		$blocks_param     = $has_blocks_param ? $request->get_param( 'blocks' ) : '';
		$only_blocks      = ! empty( $blocks_param ) ? explode( ',', $blocks_param ) : array();
		$pages_targeted   = $targeted;
		$pages_param      = $has_pages_param ? $request->get_param( 'pages' ) : '';
		$only_pages       = ! empty( $pages_param ) ? explode( ',', $pages_param ) : array();

		if ( ! $blocks_targeted ) {
			Build::refresh_blocks();
		} elseif ( ! empty( $only_blocks ) ) {
			Build::refresh_blocks( false, $only_blocks );
		}

		if ( ! $pages_targeted || ! empty( $only_pages ) ) {
			Pages::reconcile(
				array(
					'authoritative' => false,
					'plan_valid'    => false,
				)
			);
		}

		if ( $pages_targeted && empty( $only_pages ) ) {
			$response_pages = array();
		} elseif ( ! empty( $only_pages ) ) {
			$response_pages = $this->get_pages_with_content( $only_pages );
		} else {
			$response_pages = $this->get_pages_with_content();
		}

		$blocks             = $this->get_blocks_with_content( $blocks_targeted ? $only_blocks : null );
		$preload_pages      = $response_pages;
		$blockstudio_blocks = $this->preload_all_blocks( $preload_pages, $only_blocks );
		$block_preloads     = $this->preload_block_items( $blocks, $only_blocks );
		$blockstudio_blocks = array_merge( $blockstudio_blocks, $block_preloads );
		$blocks_native      = $this->native_blocks_for_selection( $blocks_targeted ? $only_blocks : null );

		return new WP_REST_Response(
			array(
				'pages'             => $response_pages,
				'blocks'            => $blocks,
				'blockstudioBlocks' => $blockstudio_blocks,
				'changedBlocks'     => $only_blocks,
				'blocksNative'      => $blocks_native,
				'tailwindCss'       => $this->selected_tailwind_css(
					$blockstudio_blocks,
					$response_pages,
					$only_blocks
				),
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
		if ( ! Settings::get( 'dev/canvas/enabled' ) ) {
			status_header( 404 );
			nocache_headers();
			return;
		}

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
		Pages::discover();
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

			$new_mtimes          = array();
			$new_fingerprint     = $this->compute_fingerprint_with_mtimes( $new_mtimes );
			$fingerprint_changed = $new_fingerprint !== $fingerprint;
			if ( ! $fingerprint_changed ) {
				Build::refresh_blocks( true );
			}
			$new_blocks        = array();
			$new_pages         = array();
			$new_dir_to_blocks = $this->build_dir_to_blocks_map();
			$new_dir_to_pages  = $this->build_dir_to_pages_map();

			if ( $fingerprint_changed ) {
				$changed_files  = $this->diff_mtimes( $prev_mtimes, $new_mtimes );
				$unmapped_files = $this->unmapped_changed_files(
					$changed_files,
					array_merge( array_keys( $dir_to_blocks ), array_keys( $dir_to_pages ) )
				);
				$unmapped_files = array_values(
					array_unique(
						array_merge(
							$unmapped_files,
							array_keys( array_diff_key( $new_mtimes, $prev_mtimes ) )
						)
					)
				);
				$new_blocks     = Build::refresh_block_paths( $unmapped_files );
				$new_pages      = $this->refresh_page_topology( $unmapped_files );

				if ( array() !== $new_blocks ) {
					$new_dir_to_blocks = $this->build_dir_to_blocks_map();
				}

				if ( array() !== $new_pages ) {
					$new_dir_to_pages = $this->build_dir_to_pages_map();
				}
			}

			$blocks_map_changed = ! empty( array_diff_assoc( $new_dir_to_blocks, $dir_to_blocks ) )
				|| ! empty( array_diff_assoc( $dir_to_blocks, $new_dir_to_blocks ) );
			$pages_map_changed  = ! empty( array_diff_assoc( $new_dir_to_pages, $dir_to_pages ) )
				|| ! empty( array_diff_assoc( $dir_to_pages, $new_dir_to_pages ) );
			$dir_maps_changed   = $blocks_map_changed || $pages_map_changed;

			if ( $fingerprint_changed || $dir_maps_changed ) {
				// Directory map changes can lag behind mtime-based fingerprint updates
				// when files are written in quick batches. Flush once maps catch up.

				$changed_blocks = array_values( array_unique( $this->detect_changed_blocks( $prev_mtimes, $new_mtimes, $dir_to_blocks ) ) );
				$changed_pages  = $this->detect_changed_pages( $prev_mtimes, $new_mtimes, $dir_to_pages );
				$changed_blocks = array_merge( $changed_blocks, $new_blocks );
				$changed_pages  = array_merge( $changed_pages, $new_pages );

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

		foreach ( Build::data() as $name => $block ) {
			$paths = array_merge(
				array( $block['path'] ?? null, $block['renderTemplate'] ?? null ),
				$block['filesPaths'] ?? array()
			);

			foreach ( array_filter( $paths, static fn( mixed $path ): bool => is_string( $path ) && '' !== $path ) as $path ) {
				$map[ dirname( $path ) ] = $name;
			}
		}

		return $map;
	}

	/**
	 * Build a mapping from page directories to source paths.
	 *
	 * @return array<string, array<int, string>> Directory path => page source paths.
	 */
	private function build_dir_to_pages_map(): array {
		$map = array();

		foreach ( Pages::pages() as $page_data ) {
			if ( ! is_array( $page_data )
				|| ! is_string( $page_data['source_path'] ?? null )
				|| '' === $page_data['source_path']
			) {
				continue;
			}

			$paths = array_merge(
				array(
					$page_data['directory'] ?? null,
					$page_data['json_path'] ?? null,
					$page_data['template_path'] ?? null,
					$page_data['layout_path'] ?? null,
				),
				is_array( $page_data['source_mtime_paths'] ?? null )
					? $page_data['source_mtime_paths']
					: array()
			);

			foreach ( array_filter( $paths, static fn( mixed $path ): bool => is_string( $path ) && '' !== $path ) as $path ) {
				$directory         = is_dir( $path ) ? $path : dirname( $path );
				$map[ $directory ] = array_values(
					array_unique(
						array_merge( $map[ $directory ] ?? array(), array( $page_data['source_path'] ) )
					)
				);
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
	 * @param array<string, int|false>          $old_mtimes     Previous mtimes.
	 * @param array<string, int|false>          $new_mtimes     Current mtimes.
	 * @param array<string, array<int, string>> $dir_to_pages Directory-to-source-path map.
	 * @return array<string> Affected page source paths.
	 */
	private function detect_changed_pages( array $old_mtimes, array $new_mtimes, array $dir_to_pages ): array {
		$changed_files = $this->diff_mtimes( $old_mtimes, $new_mtimes );
		$pages         = array();

		foreach ( $changed_files as $file ) {
			foreach ( $dir_to_pages as $dir => $source_paths ) {
				if ( str_starts_with( $file, $dir . '/' ) ) {
					$pages = array_merge( $pages, $source_paths );
					break;
				}
			}
		}

		return array_values( array_unique( $pages ) );
	}

	/**
	 * Return changed files that are outside every known item directory.
	 *
	 * These paths may represent brand-new topology. Existing item edits remain
	 * on the cheaper canonical-name/source-path refresh path.
	 *
	 * @param array<string> $changed_files Changed physical files.
	 * @param array<string> $directories   Known block and page directories.
	 *
	 * @return array<int, string> Unmapped changed files.
	 */
	private function unmapped_changed_files( array $changed_files, array $directories ): array {
		$unmapped = array();

		foreach ( $changed_files as $file ) {
			$file    = wp_normalize_path( $file );
			$matched = false;

			foreach ( $directories as $directory ) {
				$directory = untrailingslashit( wp_normalize_path( $directory ) );

				if ( '' !== $directory && ( $file === $directory || str_starts_with( $file, $directory . '/' ) ) ) {
					$matched = true;
					break;
				}
			}

			if ( ! $matched ) {
				$unmapped[] = $file;
			}
		}

		return array_values( array_unique( $unmapped ) );
	}

	/**
	 * Discover new pages only inside changed, previously unmapped directories.
	 *
	 * Parent collection manifests/layouts are included as dependency closure,
	 * while sibling page directories remain outside the scoped source.
	 *
	 * @param array<string> $paths Changed physical source files.
	 *
	 * @return array<int, string> Newly discovered page source paths.
	 */
	private function refresh_page_topology( array $paths ): array {
		$paths = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $path ): string => is_string( $path )
							? wp_normalize_path( $path )
							: '',
						$paths
					),
					static fn( string $path ): bool => '' !== $path && is_file( $path )
				)
			)
		);

		if ( array() === $paths ) {
			return array();
		}

		$registry = Page_Registry::instance();
		$before   = array_fill_keys( array_keys( $registry->get_pages() ), true );
		$sources  = array();

		foreach ( Discovery_Sources::for_paths( 'pages', Pages::get_paths() ) as $source ) {
			$all_entries = $source->entries();
			$directories = array();

			foreach ( $all_entries as $entry ) {
				if ( ! in_array( wp_normalize_path( $entry->physical_path() ), $paths, true ) ) {
					continue;
				}

				$logical_directory = dirname( Discovery_Sources::normalize_logical_path( $entry->logical_path() ) );
				$directories[ '.' === $logical_directory ? '' : $logical_directory ] = true;
			}

			if ( array() === $directories ) {
				continue;
			}

			$entries = array();

			foreach ( $all_entries as $entry ) {
				$logical       = Discovery_Sources::normalize_logical_path( $entry->logical_path() );
				$entry_dir     = dirname( $logical );
				$entry_dir     = '.' === $entry_dir ? '' : $entry_dir;
				$basename      = basename( $logical );
				$is_dependency = in_array( $basename, array( 'pages.json', 'loader.php', 'layout.php' ), true );

				foreach ( array_keys( $directories ) as $directory ) {
					$inside_directory    = '' === $directory
						|| $logical === $directory
						|| str_starts_with( $logical, $directory . '/' );
					$ancestor_dependency = $is_dependency
						&& ( '' === $entry_dir
							|| $entry_dir === $directory
							|| str_starts_with( $directory, $entry_dir . '/' )
						);

					if ( $inside_directory || $ancestor_dependency ) {
						$entries[ $logical ] = $entry;
						break;
					}
				}
			}

			$selected_source = new Inventory_Discovery_Source(
				$source->id() . '#changed-topology:' . hash( 'sha256', implode( "\n", array_keys( $entries ) ) ),
				$source->root(),
				$entries,
				null,
				$paths
			);
			$discovery       = new Page_Discovery();
			$discovered      = $discovery->discover( $selected_source );

			foreach ( $discovery->get_collections() as $collection => $collection_data ) {
				$registry->register_collection( $collection, $collection_data );
			}

			$registry->add_errors( $discovery->get_errors() );

			foreach ( $discovered as $name => $page_data ) {
				$registry->register( $name, $page_data );

				if ( is_string( $page_data['source_path'] ?? null ) && '' !== $page_data['source_path'] ) {
					$sources[] = $page_data['source_path'];
				}
			}
		}

		$after = array_fill_keys( array_keys( $registry->get_pages() ), true );
		$added = array_keys( array_diff_key( $after, $before ) );

		/**
		 * Fires after scoped changed-path page topology discovery.
		 *
		 * @since 7.6.0
		 *
		 * @param array<int, string> $added   Newly registered page keys.
		 * @param array<int, string> $sources Newly discovered page sources.
		 * @param array<int, string> $paths   Changed physical paths.
		 */
		do_action( 'blockstudio/pages/topology_refreshed', $added, $sources, $paths );

		return array_values( array_unique( $sources ) );
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
		if ( ! empty( $changed_blocks ) ) {
			Build::refresh_blocks( false, $changed_blocks );
		}

		if ( ! empty( $changed_pages ) ) {
			Pages::reconcile(
				array(
					'authoritative' => false,
					'plan_valid'    => false,
				)
			);
		}

		$response_pages = ! empty( $changed_pages )
			? $this->get_pages_with_content( $changed_pages )
			: array();

		$blocks_native = $this->native_blocks_for_selection( $changed_blocks );

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
				'tailwindCss'       => $this->selected_tailwind_css(
					$blockstudio_blocks,
					$response_pages,
					array()
				),
			);
		}

		$blocks             = $this->get_blocks_with_content( $changed_blocks );
		$blockstudio_blocks = $this->preload_all_blocks( $response_pages, $changed_blocks );
		$block_preloads     = $this->preload_block_items( $blocks, $changed_blocks );
		$blockstudio_blocks = array_merge( $blockstudio_blocks, $block_preloads );

		return array(
			'pages'             => $response_pages,
			'blocks'            => $blocks,
			'blockstudioBlocks' => $blockstudio_blocks,
			'changedBlocks'     => $changed_blocks,
			'blocksNative'      => $blocks_native,
			'tailwindCss'       => $this->selected_tailwind_css(
				$blockstudio_blocks,
				$response_pages,
				$changed_blocks
			),
		);
	}

	/**
	 * Prepare only selected native block metadata for the client.
	 *
	 * @param array<string>|null $only_blocks Names, or null for all.
	 *
	 * @return array Client-safe blocks.
	 */
	private function native_blocks_for_selection( ?array $only_blocks ): array {
		$blocks = Build::blocks();

		if ( null !== $only_blocks ) {
			$blocks = array_intersect_key( $blocks, array_fill_keys( $only_blocks, true ) );
		}

		return Build::prepare_blocks_for_client( $blocks );
	}

	/**
	 * Compile Tailwind for the selected rendered payload only.
	 *
	 * @param array         $preloads    Rendered block preloads.
	 * @param array         $pages       Selected pages.
	 * @param array<string> $block_names Selected block names.
	 *
	 * @return string Raw Tailwind CSS.
	 */
	private function selected_tailwind_css( array $preloads, array $pages, array $block_names ): string {
		if ( ! Settings::get( 'tailwind/enabled' ) ) {
			return '';
		}

		$body = '';

		foreach ( $preloads as $preload ) {
			if ( is_string( $preload['rendered'] ?? null ) ) {
				$body .= $preload['rendered'];
			}

			if ( is_string( $preload['blockName'] ?? null )
				&& ! in_array( $preload['blockName'], $block_names, true )
			) {
				$block_names[] = $preload['blockName'];
			}
		}

		foreach ( $pages as $page ) {
			if ( is_string( $page['content'] ?? null ) ) {
				$body .= Render::content( $page['content'] );
			}
		}

		if ( '' === $body ) {
			return '';
		}

		try {
			$document = Render::document_from_html( $body, $block_names );
			$markup   = is_string( $document['assets']['tailwind'] ?? null )
				? $document['assets']['tailwind']
				: '';

			if ( preg_match( '#<style\b[^>]*>(.*?)</style>#is', $markup, $match ) ) {
				return $match[1];
			}
		} catch ( \Throwable ) {
			return '';
		}

		return '';
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

		$block_paths   = array_map(
			static fn( array $path_info ): string => (string) ( $path_info['path'] ?? '' ),
			Build::paths()
		);
		$block_paths[] = Build::get_build_dir();
		$block_paths   = array_values( array_unique( array_filter( $block_paths ) ) );

		$sources = array_merge(
			Discovery_Sources::for_paths( 'blocks', $block_paths ),
			Discovery_Sources::for_paths( 'pages', Pages::get_paths() )
		);

		foreach ( $sources as $source ) {
			$this->collect_source_mtimes( $source, $mtimes );
		}

		ksort( $mtimes );

		return md5(
			wp_json_encode(
				array(
					'context' => Runtime_Context::hash( 'canvas', array( 'blocks', 'pages' ) ),
					'mtimes'  => $mtimes,
				)
			)
		);
	}

	/**
	 * Collect modification times from a logical discovery source.
	 *
	 * @param Discovery_Source         $source Discovery source.
	 * @param array<string, int|false> $mtimes Reference to populate.
	 *
	 * @return void
	 */
	private function collect_source_mtimes( Discovery_Source $source, array &$mtimes ): void {
		foreach ( $source->entries() as $entry ) {
			$pathname = $entry->physical_path();

			if ( str_contains( $pathname, '/_dist/' ) || str_contains( $pathname, '/node_modules/' ) ) {
				continue;
			}

			if ( ! in_array( pathinfo( $pathname, PATHINFO_EXTENSION ), self::WATCHED_EXTENSIONS, true ) ) {
				continue;
			}

			$mtimes[ $pathname ] = @filemtime( $pathname ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove the file between listing and stat.
		}
	}
}

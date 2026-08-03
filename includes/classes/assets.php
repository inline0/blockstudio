<?php
/**
 * Assets class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioVendor\ScssPhp\ScssPhp\Compiler;
use BlockstudioVendor\MatthiasMullie\Minify;
use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassException;

/**
 * Handles asset processing, compilation, and rendering for Blockstudio blocks.
 *
 * This class manages the complete asset lifecycle:
 *
 * Asset Processing Pipeline:
 * 1. Discovery: Assets found in block directories during Build::init()
 * 2. Compilation: SCSS→CSS, minification, ES module bundling
 * 3. Caching: Compiled files stored in _dist/ with content-hash filenames
 * 4. Rendering: Assets injected into page head/footer based on block usage
 *
 * Output Buffering Strategy:
 * - Captures entire page output via ob_start() on template_redirect
 * - Scans for block comment markers (<!-- blockstudio/name -->)
 * - Only injects assets for blocks actually present on the page
 * - Moves inline styles/scripts to head/footer for proper loading order
 *
 * Asset Types Handled:
 * - CSS/SCSS: Compiled, optionally minified, scoped to block wrapper
 * - JavaScript: ES modules bundled, external imports resolved
 * - Inline assets: Injected directly into page (style/script tags)
 * - External assets: Enqueued via wp_enqueue_style/script
 *
 * Compilation Features:
 * - SCSS compilation via scssphp library
 * - CSS minification via MatthiasMullie\Minify
 * - JS minification via MatthiasMullie\Minify
 * - CSS scoping: *-scoped.css wrapped in block's unique class
 * - ES Module resolution: blockstudio/package@version → CDN URLs
 *
 * Key Methods:
 * - process(): Main entry point for asset compilation
 * - process_css(): SCSS compilation, scoping, minification
 * - process_js(): ES module resolution, minification
 * - parse_output(): Scans page output and injects relevant assets
 * - render_inline(): Outputs inline style/script tags
 * - render_tag(): Outputs link/script tags for external files
 *
 * Cache Invalidation:
 * - Compiled filenames include content hash or mtime
 * - Changes to source files or imports trigger recompilation
 * - Import mtimes tracked for SCSS @import invalidation
 *
 * @since 1.0.0
 */
class Assets {

	/**
	 * Shared runtime instance.
	 *
	 * @var Assets|null
	 */
	private static ?Assets $instance = null;

	/**
	 * Whether settings-driven hooks have registered.
	 *
	 * @var bool
	 */
	private bool $configured_hooks_registered = false;

	/**
	 * Selector placeholder for block CSS assets.
	 *
	 * @var string
	 */
	private const SELECTOR_PLACEHOLDER = '%selector%';

	/**
	 * Temporary selector used while scoped CSS is prefixed.
	 *
	 * @var string
	 */
	private const SELECTOR_PLACEHOLDER_CLASS = '__blockstudio-selector-placeholder__';

	/**
	 * Loaded modules.
	 *
	 * @var array
	 */
	private static array $modules = array();

	/**
	 * Asset IDs already rendered by parse_output().
	 *
	 * Static so deduplication persists across multiple ob_start callback flushes.
	 *
	 * @var array
	 */
	private static array $parsed_asset_ids = array();

	/**
	 * Request-local cache for compiled asset glob lookups.
	 *
	 * @var array
	 */
	private static array $matches_cache = array();

	/**
	 * When true, is_editor_screen() returns true regardless of current screen.
	 *
	 * @var bool
	 */
	public static bool $force_editor_screen = false;

	/**
	 * Get the shared asset runtime instance.
	 *
	 * @return Assets Runtime instance.
	 */
	public static function init(): Assets {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset request-local asset state for in-process batch rendering.
	 *
	 * @return void
	 */
	public static function reset_request_state(): void {
		self::$modules             = array();
		self::$parsed_asset_ids    = array();
		self::$force_editor_screen = false;
	}

	/**
	 * Whether the editor reset is enabled.
	 *
	 * @return bool
	 */
	private static function is_reset_enabled(): bool {
		$reset = Settings::get( 'assets/reset' );

		return true === $reset || (bool) Settings::get( 'assets/reset/enabled' );
	}

	/**
	 * Get utility layout reset styles for the block editor iframe.
	 *
	 * @return string CSS.
	 */
	private static function get_reset_utility_layout_styles(): string {
		$rules = array(
			'.editor-styles-wrapper .block{display:block}',
			'.editor-styles-wrapper .inline-block{display:inline-block}',
			'.editor-styles-wrapper .inline{display:inline}',
			'.editor-styles-wrapper .flex{display:flex}',
			'.editor-styles-wrapper .inline-flex{display:inline-flex}',
			'.editor-styles-wrapper .table{display:table}',
			'.editor-styles-wrapper .inline-table{display:inline-table}',
			'.editor-styles-wrapper .table-caption{display:table-caption}',
			'.editor-styles-wrapper .table-cell{display:table-cell}',
			'.editor-styles-wrapper .table-column{display:table-column}',
			'.editor-styles-wrapper .table-column-group{display:table-column-group}',
			'.editor-styles-wrapper .table-footer-group{display:table-footer-group}',
			'.editor-styles-wrapper .table-header-group{display:table-header-group}',
			'.editor-styles-wrapper .table-row-group{display:table-row-group}',
			'.editor-styles-wrapper .table-row{display:table-row}',
			'.editor-styles-wrapper .flow-root{display:flow-root}',
			'.editor-styles-wrapper .grid{display:grid}',
			'.editor-styles-wrapper .inline-grid{display:inline-grid}',
			'.editor-styles-wrapper .contents{display:contents}',
			'.editor-styles-wrapper .list-item{display:list-item}',
			'.editor-styles-wrapper .hidden{display:none}',
			'.editor-styles-wrapper .static{position:static}',
			'.editor-styles-wrapper .fixed{position:fixed}',
			'.editor-styles-wrapper .absolute{position:absolute}',
			'.editor-styles-wrapper .relative{position:relative}',
			'.editor-styles-wrapper .sticky{position:sticky}',
		);

		return implode( '', $rules );
	}

	/**
	 * Expose frontend body classes to the block editor canvas.
	 *
	 * @param array $settings The block editor settings.
	 *
	 * @return array The block editor settings.
	 */
	public function add_canvas_body_classes( $settings ) {
		if ( ! self::is_reset_enabled() ) {
			return $settings;
		}

		$classes = apply_filters( 'body_class', array(), '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook.
		$classes = is_array( $classes ) ? $classes : array();
		$classes = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_html_class', $classes )
				)
			)
		);

		$settings['blockstudioCanvasBodyClasses'] = apply_filters(
			'blockstudio/editor/canvas/body_class',
			$classes
		);

		return $settings;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_buffer_output' ), 3 );
		add_filter( 'blockstudio/buffer/output', array( $this, 'parse_output' ), 1000000 );
		add_filter(
			'block_editor_settings_all',
			function ( $settings ) {
				$output = self::get_cached_editor_assets_output();

				if ( '' === $output || ! isset( $settings['__unstableResolvedAssets'] ) ) {
					// Still check for interactivity even with no other assets.
					if ( isset( $settings['__unstableResolvedAssets'] ) ) {
						$interactivity_output = self::get_interactivity_editor_assets();
						if ( '' !== $interactivity_output ) {
							$settings['__unstableResolvedAssets']['scripts'] = $interactivity_output . $settings['__unstableResolvedAssets']['scripts'];
						}
					}
					return $settings;
				}

				preg_match_all( '/<script\b[^>]*>.*?<\/script>/is', $output, $script_matches );
				$scripts = implode( "\n", $script_matches[0] );
				$styles  = trim( preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $output ) );

				$interactivity_output = self::get_interactivity_editor_assets();
				$scripts              = $interactivity_output . $scripts;

				$settings['__unstableResolvedAssets']['styles']  .= $styles;
				$settings['__unstableResolvedAssets']['scripts'] .= $scripts;

				return $settings;
			},
			PHP_INT_MAX
		);
		add_action(
			'admin_footer',
			array( self::class, 'render_legacy_editor_assets_fallback' )
		);
		add_action(
			'customize_preview_init',
			function () {
				$this->get_assets( 'customizer' );
			}
		);
		add_action(
			'admin_init',
			function () {
				$this->get_admin_and_editor_assets();
			}
		);
	}

	/**
	 * Register hooks for configured editor and reset features only.
	 *
	 * @return void
	 */
	public function register_configured_hooks(): void {
		if ( $this->configured_hooks_registered ) {
			return;
		}

		$this->configured_hooks_registered = true;
		$reset                             = self::is_reset_enabled();
		$enhance                           = Settings::get_bool( 'blockEditor/enhance', false );
		$full_width                        = Settings::get_array( 'assets/reset/fullWidth' );

		if ( $reset ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_reset_styles' ), 999 );
			add_action( 'admin_enqueue_scripts', array( $this, 'maybe_reset_styles' ), 999 );
			add_action( 'enqueue_block_editor_assets', array( $this, 'maybe_reset_styles' ), 999 );
			add_filter( 'block_editor_settings_all', array( $this, 'add_canvas_body_classes' ) );
		}

		if ( $enhance ) {
			add_action( 'admin_head', array( $this, 'render_parent_editor_enhancement_styles' ) );
			add_filter( 'admin_body_class', array( $this, 'add_parent_editor_enhancement_body_class' ) );
		}

		if ( $reset || $enhance ) {
			add_filter( 'block_editor_settings_all', array( $this, 'maybe_reset_editor_styles' ) );
		}

		if ( array() !== $full_width ) {
			add_filter( 'block_editor_settings_all', array( $this, 'maybe_fullwidth_editor' ), 10, 2 );
		}
	}

	/**
	 * Maybe buffer output.
	 *
	 * @return void
	 */
	public function maybe_buffer_output(): void {
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		/**
		 * Filters whether Blockstudio buffers the frontend response.
		 *
		 * Buffering exists so block style and script tags rendered inside the
		 * body can be hoisted into the head and footer, which means holding
		 * and scanning the whole document on every frontend request. A site
		 * that renders no Blockstudio block assets can return false and skip
		 * both.
		 *
		 * @since 7.6.0
		 *
		 * @param bool $enabled Whether to buffer the response.
		 */
		if ( ! apply_filters( 'blockstudio/buffer/enabled', true ) ) {
			return;
		}

		ob_start( array( $this, 'return_buffer' ) );
	}

	/**
	 * Remove all WordPress core block styles when reset is enabled.
	 *
	 * @return void
	 */
	public function maybe_reset_styles(): void {
		if ( ! self::is_reset_enabled() ) {
			return;
		}

		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wp-reset-editor-styles' );
		wp_dequeue_style( 'global-styles' );

		$styles = wp_styles();

		foreach ( $styles->registered as $handle => $style ) {
			if ( str_starts_with( $handle, 'wp-block-' ) ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Get parent editor enhancement styles.
	 *
	 * These styles run in the parent editor document. The iframe receives the full
	 * enhancement stylesheet through block_editor_settings_all.
	 *
	 * @return string Parent document enhancement styles.
	 */
	public static function get_parent_editor_enhancement_styles(): string {
		return 'html.blockstudio-editor-enhance-locked{overflow:hidden!important}body.blockstudio-editor-enhance-locked{position:fixed!important;top:0!important;bottom:0!important;left:0!important;right:var(--blockstudio-editor-enhance-scrollbar-width,0px)!important;width:auto!important;overflow:hidden!important}';
	}

	/**
	 * Render parent editor enhancement styles.
	 *
	 * @return void
	 */
	public function render_parent_editor_enhancement_styles(): void {
		if ( ! Settings::get( 'blockEditor/enhance' ) || ! self::is_editor_screen() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static stylesheet.
		echo '<style id="blockstudio-editor-enhance-parent">' . self::get_parent_editor_enhancement_styles() . '</style>';
	}

	/**
	 * Lock the parent editor body as soon as WordPress renders it.
	 *
	 * The editor client removes the class after preloaded blocks have rendered.
	 *
	 * @param string $classes Admin body classes.
	 *
	 * @return string Modified admin body classes.
	 */
	public function add_parent_editor_enhancement_body_class( string $classes ): string {
		if ( ! Settings::get( 'blockEditor/enhance' ) || ! self::is_editor_screen() ) {
			return $classes;
		}

		if ( str_contains( $classes, 'blockstudio-editor-enhance-locked' ) ) {
			return $classes;
		}

		return trim( $classes . ' blockstudio-editor-enhance-locked' );
	}

	/**
	 * Remove WordPress reset styles from the block editor iframe and optionally add editor enhancements.
	 *
	 * @param array $settings Editor settings.
	 *
	 * @return array Modified editor settings.
	 */
	public function maybe_reset_editor_styles( array $settings ): array {
		if ( ! isset( $settings['__unstableResolvedAssets']['styles'] ) ) {
			return $settings;
		}

		if ( self::is_reset_enabled() ) {
			$settings['__unstableResolvedAssets']['styles'] = preg_replace(
				array(
					'/<link\b[^>]+(?:content|common|reset|classic)(?:\.min)?\.css(?:\?[^"\']*)?[^>]*>/i',
					'/<link\b[^>]+\/wp-includes\/css\/dist\/block-library\/(?:style|editor)(?:\.min)?\.css(?:\?[^"\']*)?[^>]*>/i',
					'/<link\b[^>]+\/wp-includes\/css\/classic-themes(?:\.min)?\.css(?:\?[^"\']*)?[^>]*>/i',
				),
				'',
				$settings['__unstableResolvedAssets']['styles']
			);

			if ( ! str_contains( $settings['__unstableResolvedAssets']['styles'], 'blockstudio-reset-utility-layout' ) ) {
				$settings['__unstableResolvedAssets']['styles'] .= '<style id="blockstudio-reset-utility-layout">'
					. self::get_reset_utility_layout_styles()
					. '</style>';
			}
		}

		if ( Settings::get( 'blockEditor/enhance' ) && ! str_contains( $settings['__unstableResolvedAssets']['styles'], 'blockstudio-editor-enhance' ) ) {
			$settings['__unstableResolvedAssets']['styles'] .= '<style id="blockstudio-editor-enhance">html.blockstudio-editor-enhance-locked{overflow:hidden!important}body.blockstudio-editor-enhance-locked{position:fixed!important;top:0!important;bottom:0!important;left:0!important;right:var(--blockstudio-editor-enhance-scrollbar-width,0px)!important;width:auto!important;overflow:hidden!important}.editor-styles-wrapper .blockstudio-block{transition:opacity .25s ease}.editor-styles-wrapper.blockstudio-editor-enhance-pending:not(.blockstudio-editor-enhance-ready) .blockstudio-block{visibility:hidden;opacity:0;pointer-events:none}html.blockstudio-editor-enhance-pending:not(.blockstudio-editor-enhance-ready)::before{content:"";position:fixed;top:50%;left:calc(50% - (var(--blockstudio-editor-enhance-scrollbar-width,0px) / 2));width:24px;height:24px;margin:-12px 0 0 -12px;border:2px solid rgb(142 142 142 / .35);border-top-color:#7c3aed;border-radius:9999px;opacity:1;transition:opacity .25s ease;animation:blockstudio-editor-enhance-spin .8s linear infinite;pointer-events:none;z-index:9999}html.blockstudio-editor-enhance-pending.blockstudio-editor-enhance-ready::before{opacity:0;visibility:hidden}@keyframes blockstudio-editor-enhance-spin{to{transform:rotate(360deg)}}.editor-styles-wrapper :where(.wp-block,.blockstudio-block,.block-editor-rich-text__editable):focus,.editor-styles-wrapper :where(.wp-block,.blockstudio-block,.block-editor-rich-text__editable):focus-visible{outline:none!important;box-shadow:none}.editor-styles-wrapper :where(.wp-block,.blockstudio-block){position:relative}.editor-styles-wrapper :where(.wp-block,.blockstudio-block).is-hovered:not(.has-child-selected)::after,.editor-styles-wrapper :where(.wp-block,.blockstudio-block).is-highlighted:not(.has-child-selected)::after,.editor-styles-wrapper :where(.wp-block,.blockstudio-block).is-selected::after{content:"";position:absolute;inset:0;border:1px solid rgb(142 142 142 / .65);border-radius:inherit;pointer-events:none;z-index:1}.editor-styles-wrapper :where(.wp-block,.blockstudio-block).is-selected::after{border-color:#7c3aed}</style>';
		}

		return $settings;
	}

	/**
	 * Remove classic editor layout styles for full-width post types.
	 *
	 * @param array                    $settings Editor settings.
	 * @param \WP_Block_Editor_Context $context  Block editor context.
	 *
	 * @return array Modified editor settings.
	 */
	public function maybe_fullwidth_editor( array $settings, $context ): array {
		$post_types = Settings::get( 'assets/reset/fullWidth' );

		if ( empty( $post_types ) || ! is_array( $post_types ) ) {
			return $settings;
		}

		$post_type = $context->post->post_type ?? '';

		if ( ! in_array( $post_type, $post_types, true ) ) {
			return $settings;
		}

		$settings['__unstableResolvedAssets']['styles'] = preg_replace(
			'/<link[^>]+classic\.css[^>]*>/',
			'',
			$settings['__unstableResolvedAssets']['styles'] ?? ''
		);

		if ( ! str_contains( $settings['__unstableResolvedAssets']['styles'], 'blockstudio-fullwidth-editor' ) ) {
			$settings['__unstableResolvedAssets']['styles'] .= '<style id="blockstudio-fullwidth-editor">.editor-styles-wrapper :where(.blockstudio-block):not([class*="max-w-"]){max-width:none}.editor-styles-wrapper :where(.blockstudio-block){margin-block:0}.editor-styles-wrapper :where(.blockstudio-block):not(:where(.mx-auto,.ml-auto,.mr-auto)){margin-left:0!important;margin-right:0!important}.editor-styles-wrapper :where(.blockstudio-block.block-editor-block-list__layout){display:revert}.editor-styles-wrapper .block-editor-block-list__layout.is-root-container,.editor-styles-wrapper .is-root-container,.editor-styles-wrapper .wp-block-post-content{max-width:none}.editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper{max-width:none;margin-left:0;margin-right:0}</style>';
		}

		return $settings;
	}

	/**
	 * Parse output and return assets.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string The processed HTML.
	 */
	public function parse_output( $html ): string {
		Perf::start( 'assets' );

		$blocks         = Build::data();
		$blocks_native  = Build::blocks();
		$ids            = array();
		$blocks_on_page = array();
		$asset_ids      = &self::$parsed_asset_ids;

		$style_pattern  = '/<style[^>]+data-blockstudio-asset[^>]*>(.*?)<\/style>/is';
		$script_pattern = '/<script[^>]+data-blockstudio-asset[^>]*>(.*?)<\/script>/is';

		preg_match_all( $style_pattern, $html, $style_matches );
		$head = implode( '', $style_matches[0] );
		$html = preg_replace( $style_pattern, '', $html );

		preg_match_all( $script_pattern, $html, $script_matches );
		$footer = implode( '', $script_matches[0] );
		$html   = preg_replace( $script_pattern, '', $html );

		foreach ( $blocks as $block ) {
			$id    = Block::comment( $block['name'] );
			$ids[] = $id;

			if ( false !== stripos( $html, $id ) ) {
				$blocks_on_page[ $block['name'] ] = $blocks_native[ $block['name'] ];
			}

			if ( ! isset( $block['assets'] ) ) {
				continue;
			}

			$has_global = array_reduce(
				array_keys( $block['assets'] ),
				function ( $carry, $key ) {
					return $carry || 0 === strpos( $key, 'global' );
				},
				false
			);

			if ( false === strpos( $html, $id ) && ! $has_global ) {
				continue;
			}

			self::get_module_css_assets( $block, $asset_ids, $head );

			foreach ( $block['assets'] as $k => $v ) {
				$is_admin        = str_starts_with( $k, 'admin' );
				$is_block_editor = str_starts_with( $k, 'block-editor' );

				if ( $is_admin || $is_block_editor ) {
					continue;
				}

				$is_global = str_starts_with( $k, 'global' );
				if ( false === strpos( $html, $id ) && ! $is_global ) {
					continue;
				}

				$asset_id = $v['path'];
				if ( in_array( $asset_id, $asset_ids, true ) ) {
					continue;
				}
				$asset_ids[] = $asset_id;

				if ( $v['editor'] ) {
					continue;
				}

				if ( 'inline' !== $v['type'] ) {
					if ( self::is_css( $k ) ) {
						$head .= self::render_tag( $k, $v, $block );
					} else {
						$footer .= self::render_tag( $k, $v, $block );
					}
				} elseif ( self::is_css( $k ) ) {
						$head .= self::render_inline( $k, $v, $block, true );
				} else {
					$footer .= self::render_inline( $k, $v, $block, true );
				}
			}
		}

		// Inject importmap for blocks using the Interactivity API.
		$interactivity_importmap = self::get_interactivity_importmap( $blocks_on_page, $html );
		if ( '' !== $interactivity_importmap ) {
			$head = $interactivity_importmap . $head;
		}

		/*
		 * Bundled UI blocks read shared helpers from window.__bsui, and their
		 * own inline scripts assume it exists. Only the canvas document
		 * renderer emitted those globals, so a bundled component rendered on
		 * an ordinary page threw on the first interaction: a dialog could not
		 * lock scroll, so it never opened.
		 */
		if ( method_exists( Ui::class, 'global_assets' ) ) {
			$ui_globals = Ui::global_assets( array_keys( $blocks_on_page ), $html );

			if ( is_string( $ui_globals['style'] ?? null ) && '' !== $ui_globals['style'] ) {
				$head .= $ui_globals['style'];
			}

			if ( is_string( $ui_globals['script'] ?? null ) && '' !== $ui_globals['script'] ) {
				$footer = $ui_globals['script'] . $footer;
			}
		}

		$head   = apply_filters( 'blockstudio/render/head', $head, $blocks_on_page );
		$footer = apply_filters( 'blockstudio/render/footer', $footer, $blocks_on_page );

		$output = strtr(
			str_replace( $ids, '', $html ),
			array(
				'</body>' => $footer . '</body>',
				'</head>' => $head . '</head>',
				'</BODY>' => $footer . '</BODY>',
				'</HEAD>' => $head . '</HEAD>',
			)
		);

		Perf::stop( 'assets', 'Assets' );

		return apply_filters( 'blockstudio/render', $output, $blocks_on_page );
	}

	/**
	 * Get the importmap for blocks using the Interactivity API.
	 *
	 * Checks if any block on the page has interactivity enabled and returns
	 * an importmap script tag that maps the @wordpress/interactivity bare
	 * specifier to the URL from WordPress's rendered module script tag.
	 *
	 * @param array  $blocks_on_page The blocks present on the current page.
	 * @param string $html           The page HTML to extract the module URL from.
	 *
	 * @return string The importmap script tag, or empty string if not needed.
	 */
	public static function get_interactivity_importmap( array $blocks_on_page, string $html ): string {
		$needs_interactivity = false;

		foreach ( $blocks_on_page as $block ) {
			if ( Build::has_interactivity( $block->blockstudio ?? array() ) ) {
				$needs_interactivity = true;
				break;
			}
		}

		if ( ! $needs_interactivity ) {
			return '';
		}

		// Extract the interactivity module URL from WordPress's rendered script tag.
		if ( preg_match( '/id=["\']@wordpress\/interactivity-js-module["\'][^>]*src=["\']([^"\']+)["\']/', $html, $matches ) ) {
			$src = $matches[1];
		} elseif ( preg_match( '/src=["\']([^"\']+)["\'][^>]*id=["\']@wordpress\/interactivity-js-module["\']/', $html, $matches ) ) {
			$src = $matches[1];
		} else {
			return '';
		}

		$importmap = array(
			'imports' => array(
				'@wordpress/interactivity' => $src,
			),
		);

		return '<script type="importmap">' . wp_json_encode( $importmap ) . '</script>';
	}

	/**
	 * Get Interactivity API assets for the editor iframe.
	 *
	 * Enqueues the Interactivity API script module and returns the module script
	 * tag plus an importmap for injection into the editor iframe.
	 *
	 * @return string The interactivity API script tags, or empty string if not needed.
	 */
	public static function get_interactivity_editor_assets(): string {
		$blocks              = Build::blocks();
		$needs_interactivity = false;

		foreach ( $blocks as $block ) {
			if ( Build::has_interactivity( $block->blockstudio ?? array() ) ) {
				$needs_interactivity = true;
				break;
			}
		}

		if ( ! $needs_interactivity ) {
			return '';
		}

		wp_enqueue_script_module( '@wordpress/interactivity' );

		ob_start();
		wp_script_modules()->print_enqueued_script_modules();
		$module_output = ob_get_clean();

		// Build an importmap even if WordPress printed the module earlier.
		$importmap = '';
		$src       = self::get_script_module_src( '@wordpress/interactivity', $module_output );

		if ( '' !== $src ) {
			$importmap = '<script type="importmap">' . wp_json_encode(
				array(
					'imports' => array(
						'@wordpress/interactivity' => $src,
					),
				)
			) . '</script>';
		}

		// In the editor, blocks render asynchronously via ServerSideRender.
		// The Interactivity API runs init() at module load, before those
		// elements exist. This observer re-hydrates interactive islands that
		// appear later. It also injects server state (from data-wp-server-state)
		// and fixes React's <template> handling before hydrating.
		$reinit = '<script type="module">'
			. 'import{privateApis,store}from"@wordpress/interactivity";'
			. 'const a=privateApis("I acknowledge that using private APIs means my theme or plugin will inevitably break in the next version of WordPress.");'
			. 'const{toVdom:v,getRegionRootFragment:f,render:r}=a;'
			. 'function fixTpl(root){root.querySelectorAll("template").forEach(function(t){'
			. 'if(!t.content.childNodes.length&&t.childNodes.length){'
			. 'while(t.firstChild)t.content.appendChild(t.firstChild)'
			. '}})}'
			. 'const p=()=>{document.querySelectorAll("[data-wp-interactive]:not([data-wp-processed])").forEach(n=>{'
			. 'if(n.parentElement&&n.parentElement.closest("[data-wp-interactive]"))return;'
			. 'var ss=n.dataset.wpServerState;'
			. 'if(ss){var ns=n.dataset.wpInteractive;store(ns,{state:JSON.parse(ss)})}'
			. 'fixTpl(n);'
			. 'n.dataset.wpProcessed="1";r(v(n),f(n))})};'
			. 'const o=new MutationObserver(()=>p());'
			. 'if(document.body){o.observe(document.body,{childList:true,subtree:true});p()}'
			. 'else{document.addEventListener("DOMContentLoaded",()=>{'
			. 'o.observe(document.body,{childList:true,subtree:true});p()})}'
			. '</script>';

		// Inject bs.db / bs.fn / bs.mutate client scripts so interactive
		// blocks can make data calls from inside the editor iframe.
		$data_clients = '';
		if ( Database::has_schemas() ) {
			$data_clients .= '<script>' . Database::client_script() . '</script>';
		}
		if ( Rpc::has_any() ) {
			$data_clients .= '<script>' . Rpc::client_script() . '</script>';
		}

		return $importmap . $module_output . $data_clients . $reinit;
	}

	/**
	 * Resolve a registered script module URL.
	 *
	 * WordPress does not print modules that another asset collection already
	 * marked as done. Resolve the registered source in that case so isolated
	 * render documents remain dependency-closed.
	 *
	 * @param string $id            Script module identifier.
	 * @param string $module_output Rendered script module markup.
	 *
	 * @return string Versioned and filtered module URL, or an empty string.
	 */
	private static function get_script_module_src( string $id, string $module_output ): string {
		$module_pattern = '/<script\b'
			. '(?=[^>]*id=["\']' . preg_quote( $id, '/' ) . '[^"\']*["\'])'
			. '(?=[^>]*src=["\']([^"\']+)["\'])'
			. '[^>]*>/i';

		if ( preg_match( $module_pattern, $module_output, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		$script_modules = wp_script_modules();

		if ( method_exists( $script_modules, 'get_registered' ) ) {
			$registered = $script_modules->get_registered( $id );

			if ( is_array( $registered ) && isset( $registered['src'] ) && is_string( $registered['src'] ) ) {
				return self::version_script_module_src(
					$id,
					$registered['src'],
					$registered['version'] ?? false
				);
			}
		}

		if ( '@wordpress/interactivity' !== $id ) {
			return '';
		}

		$assets_file = ABSPATH . WPINC . '/assets/script-modules-packages.php';
		if ( ! is_file( $assets_file ) ) {
			return '';
		}

		$assets = include $assets_file;
		$file   = 'interactivity/index.js';
		if ( ! is_array( $assets ) || ! isset( $assets[ $file ] ) || ! is_array( $assets[ $file ] ) ) {
			return '';
		}

		$suffix = defined( 'WP_RUN_CORE_TESTS' ) ? '.min' : wp_scripts_get_suffix();
		if ( '' !== $suffix ) {
			$file = str_replace( '.js', $suffix . '.js', $file );
		}

		return self::version_script_module_src(
			$id,
			includes_url( 'js/dist/script-modules/' . $file ),
			$assets['interactivity/index.js']['version'] ?? false
		);
	}

	/**
	 * Apply WordPress script-module versioning and source filtering.
	 *
	 * @param string            $id      Script module identifier.
	 * @param string            $src     Unversioned module URL.
	 * @param string|false|null $version Registered version, false for WordPress version.
	 *
	 * @return string Versioned and filtered module URL.
	 */
	private static function version_script_module_src( string $id, string $src, $version ): string {
		if ( '' !== $src ) {
			if ( false === $version ) {
				$src = add_query_arg( 'ver', get_bloginfo( 'version' ), $src );
			} elseif ( null !== $version ) {
				$src = add_query_arg( 'ver', $version, $src );
			}
		}

		$src = apply_filters( 'script_module_loader_src', $src, $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook.

		return is_string( $src ) ? $src : '';
	}

	/**
	 * Get admin and editor assets.
	 *
	 * @return void
	 */
	public static function get_admin_and_editor_assets(): void {
		$admin_assets = Build::assets_admin();

		foreach ( $admin_assets as $asset ) {
			add_action(
				'admin_enqueue_scripts',
				function () use ( $asset ) {
					$path = self::get_path( $asset['path'] );
					$url  = Files::get_relative_url( $path );

					if ( self::is_css( $url ) ) {
						wp_enqueue_style(
							self::get_id(
								'admin',
								array( 'name' => Block::id( $asset, $asset ) )
							),
							$url,
							array(),
							$asset['key']
						);
					} else {
						wp_enqueue_script(
							self::get_id(
								'admin',
								array( 'name' => Block::id( $asset, $asset ) )
							),
							$url,
							array(),
							$asset['key'],
							true
						);
					}
				}
			);
		}

		$block_editor_assets = Build::assets_block_editor();

		foreach ( $block_editor_assets as $asset ) {
			add_action(
				'enqueue_block_editor_assets',
				function () use ( $asset ) {
					$path = self::get_path( $asset['path'] );
					$url  = Files::get_relative_url( $path );

					if ( self::is_css( $url ) ) {
						wp_enqueue_style(
							self::get_id(
								'block-editor',
								array( 'name' => Block::id( $asset, $asset ) )
							),
							$url,
							array(),
							$asset['key']
						);
					} else {
						wp_enqueue_script(
							self::get_id(
								'block-editor',
								array( 'name' => Block::id( $asset, $asset ) )
							),
							$url,
							array(),
							$asset['key'],
							true
						);
					}
				}
			);
		}
	}

	/**
	 * Get imported modification times.
	 *
	 * @param string $path         The file path.
	 * @param string $scoped_class The scoped class name.
	 *
	 * @return string The modification time hash.
	 */
	public static function get_imported_modification_times( $path, $scoped_class ): string {
		return self::get_asset_version( $path, $scoped_class );
	}

	/**
	 * Get a version string for an asset and its compile-time dependencies.
	 *
	 * @param string   $path         The file path.
	 * @param string   $scoped_class The scoped class name.
	 * @param int|null $source_mtime The already-read source file mtime.
	 *
	 * @return string The asset version string.
	 */
	public static function get_asset_version( string $path, string $scoped_class = '', ?int $source_mtime = null ): string {
		$mtimes       = array( (string) ( $source_mtime ?? filemtime( $path ) ) );
		$is_js        = str_ends_with( $path, '.js' );
		$process_scss = self::should_process_scss( $path );

		if ( '' !== $scoped_class ) {
			$mtimes[] = $scoped_class;
		}

		if ( $is_js ) {
			$mtimes[] = 'minify-js:' . ( Settings::get( 'assets/minify/js' ) ? '1' : '0' );
		} else {
			$mtimes[] = 'minify-css:' . ( Settings::get( 'assets/minify/css' ) ? '1' : '0' );
			$mtimes[] = 'process-scss:' . ( $process_scss ? '1' : '0' );
		}

		if ( $is_js || ! $process_scss ) {
			return md5( wp_json_encode( $mtimes ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$content = file_get_contents( $path );
		$prelude = self::get_scss_prelude( $path, $content );

		$import_paths = self::get_scss_import_paths();

		if ( ! empty( $import_paths ) ) {
			$mtimes[] = md5( wp_json_encode( $import_paths ) );
		}

		if ( '' !== $prelude ) {
			$mtimes[] = md5( $prelude );
		}

		foreach ( $import_paths as $import_path ) {
			if ( file_exists( $import_path ) ) {
				$mtimes[] = filemtime( $import_path );
			}
		}

		$dependencies = self::get_scss_dependencies(
			trim( $prelude . "\n" . $content ),
			$path
		);

		foreach ( $dependencies as $dependency ) {
			$mtimes[] = filemtime( $dependency );
		}

		if ( 1 === count( $mtimes ) ) {
			return $mtimes[0];
		}

		return md5( implode( '-', $mtimes ) );
	}

	/**
	 * Get local files that can affect compiled SCSS output.
	 *
	 * @param string $path The file path.
	 *
	 * @return array Dependency file paths.
	 */
	public static function get_asset_dependency_paths( string $path ): array {
		if ( str_ends_with( $path, '.js' ) || ! self::should_process_scss( $path ) || ! is_file( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local SCSS file for cache dependency detection.
		$content = file_get_contents( $path );

		if ( ! is_string( $content ) ) {
			return array();
		}

		$prelude = self::get_scss_prelude( $path, $content );

		return array_map(
			'wp_normalize_path',
			self::get_scss_dependencies(
				trim( $prelude . "\n" . $content ),
				$path
			)
		);
	}

	/**
	 * Get a compiled asset file name.
	 *
	 * @param string $path         The file path.
	 * @param string $scoped_class The scoped class name.
	 *
	 * @return string The compiled filename.
	 */
	public static function get_compiled_filename( $path, string $scoped_class = '' ): string {
		$file = pathinfo( $path );
		$dir  = self::get_dist_folder( $path );
		$file = $file['filename'];

		$ext               = pathinfo( $path, PATHINFO_EXTENSION );
		$is_scoped         = str_ends_with( $file, '.scoped' ) || str_ends_with( $file, '-scoped' );
		$uses_scoped_class = $is_scoped || ( self::is_css_extension( $ext ) && self::has_selector_placeholder( $path ) );
		$id                = self::get_imported_modification_times(
			$path,
			$uses_scoped_class ? $scoped_class : ''
		);

		if ( Settings::get( 'assets/process/scssFiles' ) && 'scss' === $ext ) {
			$ext = 'css';
		}

		return $dir . '/' . $file . '-' . $id . '.' . $ext;
	}

	/**
	 * Resolve the generated distribution directory for a source path.
	 *
	 * @param string $source_path Asset source file or directory.
	 *
	 * @return string Distribution directory.
	 */
	public static function get_dist_folder( string $source_path ): string {
		$source_path = wp_normalize_path( $source_path );
		$directory   = is_dir( $source_path ) ? $source_path : dirname( $source_path );
		$suggested   = rtrim( $directory, '/' ) . '/_dist';

		return Runtime_Context::output_path(
			$suggested,
			$source_path,
			'assets',
			array( 'type' => 'directory' )
		);
	}

	/**
	 * Get all matches for a compiled asset name.
	 *
	 * @param string $path The file path.
	 *
	 * @return array Array of matching files.
	 */
	public static function get_matches( $path ): array {
		$cache_key = wp_normalize_path( $path ) . '|' . ( Settings::get( 'assets/process/scssFiles' ) ? '1' : '0' );

		if ( isset( self::$matches_cache[ $cache_key ] ) ) {
			return self::$matches_cache[ $cache_key ];
		}

		$file = pathinfo( $path );
		$dir  = self::get_dist_folder( $path );
		$name = $file['filename'];
		$ext  = $file['extension'];

		if ( Settings::get( 'assets/process/scssFiles' ) && 'scss' === $ext ) {
			$ext = 'css';
		}

		$all_files = glob( $dir . '/*.' . $ext );

		if ( false === $all_files ) {
			self::$matches_cache[ $cache_key ] = array();
			return self::$matches_cache[ $cache_key ];
		}

		$matched_files = preg_grep(
			'/^' . preg_quote( $dir . '/' . $name, '/' ) . '-(?:[a-f0-9]{32}|[0-9]+)\.' . $ext . '$/',
			$all_files
		);

		self::$matches_cache[ $cache_key ] = array_values( $matched_files );

		return self::$matches_cache[ $cache_key ];
	}

	/**
	 * Clear request-local compiled asset lookup caches.
	 *
	 * @param string $path Asset source path.
	 *
	 * @return void
	 */
	private static function clear_asset_lookup_cache( string $path ): void {
		$prefix = wp_normalize_path( $path ) . '|';

		foreach ( array_keys( self::$matches_cache ) as $cache_key ) {
			if ( str_starts_with( $cache_key, $prefix ) ) {
				unset( self::$matches_cache[ $cache_key ] );
			}
		}
	}

	/**
	 * Get unique ID of a block.
	 *
	 * @param string $type  The asset type.
	 * @param array  $block The block data.
	 *
	 * @return string The unique ID.
	 */
	public static function get_id( $type, $block ): string {
		$name = $block['nameAlt'] ?? $block['name'];

		return str_replace( array( '/', '.', ' ' ), '-', "blockstudio-$name-$type" );
	}

	/**
	 * Get the path of a compiled asset name if it exists.
	 *
	 * @param string $path The file path.
	 *
	 * @return string The compiled path or original path.
	 */
	public static function get_path( $path ): string {
		$match = self::get_matches( $path );

		if ( 1 === count( $match ) ) {
			return $match[0];
		}

		return $path;
	}

	/**
	 * Get SCSS compiler.
	 *
	 * @param string $path The file path.
	 *
	 * @return Compiler The SCSS compiler.
	 */
	public static function get_scss_compiler( string $path ): Compiler {
		$compiler = new Compiler();

		if ( '' !== $path ) {
			$import_path = pathinfo( $path, PATHINFO_DIRNAME );
			$compiler->setImportPaths( $import_path );
		}

		foreach ( self::get_scss_import_paths() as $i_path ) {
			if ( ! is_dir( $i_path ) ) {
				continue;
			}
			$compiler->addImportPath(
				function ( $path ) use ( $i_path ) {
					return $i_path . '/' . $path;
				}
			);
		}

		return $compiler;
	}

	/**
	 * Get all assets for a preview window in Gutenberg.
	 *
	 * @param array $block  The block data.
	 * @param bool  $styles Whether to return styles.
	 *
	 * @return string The preview assets.
	 */
	public static function get_preview_assets( $block, bool $styles = true ): string {
		$style  = '';
		$script = '';

		foreach ( $block['assets'] ?? array() as $k => $v ) {
			if ( 'inline' !== $v['type'] ) {
				if ( self::is_css( $k ) ) {
					$style .= self::render_tag( $k, $v, $block );
				} else {
					$script .= self::render_tag( $k, $v, $block );
				}
			} else {
				$k = str_replace( array( '.inline', '-inline' ), '', $k );

				if ( self::is_css( $k ) ) {
					$style .= self::render_inline( $k, $v, $block, true );
				} else {
					$script .= self::render_inline( $k, $v, $block, true );
				}
			}
		}

		return $styles ? $style : $script;
	}

	/**
	 * Get module CSS assets.
	 *
	 * @param array  $block     The block data.
	 * @param array  $asset_ids The asset IDs array.
	 * @param string $element   The element string.
	 *
	 * @return void
	 */
	public static function get_module_css_assets( $block, &$asset_ids, &$element ): void {
		$source_paths = array( $block['path'] ?? '' );

		foreach ( $block['assets'] ?? array() as $asset ) {
			if ( is_array( $asset ) && is_string( $asset['path'] ?? null ) ) {
				$source_paths[] = $asset['path'];
			}
		}

		$filenames = array();
		foreach ( array_unique( array_filter( $source_paths ) ) as $source_path ) {
			$filenames = array_merge(
				$filenames,
				Files::get_files_with_extension( self::get_dist_folder( $source_path ) . '/modules', 'css' )
			);
		}

		foreach ( array_unique( $filenames ) as $filename ) {
			$file = pathinfo( $filename );

			if ( in_array( $file['filename'], $asset_ids, true ) ) {
				continue;
			}
			$asset_ids[] = $file['filename'];

			$element .= self::render_tag(
				$file['basename'],
				array(
					'editor' => false,
					'file'   => $file,
					'path'   => $filename,
					'type'   => 'external',
					'url'    => Files::get_relative_url( $filename ),
				),
				$block
			);
		}
	}

	/**
	 * Get assets.
	 *
	 * @param string $type The asset type.
	 *
	 * @return void
	 */
	public static function get_assets( $type = 'editor' ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Asset output.
		echo self::get_assets_html( $type );
	}

	/**
	 * Get cached editor asset HTML.
	 *
	 * This is the expensive part of block_editor_settings_all because it reads
	 * block CSS/JS files and prefixes editor CSS. Interactivity assets are kept
	 * outside this cache and appended by the settings filter on every request.
	 *
	 * @return string Editor asset HTML.
	 */
	private static function get_cached_editor_assets_output(): string {
		if ( ! self::is_editor_screen() ) {
			return '';
		}

		$cached = Build_Cache::load_editor_assets();

		if ( is_array( $cached ) && is_string( $cached['output'] ?? null ) ) {
			return $cached['output'];
		}

		ob_start();
		self::get_assets( 'editor' );
		$output = ob_get_clean();

		Build_Cache::write_editor_assets(
			array(
				'output' => $output,
			)
		);

		return $output;
	}

	/**
	 * Get assets as HTML.
	 *
	 * @param string $type The asset type.
	 *
	 * @return string The asset HTML.
	 */
	public static function get_assets_html( $type = 'editor' ): string {
		if ( 'editor' === $type && ! self::is_editor_screen() ) {
			return '';
		}

		$blocks = Build::data();

		$footer        = '';
		$editor_assets = array();
		$asset_ids     = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['assets'] ) ) {
				foreach ( $block['assets'] as $k => $v ) {
					if (
						preg_match( '/(?:^|[-.])' . preg_quote( 'customizer' === $type ? 'editor' : 'view', '/' ) . '\.(?:css|scss|js)$/', (string) $k )
					) {
						continue;
					}

					if ( preg_match( '/[-.]editor\.(css|scss|js)$/', $k ) ) {
						$editor_assets[] = array( $k, $v, $block );
						continue;
					}

					if ( 'customizer' === $type ) {
						if ( 'inline' !== $v['type'] ) {
							$footer .= self::render_tag( $k, $v, $block );
						} else {
							$footer .= self::render_inline( $k, $v, $block, true );
						}
					} elseif ( self::is_css_extension( $v['file']['extension'] ) ) {
							$footer .= self::render_inline( $k, $v, $block, true, true );
					} else {
						$footer .= self::render_inline( $k, $v, $block, true );
					}

					self::get_module_css_assets( $block, $asset_ids, $footer );
				}
			}
		}

		foreach ( $editor_assets as list( $k, $v, $block ) ) {
			if ( 'customizer' === $type ) {
				if ( 'inline' !== $v['type'] ) {
					$footer .= self::render_tag( $k, $v, $block );
				} else {
					$footer .= self::render_inline( $k, $v, $block, true );
				}
			} elseif ( self::is_css_extension( $v['file']['extension'] ) ) {
					$footer .= self::render_inline( $k, $v, $block, true, true );
			} else {
				$footer .= self::render_inline( $k, $v, $block, true );
			}
		}

		return $footer;
	}

	/**
	 * Render editor assets in the parent document only when WordPress disables the editor iframe.
	 *
	 * WordPress decides iframe compatibility in JavaScript from the client-side
	 * block registry. Server-side legacy block checks are too broad because some
	 * registered core blocks can still expose apiVersion < 3 while the editor
	 * remains iframed. Deferring the fallback until the canvas exists prevents
	 * Blockstudio view assets from running against the wp-admin shell.
	 *
	 * @return void
	 */
	public static function render_legacy_editor_assets_fallback(): void {
		if ( ! self::is_editor_screen() ) {
			return;
		}

		$script = '(()=>{'
			. 'const m="data-blockstudio-legacy-editor-assets";'
			. 'if(document.documentElement.hasAttribute(m))return;'
			. 'const hasIframe=()=>!!document.querySelector("iframe[name=\"editor-canvas\"]");'
			. 'const hasLegacyCanvas=()=>!!document.querySelector(".block-editor-block-list__layout.is-root-container");'
			. 'const getAssets=()=>{'
			. 'const s=window.wp?.data?.select?.("core/block-editor")?.getSettings?.().__unstableResolvedAssets;'
			. 'if(!s)return "";'
			. 'return `${s.styles||""}${s.scripts||""}`;'
			. '};'
			. 'const isBlockstudioAsset=(node)=>{'
			. 'const id=node.id||"";'
			. 'return id.startsWith("blockstudio-")||node.hasAttribute("data-blockstudio-asset")||node.hasAttribute("data-block");'
			. '};'
			. 'const append=(node)=>{'
			. 'if(!isBlockstudioAsset(node))return;'
			. 'if(node.id&&document.getElementById(node.id))return;'
			. 'const tag=node.tagName;'
			. 'const target="STYLE"===tag||"LINK"===tag?document.head:document.body;'
			. 'if("SCRIPT"!==tag){target.appendChild(node.cloneNode(true));return;}'
			. 'const script=document.createElement("script");'
			. 'Array.from(node.attributes).forEach(({name,value})=>script.setAttribute(name,value));'
			. 'script.text=node.textContent;'
			. 'target.appendChild(script);'
			. '};'
			. 'const inject=()=>{'
			. 'if(hasIframe())return true;'
			. 'if(!hasLegacyCanvas())return false;'
			. 'const assets=getAssets();'
			. 'if(!assets)return false;'
			. 'const template=document.createElement("template");'
			. 'template.innerHTML=assets;'
			. 'Array.from(template.content.children).forEach(append);'
			. 'document.documentElement.setAttribute(m,"true");'
			. 'return true;'
			. '};'
			. 'if(inject())return;'
			. 'const observer=new MutationObserver(()=>{if(inject())observer.disconnect();});'
			. 'const start=()=>{if(document.body)observer.observe(document.body,{childList:true,subtree:true});};'
			. '"loading"===document.readyState?document.addEventListener("DOMContentLoaded",start,{once:true}):start();'
			. 'setTimeout(()=>observer.disconnect(),10000);'
			. '})();';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Script is generated from JSON-encoded asset markup.
		echo '<script id="blockstudio-legacy-editor-assets-fallback">' . $script . '</script>';
	}

	/**
	 * Check if the editor screen is currently active.
	 *
	 * @return bool Whether the editor screen is active.
	 */
	public static function is_editor_screen(): bool {
		if ( self::$force_editor_screen ) {
			return true;
		}

		global $current_screen;

		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = get_current_screen(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		if ( ! $current_screen ) {
			return false;
		}

		return method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor();
	}

	/**
	 * Check if the path is a CSS file.
	 *
	 * @param string $path The file path.
	 *
	 * @return bool Whether the path is a CSS file.
	 */
	public static function is_css( $path ): bool {
		return str_ends_with( $path, '.css' ) || str_ends_with( $path, '.scss' );
	}

	/**
	 * Check if a path ends with a CSS extension.
	 *
	 * @param string $ext The extension.
	 *
	 * @return bool Whether it's a CSS extension.
	 */
	public static function is_css_extension( $ext ): bool {
		return 'css' === $ext || 'scss' === $ext;
	}

	/**
	 * Prefix CSS.
	 *
	 * @param string $css    The CSS content.
	 * @param string $prefix The prefix.
	 *
	 * @return string The prefixed CSS.
	 */
	public static function prefix_css( $css, $prefix ): string {
		$data = "$prefix { $css }";

		return self::compile_scss( $data, '' );
	}

	/**
	 * Prefix editor styles.
	 *
	 * @param string $css The CSS content.
	 *
	 * @return string The prefixed CSS.
	 */
	public static function prefix_editor_styles( $css ): string {
		$css = self::prefix_css( $css, '.editor-styles-wrapper' );
		$css = preg_replace( '/\bbody(?=[\s{,]|$)/', '.editor-styles-wrapper', $css );
		$css = str_replace( '.editor-styles-wrapper :root', ':root', $css );

		return str_replace(
			'.editor-styles-wrapper .editor-styles-wrapper',
			'.editor-styles-wrapper',
			$css
		);
	}

	/**
	 * Compile SCSS.
	 *
	 * @param string $scss The SCSS content.
	 * @param string $path The file path.
	 *
	 * @return string The compiled CSS.
	 */
	public static function compile_scss( string $scss, string $path ): string {
		$prelude = self::get_scss_prelude( $path, $scss );

		if ( '' !== $prelude ) {
			$scss = $prelude . "\n" . $scss;
		}

		$compiler = self::get_scss_compiler( $path );

		try {
			return $compiler->compileString( $scss )->getCss();
		} catch ( SassException $e ) {
			Error_Handler::handle_exception( $e, 'scss' );

			return '';
		}
	}

	/**
	 * Should process SCSS.
	 *
	 * @param string $path The file path.
	 *
	 * @return bool Whether to process SCSS.
	 */
	public static function should_process_scss( $path ): bool {
		$is_scss_ext = str_ends_with( $path, '.scss' ) && Settings::get( 'assets/process/scssFiles' );

		return Settings::get( 'assets/process/scss' ) || $is_scss_ext;
	}

	/**
	 * Check whether a CSS asset contains the selector placeholder.
	 *
	 * @param string $path The file path.
	 *
	 * @return bool Whether the asset contains the selector placeholder.
	 */
	private static function has_selector_placeholder( string $path ): bool {
		if ( ! is_file( $path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$content = file_get_contents( $path );

		return is_string( $content ) && str_contains( $content, self::SELECTOR_PLACEHOLDER );
	}

	/**
	 * Replace the selector placeholder before CSS processing.
	 *
	 * @param string $css          The CSS content.
	 * @param string $scoped_class The scoped class.
	 * @param bool   $scope_css    Whether this asset will be scoped.
	 *
	 * @return string The CSS with selector placeholders replaced.
	 */
	private static function replace_selector_placeholder( string $css, string $scoped_class, bool $scope_css ): string {
		if ( '' === $scoped_class || ! str_contains( $css, self::SELECTOR_PLACEHOLDER ) ) {
			return $css;
		}

		$selector = $scope_css
			? '.' . self::SELECTOR_PLACEHOLDER_CLASS
			: '.' . $scoped_class;

		return str_replace( self::SELECTOR_PLACEHOLDER, $selector, $css );
	}

	/**
	 * Resolve scoped selector placeholders after CSS has been scoped.
	 *
	 * @param string $css          The CSS content.
	 * @param string $scoped_class The scoped class.
	 *
	 * @return string The CSS with scoped selector placeholders resolved.
	 */
	private static function resolve_scoped_selector_placeholder( string $css, string $scoped_class ): string {
		if ( '' === $scoped_class || ! str_contains( $css, self::SELECTOR_PLACEHOLDER_CLASS ) ) {
			return $css;
		}

		$scoped_selector = '.' . $scoped_class;
		$placeholder     = '.' . self::SELECTOR_PLACEHOLDER_CLASS;

		return str_replace( $scoped_selector . ' ' . $placeholder, $scoped_selector, $css );
	}

	/**
	 * Get configured SCSS import paths.
	 *
	 * @return array
	 */
	private static function get_scss_import_paths(): array {
		$import_paths = apply_filters( 'blockstudio/assets/process/scss/import_paths', array() );

		// Backwards compatibility.
		$import_paths = apply_filters( 'blockstudio/assets/process/scss/importPaths', $import_paths ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- Deprecated v6 hook.

		return array_values(
			array_filter(
				(array) $import_paths,
				'is_string'
			)
		);
	}

	/**
	 * Get SCSS prelude content.
	 *
	 * @param string $path The file path.
	 * @param string $scss The SCSS content.
	 *
	 * @return string
	 */
	private static function get_scss_prelude( string $path, string $scss ): string {
		$prelude = apply_filters(
			'blockstudio/assets/process/scss/prelude',
			'',
			$path,
			$scss
		);

		return is_string( $prelude ) ? $prelude : '';
	}

	/**
	 * Get resolvable SCSS dependencies from the provided source.
	 *
	 * @param string $scss The SCSS source.
	 * @param string $path The SCSS file path.
	 *
	 * @return array
	 */
	private static function get_scss_dependencies( string $scss, string $path ): array {
		if ( '' === $scss ) {
			return array();
		}

		preg_match_all(
			'/@(import|use|forward)\s+([\'"])(.*?)(?<!\\\\)\2/',
			$scss,
			$matches
		);

		$dependencies = array();

		foreach ( $matches[3] as $import ) {
			$dependency = self::resolve_scss_dependency( $import, $path );

			if ( null !== $dependency ) {
				$dependencies[] = $dependency;
			}
		}

		return array_values( array_unique( $dependencies ) );
	}

	/**
	 * Resolve a SCSS dependency against local and configured import paths.
	 *
	 * @param string $import The import path.
	 * @param string $path   The source file path.
	 *
	 * @return string|null
	 */
	private static function resolve_scss_dependency( string $import, string $path ): ?string {
		if (
			'' === $import ||
			str_starts_with( $import, 'sass:' ) ||
			str_starts_with( $import, 'http://' ) ||
			str_starts_with( $import, 'https://' )
		) {
			return null;
		}

		$base_paths = self::get_scss_import_paths();

		if ( '' !== $path ) {
			array_unshift( $base_paths, pathinfo( $path, PATHINFO_DIRNAME ) );
		}

		foreach ( $base_paths as $base_path ) {
			foreach ( self::get_scss_dependency_candidates( $base_path, $import ) as $candidate ) {
				if ( is_file( $candidate ) ) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Get possible SCSS file candidates for a dependency.
	 *
	 * @param string $base_path Base directory.
	 * @param string $import    Import path.
	 *
	 * @return array
	 */
	private static function get_scss_dependency_candidates( string $base_path, string $import ): array {
		$import    = str_replace( '\\', '/', $import );
		$directory = dirname( $import );
		$directory = '.' === $directory ? '' : trailingslashit( $directory );
		$filename  = basename( $import );
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		$name      = '' === $extension ? $filename : basename( $filename, '.' . $extension );
		$base      = untrailingslashit( $base_path ) . '/' . $directory;

		$candidates = array(
			$base . $filename,
			$base . '_' . $filename,
		);

		if ( '' === $extension ) {
			$candidates[] = $base . $filename . '.scss';
			$candidates[] = $base . '_' . $name . '.scss';
			$candidates[] = $base . $filename . '/index.scss';
			$candidates[] = $base . $filename . '/_index.scss';
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Transform CSS assets and print to file.
	 *
	 * @param string $path         The file path.
	 * @param string $dist_folder  The distribution folder.
	 * @param string $scoped_class The scoped class.
	 *
	 * @return string|void The compiled filename or void.
	 */
	public static function process_css( $path, $dist_folder, $scoped_class ) {
		$file     = pathinfo( $path );
		$filename = $file['filename'];

		$minify_css               = Settings::get( 'assets/minify/css' );
		$process_scss             = self::should_process_scss( $path );
		$scope_css                = str_ends_with( $filename, '.scoped' ) || str_ends_with( $filename, '-scoped' );
		$has_selector_placeholder = self::has_selector_placeholder( $path );
		$should_process           = $minify_css || $process_scss || $scope_css || $has_selector_placeholder;
		$compiled_filename        = self::get_compiled_filename( $path, $scoped_class );

		if (
			file_exists( $compiled_filename ) &&
			$should_process
		) {
			return $compiled_filename;
		}

		if ( ! $should_process ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$data = apply_filters( 'blockstudio/assets/process/css/content', file_get_contents( $path ) );
		$data = self::replace_selector_placeholder( $data, $scoped_class, $scope_css );

		if ( $process_scss ) {
			$data = self::compile_scss( $data, $path );
		}

		if ( $scope_css ) {
			$data = self::prefix_css( $data, '.' . $scoped_class );
			$data = self::resolve_scoped_selector_placeholder( $data, $scoped_class );
		}

		if ( $minify_css ) {
			$minifier = new Minify\CSS();
			$minifier->add( $data );
			$data = $minifier->minify();
		}

		if ( ! Single_Flight::publish( $compiled_filename, $data ) ) {
			return;
		}

		return $compiled_filename;
	}

	/**
	 * Transform JS assets and print to file.
	 *
	 * @param string $path        The file path.
	 * @param string $dist_folder The distribution folder.
	 *
	 * @return array|void The array of filenames or void.
	 */
	public static function process_js( $path, $dist_folder ) {
		$pathinfo  = pathinfo( $path );
		$minify_js = Settings::get( 'assets/minify/js' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$data = apply_filters( 'blockstudio/assets/process/js/content', file_get_contents( $path ) );

		$compiled_filename = self::get_compiled_filename( $path );

		$css_modules     = ESModulesCSS::fetch_all_modules_and_write_to_file( $data, $pathinfo['dirname'] );
		$has_css_modules = count( $css_modules['objects'] ) >= 1;

		if ( $has_css_modules ) {
			$data = ESModulesCSS::replace_module_references( $data );
		}

		$es_modules     = ESModules::fetch_all_modules_and_write_to_file( $data, $pathinfo['dirname'] );
		$has_es_modules = count( $es_modules['objects'] ) >= 1;

		if ( $has_es_modules ) {
			foreach ( $es_modules['objects'] as $module ) {
				$name             = $module['name'];
				$version          = $module['version'];
				$name_transformed = $module['nameTransformed'];
				$local_path       = "./modules/$name_transformed/$version.js";
				$data             = str_replace( "npm:$name@$version", $local_path, $data );
				$data             = str_replace( "blockstudio/$name@$version", $local_path, $data );
			}
		}

		if (
			file_exists( $compiled_filename ) &&
			( $minify_js || $has_es_modules || $has_css_modules )
		) {
			return array_merge(
				$es_modules['filenames'],
				$css_modules['filenames'],
				array( $compiled_filename )
			);
		}

		if ( $minify_js ) {
			$minifier = new Minify\JS();
			$minifier->add( $data );
			$data = $minifier->minify();
		}

		if (
			! file_exists( $compiled_filename ) &&
			( $minify_js || $has_es_modules || $has_css_modules )
		) {
			if ( ! Single_Flight::publish( $compiled_filename, $data ) ) {
				return;
			}

			return array_merge(
				$es_modules['filenames'],
				$css_modules['filenames'],
				array( $compiled_filename )
			);
		}
	}

	/**
	 * Transform assets.
	 *
	 * @param string $path         The file path.
	 * @param string $scoped_class The scoped class.
	 *
	 * @return array|string|void The processed result.
	 */
	public static function process( $path, string $scoped_class ) {
		$pathinfo    = pathinfo( $path );
		$ext         = $pathinfo['extension'];
		$dist_folder = self::get_dist_folder( $path );
		$result      = null;

		if ( self::is_css_extension( $ext ) ) {
			$result = self::process_css( $path, $dist_folder, $scoped_class );
			self::clear_asset_lookup_cache( $path );

			return $result;
		}

		if ( 'js' === $ext ) {
			$result = self::process_js( $path, $dist_folder );
			self::clear_asset_lookup_cache( $path );

			return $result;
		}
	}

	/**
	 * Render inline asset.
	 *
	 * @param string      $type   The asset type.
	 * @param array       $data   The asset data.
	 * @param array       $block  The block data.
	 * @param bool|string $return Whether to return the result.
	 * @param bool        $prefix Whether to prefix styles.
	 *
	 * @return string|null The rendered asset or null.
	 */
	public static function render_inline( $type, $data, $block, $return = false, $prefix = false ) {
		$id = self::get_id( $type, $block );

		if (
			in_array( $id, apply_filters( 'blockstudio/assets/disable', array() ), true ) &&
			'gutenberg' !== $return
		) {
			return null;
		}

		$tag       = str_ends_with( $type, '.js' ) ? 'script' : 'style';
		$is_script = str_ends_with( $type, '.js' );
		$is_prefix = $prefix && ! $is_script;

		$processed_string = '';
		$key              = '';

		if ( 'gutenberg' !== $return ) {
			$path             = self::get_path( $data['path'] );
			$is_processed     = 1 === count( self::get_matches( $data['path'] ) );
			$processed_string = $is_processed ? 'data-processed' : '';

			if ( str_ends_with( $path, '.scss' ) ) {
				return null;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
			$contents = file_get_contents( $path );
			$key      = "data-key='" . filemtime( $path ) . "'";
		} else {
			$contents = $data;
		}

		if ( $is_prefix ) {
			$contents = self::prefix_editor_styles( $contents );
		}

		if ( ! $is_script ) {
			$contents = self::prepare_ui_inline_style( $contents, $block );
		}

		if ( $is_script ) {
			preg_match_all( "/[\"'](.\/modules\/)([a-zA-Z0-9.-@_-]*)[\"']/", $contents, $modules );

			foreach ( $modules[2] as $module ) {
				$name          = explode( '/', $module )[0];
				$version       = str_replace( '.js', '', explode( '/', $module )[1] );
				$module_source = is_string( $data['path'] ?? null ) ? $data['path'] : $block['path'];
				$module_path   = self::get_dist_folder( $module_source ) . '/modules/' . $name . '/' . $version . '.js';
				$module_id     = $name . '-' . $version;

				if ( file_exists( $module_path ) ) {
					if ( ! isset( self::$modules[ $module_id ] ) ) {
						self::$modules[ $module_id ] = Files::get_relative_url( $module_path );
					}
					$contents = preg_replace(
						"/[\"'](.\/modules\/)([a-zA-Z0-9.-@_-]*)[\"']/",
						'"' . self::$modules[ $module_id ] . '"',
						$contents,
						1
					);
				}
			}
		}

		$type       = 'script' === $tag ? 'type="module"' : '';
		$block_attr = $is_script && isset( $block['name'] ) ? "data-block='" . esc_attr( $block['name'] ) . "'" : '';
		$string     = "<$tag id='$id' $processed_string $type $key $block_attr>" . $contents . "</$tag>";

		if ( $return ) {
			return 'gutenberg' === $return ? $contents : $string;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Asset output.
		echo $string;

		return null;
	}

	/**
	 * Prepare bundled UI inline CSS for output.
	 *
	 * @param string $contents CSS contents.
	 * @param array  $block    Block data.
	 *
	 * @return string Prepared CSS.
	 */
	private static function prepare_ui_inline_style( string $contents, array $block ): string {
		$block_name = $block['name'] ?? '';

		if ( ! is_string( $block_name ) || ! str_starts_with( $block_name, 'bsui/' ) ) {
			return $contents;
		}

		$component = sanitize_key( substr( $block_name, strlen( 'bsui/' ) ) );
		if ( '' !== $component ) {
			$variants_style = apply_filters( 'blockstudio/ui/' . $component . '/variants-style', '', $block );

			if ( is_string( $variants_style ) && '' !== trim( $variants_style ) ) {
				$contents .= "\n" . trim( $variants_style );
			}
		}

		return "@layer bsui {\n" . $contents . "\n}";
	}

	/**
	 * Render tag asset.
	 *
	 * @param string $type  The asset type.
	 * @param array  $data  The asset data.
	 * @param array  $block The block data.
	 *
	 * @return string|null The rendered tag or null.
	 */
	public static function render_tag( $type, $data, $block ): ?string {
		$id = self::get_id( $type, $block );

		if ( in_array( $id, apply_filters( 'blockstudio/assets/disable', array() ), true ) ) {
			return null;
		}

		$path                = $data['path'];
		$maybe_compiled_path = self::get_path( $path );

		if ( ! is_file( $maybe_compiled_path ) || 0 === filesize( $maybe_compiled_path ) ) {
			return null;
		}

		$src = Files::get_relative_url( $maybe_compiled_path );

		/*
		 * An unresolvable URL used to still emit a tag, so the browser requested
		 * the current document as a stylesheet or module. Every page carrying
		 * such an asset logged a MIME type error.
		 */
		if ( '' === $src ) {
			return null;
		}

		$key       = filemtime( $path );
		$processed = 1 === count( self::get_matches( $path ) ) ? 'data-processed' : '';

		if ( self::is_css( $type ) ) {
			if ( str_ends_with( $src, '.scss' ) ) {
				return null;
			}

			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Intentional inline tag rendering.
			return "<link rel='stylesheet' $processed id='$id' href='$src?ver=$key'>";
		}

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Intentional inline tag rendering.
		return "<script type='module' $processed id='$id' src='$src?ver=$key'></script>";
	}

	/**
	 * Render code field assets.
	 *
	 * @param array  $attribute_data The attribute data.
	 * @param string $key           The key.
	 *
	 * @return string|null The rendered assets.
	 */
	public static function render_code_field_assets( $attribute_data, string $key = 'assets' ): ?string {
		$assets_string = '';

		foreach ( $attribute_data[ $key ] as $asset ) {
			$type  = $asset['language'] ?? 'html';
			$value = $asset['value'] ?? '';

			if ( 'javascript' === $type ) {
				$assets_string .= '<script id="' . $attribute_data['selectorAttributeId'] . '-' . uniqid() . '" data-blockstudio-asset>' . $value . '</script>';
			}
			if ( 'scss' === $type ) {
				$value = self::compile_scss( $value, '' );
				$type  = 'css';
			}
			if ( 'css' === $type ) {
				$assets_string .= '<style id="' . $attribute_data['selectorAttributeId'] . '-' . uniqid() . '" data-blockstudio-asset>' . $value . '</style>';
			}
		}

		return $assets_string;
	}

	/**
	 * Return buffer.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return mixed|null The filtered content.
	 */
	public static function return_buffer( $html ) {
		if ( ! $html ) {
			return $html;
		}

		return apply_filters( 'blockstudio/buffer/output', $html );
	}
}

Assets::init();

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
	 * Loaded modules.
	 *
	 * @var array
	 */
	private static array $modules = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_buffer_output' ), 3 );
		add_filter( 'blockstudio/buffer/output', array( $this, 'parse_output' ), 1000000 );
		add_filter(
			'block_editor_settings_all',
			function ( $settings ) {
				ob_start();
				self::get_assets( 'editor' );
				$output = ob_get_clean();

				if ( '' === $output || ! isset( $settings['__unstableResolvedAssets'] ) ) {
					return $settings;
				}

				preg_match_all( '/<script\b[^>]*>.*?<\/script>/is', $output, $script_matches );
				$scripts = implode( "\n", $script_matches[0] );
				$styles  = trim( preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $output ) );

				$settings['__unstableResolvedAssets']['styles']  .= $styles;
				$settings['__unstableResolvedAssets']['scripts'] .= $scripts;

				return $settings;
			},
			PHP_INT_MAX
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
	 * Maybe buffer output.
	 *
	 * @return bool|void
	 */
	public function maybe_buffer_output() {
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		if ( is_admin() ) {
			return false;
		}

		ob_start( array( $this, 'return_buffer' ) );
	}

	/**
	 * Parse output and return assets.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string The processed HTML.
	 */
	public function parse_output( $html ): string {
		$blocks         = Build::data();
		$blocks_native  = Build::blocks();
		$ids            = array();
		$blocks_on_page = array();
		$asset_ids      = array();

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

		return apply_filters( 'blockstudio/render', $output, $blocks_on_page );
	}

	/**
	 * Get Interactivity API import map.
	 *
	 * @return string The import map HTML.
	 */
	public static function get_interactivity_api_import_map(): string {
		$string = '<script type="importmap"> { "imports": { "@wordpress/interactivity": "@path/@wordpress/interactivity/build-module/index.js", "preact": "@path/preact/dist/preact.module.js", "preact/hooks": "@path/preact/hooks/dist/hooks.module.js", "@preact/signals": "@path/@preact/signals/dist/signals.module.js", "@preact/signals-core": "@path/@preact/signals-core/dist/signals-core.module.js" } } </script>';
		$path   = plugin_dir_url( __FILE__ ) . '../assets/interactivity';

		return str_replace( '@path', $path, $string );
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
		$mtimes = array( filemtime( $path ) );

		if ( '' !== $scoped_class ) {
			$mtimes[] = $scoped_class;
		}

		if ( str_ends_with( $path, '.js' ) || ! self::should_process_scss( $path ) ) {
			return $mtimes[0];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$content = file_get_contents( $path );
		preg_match_all( '/@import\s*([\'"])(.*?)(?<!\\\\)\1/', $content, $matches );

		$import_paths = apply_filters( 'blockstudio/assets/process/scss/import_paths', array() );
		// Backwards compatibility.
		$import_paths = apply_filters( 'blockstudio/assets/process/scss/importPaths', $import_paths ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- Deprecated v6 hook.
		foreach ( $import_paths as $import_path ) {
			if ( file_exists( $import_path ) ) {
				$mtimes[] = filemtime( $import_path );
			}
		}

		foreach ( $matches[2] as $import ) {
			$import_path = dirname( $path ) . '/' . $import;

			if ( file_exists( $import_path ) ) {
				$mtimes[] = filemtime( $import_path );
			}
		}

		if ( 1 === count( $mtimes ) ) {
			return $mtimes[0];
		}

		return md5( implode( '-', $mtimes ) );
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
		$dir  = $file['dirname'];
		$file = $file['filename'];

		$ext = pathinfo( $path, PATHINFO_EXTENSION );
		$id  = self::get_imported_modification_times(
			$path,
			str_ends_with( $file, '-scoped' ) ? $scoped_class : ''
		);

		if ( Settings::get( 'assets/process/scssFiles' ) && 'scss' === $ext ) {
			$ext = 'css';
		}

		return $dir . '/_dist/' . $file . '-' . $id . '.' . $ext;
	}

	/**
	 * Get all matches for a compiled asset name.
	 *
	 * @param string $path The file path.
	 *
	 * @return array Array of matching files.
	 */
	public static function get_matches( $path ): array {
		$file = pathinfo( $path );
		$dir  = $file['dirname'] . '/_dist';
		$name = $file['filename'];
		$ext  = $file['extension'];

		if ( Settings::get( 'assets/process/scssFiles' ) && 'scss' === $ext ) {
			$ext = 'css';
		}

		$all_files = glob( $dir . '/*.' . $ext );

		$matched_files = preg_grep(
			'/^' . preg_quote( $dir . '/' . $name, '/' ) . '-(?:[a-f0-9]{32}|[0-9]+)\.' . $ext . '$/',
			$all_files
		);

		return array_values( $matched_files );
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

		$scss_import_paths = apply_filters( 'blockstudio/assets/process/scss/import_paths', array() );
		// Backwards compatibility.
		$scss_import_paths = apply_filters( 'blockstudio/assets/process/scss/importPaths', $scss_import_paths ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- Deprecated v6 hook.
		foreach ( $scss_import_paths as $i_path ) {
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
				if ( false !== strpos( $k, 'style' ) ) {
					$style .= self::render_tag( $k, $v, $block );
				} else {
					$script .= self::render_tag( $k, $v, $block );
				}
			} else {
				$k = str_replace( '-inline', '', $k );

				if ( false !== strpos( $k, 'style' ) ) {
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
		foreach (
			Files::get_files_with_extension(
				$block['file']['dirname'] . '/_dist/modules',
				'css'
			) as $filename
		) {
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
		if ( 'editor' === $type && ! self::is_editor_screen() ) {
			return;
		}

		$blocks = Build::data();

		$footer        = '';
		$editor_assets = array();
		$asset_ids     = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['assets'] ) ) {
				foreach ( $block['assets'] as $k => $v ) {
					if (
						false !== strpos(
							$k,
							'customizer' === $type ? 'editor' : 'view'
						)
					) {
						continue;
					}

					if ( preg_match( '/-editor\.(css|scss|js)$/', $k ) ) {
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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Asset output.
		echo $footer;
	}

	/**
	 * Check if the editor screen is currently active.
	 *
	 * @return bool Whether the editor screen is active.
	 */
	public static function is_editor_screen(): bool {
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
		$compiler = self::get_scss_compiler( $path );

		try {
			return $compiler->compileString( $scss )->getCss();
		} catch ( SassException $e ) {
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

		$minify_css        = Settings::get( 'assets/minify/css' );
		$process_scss      = self::should_process_scss( $path );
		$scope_css         = str_ends_with( $filename, '-scoped' );
		$compiled_filename = self::get_compiled_filename( $path, $scoped_class );

		if (
			file_exists( $compiled_filename ) &&
			( $minify_css || $process_scss || $scope_css )
		) {
			return $compiled_filename;
		}

		if ( ! $minify_css && ! $process_scss && ! $scope_css ) {
			return;
		}

		if ( $minify_css || $process_scss || $scope_css ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
			$data = apply_filters( 'blockstudio/assets/process/css/content', file_get_contents( $path ) );

			if ( $process_scss ) {
				$data = self::compile_scss( $data, $path );
			}

			if ( $scope_css ) {
				$data = self::prefix_css( $data, '.' . $scoped_class );
			}

			if ( $minify_css ) {
				$minifier = new Minify\CSS();
				$minifier->add( $data );
				$data = $minifier->minify();
			}

			if ( ! is_dir( $dist_folder ) ) {
				wp_mkdir_p( $dist_folder );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing compiled file.
			file_put_contents( $compiled_filename, $data );

			return $compiled_filename;
		}
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
				$data             = str_replace(
					"blockstudio/$name@$version",
					"./modules/$name_transformed/$version.js",
					$data
				);
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
			if ( ! is_dir( $dist_folder ) ) {
				wp_mkdir_p( $dist_folder );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing compiled file.
			file_put_contents( $compiled_filename, $data );

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
		$dist_folder = $pathinfo['dirname'] . '/_dist';

		if ( self::is_css_extension( $ext ) ) {
			return self::process_css( $path, $dist_folder, $scoped_class );
		}

		if ( 'js' === $ext ) {
			return self::process_js( $path, $dist_folder );
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

		if ( $is_script ) {
			preg_match_all( "/[\"'](.\/modules\/)([a-zA-Z0-9.-@_-]*)[\"']/", $contents, $modules );

			foreach ( $modules[2] as $module ) {
				$name        = explode( '/', $module )[0];
				$version     = str_replace( '.js', '', explode( '/', $module )[1] );
				$module_path = $block['file']['dirname'] . '/_dist/modules/' . $name . '/' . $version . '.js';
				$module_id   = $name . '-' . $version;

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

		$type   = 'script' === $tag ? 'type="module"' : '';
		$string = "<$tag id='$id' $processed_string $type $key>" . $contents . "</$tag>";

		if ( $return ) {
			return 'gutenberg' === $return ? $contents : $string;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Asset output.
		echo $string;
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

		if ( 0 === filesize( $maybe_compiled_path ) ) {
			return null;
		}

		$src       = Files::get_relative_url( $maybe_compiled_path );
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
			$type = $asset['language'] ?? 'html';

			if ( 'javascript' === $type ) {
				$assets_string .= '<script id="' . $attribute_data['selectorAttributeId'] . '-' . uniqid() . '" data-blockstudio-asset>' . $asset['value'] . '</script>';
			}
			if ( 'css' === $type ) {
				$assets_string .= '<style id="' . $attribute_data['selectorAttributeId'] . '-' . uniqid() . '" data-blockstudio-asset>' . $asset['value'] . '</style>';
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

new Assets();

<?php
/**
 * Tailwind class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioVendor\TailwindPHP\Tailwind as TailwindPHP;

/**
 * Tailwind CSS integration for Blockstudio.
 *
 * When tailwind/enabled is true, every frontend request is compiled server-side
 * via TailwindPHP. CSS class candidates are extracted from the HTML and hashed
 * to create a file-based cache key. On cache hit, the compiled CSS is read and
 * injected. On cache miss, TailwindPHP compiles the CSS, writes it to the cache
 * directory, and injects it. The CDN script remains active in the editor for
 * live preview only.
 *
 * Cache Strategy:
 * - Extract CSS class candidates from HTML via TailwindPHP::extractCandidates()
 * - Sort candidates and hash with CSS config to create cache key
 * - Cache files stored in uploads/blockstudio/tailwind/cache/
 * - Dynamic HTML (nonces, timestamps) does NOT bust the cache
 *
 * Filter: blockstudio/tailwind/css
 * Customize the CSS input for Tailwind compilation:
 * ```php
 * add_filter('blockstudio/tailwind/css', function($css) {
 *     return $css . "\n@layer utilities { .my-class { color: red; } }";
 * });
 * ```
 *
 * @since 5.0.0
 */
class Tailwind {

	/**
	 * Whether compilation has already run for this request.
	 *
	 * @var bool
	 */
	private bool $compiled = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'blockstudio/buffer/output', array( $this, 'compile' ), 999999 );
		add_filter( 'block_editor_settings_all', array( $this, 'inject_editor_styles' ), PHP_INT_MAX );
	}

	/**
	 * Compile Tailwind CSS from the buffered HTML output.
	 *
	 * Extracts CSS class candidates, checks the file cache, compiles on miss,
	 * and injects the result as an inline style tag before </head>.
	 *
	 * @param string $html The full page HTML.
	 *
	 * @return string The modified HTML with injected Tailwind CSS.
	 */
	public function compile( string $html ): string {
		if ( ! Settings::get( 'tailwind/enabled' ) || $this->compiled ) {
			return $html;
		}

		$this->compiled = true;

		$css_input  = $this->build_css_input();
		$candidates = TailwindPHP::extractCandidates( $html );
		sort( $candidates );
		$cache_key  = md5( implode( ',', $candidates ) . $css_input );
		$cache_path = self::get_cache_dir() . '/' . $cache_key . '.css';

		if ( file_exists( $cache_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading cached CSS file.
			$compiled = file_get_contents( $cache_path );
		} else {
			$compiled = TailwindPHP::generate(
				array(
					'content' => $html,
					'css'     => $css_input,
					'minify'  => true,
				)
			);

			if ( ! empty( $compiled ) ) {
				$dir = self::get_cache_dir();

				if ( ! is_dir( $dir ) ) {
					wp_mkdir_p( $dir );
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing compiled CSS to cache file.
				file_put_contents( $cache_path, $compiled );
			}
		}

		if ( empty( $compiled ) ) {
			return $html;
		}

		return str_replace(
			'</head>',
			'<style id="blockstudio-tailwind">' . $compiled . "</style>\n</head>",
			$html
		);
	}

	/**
	 * Inject compiled Tailwind CSS into the block editor.
	 *
	 * Compiles the CSS config (e.g. @apply rules) so custom classes like
	 * .container work in the editor iframe alongside the CDN.
	 *
	 * @param array $settings Editor settings.
	 *
	 * @return array Modified editor settings.
	 */
	public function inject_editor_styles( array $settings ): array {
		if ( ! Settings::get( 'tailwind/enabled' ) || ! isset( $settings['__unstableResolvedAssets'] ) ) {
			return $settings;
		}

		$content = '';
		foreach ( Block_Registry::instance()->get_data() as $block ) {
			foreach ( $block['filesPaths'] ?? array() as $path ) {
				if ( is_file( $path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading block template files for Tailwind candidate extraction.
					$content .= file_get_contents( $path );
				}
			}
		}

		$css_input  = $this->build_css_input();
		$candidates = TailwindPHP::extractCandidates( $content );
		sort( $candidates );
		$cache_key  = md5( 'editor:' . implode( ',', $candidates ) . $css_input );
		$cache_path = self::get_cache_dir() . '/' . $cache_key . '.css';

		if ( file_exists( $cache_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading cached CSS file.
			$compiled = file_get_contents( $cache_path );
		} else {
			$compiled = TailwindPHP::generate(
				array(
					'content' => $content,
					'css'     => $css_input,
					'minify'  => true,
				)
			);

			if ( ! empty( $compiled ) ) {
				$dir = self::get_cache_dir();

				if ( ! is_dir( $dir ) ) {
					wp_mkdir_p( $dir );
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing compiled CSS to cache file.
				file_put_contents( $cache_path, $compiled );
			}
		}

		if ( ! empty( $compiled ) ) {
			$settings['__unstableResolvedAssets']['styles'] .= '<style id="blockstudio-tailwind-editor">' . $compiled . '</style>';
		}

		return $settings;
	}

	/**
	 * Build the CSS input string for compilation.
	 *
	 * @return string The CSS input with imports and config.
	 */
	private function build_css_input(): string {
		$css = '@import "tailwindcss";';

		$config = Settings::get( 'tailwind/config' );

		if ( ! empty( $config ) && is_string( $config ) ) {
			$css .= "\n" . $config;
		}

		/**
		 * Filter the CSS input for Tailwind compilation.
		 *
		 * @param string $css The CSS input string.
		 */
		$css = apply_filters( 'blockstudio/tailwind/css', $css );

		return $css;
	}

	/**
	 * Get the cache directory path.
	 *
	 * @return string The cache directory path.
	 */
	public static function get_cache_dir(): string {
		return wp_upload_dir()['basedir'] . '/blockstudio/tailwind/cache';
	}

	/**
	 * Get Tailwind CDN URL.
	 *
	 * @return string The CDN URL.
	 */
	public static function get_cdn_url(): string {
		$path = BLOCKSTUDIO_DIR . '/includes/admin/assets/tailwind/cdn.js';

		return Files::get_relative_url( $path );
	}
}

new Tailwind();

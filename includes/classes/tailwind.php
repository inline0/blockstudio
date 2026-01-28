<?php
/**
 * Tailwind class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Tailwind CSS integration for Blockstudio.
 *
 * This class handles the integration of Tailwind CSS for block development,
 * including both the CDN-based development workflow and production CSS output.
 *
 * Development Workflow:
 * 1. Enable Tailwind in settings (tailwind/enabled)
 * 2. Use Tailwind utility classes in block templates
 * 3. The React editor compiles CSS via Tailwind CDN
 * 4. Compiled CSS is saved via REST API to uploads/blockstudio/tailwind/
 *
 * Production Assets:
 * - editor.css: Global compiled styles for the editor
 * - {post_id}.css: Per-page compiled styles for frontend
 * - preflight.css: Tailwind's CSS reset (always included when active)
 *
 * Paths and URLs:
 * - get_assets_dir(): uploads/blockstudio/tailwind/ (filterable)
 * - get_css_path(): Full path to CSS file
 * - get_css_url(): Public URL for enqueuing
 * - get_cdn_url(): URL to bundled Tailwind CDN script
 *
 * Filter: blockstudio/tailwind/assets/dir
 * Customize the Tailwind output directory:
 * ```php
 * add_filter('blockstudio/tailwind/assets/dir', function($dir) {
 *     return get_stylesheet_directory() . '/assets/tailwind';
 * });
 * ```
 *
 * @since 5.0.0
 */
class Tailwind {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue Tailwind styles.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if (
			Settings::get( 'tailwind/enabled' ) &&
			file_exists( self::get_css_path() )
		) {
			wp_enqueue_style(
				'blockstudio-tailwind',
				self::get_css_url(),
				array(),
				filemtime( self::get_css_path() )
			);
		}

		if ( Build::is_tailwind_active() ) {
			$preflight = BLOCKSTUDIO_DIR . '/includes-v7/admin/assets/tailwind/preflight.css';
			wp_enqueue_style(
				'blockstudio-tailwind-preflight',
				Files::get_relative_url( $preflight ),
				array(),
				filemtime( $preflight )
			);
			if ( file_exists( self::get_css_path( get_the_ID() ) ) ) {
				$id = get_the_ID();
				wp_enqueue_style(
					'blockstudio-tailwind-' . $id,
					self::get_css_url( $id ),
					array(),
					filemtime( self::get_css_path( $id ) )
				);
			}
		}
	}

	/**
	 * Get Tailwind CDN URL.
	 *
	 * @return string The CDN URL.
	 */
	public static function get_cdn_url(): string {
		$path = BLOCKSTUDIO_DIR . '/includes-v7/admin/assets/tailwind/cdn.js';

		return Files::get_relative_url( $path );
	}

	/**
	 * Get the Tailwind assets directory path.
	 *
	 * @return string The assets directory path.
	 */
	public static function get_assets_dir(): string {
		return apply_filters(
			'blockstudio/tailwind/assets/dir',
			wp_upload_dir()['basedir'] . '/blockstudio/tailwind'
		);
	}

	/**
	 * Get the CSS file path.
	 *
	 * @param string $id The identifier (default 'editor').
	 *
	 * @return string The CSS file path.
	 */
	public static function get_css_path( string $id = 'editor' ): string {
		return self::get_assets_dir() . "/$id.css";
	}

	/**
	 * Get the CSS file URL.
	 *
	 * @param string $id The identifier (default 'editor').
	 *
	 * @return string The CSS file URL.
	 */
	public static function get_css_url( string $id = 'editor' ): string {
		return Files::get_relative_url( self::get_css_path( $id ) );
	}
}

new Tailwind();

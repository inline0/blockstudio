<?php
/**
 * Tailwind class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles Tailwind CSS integration.
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

		if ( Build::isTailwindActive() ) {
			$preflight = BLOCKSTUDIO_DIR . '/includes-v7/admin/assets/tailwind/preflight.css';
			wp_enqueue_style(
				'blockstudio-tailwind-preflight',
				Files::getRelativeUrl( $preflight ),
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

		return Files::getRelativeUrl( $path );
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
		return Files::getRelativeUrl( self::get_css_path( $id ) );
	}
}

new Tailwind();

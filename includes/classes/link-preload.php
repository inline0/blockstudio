<?php
/**
 * Link preload runtime class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Registers the optional, same-origin intent link preloader.
 *
 * @since 7.6.0
 */
final class Link_Preload {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'blockstudio-link-preload';

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the preloader when configured.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		if ( 'intent' === Runtime_Settings::current()->value( 'preload/links', 'off' ) ) {
			add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		}
	}

	/**
	 * Enqueue the preloader.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$path = BLOCKSTUDIO_DIR . '/includes/assets/runtime/link-preload.js';
		if ( ! is_file( $path ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			BLOCKSTUDIO_URL . 'includes/assets/runtime/link-preload.js',
			array(),
			(string) filemtime( $path ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
}

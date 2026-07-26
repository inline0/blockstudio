<?php
/**
 * Frontend runtime class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Initializes Blockstudio-owned generic theme runtime behavior.
 *
 * @since 7.6.0
 */
final class Runtime {

	/**
	 * Whether the runtime has initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Initialize generic runtime services.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;
		Settings::get_instance();
		Theme_Defaults::register();
		Media::register();
		Link_Preload::register();
		Performance_Measurement::register();
		WordPress_Optimizations::register();
		Static_Prerender_Runtime::register();
		add_action( 'admin_notices', array( self::class, 'render_configuration_notice' ) );

		do_action( 'blockstudio/runtime/initialized', Runtime_Settings::current() );
	}

	/**
	 * Report resolved configuration errors to administrators.
	 *
	 * @return void
	 */
	public static function render_configuration_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$errors = Runtime_Settings::current()->errors();
		if ( array() === $errors ) {
			return;
		}

		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Blockstudio ignored invalid configuration values:', 'blockstudio' )
			. '</p><ul>';
		foreach ( $errors as $error ) {
			echo '<li><code>' . esc_html( $error ) . '</code></li>';
		}
		echo '</ul></div>';
	}
}

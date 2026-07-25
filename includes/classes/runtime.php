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

		do_action( 'blockstudio/runtime/initialized', Runtime_Settings::current() );
	}
}

<?php
/**
 * UI class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Registers bundled UI components when enabled via blockstudio.json.
 *
 * @since 7.3.0
 */
class Ui {

	/**
	 * Whether the hooks have already been registered.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Whether UI blocks have already been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Initialize UI block registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		add_action( 'init', array( __CLASS__, 'register' ), PHP_INT_MAX - 2 );
	}

	/**
	 * Check whether bundled UI components are enabled.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return true === Settings::get( 'ui/enabled' );
	}

	/**
	 * Register bundled UI component directories.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered || ! self::enabled() || ! class_exists( 'Blockstudio\Build' ) ) {
			return;
		}

		self::$registered = true;

		foreach ( self::directories() as $directory ) {
			Build::init(
				array(
					'dir' => $directory,
				)
			);
		}
	}

	/**
	 * Get bundled UI component directories.
	 *
	 * @return array<string>
	 */
	public static function directories(): array {
		$directories = apply_filters(
			'blockstudio/ui/directories',
			array(
				BLOCKSTUDIO_DIR . '/includes/ui/blocks',
				BLOCKSTUDIO_DIR . '/includes/ui/apps',
			)
		);

		if ( ! is_array( $directories ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$directories,
				function ( $directory ): bool {
					return is_string( $directory ) && '' !== $directory;
				}
			)
		);
	}
}

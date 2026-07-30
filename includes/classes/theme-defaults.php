<?php
/**
 * Theme defaults class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Applies opt-in generic defaults for themes that load Blockstudio.
 *
 * @since 7.6.0
 */
final class Theme_Defaults {

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register configured theme defaults.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		if ( Settings::get_bool( 'themeDefaults/titleTag', false ) ) {
			add_action( 'after_setup_theme', array( self::class, 'enable_title_tag' ) );
		}
		if ( Settings::get_bool( 'themeDefaults/suppressDirectoryUpdates', false ) ) {
			add_filter( 'site_transient_update_themes', array( self::class, 'suppress_directory_updates' ) );
		}
	}

	/**
	 * Enable WordPress title-tag support.
	 *
	 * @return void
	 */
	public static function enable_title_tag(): void {
		add_theme_support( 'title-tag' );
	}

	/**
	 * Remove active local themes from directory update results.
	 *
	 * @param mixed $transient Theme update transient.
	 *
	 * @return mixed Filtered transient.
	 */
	public static function suppress_directory_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		foreach ( self::active_theme_slugs() as $theme_slug ) {
			if ( isset( $transient->response ) && is_array( $transient->response ) ) {
				unset( $transient->response[ $theme_slug ] );
			}
			if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
				unset( $transient->no_update[ $theme_slug ] );
			}
		}

		return $transient;
	}

	/**
	 * Get active child and parent theme slugs.
	 *
	 * @return string[] Theme slugs.
	 */
	private static function active_theme_slugs(): array {
		return array_values(
			array_unique(
				array_filter(
					array(
						(string) get_stylesheet(),
						function_exists( 'get_template' ) ? (string) get_template() : '',
					)
				)
			)
		);
	}
}

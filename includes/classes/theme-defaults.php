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
	 * Page source signature option.
	 */
	private const PAGES_SIGNATURE_OPTION = 'blockstudio_theme_defaults_pages_signature';

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
		if ( Settings::get_bool( 'themeDefaults/titleTag', true ) ) {
			add_action( 'after_setup_theme', array( self::class, 'enable_title_tag' ) );
		}
		if ( Settings::get_bool( 'themeDefaults/suppressDirectoryUpdates', false ) ) {
			add_filter( 'site_transient_update_themes', array( self::class, 'suppress_directory_updates' ) );
		}
		if ( Settings::get_bool( 'themeDefaults/syncPagesInDevelopment', false ) ) {
			add_action( 'wp_loaded', array( self::class, 'sync_pages_in_development' ), 100 );
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
	 * Force file-backed page reconciliation when sources change locally.
	 *
	 * @return void
	 */
	public static function sync_pages_in_development(): void {
		$enabled = self::is_development_environment();
		$enabled = (bool) apply_filters( 'blockstudio/theme_defaults/sync_pages_in_development', $enabled );
		if ( ! $enabled ) {
			return;
		}

		$signature = self::pages_signature();
		if ( '' !== $signature && get_option( self::PAGES_SIGNATURE_OPTION ) === $signature ) {
			return;
		}

		Pages::init( array( 'force' => true ) );
		Pages::force_sync_all();

		if ( '' !== $signature ) {
			update_option( self::PAGES_SIGNATURE_OPTION, $signature, false );
		}
	}

	/**
	 * Build a cheap signature over configured page paths.
	 *
	 * @return string Source signature.
	 */
	private static function pages_signature(): string {
		$paths = apply_filters(
			'blockstudio/pages/paths',
			array( rtrim( (string) get_stylesheet_directory(), '/' ) . '/pages' )
		);
		$parts = array();

		foreach ( (array) $paths as $path ) {
			if ( ! is_string( $path ) || '' === $path || ! is_dir( $path ) ) {
				continue;
			}
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $files as $file ) {
				if ( $file instanceof \SplFileInfo && $file->isFile() ) {
					$parts[] = $file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize();
				}
			}
		}

		if ( array() === $parts ) {
			return '';
		}

		sort( $parts, SORT_STRING );

		return hash( 'sha256', implode( '|', $parts ) );
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

	/**
	 * Determine whether WordPress is running in a development environment.
	 *
	 * @return bool Whether development behavior is allowed.
	 */
	private static function is_development_environment(): bool {
		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return true;
		}

		return defined( 'WP_DEBUG' ) && true === WP_DEBUG;
	}
}

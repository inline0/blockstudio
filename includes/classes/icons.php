<?php
/**
 * Icons class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Locates icon sets for the icon picker and frontend rendering.
 *
 * Sets are looked up in a directory outside the plugin first, then in the
 * bundled folder. The outside directory survives plugin updates, so a set
 * stored there stays available when it is no longer part of the plugin.
 *
 * @since 7.7.16
 */
class Icons {

	/**
	 * Directory of the icon sets that ship with the plugin.
	 *
	 * @return string
	 */
	public static function bundled_dir(): string {
		return BLOCKSTUDIO_DIR . '/includes/icons';
	}

	/**
	 * Directory of the icon sets stored outside the plugin.
	 *
	 * @return string The directory, or an empty string when sets stay in the plugin only.
	 */
	public static function dir(): string {
		/**
		 * Filter the directory icon sets are stored in outside the plugin.
		 *
		 * Return an empty string to keep icon sets in the plugin folder only.
		 *
		 * @since 7.7.16
		 *
		 * @param string $dir Absolute directory path.
		 */
		$dir = apply_filters( 'blockstudio/icons/dir', WP_CONTENT_DIR . '/blockstudio/icons' );

		return is_string( $dir ) ? rtrim( wp_normalize_path( $dir ), '/' ) : '';
	}

	/**
	 * Resolve an icon set name to its JSON file.
	 *
	 * @param string $name Set name, with the sub set appended after a dash.
	 *
	 * @return string The file path, or an empty string when no set matches.
	 */
	public static function set_path( string $name ): string {
		$dir  = self::dir();
		$path = '' === $dir ? '' : Utils::icon_set_path( $dir, $name );

		return '' === $path ? Utils::icon_set_path( self::bundled_dir(), $name ) : $path;
	}

	/**
	 * Copy the bundled icon sets to the directory outside the plugin.
	 *
	 * A set that is already stored with the same content is left alone, so
	 * repeated runs only write sets that are new or changed in this version.
	 * A failed copy is not an error: the bundled set keeps resolving.
	 *
	 * @return void
	 */
	public static function store_bundled_sets(): void {
		$dir   = self::dir();
		$files = glob( self::bundled_dir() . '/*.json' );

		if ( '' === $dir || ! $files || ! wp_mkdir_p( $dir ) ) {
			return;
		}

		foreach ( $files as $file ) {
			$target = $dir . '/' . basename( $file );

			if ( is_file( $target ) && md5_file( $target ) === md5_file( $file ) ) {
				continue;
			}

			// Readers never see a partly written set: copy aside, then swap.
			$partial = $target . '.' . uniqid( '', true ) . '.tmp';

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-directory atomic swap; the bundled set remains the fallback.
			if ( ! @copy( $file, $partial ) || ! @rename( $partial, $target ) ) {
				wp_delete_file( $partial );
			}
		}
	}
}

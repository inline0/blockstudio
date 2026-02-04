<?php
/**
 * ES Modules CSS class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Exception;

/**
 * CSS imports from npm packages via esm.sh CDN.
 *
 * This class enables importing CSS files from npm packages directly
 * in block JavaScript, similar to how bundlers handle CSS imports.
 *
 * Import Syntax (recommended):
 * ```javascript
 * import "npm:swiper@10.0.0/swiper-bundle.min.css";
 * import "npm:@fancyapps/ui@5.0.0/dist/fancybox.css";
 * ```
 *
 * Legacy syntax (still supported):
 * ```javascript
 * import "blockstudio/swiper@10.0.0/swiper-bundle.min.css";
 * import "blockstudio/@fancyapps/ui@5.0.0/dist/fancybox.css";
 * ```
 *
 * How It Works:
 * 1. Regex finds blockstudio/package@version/path.css imports
 * 2. Fetches CSS from esm.sh (e.g., esm.sh/swiper@10.0.0/swiper.css)
 * 3. Caches CSS file locally in _dist/modules/
 * 4. Removes import statement from JS (CSS is loaded separately)
 *
 * Directory Structure:
 * ```
 * my-block/
 * ├── index.js                  # Contains CSS import
 * └── _dist/
 *     └── modules/
 *         └── swiper/
 *             └── 10.0.0-swiper-bundle.min.css
 * ```
 *
 * The cached CSS files are then enqueued by the Assets class as
 * part of the block's stylesheet dependencies.
 *
 * Related: ESModules handles JavaScript imports with same pattern
 *
 * @since 4.0.0
 */
class ESModulesCSS {

	/**
	 * Get regex pattern for CSS imports.
	 *
	 * Supports both npm: (recommended) and blockstudio/ (legacy) prefixes.
	 *
	 * @return string The regex pattern.
	 */
	public static function get_blockstudio_regex(): string {
		return '/import\s*["\']((?:npm:|blockstudio\/).*\.css)["\']/';
	}

	/**
	 * Replace module references in a string.
	 *
	 * @param string $str The input string.
	 *
	 * @return string The string with module references removed.
	 */
	public static function replace_module_references( string $str ): string {
		$regex = self::get_blockstudio_regex();

		return preg_replace( $regex, '', $str );
	}

	/**
	 * Match all modules in a string.
	 *
	 * @param string $str The input string.
	 *
	 * @return array Array of matched modules.
	 */
	public static function get_module_matches( string $str ): array {
		$regex = self::get_blockstudio_regex();
		preg_match_all( $regex, $str, $matches );
		$result = array();

		foreach ( $matches[1] as $match ) {
			$last_at_position = strrpos( $match, '@' );
			$name             = substr( $match, 0, $last_at_position );
			$name             = str_replace( 'npm:', '', $name );
			$name             = str_replace( 'blockstudio/', '', $name );
			$version_and_file = substr( $match, $last_at_position + 1 );

			list( $version, $filename ) = explode( '/', $version_and_file, 2 );

			$result[] = array(
				'name'            => $name,
				'nameTransformed' => str_replace( '/', '-', $name ),
				'version'         => $version,
				'nameVersion'     => $name . '@' . $version,
				'filename'        => $filename,
			);
		}

		return $result;
	}

	/**
	 * Fetch a module from esm.sh.
	 *
	 * @param array $module Module data.
	 *
	 * @return string|false The module content or false on failure.
	 */
	public static function fetch_module( $module ) {
		try {
			$response = wp_remote_get(
				"https://esm.sh/{$module['nameVersion']}/{$module['filename']}"
			);
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			return wp_remote_retrieve_body( $response );
		} catch ( Exception $error ) {
			return false;
		}
	}

	/**
	 * Fetch a module and write it to file.
	 *
	 * @param array  $module Module data.
	 * @param string $folder The base folder path.
	 *
	 * @return string|false The filename or false on failure.
	 */
	public static function fetch_module_and_write_to_file( $module, $folder ) {
		$folder_dist    = $folder . '/_dist';
		$folder_modules = $folder_dist . '/modules';
		$folder_module  = $folder_modules . '/' . $module['nameTransformed'];
		$filename       = $folder_module . '/' . $module['version'] . '-' . str_replace( '/', '-', $module['filename'] );

		if ( file_exists( $filename ) ) {
			return $filename;
		}

		$data = self::fetch_module( $module );

		if ( ! $data ) {
			return false;
		}

		if ( ! is_dir( $folder_dist ) ) {
			wp_mkdir_p( $folder_dist );
		}

		if ( ! is_dir( $folder_modules ) ) {
			wp_mkdir_p( $folder_modules );
		}

		if ( ! is_dir( $folder_module ) ) {
			wp_mkdir_p( $folder_module );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing cached module file.
		file_put_contents( $filename, $data );

		return $filename;
	}

	/**
	 * Fetch all modules and write them to files.
	 *
	 * @param string $str    The input string containing module imports.
	 * @param string $folder The base folder path.
	 *
	 * @return array Array with 'objects' and 'filenames' keys.
	 */
	public static function fetch_all_modules_and_write_to_file( $str, $folder ): array {
		$modules   = self::get_module_matches( $str );
		$objects   = array();
		$filenames = array();

		try {
			foreach ( $modules as $module ) {
				$objects[]   = $module;
				$filenames[] = self::fetch_module_and_write_to_file( $module, $folder );
			}
		} catch ( Exception $error ) {
			// Silently fail on module fetch errors.
			unset( $error );
		}

		return array(
			'objects'   => $objects,
			'filenames' => $filenames,
		);
	}
}

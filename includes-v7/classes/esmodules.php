<?php
/**
 * ES Modules class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Exception;

/**
 * ES Module import resolution and bundling from esm.sh CDN.
 *
 * This class enables importing npm packages directly in block JavaScript
 * using a custom Blockstudio syntax. Packages are fetched from esm.sh
 * and cached locally in the block's _dist/modules/ directory.
 *
 * Import Syntax:
 * ```javascript
 * import lodash from "blockstudio/lodash@4.17.21";
 * import { motion } from "blockstudio/framer-motion@10.16.4";
 * ```
 *
 * How It Works:
 * 1. During asset processing, regex finds blockstudio/package@version imports
 * 2. For development: rewrites to https://esm.sh/package@version?bundle
 * 3. For production: fetches bundle from esm.sh and caches locally
 *
 * Directory Structure:
 * ```
 * my-block/
 * ├── index.js          # Contains blockstudio/lodash@4.17.21 import
 * └── _dist/
 *     └── modules/
 *         └── lodash/
 *             └── 4.17.21.js    # Cached bundle from esm.sh
 * ```
 *
 * Benefits:
 * - No build tooling required (webpack, esbuild, etc.)
 * - Direct npm package access from block code
 * - Automatic versioning and caching
 * - Works in development and production
 *
 * Related: ESModulesCSS handles CSS imports with same pattern
 *
 * @since 4.0.0
 */
class ESModules {

	/**
	 * Get Blockstudio regex pattern for module imports.
	 *
	 * @return string The regex pattern.
	 */
	public static function get_blockstudio_regex(): string {
		return '/\bfrom\s*["\']?(blockstudio\/[^"\']*)["\']/';
	}

	/**
	 * Get HTTP regex pattern for export statements.
	 *
	 * @return string The regex pattern.
	 */
	public static function get_http_regex(): string {
		return '/^export \* from\s*"([^"]*)";$/m';
	}

	/**
	 * Match all modules in a string.
	 *
	 * @param string $str The input string.
	 * @param bool   $obj Whether to return object data.
	 *
	 * @return array|string Module data or transformed string.
	 */
	public static function get_module_matches( $str, bool $obj = false ) {
		$replacer = function ( $str ) {
			$str = str_replace( 'from"blockstudio', 'from "blockstudio', $str );

			return str_replace( "from'blockstudio", "from 'blockstudio", $str );
		};

		$getter = function ( $str ) use ( $replacer ) {
			$name_version = $replacer( trim( $str, "'\"" ) );
			$name_version = str_replace( 'blockstudio/', '', $name_version );

			$last_at_position = strrpos( $name_version, '@' );
			$name             = substr( $name_version, 0, $last_at_position );
			$version          = substr( $name_version, $last_at_position + 1 );

			return array(
				'name'            => $name,
				'nameTransformed' => str_replace( '/', '-', $name ),
				'version'         => $version,
				'nameVersion'     => $name . '@' . $version,
			);
		};

		if ( $obj ) {
			return $getter( $str );
		}

		$str = $replacer( $str );

		preg_match_all( self::get_blockstudio_regex(), $str, $matches );
		foreach ( $matches[0] as $item ) {
			$module_obj = $getter( $item );
			$str        = str_replace(
				str_replace( 'from ', '', $item ),
				"\"https://esm.sh/{$module_obj['nameVersion']}?bundle\"",
				$str
			);
		}

		return $str;
	}

	/**
	 * Get module import strings from code.
	 *
	 * @param string $str The input string.
	 *
	 * @return array Array of module strings.
	 */
	public static function get_module_strings( $str ): array {
		return preg_match_all( self::get_blockstudio_regex(), $str, $matches )
			? $matches[1]
			: array();
	}

	/**
	 * Fetch a module from esm.sh.
	 *
	 * @param string $str Module import string.
	 *
	 * @return string|false Module content or false on failure.
	 */
	public static function fetch_module( $str ) {
		$module = self::get_module_matches( $str, true );

		try {
			$response = wp_remote_get(
				"https://esm.sh/{$module['nameVersion']}?bundle"
			);
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$e = wp_remote_retrieve_body( $response );

			$http_matcher = self::get_http_regex();
			preg_match_all( $http_matcher, $e, $matches );
			$match = reset( $matches[1] );

			$url          = 0 === strpos( $match, 'http' )
				? $match
				: "https://esm.sh{$match}";
			$url_response = wp_remote_get( $url );

			if ( 200 !== wp_remote_retrieve_response_code( $url_response ) ) {
				return false;
			}

			$content = wp_remote_retrieve_body( $url_response );

			if ( false !== stripos( $content, '<html' ) ) {
				return false;
			}

			return $content;
		} catch ( Exception $error ) {
			return false;
		}
	}

	/**
	 * Fetch a module and write it to file.
	 *
	 * @param string $str    Module import string.
	 * @param string $folder The base folder path.
	 *
	 * @return string|false The filename or false on failure.
	 */
	public static function fetch_module_and_write_to_file( $str, $folder ) {
		$module         = self::get_module_matches( $str, true );
		$folder_dist    = $folder . '/_dist';
		$folder_modules = $folder_dist . '/modules';
		$folder_module  = $folder_modules . '/' . $module['nameTransformed'];
		$filename       = $folder_module . '/' . $module['version'] . '.js';

		if ( file_exists( $filename ) ) {
			return $filename;
		}

		$data = self::fetch_module( $str );

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
		$modules   = self::get_module_strings( $str );
		$objects   = array();
		$filenames = array();

		try {
			foreach ( $modules as $module ) {
				$objects[]   = self::get_module_matches( $module, true );
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

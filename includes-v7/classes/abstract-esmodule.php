<?php
/**
 * Abstract ES Module class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Exception;

/**
 * Base class for ES Module handlers (JavaScript and CSS).
 *
 * Provides shared functionality for both ESModules (JS) and ESModulesCSS
 * classes including directory management, HTTP fetching, and caching.
 *
 * Shared Responsibilities:
 * - Directory structure creation (_dist/modules/package-name/)
 * - Remote HTTP requests to esm.sh CDN
 * - File caching and existence checks
 * - Module name parsing (package@version)
 * - Name transformation for filesystem (slashes → dashes)
 *
 * Abstract Methods (implemented by subclasses):
 * - get_blockstudio_regex(): Pattern to match imports in source code
 * - get_file_extension(): The output file extension (js or css)
 * - get_module_type(): Type identifier for logging/errors
 *
 * Directory Structure Created:
 * ```
 * block-folder/
 * └── _dist/
 *     └── modules/
 *         └── package-name/
 *             ├── 1.0.0.js
 *             └── 1.0.0-file.css
 * ```
 *
 * Cache Strategy:
 * - Modules are cached by version number
 * - Files only fetched if not already cached
 * - Cache persists until manually cleared
 *
 * @since 7.0.0
 */
abstract class Abstract_ESModule {

	/**
	 * The base URL for esm.sh.
	 *
	 * @var string
	 */
	protected const ESM_BASE_URL = 'https://esm.sh';

	/**
	 * Get the regex pattern for matching module imports.
	 *
	 * @return string The regex pattern.
	 */
	abstract public static function get_blockstudio_regex(): string;

	/**
	 * Get the file extension for this module type.
	 *
	 * @return string The file extension (e.g., 'js' or 'css').
	 */
	abstract protected static function get_file_extension(): string;

	/**
	 * Get the module type name.
	 *
	 * @return string The module type (e.g., 'js' or 'css').
	 */
	abstract protected static function get_module_type(): string;

	/**
	 * Ensure the directory structure exists.
	 *
	 * @param string $dist_folder    The dist folder path.
	 * @param string $modules_folder The modules folder path.
	 * @param string $module_folder  The specific module folder path.
	 *
	 * @return void
	 */
	protected static function ensure_directories(
		string $dist_folder,
		string $modules_folder,
		string $module_folder
	): void {
		if ( ! is_dir( $dist_folder ) ) {
			wp_mkdir_p( $dist_folder );
		}

		if ( ! is_dir( $modules_folder ) ) {
			wp_mkdir_p( $modules_folder );
		}

		if ( ! is_dir( $module_folder ) ) {
			wp_mkdir_p( $module_folder );
		}
	}

	/**
	 * Get the folder structure for a module.
	 *
	 * @param string $base_folder       The base folder path.
	 * @param string $name_transformed  The transformed module name.
	 *
	 * @return array The folder structure with keys: dist, modules, module.
	 */
	protected static function get_folder_structure(
		string $base_folder,
		string $name_transformed
	): array {
		$dist_folder    = $base_folder . '/_dist';
		$modules_folder = $dist_folder . '/modules';
		$module_folder  = $modules_folder . '/' . $name_transformed;

		return array(
			'dist'    => $dist_folder,
			'modules' => $modules_folder,
			'module'  => $module_folder,
		);
	}

	/**
	 * Write content to a file.
	 *
	 * @param string $filename The file path.
	 * @param string $content  The content to write.
	 *
	 * @return bool Whether the write was successful.
	 */
	protected static function write_file( string $filename, string $content ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing cached module file.
		$result = file_put_contents( $filename, $content );

		return false !== $result;
	}

	/**
	 * Make a remote request to esm.sh.
	 *
	 * @param string $url The URL to fetch.
	 *
	 * @return string|false The response body or false on failure.
	 */
	protected static function make_remote_request( string $url ): string|false {
		try {
			$response = wp_remote_get( $url );

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$content = wp_remote_retrieve_body( $response );

			// Check for HTML responses (error pages).
			if ( false !== stripos( $content, '<html' ) ) {
				return false;
			}

			return $content;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Transform a module name to a folder-safe version.
	 *
	 * @param string $name The module name.
	 *
	 * @return string The transformed name.
	 */
	protected static function transform_name( string $name ): string {
		return str_replace( '/', '-', $name );
	}

	/**
	 * Parse a module string into name and version.
	 *
	 * @param string $name_version The module name with version (e.g., 'package@1.0.0').
	 *
	 * @return array Array with 'name' and 'version' keys.
	 */
	protected static function parse_name_version( string $name_version ): array {
		$last_at_position = strrpos( $name_version, '@' );

		if ( false === $last_at_position ) {
			return array(
				'name'    => $name_version,
				'version' => '',
			);
		}

		return array(
			'name'    => substr( $name_version, 0, $last_at_position ),
			'version' => substr( $name_version, $last_at_position + 1 ),
		);
	}

	/**
	 * Build the esm.sh URL for a module.
	 *
	 * @param string $name_version The module name with version.
	 * @param string $suffix       Optional URL suffix (e.g., '?bundle').
	 *
	 * @return string The full esm.sh URL.
	 */
	protected static function build_esm_url( string $name_version, string $suffix = '' ): string {
		return self::ESM_BASE_URL . '/' . $name_version . $suffix;
	}

	/**
	 * Check if a file already exists in the cache.
	 *
	 * @param string $filename The file path.
	 *
	 * @return bool Whether the file exists.
	 */
	protected static function is_cached( string $filename ): bool {
		return file_exists( $filename );
	}
}

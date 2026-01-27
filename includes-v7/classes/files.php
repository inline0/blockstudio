<?php
/**
 * Files class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Handles file operations.
 */
class Files {

	/**
	 * Get render template.
	 *
	 * @param string $file The file path.
	 *
	 * @return string|false The template path or false if not found.
	 */
	public static function get_render_template( $file ) {
		$directory = dirname( $file );

		if ( file_exists( $directory . '/index.php' ) ) {
			return $directory . '/index.php';
		}

		if ( file_exists( $directory . '/index.blade.php' ) ) {
			return $directory . '/index.blade.php';
		}

		if ( file_exists( $directory . '/index.twig' ) ) {
			return $directory . '/index.twig';
		}

		return false;
	}

	/**
	 * Check if string starts with.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool Whether the haystack starts with the needle.
	 */
	public static function starts_with( $haystack, $needle ): bool {
		if ( function_exists( 'str_starts_with' ) ) {
			return str_starts_with( $haystack, $needle );
		}

		return 0 === strpos( $haystack, $needle );
	}

	/**
	 * Check if string ends with.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool Whether the haystack ends with the needle.
	 */
	public static function ends_with( $haystack, $needle ): bool {
		if ( function_exists( 'str_ends_with' ) ) {
			return str_ends_with( $haystack, $needle );
		}

		$length = strlen( $needle );

		if ( ! $length ) {
			return true;
		}

		return substr( $haystack, -$length ) === $needle;
	}

	/**
	 * Check if string contains another string.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool Whether the haystack contains the needle.
	 */
	public static function contains( $haystack, $needle ): bool {
		if ( function_exists( 'str_contains' ) ) {
			return str_contains( $haystack, $needle );
		}

		return false !== strpos( $haystack, $needle );
	}

	/**
	 * Delete all files in a directory.
	 *
	 * @param string $dir        The directory path.
	 * @param bool   $delete_dir Whether to delete the directory itself.
	 *
	 * @return bool Whether the operation was successful.
	 */
	public static function delete_all_files( string $dir, bool $delete_dir = true ): bool {
		if ( false === file_exists( $dir ) ) {
			return false;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file_info ) {
			if ( $file_info->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct filesystem operation for cleanup.
				if ( false === rmdir( $file_info->getRealPath() ) ) {
					return false;
				}
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct filesystem operation for cleanup.
				if ( false === unlink( $file_info->getRealPath() ) ) {
					return false;
				}
			}
		}

		if ( $delete_dir ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct filesystem operation for cleanup.
			return rmdir( $dir );
		}

		return true;
	}

	/**
	 * Check if directory is empty.
	 *
	 * @param string $dir The directory path.
	 *
	 * @return bool|null True if empty, false if not empty or not a directory, null if not readable.
	 */
	public static function is_directory_empty( string $dir ): ?bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		if ( ! is_readable( $dir ) ) {
			return null;
		}

		$files = scandir( $dir );

		return 2 === count( $files );
	}

	/**
	 * Get relative URL for assets.
	 *
	 * @param string $url The URL or path.
	 *
	 * @return string The relative URL.
	 */
	public static function get_relative_url( $url ): string {
		$url = str_replace( '\\', '/', $url );
		$str = substr(
			$url,
			strpos(
				$url,
				substr( WP_CONTENT_DIR, strrpos( WP_CONTENT_DIR, '/' ) + 1 )
			)
		);

		return WP_CONTENT_URL .
			substr(
				$str,
				strrpos( $str, self::get_root_folder() ) + strlen( self::get_root_folder() )
			);
	}

	/**
	 * Get WordPress root folder name.
	 *
	 * @return string The root folder name.
	 */
	public static function get_root_folder(): string {
		return array_slice( explode( '/', WP_CONTENT_DIR ), -1 )[0];
	}

	/**
	 * Get files recursively and delete empty folders.
	 *
	 * @param string $dir The directory path.
	 *
	 * @return array Array of file paths.
	 */
	public static function get_files_recursively_and_delete_empty_folders( $dir ): array {
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		$directory_iterator = new RecursiveDirectoryIterator(
			$dir,
			FilesystemIterator::SKIP_DOTS
		);

		$iterator = new RecursiveIteratorIterator(
			$directory_iterator,
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file_info ) {
			if ( $file_info->isFile() ) {
				$files[] = $file_info->getPathname();
			} elseif (
				$file_info->isDir() &&
				! ( new FilesystemIterator( $file_info->getPathname() ) )->valid()
			) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct filesystem operation for cleanup.
				rmdir( $file_info->getPathname() );
			}
		}

		return $files;
	}

	/**
	 * Get all files recursively with a certain extension.
	 *
	 * @param string $dir       The directory path.
	 * @param string $extension The file extension to filter by.
	 *
	 * @return array Array of file paths.
	 */
	public static function get_files_with_extension( string $dir, string $extension ): array {
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		$directory_iterator = new RecursiveDirectoryIterator(
			$dir,
			FilesystemIterator::SKIP_DOTS
		);

		$iterator = new RecursiveIteratorIterator( $directory_iterator );

		foreach ( $iterator as $file_info ) {
			if (
				$file_info->isFile() &&
				$file_info->getExtension() === $extension
			) {
				$files[] = $file_info->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Get a folder structure as an associative array with file contents.
	 *
	 * @param string $dir The directory path.
	 *
	 * @return array|false|string The folder structure with contents.
	 */
	public static function get_folder_structure_with_contents( string $dir ) {
		$structure = array();

		if ( ! is_dir( $dir ) ) {
			return $structure;
		}

		$directory_iterator = new RecursiveDirectoryIterator(
			$dir,
			FilesystemIterator::SKIP_DOTS
		);

		$iterator = new RecursiveIteratorIterator(
			$directory_iterator,
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file_info ) {
			$inner_iterator = $iterator->getInnerIterator();

			if ( ! $inner_iterator instanceof RecursiveDirectoryIterator ) {
				continue;
			}

			$sub_path   = $inner_iterator->getSubPathName();
			$path_parts = explode( DIRECTORY_SEPARATOR, $sub_path );
			$temp       = &$structure;

			foreach ( $path_parts as $part ) {
				if ( ! isset( $temp[ $part ] ) ) {
					$temp[ $part ] = array();
				}
				$temp = &$temp[ $part ];
			}

			if ( $file_info->isFile() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local files.
				$temp = file_get_contents( $file_info->getPathname() );
			}
		}

		return $structure;
	}

	/**
	 * Get render template (legacy method name).
	 *
	 * @deprecated Use get_render_template() instead.
	 *
	 * @param string $file The file path.
	 *
	 * @return string|false The template path or false if not found.
	 */
	public static function getRenderTemplate( $file ) {
		return self::get_render_template( $file );
	}

	/**
	 * Check if string starts with (legacy method name).
	 *
	 * @deprecated Use starts_with() instead.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool Whether the haystack starts with the needle.
	 */
	public static function startsWith( $haystack, $needle ): bool {
		return self::starts_with( $haystack, $needle );
	}

	/**
	 * Check if string ends with (legacy method name).
	 *
	 * @deprecated Use ends_with() instead.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool Whether the haystack ends with the needle.
	 */
	public static function endsWith( $haystack, $needle ): bool {
		return self::ends_with( $haystack, $needle );
	}

	/**
	 * Delete all files (legacy method name).
	 *
	 * @deprecated Use delete_all_files() instead.
	 *
	 * @param string $dir        The directory path.
	 * @param bool   $delete_dir Whether to delete the directory itself.
	 *
	 * @return bool Whether the operation was successful.
	 */
	public static function deleteAllFiles( string $dir, bool $delete_dir = true ): bool {
		return self::delete_all_files( $dir, $delete_dir );
	}

	/**
	 * Check if directory is empty (legacy method name).
	 *
	 * @deprecated Use is_directory_empty() instead.
	 *
	 * @param string $dir The directory path.
	 *
	 * @return bool|null True if empty, false if not empty or not a directory, null if not readable.
	 */
	public static function isDirectoryEmpty( string $dir ): ?bool {
		return self::is_directory_empty( $dir );
	}

	/**
	 * Get relative URL (legacy method name).
	 *
	 * @deprecated Use get_relative_url() instead.
	 *
	 * @param string $url The URL or path.
	 *
	 * @return string The relative URL.
	 */
	public static function getRelativeUrl( $url ): string {
		return self::get_relative_url( $url );
	}

	/**
	 * Get root folder (legacy method name).
	 *
	 * @deprecated Use get_root_folder() instead.
	 *
	 * @return string The root folder name.
	 */
	public static function getRootFolder(): string {
		return self::get_root_folder();
	}

	/**
	 * Get files recursively and delete empty folders (legacy method name).
	 *
	 * @deprecated Use get_files_recursively_and_delete_empty_folders() instead.
	 *
	 * @param string $dir The directory path.
	 *
	 * @return array Array of file paths.
	 */
	public static function getFilesRecursivelyAndDeleteEmptyFolders( $dir ): array {
		return self::get_files_recursively_and_delete_empty_folders( $dir );
	}

	/**
	 * Get files with extension (legacy method name).
	 *
	 * @deprecated Use get_files_with_extension() instead.
	 *
	 * @param string $dir       The directory path.
	 * @param string $extension The file extension to filter by.
	 *
	 * @return array Array of file paths.
	 */
	public static function getFilesWithExtension( string $dir, string $extension ): array {
		return self::get_files_with_extension( $dir, $extension );
	}

	/**
	 * Get folder structure with contents (legacy method name).
	 *
	 * @deprecated Use get_folder_structure_with_contents() instead.
	 *
	 * @param string $dir The directory path.
	 *
	 * @return array|false|string The folder structure with contents.
	 */
	public static function getFolderStructureWithContents( string $dir ) {
		return self::get_folder_structure_with_contents( $dir );
	}
}

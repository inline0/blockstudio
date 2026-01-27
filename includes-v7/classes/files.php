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
	public static function get_render_template( string $file ): string|false {
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
	public static function get_relative_url( string $url ): string {
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
	public static function get_files_recursively_and_delete_empty_folders( string $dir ): array {
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
}

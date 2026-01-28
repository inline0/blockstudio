<?php
/**
 * Examples class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles example templates.
 */
class Examples {

	/**
	 * Get all example templates.
	 *
	 * @return array Example templates organized by subdirectory.
	 */
	public static function get(): array {
		$base_path = BLOCKSTUDIO_DIR . '/includes/templates';
		$results   = array();

		if ( is_dir( $base_path ) ) {
			$subdirectories = glob( $base_path . '/*', GLOB_ONLYDIR );
			foreach ( $subdirectories as $subdir ) {
				$subdir_name             = basename( $subdir );
				$results[ $subdir_name ] = array();
				$files                   = array_merge(
					glob( $subdir . '/*.php' ),
					glob( $subdir . '/*.twig' )
				);

				foreach ( $files as $file ) {
					$file_name = basename( $file );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
					$content                                  = file_get_contents( $file );
					$results[ $subdir_name ][ $file_name ] = $content;
				}
			}
		}

		return $results;
	}
}

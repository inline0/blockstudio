<?php
/**
 * Field Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers custom field definitions by scanning filesystem directories.
 *
 * This class handles discovering field.json files for reusable custom field
 * definitions. Each field.json must contain a 'name' and 'attributes' array.
 *
 * @since 7.0.0
 */
class Field_Discovery {

	/**
	 * Discover custom fields in a directory path.
	 *
	 * Recursively scans the given path for field.json files.
	 *
	 * @param string $base_path Absolute path to scan for fields.
	 *
	 * @return array<string, array> Array of discovered field definitions indexed by name.
	 */
	public function discover( string $base_path ): array {
		$fields = array();

		if ( ! is_dir( $base_path ) ) {
			return $fields;
		}

		$base_path = wp_normalize_path( $base_path );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_path )
		);

		foreach ( $iterator as $file ) {
			$file_path = wp_normalize_path( $file->getPathname() );
			$basename  = $file->getBasename();

			if ( 'field.json' !== $basename ) {
				continue;
			}

			$field_data = $this->process_field_json( $file_path );

			if ( $field_data ) {
				$fields[ $field_data['name'] ] = $field_data;
			}
		}

		return $fields;
	}

	/**
	 * Process a field.json file.
	 *
	 * @param string $json_path Path to the field.json file.
	 *
	 * @return array|null The field data or null if invalid.
	 */
	private function process_field_json( string $json_path ): ?array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
		$contents = file_get_contents( $json_path );

		if ( false === $contents ) {
			return null;
		}

		$field_json = json_decode( $contents, true );

		if ( ! is_array( $field_json ) || empty( $field_json['name'] ) || empty( $field_json['attributes'] ) ) {
			return null;
		}

		return $field_json;
	}
}

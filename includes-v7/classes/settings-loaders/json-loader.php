<?php
/**
 * JSON Loader class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Settings_Loaders;

use Blockstudio\Interfaces\Settings_Loader_Interface;
use Exception;

/**
 * Loads settings from JSON files.
 *
 * @since 7.0.0
 */
class Json_Loader implements Settings_Loader_Interface {

	/**
	 * The JSON file path.
	 *
	 * @var string
	 */
	private string $file_path;

	/**
	 * The loader priority.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Constructor.
	 *
	 * @param string $file_path The JSON file path.
	 * @param int    $priority  The loader priority (default: 5).
	 */
	public function __construct( string $file_path, int $priority = 5 ) {
		$this->file_path = $file_path;
		$this->priority  = $priority;
	}

	/**
	 * Load settings from JSON file.
	 *
	 * @return array The loaded settings.
	 */
	public function load(): array {
		if ( ! $this->is_available() ) {
			return array();
		}

		$contents = file_get_contents( $this->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $contents ) {
			return array();
		}

		try {
			$data = json_decode( $contents, true );
			return is_array( $data ) ? $data : array();
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get the priority for this loader.
	 *
	 * @return int The priority.
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Check if this loader is available.
	 *
	 * @return bool Whether the loader can be used.
	 */
	public function is_available(): bool {
		return file_exists( $this->file_path ) && is_readable( $this->file_path );
	}

	/**
	 * Get the file path.
	 *
	 * @return string The file path.
	 */
	public function get_file_path(): string {
		return $this->file_path;
	}

	/**
	 * Save settings to JSON file.
	 *
	 * @param array $settings The settings to save.
	 *
	 * @return bool Whether the save was successful.
	 */
	public function save( array $settings ): bool {
		$directory = dirname( $this->file_path );

		if ( ! is_dir( $directory ) ) {
			if ( ! wp_mkdir_p( $directory ) ) {
				return false;
			}
		}

		$json = wp_json_encode( $settings, JSON_PRETTY_PRINT );

		if ( false === $json ) {
			return false;
		}

		$result = file_put_contents( $this->file_path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return false !== $result;
	}
}

<?php
/**
 * Options Loader class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Settings_Loaders;

use Blockstudio\Interfaces\Settings_Loader_Interface;

/**
 * Settings loader for WordPress wp_options table.
 *
 * This loader reads and writes Blockstudio settings from the WordPress
 * options table, providing persistent database-backed configuration.
 *
 * Option Name: blockstudio_settings (configurable)
 *
 * Storage Format:
 * Settings are stored as a nested associative array:
 * ```php
 * [
 *   'assets' => ['enqueue' => true, 'minify' => ['css' => true]],
 *   'editor' => ['formatOnSave' => true],
 * ]
 * ```
 *
 * Access Pattern:
 * Uses forward-slash notation for nested keys:
 * ```php
 * $loader->get('assets/minify/css'); // true
 * $loader->get('missing/key', 'default'); // 'default'
 * ```
 *
 * Priority:
 * In the Settings class loader chain, Options_Loader typically has
 * the lowest priority (runs first), allowing JSON and Filter loaders
 * to override database values.
 *
 * Persistence:
 * Unlike JSON or Filter loaders, changes via save() persist to the
 * database and survive plugin deactivation/reactivation.
 *
 * @since 7.0.0
 */
class Options_Loader implements Settings_Loader_Interface {

	/**
	 * The option name to load from.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * The loader priority.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Constructor.
	 *
	 * @param string $option_name The option name (default: 'blockstudio').
	 * @param int    $priority    The loader priority (default: 10).
	 */
	public function __construct( string $option_name = 'blockstudio', int $priority = 10 ) {
		$this->option_name = $option_name;
		$this->priority    = $priority;
	}

	/**
	 * Load settings from WordPress options.
	 *
	 * @return array The loaded settings.
	 */
	public function load(): array {
		$options = get_option( $this->option_name, array() );

		if ( ! is_array( $options ) ) {
			return array();
		}

		return $options;
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
		return function_exists( 'get_option' );
	}

	/**
	 * Save settings to WordPress options.
	 *
	 * @param array $settings The settings to save.
	 *
	 * @return bool Whether the save was successful.
	 */
	public function save( array $settings ): bool {
		return update_option( $this->option_name, $settings );
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     The setting key (supports dot notation).
	 * @param mixed  $default The default value.
	 *
	 * @return mixed The setting value.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$settings = $this->load();
		$keys     = explode( '/', $key );
		$value    = $settings;

		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
	}
}

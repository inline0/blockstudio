<?php
/**
 * Filter Loader class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Settings_Loaders;

use Blockstudio\Interfaces\Settings_Loader_Interface;

/**
 * Loads settings from WordPress filters.
 *
 * @since 7.0.0
 */
class Filter_Loader implements Settings_Loader_Interface {

	/**
	 * The filter name.
	 *
	 * @var string
	 */
	private string $filter_name;

	/**
	 * The loader priority.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Base settings to pass to filter.
	 *
	 * @var array
	 */
	private array $base_settings;

	/**
	 * Constructor.
	 *
	 * @param string $filter_name   The filter name (default: 'blockstudio/settings').
	 * @param int    $priority      The loader priority (default: 20).
	 * @param array  $base_settings Base settings to pass to filter.
	 */
	public function __construct(
		string $filter_name = 'blockstudio/settings',
		int $priority = 20,
		array $base_settings = array()
	) {
		$this->filter_name   = $filter_name;
		$this->priority      = $priority;
		$this->base_settings = $base_settings;
	}

	/**
	 * Load settings from WordPress filter.
	 *
	 * @return array The loaded settings.
	 */
	public function load(): array {
		if ( ! $this->is_available() ) {
			return $this->base_settings;
		}

		$settings = apply_filters( $this->filter_name, $this->base_settings );

		if ( ! is_array( $settings ) ) {
			return $this->base_settings;
		}

		return $settings;
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
		return function_exists( 'apply_filters' ) && has_filter( $this->filter_name );
	}

	/**
	 * Set base settings.
	 *
	 * @param array $settings The base settings.
	 *
	 * @return void
	 */
	public function set_base_settings( array $settings ): void {
		$this->base_settings = $settings;
	}

	/**
	 * Get the filter name.
	 *
	 * @return string The filter name.
	 */
	public function get_filter_name(): string {
		return $this->filter_name;
	}
}

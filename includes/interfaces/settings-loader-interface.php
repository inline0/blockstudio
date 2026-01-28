<?php
/**
 * Settings Loader Interface.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Interfaces;

/**
 * Interface for settings loaders.
 *
 * Allows loading settings from different sources (options, JSON files, filters).
 *
 * @since 7.0.0
 */
interface Settings_Loader_Interface {

	/**
	 * Load settings from this source.
	 *
	 * @return array The loaded settings.
	 */
	public function load(): array;

	/**
	 * Get the priority for this loader.
	 *
	 * Higher priority loaders are processed later and can override earlier ones.
	 *
	 * @return int The priority (default is 10).
	 */
	public function get_priority(): int;

	/**
	 * Check if this loader is available.
	 *
	 * @return bool Whether the loader can be used.
	 */
	public function is_available(): bool;
}

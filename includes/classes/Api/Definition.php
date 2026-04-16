<?php
/**
 * Public API definition interface.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api;

/**
 * Converts PHP-native API definitions into legacy array definitions.
 */
interface Definition {

	/**
	 * Convert the definition into the legacy array format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array;
}

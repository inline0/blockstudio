<?php
/**
 * Public definition interface.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Converts PHP-native definitions into legacy array definitions.
 */
interface Definition {

	/**
	 * Convert the definition into the legacy array format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array;
}

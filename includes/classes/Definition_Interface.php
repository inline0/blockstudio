<?php
/**
 * Definition interface.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Converts PHP-native definition objects into legacy array definitions.
 */
interface Definition_Interface {

	/**
	 * Convert the definition into the legacy array format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array;
}

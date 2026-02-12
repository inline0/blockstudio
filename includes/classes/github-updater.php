<?php
/**
 * GitHub Updater class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioVendor\YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Checks GitHub releases for plugin updates using plugin-update-checker.
 *
 * @since 7.0.0
 */
class Github_Updater {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$update_checker = PucFactory::buildUpdateChecker(
			'https://github.com/inline0/blockstudio/',
			BLOCKSTUDIO_FILE,
			'blockstudio'
		);

		$update_checker->getVcsApi()->enableReleaseAssets();
	}
}

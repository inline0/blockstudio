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
		require_once BLOCKSTUDIO_DIR . '/lib/puc-autoload.php';

		$update_checker = PucFactory::buildUpdateChecker(
			'https://github.com/inline0/blockstudio/',
			BLOCKSTUDIO_FILE,
			'blockstudio'
		);

		$update_checker->getVcsApi()->enableReleaseAssets();

		$update_checker->addResultFilter(
			function ( $info ) {
				$info->icons = array(
					'svg'     => BLOCKSTUDIO_URL . 'includes/assets/icon.svg',
					'default' => BLOCKSTUDIO_URL . 'includes/assets/icon.png',
				);
				return $info;
			}
		);
	}
}

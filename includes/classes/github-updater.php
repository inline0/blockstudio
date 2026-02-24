<?php
/**
 * GitHub Updater class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

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

		$update_checker->addResultFilter(
			function ( $info ) {
				$info->icons = array(
					'svg'     => plugins_url( 'includes/assets/icon.svg', BLOCKSTUDIO_FILE ),
					'default' => plugins_url( 'includes/assets/icon.png', BLOCKSTUDIO_FILE ),
				);
				return $info;
			}
		);
	}
}

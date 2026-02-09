<?php
/**
 * Devtools class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Frontend devtools for identifying block template files.
 *
 * Enqueues a grabber script on the frontend for logged-in editors.
 * Hold Cmd+C (Mac) or Ctrl+C (Windows) to activate a crosshair selector
 * that highlights Blockstudio blocks and copies the template file path
 * to the clipboard on release.
 *
 * @since 7.0.0
 */
class Devtools {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue devtools script on the frontend.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! Settings::get( 'tooling/devtools/enabled' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feature toggle, no state change.
		if ( ! isset( $_GET['blockstudio-devtools'] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$asset_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/devtools/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'blockstudio-devtools',
			plugins_url( 'includes/admin/assets/devtools/index.js', BLOCKSTUDIO_FILE ),
			array(),
			$asset['version'] ?? BLOCKSTUDIO_VERSION,
			true
		);
	}
}

new Devtools();

<?php
/**
 * Configurator class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles the Blockstudio configurator shortcode.
 */
class Configurator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'blockstudio-configurator', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the configurator shortcode.
	 *
	 * @return string The shortcode HTML output.
	 */
	public function render_shortcode(): string {
		Admin::assets();

		$block_scripts = include BLOCKSTUDIO_DIR . '/includes/admin/assets/configurator/index.tsx.asset.php';
		wp_enqueue_script(
			'blockstudio-configurator',
			plugin_dir_url( __FILE__ ) . '../admin/assets/configurator/index.tsx.js',
			$block_scripts['dependencies'],
			$block_scripts['version'],
			true
		);

		wp_localize_script(
			'blockstudio-configurator',
			'blockstudioAdmin',
			Admin::data( false )
		);

		$site = esc_attr( get_site_url() );

		return "<style>body { background: #fff !important; }</style><div class='configurator' style='width: 100%;' data-site='" . $site . "'></div>";
	}
}

new Configurator();

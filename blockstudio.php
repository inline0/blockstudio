<?php
/**
 * Plugin Name: Blockstudio
 * Plugin URI: https://blockstudio.dev
 * Description: The block framework for WordPress.
 * Author: Blockstudio
 * Version: 7.0.6
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package Blockstudio
 */

if ( defined( 'BLOCKSTUDIO_VERSION' ) ) {
	return;
}

define( 'BLOCKSTUDIO_VERSION', '7.0.6' );
define( 'BLOCKSTUDIO_FILE', __FILE__ );
define( 'BLOCKSTUDIO_DIR', __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'Blockstudio\\';
		$base_dir = __DIR__ . '/includes/classes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

require_once BLOCKSTUDIO_DIR . '/vendor/autoload.php';
require_once BLOCKSTUDIO_DIR . '/lib/tailwindphp-autoload.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/functions/functions.php';
require_once __DIR__ . '/includes/functions/placeholders.php';

/**
 * Get the Plugin instance.
 *
 * @return \Blockstudio\Plugin
 */
function blockstudio(): \Blockstudio\Plugin {
	return \Blockstudio\Plugin::get_instance();
}

blockstudio();

add_filter(
	'block_categories_all',
	function ( $categories ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'blockstudio',
					'title' => __( 'Blockstudio', 'blockstudio' ),
				),
			)
		);
	},
	10,
	2
);

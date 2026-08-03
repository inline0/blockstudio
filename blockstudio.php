<?php
/**
 * Plugin Name: Blockstudio
 * Plugin URI: https://blockstudio.dev
 * Description: The block framework for WordPress.
 * Author: Blockstudio
 * Version: 7.6.6
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package Blockstudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( defined( 'BLOCKSTUDIO_VERSION' ) ) {
	return;
}

/**
 * Get a path relative to a containing directory.
 *
 * @param string $base_dir Base directory.
 * @param string $path     Path that may be inside the base directory.
 *
 * @return string|null Relative path with a leading slash, or null when the
 *                     path is outside the base directory.
 */
function blockstudio_get_relative_path( string $base_dir, string $path ): ?string {
	$base_dir = rtrim( wp_normalize_path( $base_dir ), '/' );
	$path     = rtrim( wp_normalize_path( $path ), '/' );

	$is_windows_path = (bool) preg_match( '/^[A-Za-z]:\//', $base_dir );
	$comparison_base = $is_windows_path ? strtolower( $base_dir ) : $base_dir;
	$comparison_path = $is_windows_path ? strtolower( $path ) : $path;

	if ( $comparison_path === $comparison_base ) {
		return '';
	}

	if ( ! str_starts_with( $comparison_path, $comparison_base . '/' ) ) {
		return null;
	}

	return substr( $path, strlen( $base_dir ) );
}

/**
 * Get the plugin path relative to the WordPress content directory.
 *
 * Kept for compatibility with integrations that use the bootstrap helper.
 *
 * @param string $content_dir WordPress content directory.
 * @param string $plugin_dir  Blockstudio plugin directory.
 *
 * @return string
 */
function blockstudio_get_relative_plugin_path( string $content_dir, string $plugin_dir ): string {
	return blockstudio_get_relative_path( $content_dir, $plugin_dir )
		?? wp_normalize_path( $plugin_dir );
}

/**
 * Resolve a filesystem path against public directory-to-URL mappings.
 *
 * @param string $plugin_dir Blockstudio plugin directory.
 * @param array  $locations  Public locations with dir and url keys.
 *
 * @return string|null Public URL, or null when no location contains the path.
 */
function blockstudio_resolve_plugin_url( string $plugin_dir, array $locations ): ?string {
	foreach ( $locations as $location ) {
		if (
			! is_array( $location ) ||
			! is_string( $location['dir'] ?? null ) ||
			! is_string( $location['url'] ?? null )
		) {
			continue;
		}

		$relative = blockstudio_get_relative_path( $location['dir'], $plugin_dir );

		if ( null !== $relative ) {
			return trailingslashit( rtrim( $location['url'], '/' ) . $relative );
		}
	}

	return null;
}

define( 'BLOCKSTUDIO_VERSION', '7.6.6' );
define( 'BLOCKSTUDIO_FILE', __FILE__ );
define( 'BLOCKSTUDIO_DIR', __DIR__ );

if ( ! defined( 'BLOCKSTUDIO_URL' ) ) {
	$blockstudio_public_locations = array(
		array(
			'dir' => WP_CONTENT_DIR,
			'url' => content_url(),
		),
	);

	foreach (
		array(
			array( 'get_stylesheet_directory', 'get_stylesheet_directory_uri' ),
			array( 'get_template_directory', 'get_template_directory_uri' ),
		) as $blockstudio_theme_location
	) {
		if ( ! is_callable( $blockstudio_theme_location[0] ) || ! is_callable( $blockstudio_theme_location[1] ) ) {
			continue;
		}

		$blockstudio_theme_dir      = call_user_func( $blockstudio_theme_location[0] );
		$blockstudio_theme_real_dir = realpath( $blockstudio_theme_dir );

		$blockstudio_public_locations[] = array(
			'dir' => $blockstudio_theme_real_dir ? $blockstudio_theme_real_dir : $blockstudio_theme_dir,
			'url' => call_user_func( $blockstudio_theme_location[1] ),
		);
	}

	$blockstudio_url = blockstudio_resolve_plugin_url( BLOCKSTUDIO_DIR, $blockstudio_public_locations );

	if ( null === $blockstudio_url ) {
		$blockstudio_url = plugins_url( '', BLOCKSTUDIO_FILE );
	}

	/**
	 * Filter the public Blockstudio package URL.
	 *
	 * @param string $url Public URL with a trailing slash.
	 * @param string $dir Physical Blockstudio package directory.
	 */
	$blockstudio_url = apply_filters(
		'blockstudio/url',
		trailingslashit( set_url_scheme( $blockstudio_url ) ),
		BLOCKSTUDIO_DIR
	);

	define( 'BLOCKSTUDIO_URL', trailingslashit( $blockstudio_url ) );
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
		$relative_path  = implode(
			'/',
			array_map(
				function ( string $segment ): string {
					$segment = str_replace( '_', '-', $segment );
					$segment = preg_replace( '/(?<!^)[A-Z]/', '-$0', $segment );

					return strtolower( preg_replace( '/-+/', '-', $segment ) );
				},
				explode( '\\', $relative_class )
			)
		);
		$file           = $base_dir . $relative_path . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

if ( file_exists( BLOCKSTUDIO_DIR . '/vendor/autoload.php' ) ) {
	require_once BLOCKSTUDIO_DIR . '/vendor/autoload.php';
} else {
	// Composer install: autoloader is at vendor/autoload.php (two levels up).
	$blockstudio_autoloader = dirname( __DIR__, 2 ) . '/autoload.php';
	if ( file_exists( $blockstudio_autoloader ) ) {
		require_once $blockstudio_autoloader;
	}
}
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

register_deactivation_hook( __FILE__, array( 'Blockstudio\Cron', 'unschedule_all' ) );
register_deactivation_hook( __FILE__, array( 'Blockstudio\Static_Prerender_Runtime', 'deactivate' ) );

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
	}
);

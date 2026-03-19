<?php
/**
 * PHPUnit bootstrap with full WordPress loaded via wp-env.
 *
 * Run with: npx @wordpress/env run cli --env-cwd=/var/www/html/wp-content/plugins/blockstudio7 vendor/bin/phpunit
 */

// Load WordPress.
$wp_load = '/var/www/html/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	echo "WordPress not found at {$wp_load}. Run tests inside wp-env.\n";
	exit( 1 );
}

$_SERVER['HTTP_HOST']   = 'localhost:8888';
$_SERVER['REQUEST_URI'] = '/';

require_once $wp_load;

// Force Blockstudio to initialize blocks.
if ( class_exists( 'Blockstudio\Build' ) ) {
	Blockstudio\Build::refresh_blocks();
}

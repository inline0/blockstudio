<?php
/**
 * Blockstudio Test Theme functions.
 *
 * @package Blockstudio_Test_Theme
 */

// Load Composer autoloader.
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

// Initialize Timber.
if ( class_exists( 'Timber\Timber' ) ) {
	Timber\Timber::init();
}

// Add theme support for block editor.
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'editor-styles' );
		add_theme_support( 'wp-block-styles' );
	}
);

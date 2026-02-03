<?php
/**
 * Blockstudio Test Theme functions.
 *
 * @package Blockstudio_Test_Theme
 */

// Load Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
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

// Enqueue test classes CSS for E2E tests.
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_style(
			'test-classes',
			get_stylesheet_directory_uri() . '/test-classes.css',
			array(),
			'1.0.0'
		);
	}
);

// Also enqueue on admin for Blockstudio to parse.
add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'test-classes',
			get_stylesheet_directory_uri() . '/test-classes.css',
			array(),
			'1.0.0'
		);
	}
);

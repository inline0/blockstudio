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

// Blade template rendering.
add_filter(
	'blockstudio/blocks/render',
	function ( $value, $block ) {
		$path = $block->blockstudio['data']['path'] ?? '';

		if ( ! str_ends_with( $path, '.blade.php' ) ) {
			return $value;
		}

		if ( ! class_exists( '\Jenssegers\Blade\Blade' ) ) {
			return 'Error: Blade class not found.';
		}

		$data       = $block->blockstudio['data'];
		$blade_data = $data['blade'] ?? array();
		$blade_path = $blade_data['path'] ?? '';

		if ( empty( $blade_path ) || empty( $blade_data['templates'] ) ) {
			return $value;
		}

		$blade = new \Jenssegers\Blade\Blade( $blade_path, sys_get_temp_dir() );

		$template_name = $blade_data['templates'][ $block->name ] ?? '';
		if ( empty( $template_name ) ) {
			return $value;
		}

		return $blade->render(
			$template_name,
			array(
				'a'          => $data['attributes'],
				'attributes' => $data['attributes'],
				'b'          => $data['block'],
				'block'      => $data['block'],
				'c'          => $data['context'],
				'context'    => $data['context'],
			)
		);
	},
	10,
	2
);

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

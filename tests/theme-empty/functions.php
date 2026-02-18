<?php
/**
 * Blockstudio Empty Test Theme functions.
 *
 * @package Blockstudio_Empty_Test_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'editor-styles' );
		add_theme_support( 'wp-block-styles' );
	}
);

add_filter(
	'blockstudio/settings/blocks/paths',
	function ( $paths ) {
		$theme_blockstudio_path = get_stylesheet_directory() . '/blockstudio';

		if ( is_dir( $theme_blockstudio_path ) ) {
			$paths[] = $theme_blockstudio_path;
		}

		return $paths;
	},
	10,
	1
);

add_filter(
	'blockstudio/pages/paths',
	function ( $paths ) {
		$theme_pages_path = get_stylesheet_directory() . '/pages';

		if ( is_dir( $theme_pages_path ) ) {
			$paths[] = $theme_pages_path;
		}

		return $paths;
	},
	10,
	1
);

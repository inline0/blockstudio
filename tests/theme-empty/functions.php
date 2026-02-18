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

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'blockstudio-test/v1',
			'/cleanup',
			array(
				'methods'             => 'POST',
				'callback'            => function () {
					$posts   = get_posts(
						array(
							'post_type'   => 'any',
							'post_status' => 'any',
							'numberposts' => -1,
							'fields'      => 'ids',
							'meta_key'    => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						)
					);
					$deleted = 0;
					foreach ( $posts as $post_id ) {
						wp_delete_post( $post_id, true );
						++$deleted;
					}
					return array( 'deleted' => $deleted );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

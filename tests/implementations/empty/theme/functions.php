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
	'wp_footer',
	function () {
		if ( ! defined( 'BLOCKSTUDIO_VERSION' ) ) {
			$status  = 'NOT LOADED';
			$bg      = '#dc3545';
			$details = '';
		} else {
			$status  = 'v' . BLOCKSTUDIO_VERSION;
			$bg      = '#198754';
			$details = BLOCKSTUDIO_DIR;
		}
		printf(
			'<div style="background:%s;color:#fff;padding:12px 20px;font-family:monospace;font-size:14px;position:fixed;bottom:0;left:0;right:0;z-index:99999">Blockstudio: <strong>%s</strong> · Port %s <span style="opacity:.7;margin-left:12px">%s</span></div>',
			$bg,
			esc_html( $status ),
			esc_html( $_SERVER['SERVER_PORT'] ?? '' ), esc_html( $details )
		);
	}
);

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
			'/class-exists',
			array(
				'methods'             => 'GET',
				'callback'            => function ( $request ) {
					$class = $request->get_param( 'class' );
					return array( 'exists' => class_exists( $class, false ) );
				},
				'permission_callback' => '__return_true',
				'args'                => array(
					'class' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

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

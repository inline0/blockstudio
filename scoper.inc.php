<?php
/**
 * PHP-Scoper configuration.
 *
 * @package Blockstudio
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return array(
	'prefix'   => 'BlockstudioVendor',

	'finders'  => array(
		Finder::create()
			->files()
			->in( 'vendor/scssphp' ),
		Finder::create()
			->files()
			->in( 'vendor/matthiasmullie' ),
		Finder::create()
			->files()
			->in( 'vendor/league' ),
		Finder::create()
			->files()
			->in( 'vendor/symfony' ),
		Finder::create()
			->files()
			->in( 'vendor/tailwindphp' ),
		Finder::create()
			->files()
			->in( 'vendor/yahnis-elsts' ),
	),

	'exclude-classes'   => array(
		'WP_Error',
	),
	'exclude-functions' => array(
		'wp_normalize_path',
		'wp_rand',
		'wp_remote_get',
		'wp_remote_post',
		'wp_remote_request',
		'wp_remote_retrieve_body',
		'wp_remote_retrieve_response_code',
		'wp_remote_retrieve_headers',
		'wp_parse_url',
		'wp_strip_all_tags',
		'wp_get_installed_translations',
		'get_file_data',
		'get_plugin_data',
		'get_plugins',
		'get_core_updates',
		'get_user_locale',
		'get_locale',
		'get_submit_button',
		'is_admin',
		'apply_filters',
		'add_filter',
		'add_action',
		'do_action',
		'remove_filter',
		'remove_action',
		'current_user_can',
		'esc_html',
		'esc_url',
		'esc_attr',
		'sanitize_text_field',
		'_cleanup_header_comment',
		'user_sanitize',
		'encodeit',
		'decodeit',
	),

	'patchers' => array(
		function ( string $file_path, string $prefix, string $content ): string {
			$content = str_replace(
				'implements \\ScssPhp\\ScssPhp\\',
				'implements \\' . $prefix . '\\ScssPhp\\ScssPhp\\',
				$content
			);

			if ( str_contains( $file_path, 'load-v5p6.php' ) ) {
				$content = str_replace(
					"'" . $prefix . '\\Plugin\\UpdateChecker\'',
					"'Plugin\\UpdateChecker'",
					$content
				);
				$content = str_replace(
					"'" . $prefix . '\\Theme\\UpdateChecker\'',
					"'Theme\\UpdateChecker'",
					$content
				);
				$content = str_replace(
					"'" . $prefix . '\\Vcs\\PluginUpdateChecker\'',
					"'Vcs\\PluginUpdateChecker'",
					$content
				);
				$content = str_replace(
					"'" . $prefix . '\\Vcs\\ThemeUpdateChecker\'',
					"'Vcs\\ThemeUpdateChecker'",
					$content
				);
			}

			return $content;
		},
	),
);

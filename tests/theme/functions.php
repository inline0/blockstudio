<?php
/**
 * Blockstudio Test Theme functions.
 *
 * @package Blockstudio_Test_Theme
 */

require_once __DIR__ . '/test-helper.php';

add_action(
	'wp_body_open',
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
			'<div style="background:%s;color:#fff;padding:12px 20px;font-family:monospace;font-size:14px;position:fixed;top:0;left:0;right:0;z-index:99999">Blockstudio: <strong>%s</strong> · Port %s <span style="opacity:.7;margin-left:12px">%s</span></div>',
			$bg,
			esc_html( $status ),
			esc_html( $_SERVER['SERVER_PORT'] ?? '' ), esc_html( $details )
		);
	}
);

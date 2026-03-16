<?php
/**
 * Test block server functions.
 *
 * @package Blockstudio
 */

return array(
	'greet'  => function ( array $params ): array {
		$name = sanitize_text_field( $params['name'] ?? 'World' );
		return array( 'message' => 'Hello, ' . $name . '!' );
	},
	'add'    => function ( array $params ): array {
		$a = (int) ( $params['a'] ?? 0 );
		$b = (int) ( $params['b'] ?? 0 );
		return array( 'result' => $a + $b );
	},
	'public' => array(
		'callback' => function ( array $params ): array {
			return array( 'public' => true, 'echo' => $params['value'] ?? null );
		},
		'public'   => true,
	),
	'open_endpoint' => array(
		'callback' => function ( array $params ): array {
			return array( 'open' => true, 'echo' => $params['value'] ?? null );
		},
		'public'   => 'open',
	),
	'admin_only' => array(
		'callback'   => function ( array $params ): array {
			return array( 'admin' => true );
		},
		'capability' => 'manage_options',
	),
	'editor_up' => array(
		'callback'   => function ( array $params ): array {
			return array( 'editor' => true );
		},
		'capability' => array( 'edit_posts', 'manage_options' ),
	),
	'get_status' => array(
		'callback' => function ( array $params ): array {
			return array( 'status' => 'ok' );
		},
		'methods' => array( 'GET', 'POST' ),
	),
);

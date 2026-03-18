<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads Block_Tags and its dependencies without WordPress.
 * Stubs minimal WP functions and classes used by builders.
 */

// Stub WordPress functions.
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

// Stub Build class.
require_once __DIR__ . '/stubs.php';

$classes_dir = __DIR__ . '/../../includes/classes/';

// Renderer traits.
foreach ( glob( $classes_dir . 'block-tags-renderers/trait-*-renderer.php' ) as $trait ) {
	require_once $trait;
}

// Core classes.
require_once $classes_dir . 'html-parser.php';
require_once $classes_dir . 'block-tags.php';

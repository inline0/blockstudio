<?php
/**
 * LLM class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles LLM context serving.
 */
class LLM {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'serve' ) );
	}

	/**
	 * Get .txt URL.
	 *
	 * @return string The URL to the LLM context file.
	 */
	public static function get_txt_url(): string {
		return site_url() . '/blockstudio-llm.txt';
	}

	/**
	 * Serve the static LLM context file.
	 *
	 * @return void
	 */
	public function serve(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		if ( ! str_ends_with( $request_uri, '/blockstudio-llm.txt' ) ) {
			return;
		}

		if ( ! Settings::get( 'ai/enableContextGeneration' ) ) {
			return;
		}

		$file = BLOCKSTUDIO_DIR . '/includes/llm/blockstudio-llm.txt';

		if ( ! file_exists( $file ) ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		status_header( 200 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming local static file.
		readfile( $file );
		die();
	}
}

new LLM();

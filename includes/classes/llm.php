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
		if ( ! Settings::get_bool( 'ai/enableContextGeneration', false ) ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'serve' ) );
	}

	/**
	 * Routes served by this class, mapped to their static file.
	 *
	 * The index is the primary context file. The full text stays addressable
	 * for tools that want the complete corpus in one request.
	 *
	 * @var array<string, string>
	 */
	private const FILES = array(
		'/blockstudio-llm-full.txt' => 'blockstudio-llm-full.txt',
		'/blockstudio-llm.txt'      => 'blockstudio-llm.txt',
	);

	/**
	 * Get .txt URL.
	 *
	 * @return string The URL to the LLM documentation index.
	 */
	public static function get_txt_url(): string {
		return site_url() . '/blockstudio-llm.txt';
	}

	/**
	 * Get full text .txt URL.
	 *
	 * @return string The URL to the full LLM context file.
	 */
	public static function get_full_txt_url(): string {
		return site_url() . '/blockstudio-llm-full.txt';
	}

	/**
	 * Serve the static LLM context files.
	 *
	 * @return void
	 */
	public function serve(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$file = '';

		foreach ( self::FILES as $route => $filename ) {
			if ( str_ends_with( $request_uri, $route ) ) {
				$file = BLOCKSTUDIO_DIR . '/includes/llm/' . $filename;
				break;
			}
		}

		if ( '' === $file ) {
			return;
		}

		if ( ! Settings::get( 'ai/enableContextGeneration' ) ) {
			return;
		}

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

<?php
/**
 * Register class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles block and asset registration.
 */
class Register {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ), PHP_INT_MAX );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register all Blockstudio blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		foreach ( Build::blocks() as $block ) {
			register_block_type(
				apply_filters( 'blockstudio/blocks/meta', $block )
			);
		}
	}

	/**
	 * Register block assets (scripts and styles).
	 *
	 * @return void
	 */
	public function register_assets(): void {
		foreach ( Build::assets() as $type => $assets ) {
			foreach ( $assets as $handle => $data ) {
				if ( 'script' === $type ) {
					wp_register_script(
						$handle,
						$data['path'],
						array(),
						$data['mtime'],
						true
					);
				} else {
					wp_register_style(
						$handle,
						$data['path'],
						array(),
						$data['mtime']
					);
				}
			}
		}
	}
}

new Register();

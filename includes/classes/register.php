<?php
/**
 * Register class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * WordPress block and asset registration hooks.
 *
 * This class hooks into WordPress to register Blockstudio blocks
 * and their assets with the WordPress system.
 *
 * Hooks Used:
 * - init (PHP_INT_MAX): Register blocks after all plugins loaded
 * - wp_enqueue_scripts: Pre-register assets for conditional loading
 *
 * Block Registration:
 * Iterates through Build::blocks() and calls register_block_type()
 * for each. The blockstudio/blocks/meta filter allows modification
 * of block metadata before registration.
 *
 * Asset Registration:
 * Pre-registers all block scripts and styles so they can be
 * conditionally enqueued when blocks are rendered. Assets are
 * registered (not enqueued) to enable on-demand loading.
 *
 * Why PHP_INT_MAX Priority:
 * Blocks are registered last to ensure all other plugins have
 * registered their blocks first. This is important for overrides
 * and extensions that need to modify existing blocks.
 *
 * @since 1.0.0
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

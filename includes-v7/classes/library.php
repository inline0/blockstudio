<?php
/**
 * Library class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles the Blockstudio element library.
 */
class Library {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'block_categories_all', array( $this, 'add_block_category' ), 10, 2 );
		add_action( 'init', array( $this, 'init_library' ) );
		add_filter( 'blockstudio/blocks/meta', array( $this, 'add_element_icon' ) );
	}

	/**
	 * Add the Blockstudio Elements block category.
	 *
	 * @param array    $categories Existing block categories.
	 * @param \WP_Post $post       Current post object.
	 *
	 * @return array Modified categories.
	 */
	public function add_block_category( $categories, $post ): array {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'blockstudio-elements',
					'title' => __( 'Blockstudio Elements', 'blockstudio' ),
				),
			)
		);
	}

	/**
	 * Initialize the element library if enabled.
	 *
	 * @return void
	 */
	public function init_library(): void {
		if ( Settings::get( 'library' ) ) {
			Build::init(
				array(
					'dir'     => BLOCKSTUDIO_DIR . '/includes-v7/library',
					'library' => true,
				)
			);
		}
	}

	/**
	 * Add custom icon to Blockstudio element blocks.
	 *
	 * @param object $block The block metadata object.
	 *
	 * @return object Modified block metadata.
	 */
	public function add_element_icon( $block ) {
		if ( 0 === strpos( $block->name, 'blockstudio-element' ) ) {
			$block->blockstudio['icon'] = '<svg style="padding: 2px;" width="321px" height="321px" viewBox="0 0 321 321" xmlns="http://www.w3.org/2000/svg"><g id="assets" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><g fill="currentColor"><path d="M160.5,0 C288.912633,0 321,32.0873672 321,160.5 C321,288.912633 288.912633,321 160.5,321 C32.0873672,321 0,288.912633 0,160.5 C0,32.0873672 32.0873672,0 160.5,0 Z M54.9896927,132.862573 C32.4775364,216.879084 47.8460619,243.498151 131.862573,266.010307 C215.879084,288.522464 242.498151,273.153938 265.010307,189.137427 C287.522464,105.120916 272.153938,78.501849 188.137427,55.9896927 C104.120916,33.4775364 77.501849,48.8460619 54.9896927,132.862573 Z"></path><path d="M160,106.642665 C116.509854,106.642665 105.642665,117.509854 105.642665,161 C105.642665,204.490146 116.509854,215.357335 160,215.357335 C203.490146,215.357335 214.357335,204.490146 214.357335,161 C214.357335,117.509854 203.490146,106.642665 160,106.642665 Z" id="inner" transform="translate(160.000000, 161.000000) rotate(-45.000000) translate(-160.000000, -161.000000)"></path></g></g></svg>';
		}

		return $block;
	}
}

new Library();

<?php
/**
 * Canvas class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Figma-like canvas view showing all Blockstudio pages as iframes.
 *
 * Enqueues a React app on the frontend when activated via query param.
 * Gated by setting, query param, and capability.
 *
 * @since 7.0.0
 */
class Canvas {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feature toggle, no state change.
		if ( isset( $_GET['blockstudio-canvas-frame'] ) ) {
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

	/**
	 * Enqueue canvas script on the frontend.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! Settings::get( 'dev/canvas/enabled' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feature toggle, no state change.
		if ( ! isset( $_GET['blockstudio-canvas'] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$asset_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/canvas/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'blockstudio-canvas',
			plugins_url( 'includes/admin/assets/canvas/index.js', BLOCKSTUDIO_FILE ),
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? BLOCKSTUDIO_VERSION,
			true
		);

		wp_localize_script(
			'blockstudio-canvas',
			'blockstudioCanvas',
			array(
				'pages'    => $this->get_pages(),
				'settings' => array(
					'adminBar' => (bool) Settings::get( 'dev/canvas/adminBar' ),
				),
			)
		);
	}

	/**
	 * Get all Blockstudio-managed pages.
	 *
	 * @return array<int, array{title: string, url: string, slug: string, name: string}>
	 */
	private function get_pages(): array {
		$posts = get_posts(
			array(
				'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$pages = array();

		foreach ( $posts as $post ) {
			$name = get_post_meta( $post->ID, '_blockstudio_page_name', true );

			$pages[] = array(
				'title' => $post->post_title,
				'url'   => get_permalink( $post->ID ),
				'slug'  => $post->post_name,
				'name'  => $name ? $name : $post->post_name,
			);
		}

		return $pages;
	}
}

new Canvas();

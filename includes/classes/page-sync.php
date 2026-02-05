<?php
/**
 * Page Sync class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Error;
use WP_Post;

/**
 * Handles syncing file-based pages to WordPress posts.
 *
 * This class manages the creation and updating of WordPress posts
 * based on file-based page definitions.
 *
 * @since 7.0.0
 */
class Page_Sync {

	/**
	 * The HTML parser instance.
	 *
	 * @var Html_Parser
	 */
	private Html_Parser $parser;

	/**
	 * Constructor.
	 *
	 * @param Html_Parser|null $parser Optional parser instance.
	 */
	public function __construct( ?Html_Parser $parser = null ) {
		$this->parser = $parser ?? Html_Parser::from_settings();
	}

	/**
	 * Sync a page to WordPress.
	 *
	 * @param array $page_data The page data from discovery.
	 *
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function sync( array $page_data ): int|WP_Error {
		$sync_enabled = $page_data['sync'] ?? true;

		if ( ! $sync_enabled ) {
			$existing = $this->find_existing_post( $page_data );
			return $existing ? $existing->ID : 0;
		}

		$existing   = $this->find_existing_post( $page_data );
		$file_mtime = filemtime( $page_data['template_path'] );

		if ( $existing ) {
			$stored_mtime = (int) get_post_meta( $existing->ID, '_blockstudio_page_mtime', true );

			if ( $stored_mtime >= $file_mtime ) {
				return $existing->ID;
			}

			$new_blocks = $this->get_parsed_blocks( $page_data );

			if ( $this->blocks_have_keys( $new_blocks ) ) {
				$old_blocks = parse_blocks( $existing->post_content );
				$merger     = new Block_Merger();
				$merged     = $merger->merge( $new_blocks, $old_blocks );
				$content    = serialize_blocks( $merged );
			} else {
				$content = serialize_blocks( $new_blocks );
			}

			return $this->update_post( $existing, $page_data, $content, $file_mtime );
		}

		$content = $this->get_parsed_content( $page_data );
		return $this->create_post( $page_data, $content, $file_mtime );
	}

	/**
	 * Get parsed content from template file.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return string The parsed block content.
	 */
	private function get_parsed_content( array $page_data ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
		$template_content = file_get_contents( $page_data['template_path'] );

		if ( false === $template_content ) {
			return '';
		}

		if ( ! empty( $page_data['is_blade'] ) && class_exists( 'Jenssegers\Blade\Blade' ) ) {
			$blade            = new \Jenssegers\Blade\Blade( $page_data['directory'], sys_get_temp_dir() );
			$template_content = $blade->render( 'index', array() );
		} elseif ( ! empty( $page_data['is_twig'] ) && class_exists( 'Timber\Timber' ) ) {
			\Timber\Timber::init();
			$template_content = \Timber\Timber::compile_string( $template_content, array() );
		}

		return $this->parser->parse( $template_content );
	}

	/**
	 * Get parsed blocks as an array from template file.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return array The parsed block array.
	 */
	private function get_parsed_blocks( array $page_data ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
		$template_content = file_get_contents( $page_data['template_path'] );

		if ( false === $template_content ) {
			return array();
		}

		if ( ! empty( $page_data['is_blade'] ) && class_exists( 'Jenssegers\Blade\Blade' ) ) {
			$blade            = new \Jenssegers\Blade\Blade( $page_data['directory'], sys_get_temp_dir() );
			$template_content = $blade->render( 'index', array() );
		} elseif ( ! empty( $page_data['is_twig'] ) && class_exists( 'Timber\Timber' ) ) {
			\Timber\Timber::init();
			$template_content = \Timber\Timber::compile_string( $template_content, array() );
		}

		return $this->parser->parse_to_array( $template_content );
	}

	/**
	 * Check if any blocks in the tree have a __BLOCKSTUDIO_KEY attribute.
	 *
	 * @param array $blocks The blocks to check.
	 *
	 * @return bool True if any block has a key.
	 */
	private function blocks_have_keys( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['attrs']['__BLOCKSTUDIO_KEY'] ) ) {
				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->blocks_have_keys( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find existing post for a page.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return WP_Post|null The existing post or null.
	 */
	private function find_existing_post( array $page_data ): ?WP_Post {
		if ( ! empty( $page_data['postId'] ) ) {
			$post = get_post( (int) $page_data['postId'] );

			if ( $post instanceof WP_Post && $post->post_type === $page_data['postType'] ) {
				$source = get_post_meta( $post->ID, '_blockstudio_page_source', true );

				if ( empty( $source ) || $source === $page_data['source_path'] ) {
					return $post;
				}
			}
		}

		$posts = get_posts(
			array(
				'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $page_data['source_path'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'post_type'      => $page_data['postType'],
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		$post = get_page_by_path( $page_data['slug'], OBJECT, $page_data['postType'] );

		if ( $post instanceof WP_Post ) {
			return $post;
		}

		return null;
	}

	/**
	 * Create a new post.
	 *
	 * @param array  $page_data  The page data.
	 * @param string $content    The parsed block content.
	 * @param int    $file_mtime The file modification time.
	 *
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	private function create_post( array $page_data, string $content, int $file_mtime ): int|WP_Error {
		$post_data = array(
			'post_title'   => $page_data['title'],
			'post_name'    => $page_data['slug'],
			'post_content' => $content,
			'post_type'    => $page_data['postType'],
			'post_status'  => $page_data['postStatus'],
		);

		if ( ! empty( $page_data['postId'] ) ) {
			$post_data['import_id'] = (int) $page_data['postId'];
		}

		/**
		 * Filter the post data before creating a page.
		 *
		 * @param array $post_data The post data.
		 * @param array $page_data The page definition data.
		 */
		$post_data = apply_filters( 'blockstudio/pages/create_post_data', $post_data, $page_data );

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->update_post_meta( $post_id, $page_data, $file_mtime );

		/**
		 * Fires after a page post is created.
		 *
		 * @param int   $post_id   The post ID.
		 * @param array $page_data The page definition data.
		 */
		do_action( 'blockstudio/pages/post_created', $post_id, $page_data );

		return $post_id;
	}

	/**
	 * Update an existing post.
	 *
	 * @param WP_Post $post       The existing post.
	 * @param array   $page_data  The page data.
	 * @param string  $content    The parsed block content.
	 * @param int     $file_mtime The file modification time.
	 *
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	private function update_post( WP_Post $post, array $page_data, string $content, int $file_mtime ): int|WP_Error {
		$is_locked = (bool) get_post_meta( $post->ID, '_blockstudio_page_locked', true );

		if ( $is_locked ) {
			return $post->ID;
		}

		$post_data = array(
			'ID'           => $post->ID,
			'post_title'   => $page_data['title'],
			'post_content' => $content,
		);

		/**
		 * Filter the post data before updating a page.
		 *
		 * @param array   $post_data The post data.
		 * @param WP_Post $post      The existing post.
		 * @param array   $page_data The page definition data.
		 */
		$post_data = apply_filters( 'blockstudio/pages/update_post_data', $post_data, $post, $page_data );

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->update_post_meta( $post->ID, $page_data, $file_mtime );

		/**
		 * Fires after a page post is updated.
		 *
		 * @param int   $post_id   The post ID.
		 * @param array $page_data The page definition data.
		 */
		do_action( 'blockstudio/pages/post_updated', $post->ID, $page_data );

		return $post->ID;
	}

	/**
	 * Update post meta for a synced page.
	 *
	 * @param int   $post_id    The post ID.
	 * @param array $page_data  The page data.
	 * @param int   $file_mtime The file modification time.
	 *
	 * @return void
	 */
	private function update_post_meta( int $post_id, array $page_data, int $file_mtime ): void {
		update_post_meta( $post_id, '_blockstudio_page_source', $page_data['source_path'] );
		update_post_meta( $post_id, '_blockstudio_page_mtime', $file_mtime );
		update_post_meta( $post_id, '_blockstudio_page_name', $page_data['name'] );

		if ( ! empty( $page_data['templateLock'] ) ) {
			update_post_meta( $post_id, '_blockstudio_template_lock', $page_data['templateLock'] );
		}

		if ( ! empty( $page_data['blockEditingMode'] ) ) {
			update_post_meta( $post_id, '_blockstudio_block_editing_mode', $page_data['blockEditingMode'] );
		}
	}

	/**
	 * Lock a post to prevent sync updates.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function lock_post( int $post_id ): void {
		update_post_meta( $post_id, '_blockstudio_page_locked', true );
	}

	/**
	 * Unlock a post to allow sync updates.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function unlock_post( int $post_id ): void {
		delete_post_meta( $post_id, '_blockstudio_page_locked' );
	}

	/**
	 * Force sync a post regardless of modification time.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function force_sync( array $page_data ): int|WP_Error {
		$existing   = $this->find_existing_post( $page_data );
		$content    = $this->get_parsed_content( $page_data );
		$file_mtime = filemtime( $page_data['template_path'] );

		if ( $existing ) {
			delete_post_meta( $existing->ID, '_blockstudio_page_locked' );
			return $this->update_post( $existing, $page_data, $content, $file_mtime );
		}

		return $this->create_post( $page_data, $content, $file_mtime );
	}

	/**
	 * Delete a synced post.
	 *
	 * @param string $source_path The source path of the page.
	 * @param string $post_type   The post type.
	 *
	 * @return bool Whether the post was deleted.
	 */
	public function delete_synced_post( string $source_path, string $post_type = 'page' ): bool {
		$posts = get_posts(
			array(
				'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $source_path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		if ( empty( $posts ) ) {
			return false;
		}

		$result = wp_delete_post( $posts[0]->ID, true );

		return false !== $result;
	}
}

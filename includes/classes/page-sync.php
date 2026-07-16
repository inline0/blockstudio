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
	 * Version of the page serialization and managed-meta contract.
	 */
	public const SYNC_ENGINE_VERSION = '2';

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
		$result = $this->reconcile( $page_data );

		return $result['error'] instanceof WP_Error ? $result['error'] : $result['post_id'];
	}

	/**
	 * Reconcile one desired page and report whether WordPress changed.
	 *
	 * @param array $page_data Desired page data from discovery.
	 * @param array $args      Optional existing post, fingerprints, and authority flags.
	 *
	 * @return array{status:string,post_id:int,error:WP_Error|null,locked:bool}
	 */
	public function reconcile( array $page_data, array $args = array() ): array {
		$page_data    = $this->prepare_page_data( $page_data );
		$sync_enabled = $page_data['sync'] ?? true;
		$existing     = isset( $args['existing'] ) && $args['existing'] instanceof WP_Post
			? $args['existing']
			: $this->find_existing_post( $page_data );

		if ( ! $sync_enabled && empty( $args['always_update'] ) ) {
			return $this->reconcile_result( 'unchanged', $existing ? $existing->ID : 0 );
		}

		$file_mtime         = $this->get_source_mtime( $page_data );
		$fingerprint        = isset( $args['fingerprint'] ) && is_string( $args['fingerprint'] ) ? $args['fingerprint'] : $this->build_fingerprint( $page_data );
		$engine_fingerprint = isset( $args['engine_fingerprint'] ) && is_string( $args['engine_fingerprint'] ) ? $args['engine_fingerprint'] : self::engine_fingerprint();

		if ( $existing ) {
			$stored_fingerprint = (string) get_post_meta( $existing->ID, '_blockstudio_page_fingerprint', true );
			$stored_engine      = (string) get_post_meta( $existing->ID, '_blockstudio_page_engine_fingerprint', true );
			$equal              = '' !== $stored_fingerprint
				&& '' !== $stored_engine
				&& hash_equals( $stored_fingerprint, $fingerprint )
				&& hash_equals( $stored_engine, $engine_fingerprint )
				&& $this->post_matches_page_data( $existing, $page_data );

			if ( $equal && empty( $args['always_update'] ) ) {
				$post_id = $existing->ID;
				if ( ! empty( $args['prune_duplicates'] ) ) {
					$post_id = $this->prune_duplicate_posts( $page_data, $post_id, $fingerprint, $engine_fingerprint );
				}

				return $this->reconcile_result( 'unchanged', $post_id );
			}

			$is_locked = (bool) get_post_meta( $existing->ID, '_blockstudio_page_locked', true );
			if ( $is_locked && empty( $args['authoritative'] ) ) {
				return $this->reconcile_result( 'unchanged', $existing->ID, null, true );
			}
			if ( $is_locked ) {
				delete_post_meta( $existing->ID, '_blockstudio_page_locked' );
			}

			if ( ! empty( $args['authoritative'] ) || ! empty( $args['always_update'] ) ) {
				$content = $this->get_parsed_content( $page_data );
			} else {
				$new_blocks = $this->get_parsed_blocks( $page_data );

				if ( $this->blocks_have_keys( $new_blocks ) ) {
					$old_blocks = parse_blocks( $existing->post_content );
					$merger     = new Block_Merger();
					$merged     = $merger->merge( $new_blocks, $old_blocks );
					$content    = serialize_blocks( $merged );
				} else {
					$content = serialize_blocks( $new_blocks );
				}
			}

			$result = $this->update_post( $existing, $page_data, $content, $file_mtime, $fingerprint, $engine_fingerprint );

			if ( is_int( $result ) && $result > 0 ) {
				$post_id = $this->prune_duplicate_posts( $page_data, $result, $fingerprint, $engine_fingerprint );
				return $this->reconcile_result( 'updated', $post_id );
			}

			return $this->reconcile_result( 'failed', $existing->ID, $result instanceof WP_Error ? $result : null );
		}

		if ( $this->has_slug_conflict( $page_data ) ) {
			return $this->reconcile_result( 'failed', 0 );
		}

		$content = $this->get_parsed_content( $page_data );
		$result  = $this->create_post( $page_data, $content, $file_mtime, $fingerprint, $engine_fingerprint );

		if ( is_int( $result ) && $result > 0 ) {
			$post_id = $this->prune_duplicate_posts( $page_data, $result, $fingerprint, $engine_fingerprint );
			return $this->reconcile_result( 'created', $post_id );
		}

		return $this->reconcile_result( 'failed', 0, $result instanceof WP_Error ? $result : null );
	}

	/**
	 * Build the current page-sync engine fingerprint.
	 *
	 * @return string Engine fingerprint.
	 */
	public static function engine_fingerprint(): string {
		$inputs = array(
			'pageSync'      => self::SYNC_ENGINE_VERSION,
			'pageDiscovery' => '1',
			'htmlParser'    => '1',
			'blockTags'     => '1',
		);

		/**
		 * Filters inputs that affect serialized file-page content.
		 *
		 * Themes may add an element-mapping or migration version. CSS and normal
		 * block renderer changes should not be added because they do not require
		 * post-content writes.
		 *
		 * @param array $inputs Engine input versions.
		 */
		$inputs  = apply_filters( 'blockstudio/pages/sync_engine_inputs', $inputs );
		$encoded = wp_json_encode( is_array( $inputs ) ? $inputs : array() );

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * Build the desired content fingerprint without parsing blocks.
	 *
	 * @param array $page_data Desired page data.
	 *
	 * @return string Page fingerprint.
	 */
	public function fingerprint( array $page_data ): string {
		return $this->build_fingerprint( $this->prepare_page_data( $page_data ) );
	}

	/**
	 * Get the normalized dependency identities used by deployment plans.
	 *
	 * Absolute paths are intentionally excluded. A theme can be built in one
	 * checkout and reconciled from another without changing the page identity.
	 *
	 * @param array $page_data Desired page data.
	 *
	 * @return array<int, string> Stable dependency identities.
	 */
	public function dependency_ids( array $page_data ): array {
		$dependencies = array();

		foreach ( $this->source_paths( $page_data ) as $path ) {
			$dependencies[] = $this->dependency_id( $path, $page_data );
			$portable_path  = $this->portable_theme_path( $path );
			if ( null !== $portable_path ) {
				$dependencies[] = $portable_path;
			}
		}

		return array_values( array_unique( $dependencies ) );
	}

	/**
	 * Load the complete inventory of Blockstudio-managed page posts once.
	 *
	 * @return array<int, WP_Post> Managed posts.
	 */
	public function managed_posts(): array {
		$posts = get_posts(
			array(
				'meta_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => '_blockstudio_page_key',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_blockstudio_page_source',
						'compare' => 'EXISTS',
					),
				),
				'post_type'         => 'any',
				'posts_per_page'    => -1,
				'post_status'       => $this->synced_post_statuses(),
				'orderby'           => 'ID',
				'order'             => 'ASC',
				'suppress_filters'  => false,
				'update_meta_cache' => true,
			)
		);

		return array_values(
			array_filter(
				$posts,
				static fn ( mixed $post ): bool => $post instanceof WP_Post
			)
		);
	}

	/**
	 * Prune managed posts absent from the complete desired inventory.
	 *
	 * @param array      $active_keys    Desired managed keys.
	 * @param array      $active_sources Desired managed source identities.
	 * @param array|null $managed_posts  Optional preloaded managed inventory.
	 *
	 * @return int Number of posts removed from the active inventory.
	 */
	public function prune_missing( array $active_keys, array $active_sources, ?array $managed_posts = null ): int {
		$active_keys    = array_fill_keys( array_map( 'strval', $active_keys ), true );
		$active_sources = array_fill_keys( array_map( 'strval', $active_sources ), true );
		$removed        = 0;

		foreach ( $managed_posts ?? $this->managed_posts() as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$key    = (string) get_post_meta( $post->ID, '_blockstudio_page_key', true );
			$source = (string) get_post_meta( $post->ID, '_blockstudio_page_source', true );

			if ( ( '' !== $key && isset( $active_keys[ $key ] ) ) || ( '' !== $source && isset( $active_sources[ $source ] ) ) ) {
				continue;
			}

			if ( '' === $key && '' === $source ) {
				continue;
			}

			update_post_meta( $post->ID, '_blockstudio_page_stale', true );
			if ( $this->prune_orphan( $post ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Build one normalized reconciliation result.
	 *
	 * @param string        $status  Result status.
	 * @param int           $post_id Synced post ID.
	 * @param WP_Error|null $error   Optional error.
	 * @param bool          $locked  Whether a changed page was kept locked.
	 *
	 * @return array{status:string,post_id:int,error:WP_Error|null,locked:bool}
	 */
	private function reconcile_result( string $status, int $post_id, ?WP_Error $error = null, bool $locked = false ): array {
		return array(
			'status'  => $status,
			'post_id' => $post_id,
			'error'   => $error,
			'locked'  => $locked,
		);
	}

	/**
	 * Normalize sync identity values before lookup or persistence.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return array Normalized page data.
	 */
	private function prepare_page_data( array $page_data ): array {
		if ( ! empty( $page_data['name'] ) ) {
			$collection       = ! empty( $page_data['collection'] ) ? (string) $page_data['collection'] : null;
			$page_data['key'] = Page_Discovery::page_key( $collection, (string) $page_data['name'] );
		}

		if (
			! empty( $page_data['collection'] ) &&
			! empty( $page_data['path'] ) &&
			'.' !== $page_data['path'] &&
			false !== strpos( (string) $page_data['path'], '/' ) &&
			! is_post_type_hierarchical( (string) $page_data['postType'] )
		) {
			$page_data['slug'] = sanitize_title( str_replace( '/', '-', (string) $page_data['path'] ) );
		}

		return $page_data;
	}

	/**
	 * Get parsed content from template file.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return string The parsed block content.
	 */
	private function get_parsed_content( array $page_data ): string {
		return serialize_blocks( $this->get_parsed_blocks( $page_data ) );
	}

	/**
	 * Get parsed blocks as an array from template file.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return array The parsed block array.
	 */
	private function get_parsed_blocks( array $page_data ): array {
		$template_content = $this->get_template_content( $page_data );
		$content_type     = $page_data['contentType'] ?? 'php';

		if ( 'markdown' === $content_type ) {
			$template_content = Page_Markdown::to_html( $template_content );

			if ( ! empty( $page_data['sanitize_content'] ) ) {
				$template_content = Page_Markdown::sanitize_docs_html( $template_content );
			}
		} elseif ( 'html' === $content_type && ! empty( $page_data['sanitize_content'] ) ) {
			$template_content = Page_Markdown::sanitize_docs_html( $template_content );
		}

		$blocks = $this->parser->parse_to_array( $template_content );

		return $this->apply_template_overrides( $blocks );
	}

	/**
	 * Read or render source content for a page.
	 *
	 * @param array $page_data Page data.
	 *
	 * @return string Template/content string.
	 */
	private function get_template_content( array $page_data ): string {
		if ( isset( $page_data['inline_content'] ) && is_string( $page_data['inline_content'] ) ) {
			return $page_data['inline_content'];
		}

		$content_path = $page_data['content_path'] ?? $page_data['template_path'] ?? null;

		if ( ! is_string( $content_path ) || '' === $content_path || ! file_exists( $content_path ) ) {
			return '';
		}

		if ( 'markdown' === ( $page_data['contentType'] ?? '' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local page source file.
			$template_content = file_get_contents( $content_path );

			if ( false === $template_content ) {
				return '';
			}

			$parts = Page_Markdown::split_frontmatter( $template_content );
			return $parts['body'];
		}

		return Template_Compiler::compile(
			$content_path,
			is_string( $page_data['directory'] ?? null ) ? $page_data['directory'] : null
		) ?? '';
	}

	/**
	 * Move top-level template attributes into blockstudio.attributes for Blockstudio blocks.
	 *
	 * When a page template uses `<block name="drift/cta" heading="Custom">`, the parser
	 * puts "heading" at the top level of attrs. Blockstudio expects field values inside
	 * attrs.blockstudio.attributes. This method bridges the two.
	 *
	 * @param array $blocks The parsed blocks.
	 *
	 * @return array The blocks with overrides applied.
	 */
	private function apply_template_overrides( array $blocks ): array {
		$registered = Build::blocks();

		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? '';

			if ( $name && isset( $registered[ $name ] ) && isset( $registered[ $name ]->attributes['blockstudio'] ) && ! empty( $block['attrs'] ) ) {
				$field_keys = array();

				foreach ( $registered[ $name ]->attributes as $key => $def ) {
					if ( isset( $def['field'] ) ) {
						$field_keys[] = $key;
					}
				}

				$overrides = array();

				foreach ( $field_keys as $key ) {
					if ( array_key_exists( $key, $block['attrs'] ) ) {
						$overrides[ $key ] = $block['attrs'][ $key ];
						unset( $block['attrs'][ $key ] );
					}
				}

				if ( ! empty( $overrides ) ) {
					if ( ! isset( $block['attrs']['blockstudio'] ) ) {
						$block['attrs']['blockstudio'] = array();
					}

					$block['attrs']['blockstudio']['attributes'] = array_merge(
						$block['attrs']['blockstudio']['attributes'] ?? array(),
						$overrides
					);
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->apply_template_overrides( $block['innerBlocks'] );
			}
		}

		return $blocks;
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
			$key = $block['attrs']['__BLOCKSTUDIO_KEY'] ?? null;

			if ( is_scalar( $key ) && '' !== (string) $key ) {
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

			if ( $post instanceof WP_Post ) {
				$source = get_post_meta( $post->ID, '_blockstudio_page_source', true );
				$name   = get_post_meta( $post->ID, '_blockstudio_page_name', true );
				$key    = get_post_meta( $post->ID, '_blockstudio_page_key', true );

				$expected_key = $page_data['key'] ?? null;
				$has_identity = '' !== (string) $source || '' !== (string) $name || '' !== (string) $key;
				$matches      = $page_data['source_path'] === $source || $page_data['name'] === $name || ( ! empty( $expected_key ) && $expected_key === $key );

				return ! $has_identity || $matches ? $post : null;
			}
		}

		return $this->find_existing_post_by_identity( $page_data, (string) $page_data['postType'] )
			?? $this->find_existing_post_by_identity( $page_data, 'any' );
	}

	/**
	 * Find an existing synced post by Blockstudio identity.
	 *
	 * @param array        $page_data Page data.
	 * @param string|array $post_type Post type query.
	 *
	 * @return WP_Post|null Post object.
	 */
	private function find_existing_post_by_identity( array $page_data, string|array $post_type ): ?WP_Post {
		if ( ! empty( $page_data['key'] ) ) {
			$posts = get_posts(
				array(
					'meta_key'       => '_blockstudio_page_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $page_data['key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'post_type'      => $post_type,
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			if ( ! empty( $posts ) ) {
				return $posts[0];
			}
		}

		$posts = get_posts(
			array(
				'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $page_data['source_path'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		$meta_query = array(
			array(
				'key'   => '_blockstudio_page_name',
				'value' => $page_data['name'],
			),
		);

		if ( ! empty( $page_data['collection'] ) ) {
			$meta_query[] = array(
				'key'   => '_blockstudio_page_collection',
				'value' => $page_data['collection'],
			);
		}

		$posts = get_posts(
			array(
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Check whether a page slug is already claimed by an unrelated post.
	 *
	 * @param array $page_data The page data.
	 *
	 * @return bool True when the slug is occupied by a different page.
	 */
	private function has_slug_conflict( array $page_data ): bool {
		if ( ! empty( $page_data['collection'] ) && ! is_post_type_hierarchical( (string) $page_data['postType'] ) ) {
			return false;
		}

		$posts = get_posts(
			array(
				'name'           => $page_data['slug'],
				'post_type'      => $page_data['postType'],
				'post_parent'    => $this->resolve_parent_id( $page_data ),
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		if ( empty( $posts ) ) {
			return false;
		}

		$post   = $posts[0];
		$source = get_post_meta( $post->ID, '_blockstudio_page_source', true );
		$name   = get_post_meta( $post->ID, '_blockstudio_page_name', true );
		$key    = get_post_meta( $post->ID, '_blockstudio_page_key', true );

		$expected_key = $page_data['key'] ?? null;

		return $page_data['source_path'] !== $source && $page_data['name'] !== $name && $expected_key !== $key;
	}

	/**
	 * Create a new post.
	 *
	 * @param array  $page_data  The page data.
	 * @param string $content    The parsed block content.
	 * @param int    $file_mtime The file modification time.
	 * @param string $fingerprint        Source fingerprint.
	 * @param string $engine_fingerprint Sync-engine fingerprint.
	 *
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	private function create_post( array $page_data, string $content, int $file_mtime, string $fingerprint, string $engine_fingerprint ): int|WP_Error {
		$post_data = array(
			'post_title'   => $page_data['title'],
			'post_name'    => $page_data['slug'],
			'post_content' => $content,
			'post_type'    => $page_data['postType'],
			'post_status'  => $page_data['postStatus'],
		);

		if ( isset( $page_data['order'] ) && is_numeric( $page_data['order'] ) ) {
			$post_data['menu_order'] = (int) $page_data['order'];
		}

		$post_parent = $this->resolve_parent_id( $page_data );

		if ( $post_parent > 0 ) {
			$post_data['post_parent'] = $post_parent;
		}

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

		$post_id = wp_insert_post( wp_slash( $post_data ), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->update_post_meta( $post_id, $page_data, $file_mtime, $fingerprint, $engine_fingerprint );

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
	 * Check whether the WordPress fields managed by page sync still match.
	 *
	 * Source fingerprints describe the desired files, not the current database
	 * row. A trashed or otherwise modified post must therefore leave the
	 * zero-write path even when its stored source fingerprint is current.
	 *
	 * @param WP_Post $post      Existing post.
	 * @param array   $page_data Desired page data.
	 *
	 * @return bool Whether the managed post fields match.
	 */
	private function post_matches_page_data( WP_Post $post, array $page_data ): bool {
		$post_data = $this->build_update_post_data( $post, $page_data, $post->post_content, false );

		if ( $this->post_matches_update_data( $post, $post_data ) ) {
			return true;
		}

		$update_post_data = apply_filters( 'blockstudio/pages/update_post_data', $post_data, $post, $page_data );
		if ( is_array( $update_post_data ) && $this->post_matches_update_data( $post, $update_post_data ) ) {
			return true;
		}

		unset( $post_data['ID'] );
		$create_post_data = apply_filters( 'blockstudio/pages/create_post_data', $post_data, $page_data );

		return is_array( $create_post_data ) && $this->post_matches_update_data( $post, $create_post_data );
	}

	/**
	 * Build the post fields controlled by page sync.
	 *
	 * @param WP_Post $post          Existing post.
	 * @param array   $page_data     Desired page data.
	 * @param string  $content       Desired serialized content.
	 * @param bool    $apply_filters Whether to apply the public update filter.
	 *
	 * @return array Post data for wp_update_post().
	 */
	private function build_update_post_data( WP_Post $post, array $page_data, string $content, bool $apply_filters = true ): array {
		$post_data = array(
			'ID'           => $post->ID,
			'post_title'   => $page_data['title'],
			'post_name'    => $page_data['slug'],
			'post_content' => $content,
			'post_type'    => $page_data['postType'],
			'post_status'  => $page_data['postStatus'],
		);

		if ( isset( $page_data['order'] ) && is_numeric( $page_data['order'] ) ) {
			$post_data['menu_order'] = (int) $page_data['order'];
		}

		$post_parent = $this->resolve_parent_id( $page_data );

		if ( $post_parent > 0 || (int) $post->post_parent > 0 ) {
			$post_data['post_parent'] = $post_parent;
		}

		if ( $apply_filters ) {
			/**
			 * Filter the post data before updating a page.
			 *
			 * @param array   $post_data The post data.
			 * @param WP_Post $post      The existing post.
			 * @param array   $page_data The page definition data.
			 */
			$post_data = apply_filters( 'blockstudio/pages/update_post_data', $post_data, $post, $page_data );
		}

		return is_array( $post_data ) ? $post_data : array();
	}

	/**
	 * Compare managed WP_Post fields with prepared update data.
	 *
	 * Post content is intentionally excluded because keyed pages may preserve
	 * editor changes while retaining the source fingerprint.
	 *
	 * @param WP_Post $post      Existing post.
	 * @param array   $post_data Prepared update data.
	 *
	 * @return bool Whether every managed field matches.
	 */
	private function post_matches_update_data( WP_Post $post, array $post_data ): bool {
		$string_fields = array( 'post_title', 'post_name', 'post_type', 'post_status' );
		$int_fields    = array( 'post_parent', 'menu_order' );

		foreach ( $string_fields as $field ) {
			if ( array_key_exists( $field, $post_data ) && (string) $post->{$field} !== (string) $post_data[ $field ] ) {
				return false;
			}
		}
		foreach ( $int_fields as $field ) {
			if ( array_key_exists( $field, $post_data ) && (int) $post->{$field} !== (int) $post_data[ $field ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Update an existing post.
	 *
	 * @param WP_Post $post       The existing post.
	 * @param array   $page_data  The page data.
	 * @param string  $content    The parsed block content.
	 * @param int     $file_mtime The file modification time.
	 * @param string  $fingerprint        Source fingerprint.
	 * @param string  $engine_fingerprint Sync-engine fingerprint.
	 *
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	private function update_post( WP_Post $post, array $page_data, string $content, int $file_mtime, string $fingerprint, string $engine_fingerprint ): int|WP_Error {
		$is_locked = (bool) get_post_meta( $post->ID, '_blockstudio_page_locked', true );

		if ( $is_locked ) {
			return $post->ID;
		}

		$post_data = $this->build_update_post_data( $post, $page_data, $content );

		$result = wp_update_post( wp_slash( $post_data ), true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->update_post_meta( $post->ID, $page_data, $file_mtime, $fingerprint, $engine_fingerprint );

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
	 * @param int    $post_id     The post ID.
	 * @param array  $page_data   The page data.
	 * @param int    $file_mtime  The file modification time.
	 * @param string $fingerprint        Source fingerprint.
	 * @param string $engine_fingerprint Sync-engine fingerprint.
	 *
	 * @return void
	 */
	private function update_post_meta( int $post_id, array $page_data, int $file_mtime, string $fingerprint, string $engine_fingerprint ): void {
		update_post_meta( $post_id, '_blockstudio_page_source', $page_data['source_path'] );
		update_post_meta( $post_id, '_blockstudio_page_mtime', $file_mtime );
		update_post_meta( $post_id, '_blockstudio_page_name', $page_data['name'] );
		update_post_meta( $post_id, '_blockstudio_page_key', $page_data['key'] ?? $page_data['name'] );
		update_post_meta( $post_id, '_blockstudio_page_fingerprint', $fingerprint );
		update_post_meta( $post_id, '_blockstudio_page_engine_fingerprint', $engine_fingerprint );
		update_post_meta( $post_id, '_blockstudio_page_collection', $page_data['collection'] ?? '' );
		update_post_meta( $post_id, '_blockstudio_page_path', $page_data['path'] ?? '' );
		update_post_meta( $post_id, '_blockstudio_page_route', ( $page_data['collection'] ?? '' ) . ':' . ( $page_data['path'] ?? '' ) );
		update_post_meta( $post_id, '_blockstudio_page_generated', ! empty( $page_data['generated'] ) );
		update_post_meta( $post_id, '_blockstudio_page_content_type', $page_data['contentType'] ?? 'php' );
		update_post_meta( $post_id, '_blockstudio_page_stale', false );
		$dependencies = $this->dependency_ids( $page_data );
		if ( ! empty( $dependencies ) ) {
			update_post_meta( $post_id, '_blockstudio_page_dependencies', $dependencies );
		} else {
			delete_post_meta( $post_id, '_blockstudio_page_dependencies' );
		}

		$content_path = $page_data['content_path'] ?? $page_data['template_path'] ?? '';
		if ( 'markdown' === ( $page_data['contentType'] ?? '' ) && is_string( $content_path ) && '' !== $content_path ) {
			update_post_meta( $post_id, '_blockstudio_page_content_path', $content_path );
		} else {
			delete_post_meta( $post_id, '_blockstudio_page_content_path' );
		}

		if ( ! empty( $page_data['parent_key'] ) ) {
			update_post_meta( $post_id, '_blockstudio_page_parent_key', $page_data['parent_key'] );
		} else {
			delete_post_meta( $post_id, '_blockstudio_page_parent_key' );
		}

		if ( ! empty( $page_data['layout_path'] ) ) {
			update_post_meta( $post_id, '_blockstudio_page_layout', $page_data['layout_path'] );
		} else {
			delete_post_meta( $post_id, '_blockstudio_page_layout' );
		}

		if ( ! empty( $page_data['meta'] ) ) {
			update_post_meta( $post_id, '_blockstudio_page_meta', $page_data['meta'] );
		} else {
			delete_post_meta( $post_id, '_blockstudio_page_meta' );
		}

		if ( ! empty( $page_data['templateLock'] ) ) {
			update_post_meta( $post_id, '_blockstudio_template_lock', $page_data['templateLock'] );
		} else {
			delete_post_meta( $post_id, '_blockstudio_template_lock' );
		}

		if ( ! empty( $page_data['blockEditingMode'] ) ) {
			update_post_meta( $post_id, '_blockstudio_block_editing_mode', $page_data['blockEditingMode'] );
		} else {
			delete_post_meta( $post_id, '_blockstudio_block_editing_mode' );
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
		$result = $this->reconcile(
			$page_data,
			array(
				'authoritative' => true,
				'always_update' => true,
			)
		);

		return $result['error'] instanceof WP_Error ? $result['error'] : $result['post_id'];
	}

	/**
	 * Mark synced collection posts missing from the latest sync as stale and prune them.
	 *
	 * Covers both generated container pages and source-backed pages. Any synced post
	 * whose source is no longer present is flagged stale and pruned, so removing a page
	 * source also removes the orphaned post instead of leaving it published and claiming
	 * its slug.
	 *
	 * @param array       $active_sources Active source identifiers.
	 * @param string|null $collection     Collection slug.
	 * @param array       $post_types     Active post types.
	 *
	 * @return int Number of posts removed.
	 */
	public function mark_stale_missing( array $active_sources, ?string $collection, array $post_types ): int {
		if ( ! $collection || empty( $post_types ) ) {
			return 0;
		}

		$removed = 0;

		$posts = get_posts(
			array(
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_blockstudio_page_collection',
						'value' => $collection,
					),
				),
				'post_type'      => $post_types,
				'posts_per_page' => -1,
				'post_status'    => $this->synced_post_statuses(),
			)
		);

		foreach ( $posts as $post ) {
			$source = (string) get_post_meta( $post->ID, '_blockstudio_page_source', true );

			if ( in_array( $source, $active_sources, true ) ) {
				continue;
			}

			update_post_meta( $post->ID, '_blockstudio_page_stale', true );
			if ( $this->prune_orphan( $post ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Prune a synced page whose source no longer exists.
	 *
	 * Only posts Blockstudio synced are eligible, so manually authored posts are
	 * never touched. The action is filterable to support hard deletes or opting out.
	 *
	 * @param \WP_Post $post The orphaned post.
	 *
	 * @return bool Whether the post was removed from the active inventory.
	 */
	private function prune_orphan( \WP_Post $post ): bool {
		/**
		 * Filters how an orphaned synced page is handled.
		 *
		 * @param string   $action The action: 'trash', 'delete', or 'keep'.
		 * @param \WP_Post $post   The orphaned post.
		 */
		$action = apply_filters( 'blockstudio/pages/orphan_action', 'trash', $post );

		if ( 'delete' === $action ) {
			return false !== wp_delete_post( $post->ID, true );
		} elseif ( 'trash' === $action ) {
			return false !== wp_trash_post( $post->ID );
		}

		return false;
	}

	/**
	 * Prune synced duplicate posts for the same page identity.
	 *
	 * @param array  $page_data    Page data.
	 * @param int    $keep_post_id Post ID to keep.
	 * @param string $fingerprint        Desired source fingerprint.
	 * @param string $engine_fingerprint Desired sync-engine fingerprint.
	 *
	 * @return int Canonical managed post ID.
	 */
	private function prune_duplicate_posts( array $page_data, int $keep_post_id, string $fingerprint, string $engine_fingerprint ): int {
		if ( empty( $page_data['key'] ) ) {
			return $keep_post_id;
		}

		$posts = get_posts(
			array(
				'meta_key'       => '_blockstudio_page_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $page_data['key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'post_status'    => $this->synced_post_statuses(),
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$posts = array_values(
			array_filter(
				$posts,
				static fn ( mixed $post ): bool => $post instanceof WP_Post
					&& (string) get_post_meta( $post->ID, '_blockstudio_page_collection', true ) === (string) ( $page_data['collection'] ?? '' )
			)
		);

		$canonical_post_id = $keep_post_id;
		$explicit_post_id  = isset( $page_data['postId'] ) ? (int) $page_data['postId'] : 0;
		if ( $explicit_post_id <= 0 || $explicit_post_id !== $keep_post_id ) {
			foreach ( $posts as $post ) {
				if (
					hash_equals( $fingerprint, (string) get_post_meta( $post->ID, '_blockstudio_page_fingerprint', true ) )
					&& hash_equals( $engine_fingerprint, (string) get_post_meta( $post->ID, '_blockstudio_page_engine_fingerprint', true ) )
					&& $this->post_matches_page_data( $post, $page_data )
				) {
					$canonical_post_id = (int) $post->ID;
					break;
				}
			}
		}

		foreach ( $posts as $post ) {
			if ( (int) $post->ID === $canonical_post_id ) {
				continue;
			}

			update_post_meta( $post->ID, '_blockstudio_page_stale', true );
			$this->prune_orphan( $post );
		}

		return $canonical_post_id;
	}

	/**
	 * Resolve the synced parent post ID for hierarchical post types.
	 *
	 * @param array $page_data Page data.
	 *
	 * @return int Parent post ID or 0.
	 */
	private function resolve_parent_id( array $page_data ): int {
		if ( empty( $page_data['parent_key'] ) || ! is_post_type_hierarchical( $page_data['postType'] ) ) {
			return 0;
		}

		$parent = Page_Registry::instance()->get_page( (string) $page_data['parent_key'] );
		if ( ! empty( $parent['post_id'] ) ) {
			return (int) $parent['post_id'];
		}

		$posts = get_posts(
			array(
				'post_type'      => (string) $page_data['postType'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'   => '_blockstudio_page_key',
						'value' => (string) $page_data['parent_key'],
					),
					array(
						'key'   => '_blockstudio_page_name',
						'value' => (string) $page_data['parent_key'],
					),
				),
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Get all post statuses that may hold synced page duplicates or orphans.
	 *
	 * @return array<int, string> Post statuses.
	 */
	private function synced_post_statuses(): array {
		return array_values( get_post_stati( array(), 'names' ) );
	}

	/**
	 * Get the newest mtime among a page's source files.
	 *
	 * @param array $page_data Page data.
	 *
	 * @return int Source mtime.
	 */
	private function get_source_mtime( array $page_data ): int {
		$mtime = 0;
		$paths = $page_data['source_mtime_paths'] ?? array_filter(
			array(
				$page_data['json_path'] ?? null,
				$page_data['content_path'] ?? $page_data['template_path'] ?? null,
			)
		);

		foreach ( $paths as $path ) {
			if ( is_string( $path ) && file_exists( $path ) ) {
				$mtime = max( $mtime, (int) filemtime( $path ) );
			}
		}

		return $mtime;
	}

	/**
	 * Build a content fingerprint from relevant page inputs.
	 *
	 * @param array $page_data Page data.
	 *
	 * @return string Fingerprint.
	 */
	private function build_fingerprint( array $page_data ): string {
		$parts = array(
			'name'         => $page_data['name'] ?? '',
			'key'          => $page_data['key'] ?? '',
			'title'        => $page_data['title'] ?? '',
			'slug'         => $page_data['slug'] ?? '',
			'path'         => $page_data['path'] ?? '',
			'postType'     => $page_data['postType'] ?? '',
			'postStatus'   => $page_data['postStatus'] ?? '',
			'templateLock' => $page_data['templateLock'] ?? '',
			'collection'   => $page_data['collection'] ?? '',
			'contentType'  => $page_data['contentType'] ?? '',
			'parent_key'   => $page_data['parent_key'] ?? '',
			'generated'    => ! empty( $page_data['generated'] ),
			'order'        => isset( $page_data['order'] ) && is_numeric( $page_data['order'] ) ? (int) $page_data['order'] : null,
			'templateFor'  => $page_data['templateFor'] ?? '',
			'blockMode'    => $page_data['blockEditingMode'] ?? '',
			'sanitize'     => ! empty( $page_data['sanitize_content'] ),
			'inline'       => $page_data['inline_content'] ?? null,
			'meta'         => $this->normalize_fingerprint_value( $page_data['meta'] ?? array() ),
			'files'        => $this->fingerprint_files( $page_data ),
		);

		$encoded = wp_json_encode( $parts );

		if ( false === $encoded ) {
			$encoded = '';
		}

		return hash( 'sha256', $encoded );
	}

	/**
	 * Normalize machine-local values before they enter a content fingerprint.
	 *
	 * Loader metadata can contain absolute candidate directories. Their basename
	 * is stable across the authoring checkout and deployed theme; the source file
	 * hashes still provide the actual content boundary.
	 *
	 * @param mixed $value Fingerprint value.
	 *
	 * @return mixed Stable value.
	 */
	private function normalize_fingerprint_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = $this->normalize_fingerprint_value( $item );
			}

			if ( ! array_is_list( $normalized ) ) {
				ksort( $normalized, SORT_STRING );
			}

			return $normalized;
		}

		if ( is_string( $value ) && ( str_starts_with( $value, '/' ) || preg_match( '/^[A-Za-z]:[\\\\\/]/', $value ) ) ) {
			return 'path:' . basename( wp_normalize_path( $value ) );
		}

		return $value;
	}

	/**
	 * Hash source files with machine-independent identities.
	 *
	 * @param array $page_data Page data.
	 *
	 * @return array<int, array{id:string,hash:string}> Fingerprint file records.
	 */
	private function fingerprint_files( array $page_data ): array {
		$records = array();
		foreach ( $this->source_paths( $page_data ) as $path ) {
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$hash = hash_file( 'sha256', $path );
			if ( false === $hash ) {
				continue;
			}

			$records[] = array(
				'id'   => $this->dependency_id( $path, $page_data ),
				'hash' => $hash,
			);
		}

		usort(
			$records,
			static fn ( array $a, array $b ): int => ( $a['id'] . ':' . $a['hash'] ) <=> ( $b['id'] . ':' . $b['hash'] )
		);

		return $records;
	}

	/**
	 * Get unique source paths contributing to a page fingerprint.
	 *
	 * @param array $page_data Page data.
	 *
	 * @return array<int, string> Source paths.
	 */
	private function source_paths( array $page_data ): array {
		$paths = array_values( array_filter( $page_data['source_mtime_paths'] ?? array(), 'is_string' ) );

		if ( ! empty( $page_data['layout_path'] ) && is_string( $page_data['layout_path'] ) ) {
			$paths[] = $page_data['layout_path'];
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Build a stable identity for one source dependency.
	 *
	 * @param string $path      Absolute source path.
	 * @param array  $page_data Page data.
	 *
	 * @return string Stable dependency identity.
	 */
	private function dependency_id( string $path, array $page_data ): string {
		$path = wp_normalize_path( $path );

		$roles = array(
			'definition' => $page_data['json_path'] ?? null,
			'content'    => $page_data['content_path'] ?? $page_data['template_path'] ?? null,
			'layout'     => $page_data['layout_path'] ?? null,
		);

		foreach ( $roles as $role => $role_path ) {
			if ( is_string( $role_path ) && wp_normalize_path( $role_path ) === $path ) {
				return 'page:' . $role . ':' . basename( $path );
			}
		}

		$basename = basename( $path );
		if ( 'pages.json' === $basename ) {
			return 'collection:manifest';
		}
		if ( 'loader.php' === $basename ) {
			return 'collection:loader';
		}

		return 'external:' . $basename;
	}

	/**
	 * Convert a path inside the active theme to a portable plan identity.
	 *
	 * @param string $path Absolute dependency path.
	 *
	 * @return string|null Portable path or null for external sources.
	 */
	private function portable_theme_path( string $path ): ?string {
		$path  = wp_normalize_path( $path );
		$roots = array_filter(
			array(
				function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : null,
				function_exists( 'get_template_directory' ) ? get_template_directory() : null,
			)
		);

		foreach ( $roots as $root ) {
			$root = trailingslashit( wp_normalize_path( (string) $root ) );
			if ( str_starts_with( $path, $root ) ) {
				return 'theme:' . ltrim( substr( $path, strlen( $root ) ), '/' );
			}
		}

		return null;
	}
}

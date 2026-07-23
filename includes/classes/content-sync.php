<?php
/**
 * Content Sync class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Bidirectional file projection for allowlisted WordPress content.
 *
 * @since 7.4.0
 */
class Content_Sync {

	public const META_UID         = '_blockstudio_content_uid';
	public const META_SET         = '_blockstudio_content_set';
	public const META_SOURCE      = '_blockstudio_content_source';
	public const META_FINGERPRINT = '_blockstudio_content_fingerprint';
	public const META_LOCKED      = '_blockstudio_content_locked';

	/**
	 * Sync configuration.
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Whether this run may write to the database or files.
	 *
	 * @var bool
	 */
	private bool $dry_run = false;

	/**
	 * Constructor.
	 *
	 * @param array|null $config Optional config override.
	 */
	public function __construct( ?array $config = null ) {
		$this->config = $this->normalize_config( $config ?? (array) Settings::get( 'content', array() ) );
	}

	/**
	 * Pull database content into files.
	 *
	 * @param array $args Pull options.
	 *
	 * @return array Result rows.
	 */
	public function pull( array $args = array() ): array {
		if ( ! $this->config['enabled'] ) {
			return $this->disabled_rows();
		}

		$selection_errors = $this->selection_error_rows( $args );
		if ( ! empty( $selection_errors ) ) {
			return $selection_errors;
		}

		$this->dry_run = ! empty( $args['dry-run'] );
		$rows          = array();
		$seen_sources  = array();

		foreach ( $this->selected_post_types( $args ) as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				$rows[] = $this->row( 'error', 'post_type', $post_type, '', "Post type '{$post_type}' is not registered." );
				continue;
			}

			foreach ( $this->query_posts( $post_type ) as $post ) {
				if ( $this->is_page_sync_managed( $post->ID ) && ! $this->config['includePageSyncManaged'] ) {
					$rows[] = $this->row( 'skipped', 'post', (string) $post->ID, '', 'Page Sync managed post.' );
					continue;
				}

				$uid = $this->ensure_post_uid( $post->ID );
				if ( '' === $uid ) {
					$uid = 'dry-run';
				}

				$projection  = $this->project_post( $post, $uid );
				$source      = $this->post_source_path( $post, $uid );
				$body_path   = $this->body_path_for_source( $source );
				$body        = $this->is_page_sync_managed( $post->ID ) ? '' : (string) $post->post_content;
				$fingerprint = $this->fingerprint_projection(
					$projection,
					$body
				);

				$seen_sources[ $this->normalize_file_path( $source ) ] = $uid;

				if ( $this->dry_run ) {
					$rows[] = $this->row( 'would-write', 'post', (string) $post->ID, $uid, $this->relative_path( $source ) );
					continue;
				}

				$json_status = $this->write_json_file_status( $source, $projection );
				$body_status = 'unchanged';

				if ( '' !== $body ) {
					$body_status = $this->write_file_status( $body_path, $body );
				} elseif ( file_exists( $body_path ) ) {
					wp_delete_file( $body_path );
					$body_status = file_exists( $body_path ) ? 'failed' : 'written';
				}

				if ( 'failed' === $json_status || 'failed' === $body_status ) {
					$failed_path = 'failed' === $json_status ? $source : $body_path;
					$rows[]      = $this->row(
						'error',
						'post',
						(string) $post->ID,
						$uid,
						'Failed to write ' . $this->relative_path( $failed_path ) . '.'
					);
					continue;
				}

				$this->update_entity_state( 'post', $post->ID, $uid, $source, $fingerprint );
				$changed = in_array( 'written', array( $json_status, $body_status ), true );
				$rows[]  = $this->row( $changed ? 'written' : 'unchanged', 'post', (string) $post->ID, $uid, $this->relative_path( $source ) );
			}
		}

		foreach ( $this->selected_taxonomies( $args ) as $taxonomy ) {
			$rows = array_merge( $rows, $this->pull_terms( $taxonomy, $seen_sources ) );
		}

		$rows = array_merge( $rows, $this->stale_file_rows( $args, $seen_sources ) );
		$this->write_media_manifest( $rows );

		return $rows;
	}

	/**
	 * Push file content into the database.
	 *
	 * @param array $args Push options.
	 *
	 * @return array Result rows.
	 */
	public function push( array $args = array() ): array {
		if ( ! $this->config['enabled'] ) {
			return $this->disabled_rows();
		}

		$selection_errors = $this->selection_error_rows( $args );
		if ( ! empty( $selection_errors ) ) {
			return $selection_errors;
		}

		$this->dry_run = ! empty( $args['dry-run'] );
		$plan          = $this->build_push_plan( $args );

		if ( ! empty( $plan['errors'] ) || $this->dry_run ) {
			return array_merge( $plan['rows'], $plan['errors'] );
		}

		$rows     = $plan['rows'];
		$post_ids = array();
		$term_ids = array();

		foreach ( $this->sort_terms_by_parent( $plan['terms'] ) as $item ) {
			$result = $this->apply_term_file( $item, $term_ids );
			$rows[] = $result['row'];

			if ( isset( $result['term_id'] ) ) {
				$term_ids[ $item['data']['uid'] ] = (int) $result['term_id'];
			}
		}

		foreach ( $this->sort_posts_by_parent( $plan['posts'] ) as $item ) {
			$result = $this->apply_post_file( $item, $post_ids, $term_ids );
			$rows[] = $result['row'];

			if ( isset( $result['post_id'] ) ) {
				$post_ids[ $item['data']['uid'] ] = (int) $result['post_id'];
			}
		}

		if ( ! empty( $args['prune'] ) && ! $this->has_error_rows( $rows ) ) {
			$rows = array_merge( $rows, $this->prune_missing( $plan, $args ) );
		}

		return $rows;
	}

	/**
	 * Compare files and database state.
	 *
	 * @param array $args Status options.
	 *
	 * @return array Status rows.
	 */
	public function status( array $args = array() ): array {
		if ( ! $this->config['enabled'] ) {
			return $this->disabled_rows();
		}

		$selection_errors = $this->selection_error_rows( $args );
		if ( ! empty( $selection_errors ) ) {
			return $selection_errors;
		}

		$rows = array();
		$plan = $this->build_push_plan(
			array_merge(
				$args,
				array(
					'dry-run' => true,
					'preview' => false,
				)
			)
		);

		foreach ( $plan['posts'] as $item ) {
			$rows[] = $this->status_post_file( $item, false );
		}

		foreach ( $plan['terms'] as $item ) {
			$rows[] = $this->status_term_file( $item, false );
		}

		return array_merge( $rows, $this->preview_prune_missing( $plan, 'orphaned', $args ), $this->status_meta_secret_warnings( $plan ), $this->status_body_reference_warnings( $plan ), $plan['errors'] );
	}

	/**
	 * Get normalized config.
	 *
	 * @return array
	 */
	public function config(): array {
		return $this->config;
	}

	/**
	 * Build rows for disabled Content Sync runs.
	 *
	 * @return array
	 */
	private function disabled_rows(): array {
		return array(
			$this->row( 'skipped', 'content', $this->config['id'], '', 'Content Sync is disabled. Set content.enabled to true to run this command.' ),
		);
	}

	/**
	 * Build errors for selectors outside the configured allowlist.
	 *
	 * @param array $args Command options.
	 *
	 * @return array
	 */
	private function selection_error_rows( array $args ): array {
		$rows = array();

		if ( ! empty( $args['post-type'] ) ) {
			$post_type = sanitize_key( (string) $args['post-type'] );
			if ( '' !== $post_type && ! in_array( $post_type, $this->config['postTypes'], true ) ) {
				$rows[] = $this->row( 'error', 'post_type', $post_type, '', "Post type '{$post_type}' is not configured for Content Sync." );
			}
		}

		if ( ! empty( $args['taxonomy'] ) ) {
			$taxonomy = sanitize_key( (string) $args['taxonomy'] );
			if ( '' !== $taxonomy && ! in_array( $taxonomy, $this->config['taxonomies'], true ) ) {
				$rows[] = $this->row( 'error', 'taxonomy', $taxonomy, '', "Taxonomy '{$taxonomy}' is not configured for Content Sync." );
			}
		}

		return $rows;
	}

	/**
	 * Normalize settings.
	 *
	 * @param array $config Raw config.
	 *
	 * @return array
	 */
	private function normalize_config( array $config ): array {
		$defaults = array(
			'enabled'                => false,
			'id'                     => 'default',
			'path'                   => 'content',
			'includePageSyncManaged' => false,
			'authors'                => 'ignore',
			'postTypes'              => array(),
			'meta'                   => array(
				'include'    => array(),
				'exclude'    => array( '_edit_lock', '_edit_last', '_wp_old_slug' ),
				'references' => array(),
			),
			'taxonomies'             => array(),
			'media'                  => 'manifest',
		);

		$config         = array_replace_recursive( $defaults, $config );
		$content_id     = sanitize_key( (string) $config['id'] );
		$config['id']   = '' !== $content_id ? $content_id : 'default';
		$config['path'] = trim( (string) $config['path'], "/ \t\n\r\0\x0B" );

		if ( '' === $config['path'] ) {
			$config['path'] = 'content';
		}

		$config['postTypes']  = array_values( array_filter( array_map( 'sanitize_key', (array) $config['postTypes'] ) ) );
		$config['taxonomies'] = array_values( array_filter( array_map( 'sanitize_key', (array) $config['taxonomies'] ) ) );

		$config['meta']['include'] = array_values( array_filter( array_map( 'strval', (array) ( $config['meta']['include'] ?? array() ) ) ) );
		$config['meta']['exclude'] = array_values(
			array_unique(
				array_merge(
					array( '_edit_lock', '_edit_last', '_wp_old_slug', self::META_UID, self::META_SET, self::META_SOURCE, self::META_FINGERPRINT, self::META_LOCKED ),
					array_filter( array_map( 'strval', (array) ( $config['meta']['exclude'] ?? array() ) ) )
				)
			)
		);

		$config['meta']['references'] = $this->normalize_reference_config( (array) ( $config['meta']['references'] ?? array() ) );
		$config['media']              = 'none' === $config['media'] ? 'none' : 'manifest';
		$config['authors']            = 'login' === $config['authors'] ? 'login' : 'ignore';

		return $config;
	}

	/**
	 * Normalize meta reference declarations.
	 *
	 * @param array $references Raw references.
	 *
	 * @return array
	 */
	private function normalize_reference_config( array $references ): array {
		$normalized = array();

		foreach ( $references as $key => $definition ) {
			$key = (string) $key;
			if ( '' === $key ) {
				continue;
			}

			if ( is_string( $definition ) ) {
				$definition = array( 'kind' => $definition );
			}

			if ( ! is_array( $definition ) ) {
				continue;
			}

			$kind = sanitize_key( (string) ( $definition['kind'] ?? '' ) );
			if ( ! in_array( $kind, array( 'post', 'attachment', 'term' ), true ) ) {
				continue;
			}

			$normalized[ $key ] = array(
				'kind' => $kind,
				'path' => isset( $definition['path'] ) ? (string) $definition['path'] : '',
			);
		}

		return $normalized;
	}

	/**
	 * Build a push plan from files.
	 *
	 * @param array $args Push options.
	 *
	 * @return array
	 */
	private function build_push_plan( array $args ): array {
		$this->dry_run = ! empty( $args['dry-run'] );
		$file_errors   = array();

		$plan = array(
			'posts'  => $this->read_post_files( $args, $file_errors ),
			'terms'  => $this->read_term_files( $args, $file_errors ),
			'rows'   => array(),
			'errors' => $file_errors,
		);

		$post_uids = $this->collect_plan_uids( $plan['posts'] );
		$term_uids = $this->collect_plan_uids( $plan['terms'] );

		$plan['errors'] = array_merge(
			$plan['errors'],
			$this->validate_post_files( $plan['posts'], $post_uids, $term_uids ),
			$this->validate_term_files( $plan['terms'], $term_uids )
		);

		if ( $this->dry_run && false !== ( $args['preview'] ?? true ) ) {
			$plan['rows'] = $this->preview_push_plan( $plan, ! empty( $args['prune'] ), $args );
		}

		return $plan;
	}

	/**
	 * Build non-writing push plan rows.
	 *
	 * @param array $plan          Push plan.
	 * @param bool  $include_prune Whether to preview prune candidates.
	 * @param array $args          Push options.
	 *
	 * @return array
	 */
	private function preview_push_plan( array $plan, bool $include_prune, array $args ): array {
		$rows = array();

		foreach ( $this->sort_terms_by_parent( $plan['terms'] ) as $item ) {
			$rows[] = $this->status_term_file( $item, true );
		}

		foreach ( $this->sort_posts_by_parent( $plan['posts'] ) as $item ) {
			$rows[] = $this->status_post_file( $item, true );
		}

		if ( $include_prune ) {
			$rows = array_merge( $rows, $this->preview_prune_missing( $plan, 'would-prune', $args ) );
		}

		return $rows;
	}

	/**
	 * Build status for a post file.
	 *
	 * @param array $item    File item.
	 * @param bool  $dry_run Whether the row is for push --dry-run.
	 *
	 * @return array
	 */
	private function status_post_file( array $item, bool $dry_run ): array {
		$data     = $item['data'];
		$uid      = (string) ( $data['uid'] ?? '' );
		$existing = '' !== $uid ? $this->find_post_by_uid( $uid ) : null;
		$source   = $this->relative_path( $item['source'] );

		if ( ! $existing ) {
			return $this->row( $dry_run ? 'would-create' : 'missing-db', 'post', (string) ( $data['slug'] ?? '' ), $uid, $source );
		}

		if ( get_post_meta( $existing->ID, self::META_LOCKED, true ) ) {
			return $this->row( 'locked', 'post', (string) $existing->ID, $uid, 'Locked entity skipped.' );
		}

		$file_fingerprint = $this->fingerprint_projection( $data, (string) ( $item['body'] ?? '' ) );
		$stored           = (string) get_post_meta( $existing->ID, self::META_FINGERPRINT, true );
		$live             = $this->fingerprint_post_for_file( $existing, $data, (string) ( $item['body'] ?? '' ) );

		if ( '' !== $stored && ! hash_equals( $stored, $live ) ) {
			return $this->row( 'conflict', 'post', (string) $existing->ID, $uid, 'Database changed since last sync.' );
		}

		if ( '' !== $stored && hash_equals( $stored, $file_fingerprint ) ) {
			return $this->row( 'unchanged', 'post', (string) $existing->ID, $uid, $source );
		}

		return $this->row( 'would-update', 'post', (string) $existing->ID, $uid, $source );
	}

	/**
	 * Build status for a term file.
	 *
	 * @param array $item    File item.
	 * @param bool  $dry_run Whether the row is for push --dry-run.
	 *
	 * @return array
	 */
	private function status_term_file( array $item, bool $dry_run ): array {
		$data     = $item['data'];
		$uid      = (string) ( $data['uid'] ?? '' );
		$taxonomy = (string) ( $data['taxonomy'] ?? '' );
		$existing = '' !== $uid && '' !== $taxonomy ? $this->find_term_by_uid( $uid, $taxonomy ) : null;
		$source   = $this->relative_path( $item['source'] );

		if ( ! $existing ) {
			return $this->row( $dry_run ? 'would-create' : 'missing-db', 'term', (string) ( $data['slug'] ?? '' ), $uid, $source );
		}

		if ( get_term_meta( $existing->term_id, self::META_LOCKED, true ) ) {
			return $this->row( 'locked', 'term', (string) $existing->term_id, $uid, 'Locked entity skipped.' );
		}

		$file_fingerprint = $this->fingerprint_projection( $data, '' );
		$stored           = (string) get_term_meta( $existing->term_id, self::META_FINGERPRINT, true );
		$live             = $this->fingerprint_term( $existing, $data );

		if ( '' !== $stored && ! hash_equals( $stored, $live ) ) {
			return $this->row( 'conflict', 'term', (string) $existing->term_id, $uid, 'Database changed since last sync.' );
		}

		if ( '' !== $stored && hash_equals( $stored, $file_fingerprint ) ) {
			return $this->row( 'unchanged', 'term', (string) $existing->term_id, $uid, $source );
		}

		return $this->row( 'would-update', 'term', (string) $existing->term_id, $uid, $source );
	}

	/**
	 * Preview content-set entities missing from the file plan.
	 *
	 * @param array  $plan       Push plan.
	 * @param string $row_action Row action. Use orphaned for status output.
	 * @param array  $args       Selection options.
	 *
	 * @return array
	 */
	private function preview_prune_missing( array $plan, string $row_action = 'would-prune', array $args = array() ): array {
		$rows      = array();
		$post_uids = array();
		$term_uids = array();

		foreach ( $plan['posts'] as $item ) {
			$post_uids[] = (string) ( $item['data']['uid'] ?? '' );
		}

		foreach ( $plan['terms'] as $item ) {
			$term_uids[] = (string) ( $item['data']['uid'] ?? '' );
		}

		foreach ( $this->query_prunable_posts( $args ) as $post ) {
			$uid = (string) get_post_meta( $post->ID, self::META_UID, true );
			if ( '' === $uid || in_array( $uid, $post_uids, true ) ) {
				continue;
			}

			$action  = $this->orphan_action( 'post', $post->ID );
			$message = 'orphaned' === $row_action ? 'Missing from files; push --prune would ' . $action . ' this entity.' : '';
			$rows[]  = $this->row( 'orphaned' === $row_action ? 'orphaned' : $row_action . '-' . $action, 'post', (string) $post->ID, $uid, $message );
		}

		foreach ( $this->query_content_terms( $args ) as $term ) {
			$uid = (string) get_term_meta( $term->term_id, self::META_UID, true );
			if ( '' === $uid || in_array( $uid, $term_uids, true ) ) {
				continue;
			}

			$action  = $this->orphan_action( 'term', $term->term_id );
			$message = 'orphaned' === $row_action ? 'Missing from files; push --prune would ' . $action . ' this entity.' : '';
			$rows[]  = $this->row( 'orphaned' === $row_action ? 'orphaned' : $row_action . '-' . $action, 'term', (string) $term->term_id, $uid, $message );
		}

		return $rows;
	}

	/**
	 * Build status warnings for allowlisted meta keys that look sensitive.
	 *
	 * @param array $plan Push plan.
	 *
	 * @return array
	 */
	private function status_meta_secret_warnings( array $plan ): array {
		$keys = array();

		foreach ( $this->config['meta']['include'] as $pattern ) {
			$keys[ (string) $pattern ] = true;
		}

		foreach ( array_merge( $plan['posts'], $plan['terms'] ) as $item ) {
			foreach ( array_keys( (array) ( $item['data']['meta'] ?? array() ) ) as $key ) {
				if ( $this->meta_key_allowed( (string) $key ) ) {
					$keys[ (string) $key ] = true;
				}
			}
		}

		$warnings = array();
		foreach ( array_keys( $keys ) as $key ) {
			if ( ! $this->meta_key_looks_sensitive( $key ) ) {
				continue;
			}

			$warnings[] = $this->row( 'warning', 'meta', $key, '', 'Allowlisted meta key may contain secrets or PII; review before committing synced files.' );
		}

		usort(
			$warnings,
			static fn( array $a, array $b ): int => strcmp( (string) $a['id'], (string) $b['id'] )
		);

		return $warnings;
	}

	/**
	 * Check whether a meta key or pattern looks sensitive.
	 *
	 * @param string $key Meta key or glob pattern.
	 *
	 * @return bool
	 */
	private function meta_key_looks_sensitive( string $key ): bool {
		return 1 === preg_match( '/(?:secret|token|password|passwd|pwd|api[\W_]*key|private[\W_]*key|client[\W_]*secret|access[\W_]*key|credential)/i', $key );
	}

	/**
	 * Build status warnings for numeric IDs inside block markup bodies.
	 *
	 * @param array $plan Push plan.
	 *
	 * @return array
	 */
	private function status_body_reference_warnings( array $plan ): array {
		$warnings = array();

		foreach ( $plan['posts'] as $item ) {
			$body = (string) ( $item['body'] ?? '' );
			if ( '' === $body || ! $this->body_contains_block_id_reference( $body ) ) {
				continue;
			}

			$data       = $item['data'];
			$warnings[] = $this->row(
				'warning',
				'body',
				(string) ( $data['slug'] ?? '' ),
				(string) ( $data['uid'] ?? '' ),
				'Post body contains numeric IDs in block markup; Content Sync does not rewrite IDs inside .html files.'
			);
		}

		return $warnings;
	}

	/**
	 * Check whether block markup contains likely numeric content references.
	 *
	 * @param string $body Post body.
	 *
	 * @return bool
	 */
	private function body_contains_block_id_reference( string $body ): bool {
		if ( ! str_contains( $body, '<!-- wp:' ) ) {
			return false;
		}

		foreach ( parse_blocks( $body ) as $block ) {
			if ( $this->block_contains_id_reference( $block ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether one parsed block contains a likely numeric content reference.
	 *
	 * @param array $block Parsed block.
	 *
	 * @return bool
	 */
	private function block_contains_id_reference( array $block ): bool {
		if ( $this->attributes_contain_id_reference( (array) ( $block['attrs'] ?? array() ) ) ) {
			return true;
		}

		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner_block ) {
			if ( is_array( $inner_block ) && $this->block_contains_id_reference( $inner_block ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check parsed block attributes for numeric content reference fields.
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return bool
	 */
	private function attributes_contain_id_reference( array $attributes ): bool {
		$reference_keys = array(
			'attachmentid',
			'author',
			'authors',
			'categoryids',
			'id',
			'ids',
			'include',
			'mediaid',
			'postid',
			'postids',
			'termid',
			'termids',
		);

		foreach ( $attributes as $key => $value ) {
			$normalized_key = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $key ) ?? '' );

			if ( in_array( $normalized_key, $reference_keys, true ) && $this->value_contains_numeric_reference( $value ) ) {
				return true;
			}

			if ( is_array( $value ) && $this->attributes_contain_id_reference( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a value is, or contains, a numeric ID reference.
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	private function value_contains_numeric_reference( mixed $value ): bool {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return (int) $value > 0;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( $this->value_contains_numeric_reference( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Collect UIDs from file plan items.
	 *
	 * @param array $items File plan items.
	 *
	 * @return array
	 */
	private function collect_plan_uids( array $items ): array {
		$uids = array();

		foreach ( $items as $item ) {
			$uid = (string) ( $item['data']['uid'] ?? '' );
			if ( '' !== $uid ) {
				$uids[ $uid ] = true;
			}
		}

		return $uids;
	}

	/**
	 * Validate post file plan.
	 *
	 * @param array $items     Post file items.
	 * @param array $post_uids Post UIDs in the current push plan.
	 * @param array $term_uids Term UIDs in the current push plan.
	 *
	 * @return array Error rows.
	 */
	private function validate_post_files( array $items, array $post_uids, array $term_uids ): array {
		$errors    = array();
		$seen      = array();
		$slug_keys = array();

		foreach ( $items as $item ) {
			$data = $item['data'];
			$uid  = (string) ( $data['uid'] ?? '' );

			if ( '' === $uid ) {
				$errors[] = $this->row( 'error', 'post', '', '', $this->relative_path( $item['source'] ) . ' has no uid.' );
				continue;
			}

			if ( isset( $seen[ $uid ] ) ) {
				$errors[] = $this->row( 'error', 'post', '', $uid, 'Duplicate post uid.' );
			}

			$seen[ $uid ] = true;

			$post_type = (string) ( $data['type'] ?? '' );
			if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
				$errors[] = $this->row( 'error', 'post', '', $uid, "Post type '{$post_type}' is not registered." );
			}

			$existing = $this->find_post_by_uid( $uid );

			$slug_key = $this->post_slug_plan_key( $data );
			if ( '' !== $slug_key ) {
				if ( isset( $slug_keys[ $slug_key ] ) ) {
					$errors[] = $this->row( 'error', 'post', (string) ( $data['slug'] ?? '' ), $uid, 'Duplicate post slug in file plan.' );
				}

				$slug_keys[ $slug_key ] = true;
			}

			if ( $this->has_slug_conflict( $data, $existing, $post_uids ) ) {
				$errors[] = $this->row( 'error', 'post', (string) ( $data['slug'] ?? '' ), $uid, 'Slug conflict.' );
			}

			if ( ! empty( $data['parent'] ) && ! $this->can_resolve_post_reference( (string) $data['parent'], $items ) ) {
				$errors[] = $this->row( 'error', 'post', (string) ( $data['slug'] ?? '' ), $uid, 'Parent reference cannot be resolved.' );
			}

			$author_login = (string) ( $data['author']['login'] ?? '' );
			if ( 'login' === $this->config['authors'] && '' !== $author_login && ! get_user_by( 'login', $author_login ) ) {
				$errors[] = $this->row( 'error', 'post', (string) ( $data['slug'] ?? '' ), $uid, "Author login '{$author_login}' cannot be resolved." );
			}

			$reference_errors = array_merge(
				$this->validate_meta_references( $data['meta'] ?? array(), $data['metaEncoding'] ?? array(), $uid, $post_uids, $term_uids ),
				$this->validate_post_term_references( $data, $uid, $term_uids )
			);
			$errors           = array_merge( $errors, $reference_errors );
		}

		return $errors;
	}

	/**
	 * Validate term file plan.
	 *
	 * @param array $items     Term file items.
	 * @param array $term_uids Term UIDs in the current push plan.
	 *
	 * @return array Error rows.
	 */
	private function validate_term_files( array $items, array $term_uids ): array {
		$errors = array();
		$seen   = array();

		foreach ( $items as $item ) {
			$data     = $item['data'];
			$uid      = (string) ( $data['uid'] ?? '' );
			$taxonomy = (string) ( $data['taxonomy'] ?? '' );

			if ( '' === $uid ) {
				$errors[] = $this->row( 'error', 'term', '', '', $this->relative_path( $item['source'] ) . ' has no uid.' );
				continue;
			}

			if ( isset( $seen[ $uid ] ) ) {
				$errors[] = $this->row( 'error', 'term', '', $uid, 'Duplicate term uid.' );
			}

			$seen[ $uid ] = true;

			if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				$errors[] = $this->row( 'error', 'term', '', $uid, "Taxonomy '{$taxonomy}' is not registered." );
			}

			if ( ! empty( $data['parent'] ) && ! isset( $term_uids[ (string) $data['parent'] ] ) && $this->resolve_term_uid( (string) $data['parent'], $taxonomy ) <= 0 ) {
				$errors[] = $this->row( 'error', 'term', (string) ( $data['slug'] ?? '' ), $uid, 'Parent term reference cannot be resolved.' );
			}
		}

		return $errors;
	}

	/**
	 * Validate post term relationship references.
	 *
	 * @param array  $data      Post file data.
	 * @param string $owner_uid Owner UID.
	 * @param array  $term_uids Term UIDs in the current push plan.
	 *
	 * @return array Error rows.
	 */
	private function validate_post_term_references( array $data, string $owner_uid, array $term_uids ): array {
		$errors = array();

		foreach ( (array) ( $data['terms'] ?? array() ) as $taxonomy => $uids ) {
			$taxonomy = (string) $taxonomy;
			if ( ! in_array( $taxonomy, $this->config['taxonomies'], true ) ) {
				$errors[] = $this->row( 'error', 'terms', $taxonomy, $owner_uid, "Taxonomy '{$taxonomy}' is not configured for Content Sync." );
				continue;
			}

			if ( ! taxonomy_exists( $taxonomy ) ) {
				$errors[] = $this->row( 'error', 'terms', $taxonomy, $owner_uid, "Taxonomy '{$taxonomy}' is not registered." );
				continue;
			}

			foreach ( (array) $uids as $uid ) {
				$uid = (string) $uid;
				if ( '' === $uid || isset( $term_uids[ $uid ] ) || $this->resolve_term_uid( $uid, $taxonomy ) > 0 ) {
					continue;
				}

				$errors[] = $this->row( 'error', 'terms', $taxonomy, $owner_uid, "Term reference '{$uid}' cannot be resolved." );
			}
		}

		return $errors;
	}

	/**
	 * Validate declared meta references.
	 *
	 * @param array  $meta          Meta values.
	 * @param array  $meta_encoding Meta encodings.
	 * @param string $owner_uid     Owner UID.
	 * @param array  $post_uids     Post UIDs in the current push plan.
	 * @param array  $term_uids     Term UIDs in the current push plan.
	 *
	 * @return array Error rows.
	 */
	private function validate_meta_references( array $meta, array $meta_encoding, string $owner_uid, array $post_uids, array $term_uids ): array {
		$errors = array();

		foreach ( $this->config['meta']['references'] as $key => $definition ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				continue;
			}

			$encoding = $meta_encoding[ $key ] ?? 'scalar';
			if ( 'base64' === $encoding ) {
				$errors[] = $this->row( 'error', 'meta', $key, $owner_uid, 'Reference meta cannot use opaque base64 encoding.' );
				continue;
			}

			$values = $this->collect_reference_values( $meta[ $key ], (string) $definition['path'] );
			foreach ( $values as $value ) {
				if ( null === $value || '' === $value ) {
					continue;
				}

				if ( 'attachment' === $definition['kind'] && 'none' === $this->config['media'] ) {
					continue;
				}

				if ( 'post' === $definition['kind'] && isset( $post_uids[ (string) $value ] ) ) {
					continue;
				}

				if ( 'term' === $definition['kind'] && isset( $term_uids[ (string) $value ] ) ) {
					continue;
				}

				if ( ! $this->resolve_reference_uid( (string) $value, (string) $definition['kind'] ) ) {
					$errors[] = $this->row( 'error', 'meta', $key, $owner_uid, "Reference '{$value}' cannot be resolved." );
				}
			}
		}

		return $errors;
	}

	/**
	 * Pull terms for one taxonomy.
	 *
	 * @param string $taxonomy     Taxonomy.
	 * @param array  $seen_sources Absolute source paths seen during pull.
	 *
	 * @return array Result rows.
	 */
	private function pull_terms( string $taxonomy, array &$seen_sources ): array {
		$rows = array();

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( $this->row( 'error', 'taxonomy', $taxonomy, '', "Taxonomy '{$taxonomy}' is not registered." ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array( $this->row( 'error', 'taxonomy', $taxonomy, '', $terms->get_error_message() ) );
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$uid = $this->ensure_term_uid( $term->term_id );
			if ( '' === $uid ) {
				$uid = 'dry-run';
			}

			$projection  = $this->project_term( $term, $uid );
			$source      = $this->term_source_path( $term, $uid );
			$fingerprint = $this->fingerprint_projection( $projection, '' );

			$seen_sources[ $this->normalize_file_path( $source ) ] = $uid;

			if ( $this->dry_run ) {
				$rows[] = $this->row( 'would-write', 'term', (string) $term->term_id, $uid, $this->relative_path( $source ) );
				continue;
			}

			$status = $this->write_json_file_status( $source, $projection );
			if ( 'failed' === $status ) {
				$rows[] = $this->row(
					'error',
					'term',
					(string) $term->term_id,
					$uid,
					'Failed to write ' . $this->relative_path( $source ) . '.'
				);
				continue;
			}

			$this->update_entity_state( 'term', $term->term_id, $uid, $source, $fingerprint );
			$changed = 'written' === $status;
			$rows[]  = $this->row( $changed ? 'written' : 'unchanged', 'term', (string) $term->term_id, $uid, $this->relative_path( $source ) );
		}

		return $rows;
	}

	/**
	 * Apply one post file.
	 *
	 * @param array $item     File item.
	 * @param array $post_ids UID to post ID map.
	 * @param array $term_ids UID to term ID map.
	 *
	 * @return array
	 */
	private function apply_post_file( array $item, array $post_ids, array $term_ids ): array {
		$data        = $item['data'];
		$uid         = (string) $data['uid'];
		$existing    = $this->find_post_by_uid( $uid );
		$fingerprint = $this->fingerprint_projection( $data, $item['body'] );

		if ( $existing && (string) get_post_meta( $existing->ID, self::META_FINGERPRINT, true ) === $fingerprint && hash_equals( $fingerprint, $this->fingerprint_post_for_file( $existing, $data, (string) $item['body'] ) ) ) {
			return array(
				'post_id' => $existing->ID,
				'row'     => $this->row( 'unchanged', 'post', (string) $existing->ID, $uid, $this->relative_path( $item['source'] ) ),
			);
		}

		if ( $existing && get_post_meta( $existing->ID, self::META_LOCKED, true ) ) {
			return array(
				'post_id' => $existing->ID,
				'row'     => $this->row( 'locked', 'post', (string) $existing->ID, $uid, 'Locked entity skipped.' ),
			);
		}

		$post_data = array(
			'post_type'   => (string) $data['type'],
			'post_status' => (string) ( $data['status'] ?? 'publish' ),
			'post_name'   => (string) $data['slug'],
			'post_title'  => (string) ( $data['title'] ?? '' ),
			'menu_order'  => (int) ( $data['menuOrder'] ?? 0 ),
		);

		if ( ! $existing || ! $this->is_page_sync_managed( $existing->ID ) ) {
			$post_data['post_content'] = $item['body'];
		}

		if ( ! empty( $data['date'] ) ) {
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['date'] ) );
			$post_data['post_date']     = get_date_from_gmt( $post_data['post_date_gmt'] );
		}

		if ( ! empty( $data['modified'] ) ) {
			$post_data['post_modified_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['modified'] ) );
			$post_data['post_modified']     = get_date_from_gmt( $post_data['post_modified_gmt'] );
		}

		if ( 'login' === $this->config['authors'] && ! empty( $data['author']['login'] ) ) {
			$user = get_user_by( 'login', (string) $data['author']['login'] );
			if ( $user ) {
				$post_data['post_author'] = (int) $user->ID;
			}
		}

		if ( ! empty( $data['parent'] ) ) {
			$post_data['post_parent'] = $post_ids[ (string) $data['parent'] ] ?? $this->resolve_post_uid( (string) $data['parent'] );
		}

		if ( $existing ) {
			$post_data['ID'] = $existing->ID;
			$post_id         = wp_update_post( wp_slash( $post_data ), true );
			$action          = 'updated';
		} else {
			$post_id = wp_insert_post( wp_slash( $post_data ), true );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return array( 'row' => $this->row( 'error', 'post', (string) ( $data['slug'] ?? '' ), $uid, $post_id->get_error_message() ) );
		}

		$this->apply_post_meta( (int) $post_id, $data );
		$this->apply_post_terms( (int) $post_id, $data, $term_ids );
		$this->update_entity_state( 'post', (int) $post_id, $uid, $item['source'], $fingerprint );

		return array(
			'post_id' => (int) $post_id,
			'row'     => $this->row( $action, 'post', (string) $post_id, $uid, $this->relative_path( $item['source'] ) ),
		);
	}

	/**
	 * Apply one term file.
	 *
	 * @param array $item     File item.
	 * @param array $term_ids UID to term ID map.
	 *
	 * @return array
	 */
	private function apply_term_file( array $item, array $term_ids ): array {
		$data        = $item['data'];
		$uid         = (string) $data['uid'];
		$taxonomy    = (string) $data['taxonomy'];
		$existing    = $this->find_term_by_uid( $uid, $taxonomy );
		$fingerprint = $this->fingerprint_projection( $data, '' );

		if ( $existing && (string) get_term_meta( $existing->term_id, self::META_FINGERPRINT, true ) === $fingerprint && hash_equals( $fingerprint, $this->fingerprint_term( $existing, $data ) ) ) {
			return array(
				'term_id' => $existing->term_id,
				'row'     => $this->row( 'unchanged', 'term', (string) $existing->term_id, $uid, $this->relative_path( $item['source'] ) ),
			);
		}

		if ( $existing && get_term_meta( $existing->term_id, self::META_LOCKED, true ) ) {
			return array(
				'term_id' => $existing->term_id,
				'row'     => $this->row( 'locked', 'term', (string) $existing->term_id, $uid, 'Locked entity skipped.' ),
			);
		}

		$args = array(
			'slug'        => (string) ( $data['slug'] ?? '' ),
			'description' => (string) ( $data['description'] ?? '' ),
		);

		if ( ! empty( $data['parent'] ) ) {
			$args['parent'] = $term_ids[ (string) $data['parent'] ] ?? $this->resolve_term_uid( (string) $data['parent'], $taxonomy );
		}

		if ( $existing ) {
			$result = wp_update_term( $existing->term_id, $taxonomy, array_merge( $args, array( 'name' => (string) ( $data['name'] ?? '' ) ) ) );
			$action = 'updated';
		} else {
			$result = wp_insert_term( (string) ( $data['name'] ?? $data['slug'] ?? '' ), $taxonomy, $args );
			$action = 'created';
		}

		if ( is_wp_error( $result ) ) {
			return array( 'row' => $this->row( 'error', 'term', (string) ( $data['slug'] ?? '' ), $uid, $result->get_error_message() ) );
		}

		$term_id = (int) ( $result['term_id'] ?? $existing->term_id ?? 0 );
		$this->apply_term_meta( $term_id, $data );
		$this->update_entity_state( 'term', $term_id, $uid, $item['source'], $fingerprint );

		return array(
			'term_id' => $term_id,
			'row'     => $this->row( $action, 'term', (string) $term_id, $uid, $this->relative_path( $item['source'] ) ),
		);
	}

	/**
	 * Apply post meta from a file projection.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    File data.
	 *
	 * @return void
	 */
	private function apply_post_meta( int $post_id, array $data ): void {
		$this->apply_meta( 'post', $post_id, (array) ( $data['meta'] ?? array() ), (array) ( $data['metaEncoding'] ?? array() ) );
	}

	/**
	 * Apply term meta from a file projection.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $data    File data.
	 *
	 * @return void
	 */
	private function apply_term_meta( int $term_id, array $data ): void {
		$this->apply_meta( 'term', $term_id, (array) ( $data['meta'] ?? array() ), (array) ( $data['metaEncoding'] ?? array() ) );
	}

	/**
	 * Apply meta to an entity.
	 *
	 * @param string $type          Entity type.
	 * @param int    $id            Entity ID.
	 * @param array  $meta          Meta values.
	 * @param array  $meta_encoding Meta encodings.
	 *
	 * @return void
	 */
	private function apply_meta( string $type, int $id, array $meta, array $meta_encoding ): void {
		$meta = $this->filter_applicable_meta( $meta );

		$existing = $this->get_raw_meta( $type, $id );

		foreach ( $existing as $key => $_values ) {
			if ( $this->meta_key_allowed( $key ) && ! $this->should_drop_meta_key( $key ) && ! array_key_exists( $key, $meta ) ) {
				delete_metadata( $type, $id, $key );
			}
		}

		foreach ( $meta as $key => $value ) {
			if ( ! $this->meta_key_allowed( (string) $key ) ) {
				continue;
			}

			$value    = $this->rewrite_meta_references_for_push( (string) $key, $value );
			$encoding = $meta_encoding[ $key ] ?? 'scalar';
			$values   = $this->encode_meta_values( $value, $encoding );

			delete_metadata( $type, $id, (string) $key );

			foreach ( $values as $raw ) {
				add_metadata( $type, $id, (string) $key, wp_slash( $raw ), false );
			}
		}
	}

	/**
	 * Remove file meta entries that should not be applied.
	 *
	 * @param array $meta Meta values.
	 *
	 * @return array
	 */
	private function filter_applicable_meta( array $meta ): array {
		foreach ( array_keys( $meta ) as $key ) {
			if ( $this->should_drop_meta_key( (string) $key ) ) {
				unset( $meta[ $key ] );
			}
		}

		return $meta;
	}

	/**
	 * Check whether a meta key should be omitted entirely.
	 *
	 * @param string $key Meta key.
	 *
	 * @return bool
	 */
	private function should_drop_meta_key( string $key ): bool {
		$definition = $this->config['meta']['references'][ $key ] ?? null;
		return 'none' === $this->config['media'] && is_array( $definition ) && 'attachment' === ( $definition['kind'] ?? '' );
	}

	/**
	 * Apply post term relationships.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $data     File data.
	 * @param array $term_ids UID to term ID map.
	 *
	 * @return void
	 */
	private function apply_post_terms( int $post_id, array $data, array $term_ids ): void {
		foreach ( (array) ( $data['terms'] ?? array() ) as $taxonomy => $uids ) {
			if ( ! in_array( (string) $taxonomy, $this->config['taxonomies'], true ) ) {
				continue;
			}

			if ( ! taxonomy_exists( (string) $taxonomy ) ) {
				continue;
			}

			$ids = array();
			foreach ( (array) $uids as $uid ) {
				$term_id = $term_ids[ (string) $uid ] ?? $this->resolve_term_uid( (string) $uid, (string) $taxonomy );
				if ( $term_id > 0 ) {
					$ids[] = $term_id;
				}
			}

			wp_set_object_terms( $post_id, $ids, (string) $taxonomy, false );
		}
	}

	/**
	 * Read post files.
	 *
	 * @param array $args   Options.
	 * @param array $errors Read errors.
	 *
	 * @return array
	 */
	private function read_post_files( array $args = array(), array &$errors = array() ): array {
		$items      = array();
		$post_types = $this->selected_post_types( $args );

		foreach ( $post_types as $post_type ) {
			$dir = $this->root_path() . '/posts/' . $post_type;
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$files = glob( $dir . '/*.json' );
			foreach ( false !== $files ? $files : array() as $file ) {
				$error = '';
				$data  = Utils::read_json_file( $file, $error );
				if ( ! is_array( $data ) ) {
					$errors[] = $this->row( 'error', 'post', basename( $file ), '', $this->relative_path( $file ) . ' could not be read: ' . $error );
					continue;
				}

				$body_file = $this->body_path_for_source( $file );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync body file.
				$body = file_exists( $body_file ) ? (string) file_get_contents( $body_file ) : '';

				$items[] = array(
					'source' => $file,
					'body'   => $body,
					'data'   => $data,
				);
			}
		}

		return $items;
	}

	/**
	 * Read term files.
	 *
	 * @param array $args   Options.
	 * @param array $errors Read errors.
	 *
	 * @return array
	 */
	private function read_term_files( array $args = array(), array &$errors = array() ): array {
		$items = array();

		foreach ( $this->selected_taxonomies( $args ) as $taxonomy ) {
			$dir = $this->root_path() . '/terms/' . $taxonomy;
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$files = glob( $dir . '/*.json' );
			foreach ( false !== $files ? $files : array() as $file ) {
				$error = '';
				$data  = Utils::read_json_file( $file, $error );
				if ( ! is_array( $data ) ) {
					$errors[] = $this->row( 'error', 'term', basename( $file ), '', $this->relative_path( $file ) . ' could not be read: ' . $error );
					continue;
				}

				$items[] = array(
					'source' => $file,
					'data'   => $data,
				);
			}
		}

		return $items;
	}

	/**
	 * Report existing content files that were not produced by the current pull.
	 *
	 * @param array $args         Pull options.
	 * @param array $seen_sources Absolute source paths seen during pull.
	 *
	 * @return array
	 */
	private function stale_file_rows( array $args, array $seen_sources ): array {
		$rows = array();

		foreach ( $this->selected_post_types( $args ) as $post_type ) {
			$rows = array_merge( $rows, $this->stale_json_files_in_dir( $this->root_path() . '/posts/' . $post_type, 'post', $seen_sources ) );
		}

		foreach ( $this->selected_taxonomies( $args ) as $taxonomy ) {
			$rows = array_merge( $rows, $this->stale_json_files_in_dir( $this->root_path() . '/terms/' . $taxonomy, 'term', $seen_sources ) );
		}

		return $rows;
	}

	/**
	 * Report stale JSON files in one directory.
	 *
	 * @param string $dir          Directory.
	 * @param string $entity       Entity type.
	 * @param array  $seen_sources Absolute source paths seen during pull.
	 *
	 * @return array
	 */
	private function stale_json_files_in_dir( string $dir, string $entity, array $seen_sources ): array {
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$rows  = array();
		$files = glob( $dir . '/*.json' );

		foreach ( false !== $files ? $files : array() as $file ) {
			if ( isset( $seen_sources[ $this->normalize_file_path( $file ) ] ) ) {
				continue;
			}

			$data   = Utils::read_json_file( $file );
			$uid    = is_array( $data ) ? (string) ( $data['uid'] ?? '' ) : '';
			$rows[] = $this->row( 'stale', $entity, '', $uid, $this->relative_path( $file ) );
		}

		return $rows;
	}

	/**
	 * Project a post into file data.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $uid  UID.
	 *
	 * @return array
	 */
	private function project_post( WP_Post $post, string $uid ): array {
		$meta = $this->project_meta( 'post', $post->ID );
		$data = array(
			'uid'          => $uid,
			'type'         => $post->post_type,
			'status'       => $post->post_status,
			'slug'         => $post->post_name,
			'title'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'date'         => $this->mysql_gmt_to_iso( $post->post_date_gmt ),
			'modified'     => $this->mysql_gmt_to_iso( $post->post_modified_gmt ),
			'parent'       => $post->post_parent > 0 ? $this->ensure_post_uid( (int) $post->post_parent ) : null,
			'menuOrder'    => (int) $post->menu_order,
			'meta'         => $meta['meta'],
			'metaEncoding' => $meta['encoding'],
		);

		if ( 'login' === $this->config['authors'] ) {
			$user = get_user_by( 'id', (int) $post->post_author );
			if ( $user ) {
				$data['author'] = array( 'login' => $user->user_login );
			}
		}

		$terms = $this->project_post_terms( $post->ID );
		if ( ! empty( $terms ) ) {
			$data['terms'] = $terms;
		}

		return $this->sort_projection( $data );
	}

	/**
	 * Project a term into file data.
	 *
	 * @param WP_Term $term Term.
	 * @param string  $uid  UID.
	 *
	 * @return array
	 */
	private function project_term( WP_Term $term, string $uid ): array {
		$meta = $this->project_meta( 'term', $term->term_id );

		return $this->sort_projection(
			array(
				'uid'          => $uid,
				'taxonomy'     => $term->taxonomy,
				'slug'         => $term->slug,
				'name'         => $term->name,
				'description'  => $term->description,
				'parent'       => $term->parent > 0 ? $this->ensure_term_uid( (int) $term->parent ) : null,
				'meta'         => $meta['meta'],
				'metaEncoding' => $meta['encoding'],
			)
		);
	}

	/**
	 * Project allowed meta.
	 *
	 * @param string $type Entity type.
	 * @param int    $id   Entity ID.
	 *
	 * @return array
	 */
	private function project_meta( string $type, int $id ): array {
		$meta     = array();
		$encoding = array();

		foreach ( $this->get_raw_meta( $type, $id ) as $key => $values ) {
			if ( ! $this->meta_key_allowed( $key ) ) {
				continue;
			}

			if ( $this->should_drop_meta_key( $key ) ) {
				continue;
			}

			$projected_values   = array();
			$projected_encoding = array();

			foreach ( $values as $raw ) {
				$decoded = $this->decode_meta_value( (string) $raw );
				$value   = $this->rewrite_meta_references_for_pull( $key, $decoded['value'] );

				$projected_values[]   = $value;
				$projected_encoding[] = $decoded['encoding'];
			}

			$meta[ $key ]     = 1 === count( $projected_values ) ? $projected_values[0] : $projected_values;
			$encoding[ $key ] = 1 === count( $projected_encoding ) ? $projected_encoding[0] : $projected_encoding;
		}

		return array(
			'meta'     => $meta,
			'encoding' => $encoding,
		);
	}

	/**
	 * Project post term relationships.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private function project_post_terms( int $post_id ): array {
		$data = array();

		foreach ( $this->config['taxonomies'] as $taxonomy ) {
			$data[ $taxonomy ] = array();

			$terms = wp_get_object_terms( $post_id, $taxonomy );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$uids = array();
			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term ) {
					$uids[] = $this->ensure_term_uid( $term->term_id );
				}
			}

			sort( $uids );
			$data[ $taxonomy ] = $uids;
		}

		return $data;
	}

	/**
	 * Rewrite meta references from local IDs to UIDs.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 *
	 * @return mixed
	 */
	private function rewrite_meta_references_for_pull( string $key, mixed $value ): mixed {
		$definition = $this->config['meta']['references'][ $key ] ?? null;
		if ( ! $definition ) {
			return $value;
		}

		if ( 'attachment' === $definition['kind'] && 'none' === $this->config['media'] ) {
			return null;
		}

		return $this->map_reference_value(
			$value,
			(string) $definition['path'],
			function ( $local_id ) use ( $definition ) {
				$local_id = (int) $local_id;
				if ( $local_id <= 0 ) {
					return $local_id;
				}

				if ( 'attachment' === $definition['kind'] || 'post' === $definition['kind'] ) {
					return $this->ensure_post_uid( $local_id );
				}

				if ( 'term' === $definition['kind'] ) {
					return $this->ensure_term_uid( $local_id );
				}

				return $local_id;
			}
		);
	}

	/**
	 * Rewrite meta references from UIDs to local IDs.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 *
	 * @return mixed
	 */
	private function rewrite_meta_references_for_push( string $key, mixed $value ): mixed {
		$definition = $this->config['meta']['references'][ $key ] ?? null;
		if ( ! $definition ) {
			return $value;
		}

		if ( 'attachment' === $definition['kind'] && 'none' === $this->config['media'] ) {
			return null;
		}

		return $this->map_reference_value(
			$value,
			(string) $definition['path'],
			function ( $uid ) use ( $definition ) {
				if ( null === $uid || '' === $uid ) {
					return $uid;
				}

				$id = $this->resolve_reference_uid( (string) $uid, (string) $definition['kind'] );
				return $id > 0 ? $id : $uid;
			}
		);
	}

	/**
	 * Map a reference value at a declared path.
	 *
	 * @param mixed    $value    Value.
	 * @param string   $path     Path.
	 * @param callable $callback Mapper.
	 *
	 * @return mixed
	 */
	private function map_reference_value( mixed $value, string $path, callable $callback ): mixed {
		if ( '' === $path ) {
			return $callback( $value );
		}

		$segments = explode( '.', $path );
		return $this->map_reference_segments( $value, $segments, $callback );
	}

	/**
	 * Map reference path segments.
	 *
	 * @param mixed    $value    Value.
	 * @param array    $segments Path segments.
	 * @param callable $callback Mapper.
	 *
	 * @return mixed
	 */
	private function map_reference_segments( mixed $value, array $segments, callable $callback ): mixed {
		if ( empty( $segments ) ) {
			return $callback( $value );
		}

		$segment = array_shift( $segments );

		if ( '*' === $segment && is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				$value[ $index ] = $this->map_reference_segments( $item, $segments, $callback );
			}
			return $value;
		}

		if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
			$value[ $segment ] = $this->map_reference_segments( $value[ $segment ], $segments, $callback );
		}

		return $value;
	}

	/**
	 * Collect declared reference values.
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path.
	 *
	 * @return array
	 */
	private function collect_reference_values( mixed $value, string $path ): array {
		$values = array();

		$this->map_reference_value(
			$value,
			$path,
			function ( $item ) use ( &$values ) {
				$values[] = $item;
				return $item;
			}
		);

		return $values;
	}

	/**
	 * Decode raw meta value.
	 *
	 * @param string $raw Raw meta value.
	 *
	 * @return array
	 */
	private function decode_meta_value( string $raw ): array {
		if ( ! $this->is_utf8( $raw ) ) {
			return array(
				'encoding' => 'base64',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Preserving opaque non-UTF8 meta values for byte-exact round-trip.
				'value'    => base64_encode( $raw ),
			);
		}

		if ( is_serialized( $raw ) ) {
			return array(
				'encoding' => 'php-serialized',
				'value'    => maybe_unserialize( $raw ),
			);
		}

		$trimmed = trim( $raw );
		if ( '' !== $trimmed && in_array( $trimmed[0], array( '{', '[' ), true ) ) {
			$json = json_decode( $raw, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return array(
					'encoding' => 'json',
					'value'    => $json,
				);
			}
		}

		return array(
			'encoding' => 'scalar',
			'value'    => $raw,
		);
	}

	/**
	 * Encode meta values for storage.
	 *
	 * @param mixed        $value    Value.
	 * @param string|array $encoding Encoding.
	 *
	 * @return array
	 */
	private function encode_meta_values( mixed $value, string|array $encoding ): array {
		if ( is_array( $encoding ) && array_is_list( $encoding ) ) {
			$values = array();
			foreach ( (array) $value as $index => $item ) {
				$values[] = $this->encode_single_meta_value( $item, (string) ( $encoding[ $index ] ?? 'scalar' ) );
			}
			return $values;
		}

		return array( $this->encode_single_meta_value( $value, (string) $encoding ) );
	}

	/**
	 * Encode one meta value.
	 *
	 * @param mixed  $value    Value.
	 * @param string $encoding Encoding.
	 *
	 * @return string
	 */
	private function encode_single_meta_value( mixed $value, string $encoding ): string {
		if ( 'base64' === $encoding ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Restoring opaque non-UTF8 meta values from the file projection.
			$decoded = base64_decode( (string) $value, true );
			return false === $decoded ? '' : $decoded;
		}

		if ( 'php-serialized' === $encoding ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Preserving the original WordPress meta storage encoding.
			return serialize( $value );
		}

		if ( 'json' === $encoding ) {
			return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return is_scalar( $value ) || null === $value ? (string) $value : (string) wp_json_encode( $value );
	}

	/**
	 * Query posts by type.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return array<WP_Post>
	 */
	private function query_posts( string $post_type ): array {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => array(
					'post_parent' => 'ASC',
					'menu_order'  => 'ASC',
					'ID'          => 'ASC',
				),
			)
		);
	}

	/**
	 * Selected post types.
	 *
	 * @param array $args Options.
	 *
	 * @return array
	 */
	private function selected_post_types( array $args = array() ): array {
		if ( ! empty( $args['post-type'] ) ) {
			$post_type = sanitize_key( (string) $args['post-type'] );
			return in_array( $post_type, $this->config['postTypes'], true ) ? array( $post_type ) : array();
		}

		return $this->config['postTypes'];
	}

	/**
	 * Selected taxonomies.
	 *
	 * @param array $args Options.
	 *
	 * @return array
	 */
	private function selected_taxonomies( array $args = array() ): array {
		if ( ! empty( $args['taxonomy'] ) ) {
			$taxonomy = sanitize_key( (string) $args['taxonomy'] );
			return in_array( $taxonomy, $this->config['taxonomies'], true ) ? array( $taxonomy ) : array();
		}

		return $this->config['taxonomies'];
	}

	/**
	 * Ensure post UID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function ensure_post_uid( int $post_id ): string {
		$uid = (string) get_post_meta( $post_id, self::META_UID, true );
		if ( '' !== $uid ) {
			return $uid;
		}

		$uid = wp_generate_uuid4();
		if ( ! $this->dry_run ) {
			update_post_meta( $post_id, self::META_UID, $uid );
			update_post_meta( $post_id, self::META_SET, $this->config['id'] );
		}

		return $uid;
	}

	/**
	 * Ensure term UID.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return string
	 */
	private function ensure_term_uid( int $term_id ): string {
		$uid = (string) get_term_meta( $term_id, self::META_UID, true );
		if ( '' !== $uid ) {
			return $uid;
		}

		$uid = wp_generate_uuid4();
		if ( ! $this->dry_run ) {
			update_term_meta( $term_id, self::META_UID, $uid );
			update_term_meta( $term_id, self::META_SET, $this->config['id'] );
		}

		return $uid;
	}

	/**
	 * Find post by UID.
	 *
	 * @param string $uid UID.
	 *
	 * @return WP_Post|null
	 */
	private function find_post_by_uid( string $uid ): ?WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_UID,
						'value' => $uid,
					),
					array(
						'key'   => self::META_SET,
						'value' => $this->config['id'],
					),
				),
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Find term by UID.
	 *
	 * @param string $uid      UID.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return WP_Term|null
	 */
	private function find_term_by_uid( string $uid, string $taxonomy ): ?WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_UID,
						'value' => $uid,
					),
					array(
						'key'   => self::META_SET,
						'value' => $this->config['id'],
					),
				),
			)
		);

		return ! is_wp_error( $terms ) && ! empty( $terms ) && $terms[0] instanceof WP_Term ? $terms[0] : null;
	}

	/**
	 * Resolve a reference UID.
	 *
	 * @param string $uid  UID.
	 * @param string $kind Kind.
	 *
	 * @return int
	 */
	private function resolve_reference_uid( string $uid, string $kind ): int {
		if ( 'term' === $kind ) {
			return $this->resolve_term_uid( $uid, '' );
		}

		$id = $this->resolve_post_uid( $uid );
		if ( $id <= 0 ) {
			return 0;
		}

		if ( 'attachment' === $kind && 'attachment' !== get_post_type( $id ) ) {
			return 0;
		}

		return $id;
	}

	/**
	 * Resolve post UID.
	 *
	 * @param string $uid UID.
	 *
	 * @return int
	 */
	private function resolve_post_uid( string $uid ): int {
		$post = $this->find_post_by_uid( $uid );
		return $post ? (int) $post->ID : 0;
	}

	/**
	 * Resolve term UID.
	 *
	 * @param string $uid      UID.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return int
	 */
	private function resolve_term_uid( string $uid, string $taxonomy ): int {
		$taxonomies = '' !== $taxonomy ? array( $taxonomy ) : $this->config['taxonomies'];

		foreach ( $taxonomies as $tax ) {
			$term = $this->find_term_by_uid( $uid, $tax );
			if ( $term ) {
				return (int) $term->term_id;
			}
		}

		return 0;
	}

	/**
	 * Check whether a post UID can resolve.
	 *
	 * @param string $uid   UID.
	 * @param array  $items File items.
	 *
	 * @return bool
	 */
	private function can_resolve_post_reference( string $uid, array $items ): bool {
		if ( $this->resolve_post_uid( $uid ) > 0 ) {
			return true;
		}

		foreach ( $items as $item ) {
			if ( (string) ( $item['data']['uid'] ?? '' ) === $uid ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a slug uniqueness key for one post file.
	 *
	 * @param array $data File data.
	 *
	 * @return string
	 */
	private function post_slug_plan_key( array $data ): string {
		$slug      = (string) ( $data['slug'] ?? '' );
		$post_type = (string) ( $data['type'] ?? '' );

		if ( '' === $slug || '' === $post_type ) {
			return '';
		}

		$parent = (string) ( $data['parent'] ?? '0' );
		return $post_type . '|' . $parent . '|' . $slug;
	}

	/**
	 * Check slug conflict.
	 *
	 * @param array        $data     File data.
	 * @param WP_Post|null $existing Existing post.
	 * @param array        $post_uids Post UIDs in the current push plan.
	 *
	 * @return bool
	 */
	private function has_slug_conflict( array $data, ?WP_Post $existing, array $post_uids ): bool {
		$slug      = (string) ( $data['slug'] ?? '' );
		$post_type = (string) ( $data['type'] ?? '' );
		$parent_id = 0;

		if ( '' === $slug || '' === $post_type ) {
			return false;
		}

		if ( ! empty( $data['parent'] ) ) {
			$parent_uid = (string) $data['parent'];
			$parent_id  = $this->resolve_post_uid( $parent_uid );

			if ( $parent_id <= 0 && isset( $post_uids[ $parent_uid ] ) ) {
				return false;
			}
		}

		$posts = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'post_parent'    => $parent_id,
				'posts_per_page' => 1,
			)
		);

		if ( empty( $posts ) ) {
			return false;
		}

		return ! $existing || (int) $posts[0]->ID !== (int) $existing->ID;
	}

	/**
	 * Check whether post is managed by Page Sync.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	private function is_page_sync_managed( int $post_id ): bool {
		foreach ( array( '_blockstudio_page_key', '_blockstudio_page_source', '_blockstudio_page_name' ) as $key ) {
			if ( '' !== (string) get_post_meta( $post_id, $key, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get raw meta values grouped by key.
	 *
	 * @param string $type Entity type.
	 * @param int    $id   Entity ID.
	 *
	 * @return array
	 */
	private function get_raw_meta( string $type, int $id ): array {
		global $wpdb;

		$table     = 'term' === $type ? $wpdb->termmeta : $wpdb->postmeta;
		$id_column = 'term' === $type ? 'term_id' : 'post_id';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table and column are controlled internally and raw meta order is required.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$table} WHERE {$id_column} = %d ORDER BY meta_id ASC", $id ) );

		$meta = array();
		foreach ( $rows as $row ) {
			$key = (string) $row->meta_key;
			if ( ! isset( $meta[ $key ] ) ) {
				$meta[ $key ] = array();
			}

			$meta[ $key ][] = (string) $row->meta_value;
		}

		return $meta;
	}

	/**
	 * Check meta key allowlist.
	 *
	 * @param string $key Meta key.
	 *
	 * @return bool
	 */
	private function meta_key_allowed( string $key ): bool {
		foreach ( $this->config['meta']['exclude'] as $pattern ) {
			if ( fnmatch( $pattern, $key ) ) {
				return false;
			}
		}

		foreach ( $this->config['meta']['include'] as $pattern ) {
			if ( fnmatch( $pattern, $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sort projection keys.
	 *
	 * @param array $data Projection data.
	 *
	 * @return array
	 */
	private function sort_projection( array $data ): array {
		$order  = array( 'uid', 'type', 'taxonomy', 'status', 'slug', 'title', 'name', 'description', 'date', 'modified', 'parent', 'author', 'menuOrder', 'terms', 'meta', 'metaEncoding' );
		$sorted = array();

		foreach ( $order as $key ) {
			if ( array_key_exists( $key, $data ) && ( null !== $data[ $key ] || in_array( $key, array( 'parent' ), true ) ) ) {
				$sorted[ $key ] = $data[ $key ];
			}
		}

		foreach ( $data as $key => $value ) {
			if ( ! array_key_exists( $key, $sorted ) ) {
				$sorted[ $key ] = $value;
			}
		}

		if ( isset( $sorted['meta'] ) && is_array( $sorted['meta'] ) ) {
			ksort( $sorted['meta'] );
		}

		if ( isset( $sorted['metaEncoding'] ) && is_array( $sorted['metaEncoding'] ) ) {
			ksort( $sorted['metaEncoding'] );
		}

		return $sorted;
	}

	/**
	 * Sort posts parent-first.
	 *
	 * @param array $items Post items.
	 *
	 * @return array
	 */
	private function sort_posts_by_parent( array $items ): array {
		return $this->sort_by_parent_uid( $items );
	}

	/**
	 * Sort terms parent-first.
	 *
	 * @param array $items Term items.
	 *
	 * @return array
	 */
	private function sort_terms_by_parent( array $items ): array {
		return $this->sort_by_parent_uid( $items );
	}

	/**
	 * Sort items by parent UID.
	 *
	 * @param array $items Items.
	 *
	 * @return array
	 */
	private function sort_by_parent_uid( array $items ): array {
		$sorted = array();
		$seen   = array();
		$count  = count( $items );

		$sorted_count = 0;
		while ( $sorted_count < $count ) {
			$progress = false;

			foreach ( $items as $item ) {
				$uid = (string) ( $item['data']['uid'] ?? '' );
				if ( isset( $seen[ $uid ] ) ) {
					continue;
				}

				$parent = (string) ( $item['data']['parent'] ?? '' );
				if ( '' !== $parent && ! isset( $seen[ $parent ] ) && $this->item_uid_exists( $parent, $items ) ) {
					continue;
				}

				$seen[ $uid ] = true;
				$sorted[]     = $item;
				++$sorted_count;
				$progress = true;
			}

			if ( ! $progress ) {
				foreach ( $items as $item ) {
					$uid = (string) ( $item['data']['uid'] ?? '' );
					if ( ! isset( $seen[ $uid ] ) ) {
						$seen[ $uid ] = true;
						$sorted[]     = $item;
						++$sorted_count;
					}
				}
			}
		}

		return $sorted;
	}

	/**
	 * Check if item UID exists in a list.
	 *
	 * @param string $uid   UID.
	 * @param array  $items Items.
	 *
	 * @return bool
	 */
	private function item_uid_exists( string $uid, array $items ): bool {
		foreach ( $items as $item ) {
			if ( (string) ( $item['data']['uid'] ?? '' ) === $uid ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prune missing entities.
	 *
	 * @param array $plan Push plan.
	 * @param array $args Options.
	 *
	 * @return array
	 */
	private function prune_missing( array $plan, array $args = array() ): array {
		$rows      = array();
		$post_uids = array();
		$term_uids = array();

		foreach ( $plan['posts'] as $item ) {
			$post_uids[] = (string) $item['data']['uid'];
		}

		foreach ( $plan['terms'] as $item ) {
			$term_uids[] = (string) $item['data']['uid'];
		}

		foreach ( $this->query_prunable_posts( $args ) as $post ) {
			$uid = (string) get_post_meta( $post->ID, self::META_UID, true );
			if ( '' !== $uid && ! in_array( $uid, $post_uids, true ) ) {
				$action = $this->orphan_action( 'post', $post->ID );
				$this->apply_orphan_action( 'post', $post->ID, $action );
				$rows[] = $this->row( 'pruned-' . $action, 'post', (string) $post->ID, $uid, '' );
			}
		}

		foreach ( $this->query_content_terms( $args ) as $term ) {
			$uid = (string) get_term_meta( $term->term_id, self::META_UID, true );
			if ( '' !== $uid && ! in_array( $uid, $term_uids, true ) ) {
				$action = $this->orphan_action( 'term', $term->term_id );
				$this->apply_orphan_action( 'term', $term->term_id, $action );
				$rows[] = $this->row( 'pruned-' . $action, 'term', (string) $term->term_id, $uid, '' );
			}
		}

		return $rows;
	}

	/**
	 * Query content-owned posts.
	 *
	 * @return array
	 */
	private function query_content_posts(): array {
		return get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_SET,
						'value' => $this->config['id'],
					),
				),
			)
		);
	}

	/**
	 * Query posts that prune is allowed to remove.
	 *
	 * @param array $args Options.
	 *
	 * @return array
	 */
	private function query_prunable_posts( array $args = array() ): array {
		if ( empty( $this->config['postTypes'] ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => $this->selected_post_types( $args ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_SET,
						'value' => $this->config['id'],
					),
				),
			)
		);
	}

	/**
	 * Query content-owned terms.
	 *
	 * @param array $args Options.
	 *
	 * @return array
	 */
	private function query_content_terms( array $args = array() ): array {
		$terms = array();

		foreach ( $this->selected_taxonomies( $args ) as $taxonomy ) {
			$result = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => self::META_SET,
							'value' => $this->config['id'],
						),
					),
				)
			);

			if ( ! is_wp_error( $result ) ) {
				$terms = array_merge( $terms, $result );
			}
		}

		return $terms;
	}

	/**
	 * Resolve orphan action.
	 *
	 * @param string $type Entity type.
	 * @param int    $id   Entity ID.
	 *
	 * @return string
	 */
	private function orphan_action( string $type, int $id ): string {
		$action = (string) apply_filters( 'blockstudio/content/orphan_action', 'trash', $type, $id, $this->config );
		return in_array( $action, array( 'trash', 'delete', 'keep' ), true ) ? $action : 'trash';
	}

	/**
	 * Apply orphan action.
	 *
	 * @param string $type   Entity type.
	 * @param int    $id     Entity ID.
	 * @param string $action Action.
	 *
	 * @return void
	 */
	private function apply_orphan_action( string $type, int $id, string $action ): void {
		if ( 'keep' === $action ) {
			return;
		}

		if ( 'post' === $type ) {
			if ( 'delete' === $action ) {
				wp_delete_post( $id, true );
			} else {
				wp_trash_post( $id );
			}
			return;
		}

		$term = get_term( $id );
		if ( $term instanceof WP_Term ) {
			wp_delete_term( $id, $term->taxonomy );
		}
	}

	/**
	 * Update sync state.
	 *
	 * @param string $type        Entity type.
	 * @param int    $id          Entity ID.
	 * @param string $uid         UID.
	 * @param string $source      Source path.
	 * @param string $fingerprint Fingerprint.
	 *
	 * @return void
	 */
	private function update_entity_state( string $type, int $id, string $uid, string $source, string $fingerprint ): void {
		$update = 'term' === $type ? 'update_term_meta' : 'update_post_meta';

		$update( $id, self::META_UID, $uid );
		$update( $id, self::META_SET, $this->config['id'] );
		$update( $id, self::META_SOURCE, $this->relative_path( $source ) );
		$update( $id, self::META_FINGERPRINT, $fingerprint );
	}

	/**
	 * Fingerprint a live post.
	 *
	 * @param WP_Post    $post       Post.
	 * @param array|null $file_shape Optional file projection shape.
	 *
	 * @return string
	 */
	private function fingerprint_post( WP_Post $post, ?array $file_shape = null ): string {
		return $this->fingerprint_post_for_file( $post, $file_shape, (string) $post->post_content );
	}

	/**
	 * Fingerprint a live post using the ownership shape of one file projection.
	 *
	 * @param WP_Post    $post       Post.
	 * @param array|null $file_shape Optional file projection shape.
	 * @param string     $file_body  File-owned body content.
	 *
	 * @return string
	 */
	private function fingerprint_post_for_file( WP_Post $post, ?array $file_shape, string $file_body ): string {
		$uid        = (string) get_post_meta( $post->ID, self::META_UID, true );
		$projection = $this->project_post( $post, $uid );

		if ( null !== $file_shape ) {
			$projection = $this->shape_live_projection_for_file( $projection, $file_shape );
		}

		$body = $this->is_page_sync_managed( $post->ID ) ? $file_body : (string) $post->post_content;
		return $this->fingerprint_projection( $projection, $body );
	}

	/**
	 * Fingerprint a live term.
	 *
	 * @param WP_Term    $term       Term.
	 * @param array|null $file_shape Optional file projection shape.
	 *
	 * @return string
	 */
	private function fingerprint_term( WP_Term $term, ?array $file_shape = null ): string {
		$uid        = (string) get_term_meta( $term->term_id, self::META_UID, true );
		$projection = $this->project_term( $term, $uid );

		if ( null !== $file_shape ) {
			$projection = $this->shape_live_projection_for_file( $projection, $file_shape );
		}

		return $this->fingerprint_projection( $projection, '' );
	}

	/**
	 * Remove volatile live-only fields when a file did not own them.
	 *
	 * @param array $live Live projection.
	 * @param array $file File projection.
	 *
	 * @return array
	 */
	private function shape_live_projection_for_file( array $live, array $file ): array {
		foreach ( array( 'date', 'modified', 'author', 'terms' ) as $optional_key ) {
			if ( ! array_key_exists( $optional_key, $file ) ) {
				unset( $live[ $optional_key ] );
			}
		}

		return $live;
	}

	/**
	 * Fingerprint projection.
	 *
	 * @param array  $projection Projection.
	 * @param string $body       Body.
	 *
	 * @return string
	 */
	private function fingerprint_projection( array $projection, string $body ): string {
		return hash( 'sha256', wp_json_encode( $this->sort_projection( $projection ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" . $body );
	}

	/**
	 * Root sync path.
	 *
	 * @return string
	 */
	private function root_path(): string {
		return trailingslashit( get_stylesheet_directory() ) . $this->config['path'];
	}

	/**
	 * Post source path.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $uid  UID.
	 *
	 * @return string
	 */
	private function post_source_path( WP_Post $post, string $uid ): string {
		$source = '' !== (string) $post->post_name ? $post->post_name : $post->post_title;
		if ( '' === (string) $source ) {
			$source = (string) $post->ID;
		}

		$filename = sanitize_title( $source ) . '.' . substr( $uid, 0, 8 ) . '.json';
		return $this->root_path() . '/posts/' . $post->post_type . '/' . $filename;
	}

	/**
	 * Term source path.
	 *
	 * @param WP_Term $term Term.
	 * @param string  $uid  UID.
	 *
	 * @return string
	 */
	private function term_source_path( WP_Term $term, string $uid ): string {
		$source = '' !== (string) $term->slug ? $term->slug : $term->name;
		if ( '' === (string) $source ) {
			$source = (string) $term->term_id;
		}

		$filename = sanitize_title( $source ) . '.' . substr( $uid, 0, 8 ) . '.json';
		return $this->root_path() . '/terms/' . $term->taxonomy . '/' . $filename;
	}

	/**
	 * Body path for post source.
	 *
	 * @param string $source JSON path.
	 *
	 * @return string
	 */
	private function body_path_for_source( string $source ): string {
		$body_path = preg_replace( '/\.json$/', '.html', $source );
		return null !== $body_path ? $body_path : $source . '.html';
	}

	/**
	 * Write JSON file.
	 *
	 * @param string $file File path.
	 * @param array  $data Data.
	 *
	 * @return bool True when the file changed.
	 */
	private function write_json_file( string $file, array $data ): bool {
		return 'written' === $this->write_json_file_status( $file, $data );
	}

	/**
	 * Write JSON file and return write status.
	 *
	 * @param string $file File path.
	 * @param array  $data Data.
	 *
	 * @return string written, unchanged, or failed.
	 */
	private function write_json_file_status( string $file, array $data ): string {
		return $this->write_file_status(
			$file,
			wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
		);
	}

	/**
	 * Write file.
	 *
	 * @param string $file    File path.
	 * @param string $content Content.
	 *
	 * @return bool True when the file changed.
	 */
	private function write_file( string $file, string $content ): bool {
		return 'written' === $this->write_file_status( $file, $content );
	}

	/**
	 * Write file and return write status.
	 *
	 * @param string $file    File path.
	 * @param string $content Content.
	 *
	 * @return string written, unchanged, or failed.
	 */
	private function write_file_status( string $file, string $content ): string {
		if ( file_exists( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync file before deciding whether to rewrite it.
			$existing = file_get_contents( $file );
			if ( $content === $existing ) {
				return 'unchanged';
			}
		}

		$directory = dirname( $file );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return 'failed';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local content sync file.
		return false !== file_put_contents( $file, $content, LOCK_EX ) ? 'written' : 'failed';
	}

	/**
	 * Write media manifest.
	 *
	 * @param array $rows Rows.
	 *
	 * @return void
	 */
	private function write_media_manifest( array &$rows ): void {
		if ( 'manifest' !== $this->config['media'] || $this->dry_run ) {
			return;
		}

		$manifest = array();

		foreach ( $this->query_content_posts() as $post ) {
			if ( 'attachment' !== $post->post_type ) {
				continue;
			}

			$uid = (string) get_post_meta( $post->ID, self::META_UID, true );
			if ( '' === $uid ) {
				continue;
			}

			$file             = get_attached_file( $post->ID );
			$manifest[ $uid ] = array(
				'path' => $file ? wp_basename( $file ) : '',
				'hash' => $file && file_exists( $file ) ? hash_file( 'sha256', $file ) : '',
				'mime' => get_post_mime_type( $post ),
				'alt'  => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			);
		}

		if ( empty( $manifest ) ) {
			return;
		}

		ksort( $manifest );
		$manifest_path = $this->root_path() . '/media/manifest.json';
		$changed       = $this->write_json_file( $manifest_path, $manifest );
		$rows[]        = $this->row( $changed ? 'written' : 'unchanged', 'media', 'manifest', '', $this->relative_path( $manifest_path ) );
	}

	/**
	 * Relative path from theme.
	 *
	 * @param string $path Absolute path.
	 *
	 * @return string
	 */
	private function relative_path( string $path ): string {
		$theme = trailingslashit( get_stylesheet_directory() );
		return str_starts_with( $path, $theme ) ? ltrim( substr( $path, strlen( $theme ) ), '/' ) : $path;
	}

	/**
	 * Normalize a file path for comparisons.
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	private function normalize_file_path( string $path ): string {
		return wp_normalize_path( $path );
	}

	/**
	 * Format a result row.
	 *
	 * @param string $action  Action.
	 * @param string $entity  Entity.
	 * @param string $id      ID.
	 * @param string $uid     UID.
	 * @param string $message Message.
	 *
	 * @return array
	 */
	private function row( string $action, string $entity, string $id, string $uid, string $message ): array {
		return array(
			'action'  => $action,
			'entity'  => $entity,
			'id'      => $id,
			'uid'     => $uid,
			'message' => $message,
		);
	}

	/**
	 * Check whether rows contain errors.
	 *
	 * @param array $rows Rows.
	 *
	 * @return bool
	 */
	private function has_error_rows( array $rows ): bool {
		foreach ( $rows as $row ) {
			if ( 'error' === ( $row['action'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert MySQL GMT date to ISO.
	 *
	 * @param string $date Date.
	 *
	 * @return string|null
	 */
	private function mysql_gmt_to_iso( string $date ): ?string {
		if ( '' === $date || '0000-00-00 00:00:00' === $date ) {
			return null;
		}

		return gmdate( 'c', strtotime( $date . ' UTC' ) );
	}

	/**
	 * Check UTF-8.
	 *
	 * @param string $value Value.
	 *
	 * @return bool
	 */
	private function is_utf8( string $value ): bool {
		return 1 === preg_match( '//u', $value );
	}
}

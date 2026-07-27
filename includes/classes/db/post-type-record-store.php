<?php
/**
 * Custom post type record store.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

/**
 * Stores each record as a post of a schema-specific hidden post type.
 */
class Post_Type_Record_Store extends Record_Store {

	/**
	 * Post type name for a schema key.
	 *
	 * WordPress caps post type names at 20 characters, so a key that would
	 * overflow is hashed instead of truncated, which would collide.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	public static function post_type_for_key( string $key ): string {
		$name = 'bs_' . str_replace( array( '/', '-', ':' ), '_', $key );

		if ( strlen( $name ) > 20 ) {
			$name = 'bs_' . substr( md5( $key ), 0, 17 );
		}

		return $name;
	}

	/**
	 * Post type for this store.
	 *
	 * @return string
	 */
	private function post_type(): string {
		return self::post_type_for_key( $this->key );
	}

	/**
	 * Convert a post into a record.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array<string, mixed>
	 */
	private function to_record( \WP_Post $post ): array {
		$record = array(
			'id'         => $post->ID,
			'created_at' => $post->post_date_gmt,
			'updated_at' => $post->post_modified_gmt,
		);

		$fields = $this->schema['fields'] ?? array();

		foreach ( array_keys( $fields ) as $field ) {
			$value = get_post_meta( $post->ID, $field, true );
			$type  = $fields[ $field ]['type'] ?? 'string';

			if ( 'boolean' === $type ) {
				$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			} elseif ( 'integer' === $type ) {
				$value = (int) $value;
			} elseif ( 'number' === $type ) {
				$value = (float) $value;
			} elseif ( '' === $value && isset( $fields[ $field ]['default'] ) ) {
				$value = $fields[ $field ]['default'];
			}

			$record[ $field ] = $value;
		}

		if ( isset( $this->schema['userScoped'] ) && $this->schema['userScoped'] ) {
			$record['user_id'] = (int) $post->post_author;
		}

		return $record;
	}

	/**
	 * Fold filters into query args.
	 *
	 * `user_id` maps to the post author, everything else to a meta comparison.
	 *
	 * @param array<string, mixed> $args    Query args.
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return array<string, mixed>
	 */
	private function apply_filters( array $args, array $filters ): array {
		$user_id = $filters['user_id'] ?? null;
		unset( $filters['user_id'] );

		if ( $user_id ) {
			$args['author'] = (int) $user_id;
		}

		if ( ! empty( $filters ) ) {
			$meta_query = array();

			foreach ( $filters as $field => $value ) {
				$meta_query[] = array(
					'key'   => $field,
					'value' => $value,
				);
			}

			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $args;
	}

	/**
	 * Query records.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 * @param int                  $limit   Maximum rows.
	 * @param int                  $offset  Row offset.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function query( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		$args = $this->apply_filters(
			array(
				'post_type'      => $this->post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			),
			$filters
		);

		$records = array();

		foreach ( get_posts( $args ) as $post ) {
			$records[] = $this->to_record( $post );
		}

		return $records;
	}

	/**
	 * Count records matching filters.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		$args = $this->apply_filters(
			array(
				'post_type'              => $this->post_type(),
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			),
			$filters
		);

		$query = new \WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Get a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		$post = get_post( $id );

		if (
			! $post ||
			$this->post_type() !== $post->post_type ||
			'publish' !== $post->post_status
		) {
			return null;
		}

		return $this->to_record( $post );
	}

	/**
	 * Create a new record with an auto-incrementing public ID.
	 *
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 */
	public function create( array $data ): array {
		$post_type = $this->post_type();
		$post_data = array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'post_title'  => $post_type . '_entry',
		);

		if ( isset( $data['user_id'] ) ) {
			$post_data['post_author'] = (int) $data['user_id'];
			unset( $data['user_id'] );
		}

		$post_id = wp_insert_post( $post_data );

		foreach ( $data as $field => $value ) {
			update_post_meta( $post_id, $field, $value );
		}

		return $this->to_record( get_post( $post_id ) );
	}

	/**
	 * Put a record with an explicit public ID.
	 *
	 * A post ID is assigned by WordPress, so an ID that does not already name
	 * a post of this type cannot be claimed.
	 *
	 * @param int                  $id   Public record ID.
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 */
	public function put( int $id, array $data ): array {
		return $this->update( $id, $data ) ?? $this->create( $data );
	}

	/**
	 * Update a record by public ID.
	 *
	 * @param int                  $id   Public record ID.
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>|null
	 */
	public function update( int $id, array $data ): ?array {
		$post = get_post( $id );

		if ( ! $post || $this->post_type() !== $post->post_type ) {
			return null;
		}

		wp_update_post(
			array(
				'ID'            => $id,
				'post_modified' => current_time( 'mysql' ),
			)
		);

		foreach ( $data as $field => $value ) {
			update_post_meta( $id, $field, $value );
		}

		clean_post_cache( $id );

		return $this->to_record( get_post( $id ) );
	}

	/**
	 * Delete a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$post = get_post( $id );

		if ( ! $post || $this->post_type() !== $post->post_type ) {
			return false;
		}

		return (bool) wp_delete_post( $id, true );
	}
}

<?php
/**
 * Post meta record store.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

/**
 * Stores every record for a schema in one post meta array.
 */
class Meta_Record_Store extends Record_Store {

	/**
	 * Meta key for a schema key.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	public static function meta_key_for_key( string $key ): string {
		return '_bs_db_' . str_replace( array( '/', '-', ':' ), '_', $key );
	}

	/**
	 * Post the entries are attached to.
	 *
	 * @return int
	 */
	private function post_id(): int {
		return (int) ( $this->schema['postId'] ?? 0 );
	}

	/**
	 * Read every entry for this schema.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function all(): array {
		$entries = get_post_meta( $this->post_id(), self::meta_key_for_key( $this->key ), true );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Persist the entry list.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries to write.
	 *
	 * @return void
	 */
	private function write( array $entries ): void {
		update_post_meta( $this->post_id(), self::meta_key_for_key( $this->key ), $entries );
	}

	/**
	 * Reduce entries to those matching every filter.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries to filter.
	 * @param array<string, mixed>             $filters Equality filters.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function matching( array $entries, array $filters ): array {
		if ( empty( $filters ) ) {
			return $entries;
		}

		return array_filter(
			$entries,
			function ( $entry ) use ( $filters ) {
				foreach ( $filters as $k => $val ) {
					if ( ( $entry[ $k ] ?? null ) != $val ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Intentional loose comparison for query param strings.
						return false;
					}
				}

				return true;
			}
		);
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
		return array_values(
			array_slice( $this->matching( $this->all(), $filters ), $offset, $limit )
		);
	}

	/**
	 * Query records and report the total matching them.
	 *
	 * One read answers both, so this does not fall back to a query plus a
	 * count.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 * @param int                  $limit   Maximum rows.
	 * @param int                  $offset  Row offset.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function paginate( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		$entries = array_values( $this->matching( $this->all(), $filters ) );

		return array(
			'items' => array_values( array_slice( $entries, $offset, $limit ) ),
			'total' => count( $entries ),
		);
	}

	/**
	 * Count records matching filters.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		return count( $this->matching( $this->all(), $filters ) );
	}

	/**
	 * Get a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		foreach ( $this->all() as $entry ) {
			if ( ( $entry['id'] ?? 0 ) === $id ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Create a new record with an auto-incrementing public ID.
	 *
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 */
	public function create( array $data ): array {
		$entries = $this->all();
		$max_id  = 0;

		foreach ( $entries as $entry ) {
			$max_id = max( $max_id, $entry['id'] ?? 0 );
		}

		$data['id']         = $max_id + 1;
		$data['created_at'] = current_time( 'mysql', true );
		$data['updated_at'] = current_time( 'mysql', true );
		$entries[]          = $data;

		$this->write( $entries );

		return $data;
	}

	/**
	 * Put a record with an explicit public ID.
	 *
	 * @param int                  $id   Public record ID.
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 */
	public function put( int $id, array $data ): array {
		$existing = $this->get( $id );

		if ( $existing ) {
			return $this->update( $id, $data ) ?? $data;
		}

		$entries            = $this->all();
		$data['id']         = $id;
		$data['created_at'] = current_time( 'mysql', true );
		$data['updated_at'] = current_time( 'mysql', true );
		$entries[]          = $data;

		$this->write( $entries );

		return $data;
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
		$entries = $this->all();
		$found   = false;

		foreach ( $entries as &$entry ) {
			if ( ( $entry['id'] ?? 0 ) === $id ) {
				$entry               = array_merge( $entry, $data );
				$entry['updated_at'] = current_time( 'mysql', true );
				$found               = $entry;
				break;
			}
		}
		unset( $entry );

		if ( ! $found ) {
			return null;
		}

		$this->write( $entries );

		return $found;
	}

	/**
	 * Delete a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$entries = $this->all();
		$before  = count( $entries );

		$entries = array_values(
			array_filter(
				$entries,
				fn( $entry ) => ( $entry['id'] ?? 0 ) !== $id
			)
		);

		if ( count( $entries ) === $before ) {
			return false;
		}

		$this->write( $entries );

		return true;
	}
}

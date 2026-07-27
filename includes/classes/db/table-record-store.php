<?php
/**
 * Custom table record store.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

/**
 * Stores records as rows in a dedicated `bs_` table.
 */
class Table_Record_Store extends Record_Store {

	/**
	 * Table name for a schema key.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	public static function table_for_key( string $key ): string {
		global $wpdb;

		return $wpdb->prefix . 'bs_' . str_replace( array( '/', '-', ':' ), '_', $key );
	}

	/**
	 * Table name for this store.
	 *
	 * @return string
	 */
	private function table(): string {
		return self::table_for_key( $this->key );
	}

	/**
	 * Build the WHERE clause and bound values for a filter set.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function where( array $filters ): array {
		$where  = array();
		$values = array();

		foreach ( $filters as $k => $val ) {
			if ( $this->is_filterable( (string) $k ) ) {
				$where[]  = sanitize_key( $k ) . ' = %s';
				$values[] = $val;
			}
		}

		return array(
			empty( $where ) ? '' : ' WHERE ' . implode( ' AND ', $where ),
			$values,
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
		global $wpdb;

		$table                   = $this->table();
		list( $clause, $values ) = $this->where( $filters );

		$sql      = "SELECT * FROM $table" . $clause . ' ORDER BY id DESC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values[] = $limit;
		$values[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count records matching filters.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		global $wpdb;

		$table                   = $this->table();
		list( $clause, $values ) = $this->where( $filters );

		$sql = "SELECT COUNT(*) FROM $table" . $clause; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		$table = $this->table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create a new record with an auto-incrementing public ID.
	 *
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 */
	public function create( array $data ): array {
		global $wpdb;

		$now                = current_time( 'mysql', true );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$wpdb->insert( $this->table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $this->get( (int) $wpdb->insert_id );
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
		global $wpdb;

		$now                = current_time( 'mysql', true );
		$data['id']         = $id;
		$data['updated_at'] = $now;

		if ( $this->get( $id ) ) {
			$wpdb->update( $this->table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $this->table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return $this->get( $id ) ?? $data;
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
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		$wpdb->update( $this->table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->get( $id );
	}

	/**
	 * Delete a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

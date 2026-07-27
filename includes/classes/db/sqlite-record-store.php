<?php
/**
 * SQLite record store.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

/**
 * Stores records in a SQLite database file beside the block.
 */
class Sqlite_Record_Store extends Record_Store {

	/**
	 * Open connections keyed by file path.
	 *
	 * @var array<string, \PDO>
	 */
	private static array $connections = array();

	/**
	 * Database file this store reads and writes.
	 *
	 * @return string
	 */
	private function path(): string {
		return $this->storage_directory() . '/' . $this->schema_name() . '.sqlite';
	}

	/**
	 * Get or open the connection for this store.
	 *
	 * @return \PDO
	 */
	private function pdo(): \PDO {
		$path = $this->path();

		if ( isset( self::$connections[ $path ] ) ) {
			return self::$connections[ $path ];
		}

		$dir = dirname( $path );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- SQLite via PDO, not MySQL.
		$pdo = new \PDO( 'sqlite:' . $path );
		$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$pdo->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC );
		$pdo->exec( 'PRAGMA journal_mode=WAL' );
		// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO

		self::$connections[ $path ] = $pdo;

		return $pdo;
	}

	/**
	 * Map a field type to a SQLite column type.
	 *
	 * @param array<string, mixed> $def Field definition.
	 *
	 * @return string
	 */
	private static function column_type( array $def ): string {
		switch ( $def['type'] ?? 'string' ) {
			case 'integer':
				return 'INTEGER DEFAULT 0';
			case 'number':
				return 'REAL DEFAULT 0';
			case 'boolean':
				return 'INTEGER DEFAULT 0';
			default:
				return "TEXT DEFAULT ''";
		}
	}

	/**
	 * Create the table, and add any column the schema gained since.
	 *
	 * @return void
	 */
	public function ensure(): void {
		$pdo     = $this->pdo();
		$fields  = $this->schema['fields'] ?? array();
		$columns = array();

		foreach ( $fields as $name => $def ) {
			$col_type  = self::column_type( $def );
			$col_name  = preg_replace( '/[^a-z0-9_]/', '', $name );
			$columns[] = "$col_name $col_type";
		}

		$columns_sql = implode( ', ', $columns );

		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS data (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				$columns_sql,
				created_at TEXT DEFAULT (datetime('now')),
				updated_at TEXT DEFAULT (datetime('now'))
			)"
		);

		$existing = array();

		foreach ( $pdo->query( 'PRAGMA table_info(data)' ) as $col ) {
			$existing[] = $col['name'];
		}

		foreach ( $fields as $name => $def ) {
			$col_name = preg_replace( '/[^a-z0-9_]/', '', $name );

			if ( ! in_array( $col_name, $existing, true ) ) {
				$col_type = self::column_type( $def );
				$pdo->exec( "ALTER TABLE data ADD COLUMN $col_name $col_type" );
			}
		}
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
				$where[]  = "$k = ?";
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
		list( $clause, $values ) = $this->where( $filters );

		$values[] = $limit;
		$values[] = $offset;

		$stmt = $this->pdo()->prepare(
			'SELECT * FROM data' . $clause . ' ORDER BY id DESC LIMIT ? OFFSET ?'
		);
		$stmt->execute( $values );

		return $stmt->fetchAll();
	}

	/**
	 * Count records matching filters.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		list( $clause, $values ) = $this->where( $filters );

		$stmt = $this->pdo()->prepare( 'SELECT COUNT(*) FROM data' . $clause );
		$stmt->execute( $values );

		return (int) $stmt->fetchColumn();
	}

	/**
	 * Get a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		$stmt = $this->pdo()->prepare( 'SELECT * FROM data WHERE id = ?' );
		$stmt->execute( array( $id ) );
		$row = $stmt->fetch();

		return false !== $row ? $row : null;
	}

	/**
	 * Create a new record with an auto-incrementing public ID.
	 *
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 */
	public function create( array $data ): array {
		$pdo = $this->pdo();
		$now = gmdate( 'Y-m-d H:i:s' );

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$columns      = implode( ', ', array_keys( $data ) );
		$placeholders = implode( ', ', array_fill( 0, count( $data ), '?' ) );

		$stmt = $pdo->prepare( "INSERT INTO data ($columns) VALUES ($placeholders)" );
		$stmt->execute( array_values( $data ) );

		return $this->get( (int) $pdo->lastInsertId() );
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
		if ( $this->get( $id ) ) {
			return $this->update( $id, $data ) ?? $data;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$data['id']         = $id;
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$columns      = implode( ', ', array_keys( $data ) );
		$placeholders = implode( ', ', array_fill( 0, count( $data ), '?' ) );

		$stmt = $this->pdo()->prepare( "INSERT INTO data ($columns) VALUES ($placeholders)" );
		$stmt->execute( array_values( $data ) );

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
		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		$sets   = array();
		$values = array();

		foreach ( $data as $col => $val ) {
			$sets[]   = "$col = ?";
			$values[] = $val;
		}

		$values[] = $id;

		$stmt = $this->pdo()->prepare(
			'UPDATE data SET ' . implode( ', ', $sets ) . ' WHERE id = ?'
		);
		$stmt->execute( $values );

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
		$stmt = $this->pdo()->prepare( 'DELETE FROM data WHERE id = ?' );
		$stmt->execute( array( $id ) );

		return $stmt->rowCount() > 0;
	}
}

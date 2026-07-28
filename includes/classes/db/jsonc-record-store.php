<?php
/**
 * JSONC file record store.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

/**
 * Stores records as one JSON object per line beside the block.
 */
class Jsonc_Record_Store extends Record_Store {

	/**
	 * File this store reads and writes.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->storage_directory() . '/' . $this->schema_name() . '.jsonc';
	}

	/**
	 * Read every record from the file.
	 *
	 * Comment and blank lines are skipped, and a record without an ID is given
	 * the next one, so a hand-written seed file stays valid input.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function read(): array {
		$path = $this->path();

		if ( ! file_exists( $path ) ) {
			return array();
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$records = array();
		$id      = 0;

		foreach ( explode( "\n", $content ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '//' ) ) {
				continue;
			}

			$record = json_decode( $line, true );

			if ( is_array( $record ) ) {
				++$id;
				$record['id'] = $record['id'] ?? $id;
				$id           = max( $id, $record['id'] );
				$records[]    = $record;
			}
		}

		return $records;
	}

	/**
	 * Write every record back to the file.
	 *
	 * @param array<int, array<string, mixed>> $records Records to write.
	 *
	 * @return void
	 */
	private function write( array $records ): void {
		$path = $this->path();
		$dir  = dirname( $path );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$lines = array();

		foreach ( $records as $record ) {
			$lines[] = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		file_put_contents( $path, implode( "\n", $lines ) . "\n", LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Reduce records to those matching every filter.
	 *
	 * @param array<int, array<string, mixed>> $records Records to filter.
	 * @param array<string, mixed>             $filters Equality filters.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function matching( array $records, array $filters ): array {
		if ( empty( $filters ) ) {
			return $records;
		}

		return array_filter(
			$records,
			function ( $record ) use ( $filters ) {
				foreach ( $filters as $k => $val ) {
					if ( ( $record[ $k ] ?? null ) != $val ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Intentional loose comparison for query param strings.
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
			array_slice( $this->matching( $this->read(), $filters ), $offset, $limit )
		);
	}

	/**
	 * Query records and report the total matching them.
	 *
	 * One read answers both.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 * @param int                  $limit   Maximum rows.
	 * @param int                  $offset  Row offset.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function paginate( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		$records = array_values( $this->matching( $this->read(), $filters ) );

		return array(
			'items' => array_values( array_slice( $records, $offset, $limit ) ),
			'total' => count( $records ),
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
		return count( $this->matching( $this->read(), $filters ) );
	}

	/**
	 * Get a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		foreach ( $this->read() as $record ) {
			if ( ( $record['id'] ?? 0 ) === $id ) {
				return $record;
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
		$records = $this->read();
		$max_id  = 0;

		foreach ( $records as $record ) {
			$max_id = max( $max_id, $record['id'] ?? 0 );
		}

		$data['id']         = $max_id + 1;
		$data['created_at'] = current_time( 'mysql', true );
		$data['updated_at'] = current_time( 'mysql', true );
		$records[]          = $data;

		$this->write( $records );

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
		if ( $this->get( $id ) ) {
			return $this->update( $id, $data ) ?? $data;
		}

		$records            = $this->read();
		$data['id']         = $id;
		$data['created_at'] = current_time( 'mysql', true );
		$data['updated_at'] = current_time( 'mysql', true );
		$records[]          = $data;

		$this->write( $records );

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
		$records = $this->read();
		$found   = false;

		foreach ( $records as &$record ) {
			if ( ( $record['id'] ?? 0 ) === $id ) {
				$record               = array_merge( $record, $data );
				$record['updated_at'] = current_time( 'mysql', true );
				$found                = $record;
				break;
			}
		}
		unset( $record );

		if ( ! $found ) {
			return null;
		}

		$this->write( $records );

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
		$records = $this->read();
		$before  = count( $records );

		$records = array_values(
			array_filter(
				$records,
				fn( $record ) => ( $record['id'] ?? 0 ) !== $id
			)
		);

		if ( count( $records ) === $before ) {
			return false;
		}

		$this->write( $records );

		return true;
	}
}

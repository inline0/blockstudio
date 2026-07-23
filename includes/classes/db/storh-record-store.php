<?php
/**
 * Storh record store adapter.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

/**
 * Storh-backed file store for bs.db records.
 *
 * Storh record files use UUIDv7 IDs internally. Blockstudio keeps the public
 * bs.db contract as integer IDs by mapping each integer to a deterministic
 * UUIDv7-compatible value and storing the integer ID in the document data.
 */
final class Storh_Record_Store implements Record_Store_Interface {

	/**
	 * Schema key in the form block/schema:database.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Blockstudio schema definition.
	 *
	 * @var array<string, mixed>
	 */
	private array $schema;

	/**
	 * Store root for the block scope.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Storh collection name.
	 *
	 * @var string
	 */
	private string $collection;

	/**
	 * Storh DocStore instance.
	 *
	 * @var object
	 */
	private object $store;

	/**
	 * Constructor.
	 *
	 * @param string               $key    Schema key.
	 * @param array<string, mixed> $schema Blockstudio schema definition.
	 * @param string|null          $root   Optional storage root override.
	 */
	public function __construct( string $key, array $schema, ?string $root = null ) {
		$this->key        = $key;
		$this->schema     = $schema;
		$this->root       = $root ?? self::root_for_key( $key );
		$this->collection = self::collection_for_key( $key );
		$this->store      = $this->create_store();
	}

	/**
	 * Get the store root for a schema key.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	public static function root_for_key( string $key ): string {
		$upload_dir = wp_upload_dir();
		$base       = trailingslashit( $upload_dir['basedir'] ) . 'blockstudio/db';
		$scope      = self::scope_for_key( $key );
		$class      = self::storh_class( 'StorageRoot' );

		return $class::resolve( $base, $scope );
	}

	/**
	 * Get the full collection directory for a schema key.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	public static function directory_for_key( string $key ): string {
		return self::root_for_key( $key ) . '/' . self::collection_for_key( $key );
	}

	/**
	 * Create a new record with an auto-incrementing public ID.
	 *
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 */
	public function create( array $data ): array {
		return $this->with_sequence_lock(
			function () use ( $data ): array {
				$id = $this->next_id_unlocked();

				return $this->write_record( $id, $data );
			}
		);
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
		$record = $this->write_record( $id, $data );
		$this->sync_sequence( $id );

		return $record;
	}

	/**
	 * Get a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		if ( $id < 1 ) {
			return null;
		}

		$record = $this->store->get( self::uuid_for_id( $id ) );

		return $record ? $this->normalize_record( $record ) : null;
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
		$existing = $this->get( $id );

		if ( null === $existing ) {
			return null;
		}

		unset( $data['id'], $data['created_at'] );

		$merged               = array_merge( $existing, $data );
		$merged['updated_at'] = current_time( 'mysql', true );

		return $this->write_record( $id, $merged );
	}

	/**
	 * Delete a record by public ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		if ( $id < 1 || null === $this->get( $id ) ) {
			return false;
		}

		$this->store->delete( self::uuid_for_id( $id ) );

		return true;
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
		$limit  = max( 1, $limit );
		$offset = max( 0, $offset );
		$query  = $this->build_query( $filters )->orderBy( 'id', 'asc' )->limit( $limit + $offset );
		$rows   = array_map(
			fn( $record ) => $this->normalize_record( $record ),
			$query->get()
		);

		return array_values( array_slice( $rows, $offset, $limit ) );
	}

	/**
	 * Count records matching filters.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		return $this->build_query( $filters )->count();
	}

	/**
	 * Paginate records.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 * @param int                  $limit   Maximum rows.
	 * @param int                  $offset  Row offset.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function paginate( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		return array(
			'items' => $this->query( $filters, $limit, $offset ),
			'total' => $this->count( $filters ),
		);
	}

	/**
	 * Explain the query plan.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return array<string, mixed>
	 */
	public function explain( array $filters = array() ): array {
		return $this->build_query( $filters )->explain();
	}

	/**
	 * Rebuild Storh indexes.
	 *
	 * @return array<string, mixed>
	 */
	public function reindex(): array {
		return $this->store->reindex();
	}

	/**
	 * Verify the Storh collection.
	 *
	 * @return array<string, mixed>
	 */
	public function verify(): array {
		return $this->store->verify();
	}

	/**
	 * Sync the public ID sequence to at least the given ID.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return void
	 */
	public function sync_sequence( int $id ): void {
		if ( $id < 1 ) {
			return;
		}

		$this->with_sequence_lock(
			function () use ( $id ): void {
				$current = $this->read_sequence_unlocked();

				if ( $id > $current ) {
					$this->write_sequence_unlocked( $id );
				}
			}
		);
	}

	/**
	 * Create the Storh store instance.
	 *
	 * @return object
	 */
	private function create_store(): object {
		$doc_store_class = self::storh_class( 'DocStore' );
		$cache_class     = self::storh_class( 'Cache' );

		return new $doc_store_class(
			$this->root,
			$this->collection,
			null,
			$cache_class::memory(),
			$this->storh_schema()
		);
	}

	/**
	 * Build the Storh schema with indexes for every filterable field.
	 *
	 * @return object
	 */
	private function storh_schema(): object {
		$schema_class = self::storh_class( 'Schema' );
		$schema       = $schema_class::collection( $this->collection );

		$schema->int( 'id' )->unique();
		$schema->string( 'created_at' )->index();
		$schema->string( 'updated_at' )->index();

		foreach ( $this->schema['fields'] ?? array() as $name => $definition ) {
			$name = sanitize_key( (string) $name );

			if ( '' === $name ) {
				continue;
			}

			$this->add_schema_field( $schema, $name, is_array( $definition ) ? $definition : array() );
		}

		return $schema;
	}

	/**
	 * Add one schema field to Storh.
	 *
	 * @param object               $schema     Storh schema.
	 * @param string               $name       Field name.
	 * @param array<string, mixed> $definition Field definition.
	 *
	 * @return void
	 */
	private function add_schema_field( object $schema, string $name, array $definition ): void {
		$type = $definition['type'] ?? 'string';

		switch ( $type ) {
			case 'integer':
				$builder = $schema->int( $name );
				break;
			case 'number':
				$builder = $schema->float( $name );
				break;
			case 'boolean':
				$builder = $schema->bool( $name );
				break;
			case 'object':
			case 'array':
				$builder = $schema->mixed( $name );
				break;
			default:
				$builder = $schema->string( $name );
				break;
		}

		if ( ! empty( $definition['unique'] ) || 'unique' === ( $definition['index'] ?? '' ) ) {
			$builder->unique();
			return;
		}

		if ( $this->is_range_field( $definition ) ) {
			$builder->range();
			return;
		}

		$builder->index();
	}

	/**
	 * Check whether a field should use a range index.
	 *
	 * @param array<string, mixed> $definition Field definition.
	 *
	 * @return bool
	 */
	private function is_range_field( array $definition ): bool {
		$type   = $definition['type'] ?? 'string';
		$format = $definition['format'] ?? '';

		return in_array( $type, array( 'integer', 'number' ), true )
			|| in_array( $format, array( 'date', 'date-time', 'datetime', 'time' ), true )
			|| ! empty( $definition['range'] );
	}

	/**
	 * Build a Storh query.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return object
	 */
	private function build_query( array $filters ): object {
		$query = $this->store->query();

		foreach ( $filters as $field => $value ) {
			$query = $query->where( sanitize_key( (string) $field ) )->eq( $this->cast_filter_value( (string) $field, $value ) );
		}

		return $query;
	}

	/**
	 * Cast query params to schema-native values before asking Storh indexes.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Filter value.
	 *
	 * @return mixed
	 */
	private function cast_filter_value( string $field, mixed $value ): mixed {
		$definition = $this->schema['fields'][ $field ] ?? null;

		if ( ! is_array( $definition ) ) {
			return $value;
		}

		switch ( $definition['type'] ?? 'string' ) {
			case 'integer':
				return (int) $value;
			case 'number':
				return (float) $value;
			case 'boolean':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? (bool) $value;
			default:
				return (string) $value;
		}
	}

	/**
	 * Write one record.
	 *
	 * @param int                  $id   Public record ID.
	 * @param array<string, mixed> $data Record data.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException When the public ID is invalid.
	 */
	private function write_record( int $id, array $data ): array {
		if ( $id < 1 ) {
			throw new \InvalidArgumentException( 'Storh public IDs must be positive integers.' );
		}

		$now                = current_time( 'mysql', true );
		$data['id']         = $id;
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$record = $this->store->put( $data, self::uuid_for_id( $id ) );

		return $this->normalize_record( $record );
	}

	/**
	 * Normalize a Storh record into Blockstudio's public shape.
	 *
	 * @param object $record Storh StorageRecord.
	 *
	 * @return array<string, mixed>
	 */
	private function normalize_record( object $record ): array {
		$data       = $record->data();
		$data['id'] = (int) ( $data['id'] ?? self::id_from_uuid( $record->id() ) );

		return $data;
	}

	/**
	 * Get the next public ID while holding the sequence lock.
	 *
	 * @return int
	 */
	private function next_id_unlocked(): int {
		$last_id = max( $this->read_sequence_unlocked(), $this->max_public_id() );
		$next_id = $last_id + 1;
		$this->write_sequence_unlocked( $next_id );

		return $next_id;
	}

	/**
	 * Read the current sequence value while holding the sequence lock.
	 *
	 * @return int
	 */
	private function read_sequence_unlocked(): int {
		$path = $this->sequence_path();

		if ( ! is_file( $path ) ) {
			return 0;
		}

		$data = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return is_array( $data ) ? max( 0, (int) ( $data['last_id'] ?? 0 ) ) : 0;
	}

	/**
	 * Write the current sequence value while holding the sequence lock.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return void
	 */
	private function write_sequence_unlocked( int $id ): void {
		$dir = dirname( $this->sequence_path() );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->sequence_path(),
			wp_json_encode( array( 'last_id' => max( 0, $id ) ), JSON_UNESCAPED_SLASHES ) . "\n",
			LOCK_EX
		);
	}

	/**
	 * Get the maximum public ID in the store.
	 *
	 * @return int
	 */
	private function max_public_id(): int {
		$max = 0;

		foreach ( $this->store->query()->orderBy( 'id', 'asc' )->get() as $record ) {
			$data = $record->data();
			$max  = max( $max, (int) ( $data['id'] ?? 0 ) );
		}

		return $max;
	}

	/**
	 * Run a callback while holding the sequence lock.
	 *
	 * @template T
	 *
	 * @param callable(): T $callback Callback.
	 *
	 * @return T
	 *
	 * @throws \RuntimeException When the sequence lock cannot be opened.
	 */
	private function with_sequence_lock( callable $callback ) {
		$dir = dirname( $this->sequence_path() );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$handle = fopen( $this->sequence_lock_path(), 'c+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			throw new \RuntimeException( 'Unable to open Storh sequence lock.' );
		}

		try {
			flock( $handle, LOCK_EX );

			return $callback();
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}

	/**
	 * Get the sequence metadata file path.
	 *
	 * @return string
	 */
	private function sequence_path(): string {
		return $this->root . '/' . $this->collection . '/.blockstudio/sequence.json';
	}

	/**
	 * Get the sequence lock file path.
	 *
	 * @return string
	 */
	private function sequence_lock_path(): string {
		return $this->root . '/' . $this->collection . '/.blockstudio/sequence.lock';
	}

	/**
	 * Get a deterministic UUIDv7-compatible internal ID for a public integer.
	 *
	 * @param int $id Public record ID.
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException When the public ID is out of range.
	 */
	private static function uuid_for_id( int $id ): string {
		if ( $id < 1 || $id > 0xffffffffffff ) {
			throw new \InvalidArgumentException( 'Storh public IDs must fit in 48 bits.' );
		}

		return '00000000-0000-7000-8000-' . str_pad( dechex( $id ), 12, '0', STR_PAD_LEFT );
	}

	/**
	 * Recover the public integer ID from a deterministic internal ID.
	 *
	 * @param string $uuid UUIDv7-compatible internal ID.
	 *
	 * @return int
	 */
	private static function id_from_uuid( string $uuid ): int {
		return hexdec( substr( $uuid, -12 ) );
	}

	/**
	 * Resolve a Storh class, preferring the scoped distribution namespace.
	 *
	 * @param string $class Short class name.
	 *
	 * @return class-string
	 *
	 * @throws \RuntimeException When Storh is not available.
	 */
	private static function storh_class( string $class ): string {
		$scoped   = '\\BlockstudioVendor\\Storh\\' . $class;
		$unscoped = '\\Storh\\' . $class;

		if ( class_exists( $scoped ) ) {
			return $scoped;
		}

		if ( class_exists( $unscoped ) ) {
			return $unscoped;
		}

		throw new \RuntimeException( 'Storh dependency is not available.' );
	}

	/**
	 * Get the block scope for a schema key.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	private static function scope_for_key( string $key ): string {
		$block_name = explode( ':', $key, 2 )[0] ?? $key;

		return trim( preg_replace( '/[^a-zA-Z0-9_.-]+/', '-', str_replace( '/', '-', $block_name ) ) ?? '', '-.' );
	}

	/**
	 * Get the collection name for a schema key.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	private static function collection_for_key( string $key ): string {
		$schema_name = explode( ':', $key, 2 )[1] ?? 'default';
		$name        = trim( preg_replace( '/[^a-zA-Z0-9_.-]+/', '-', $schema_name ) ?? '', '-.' );

		return '' === $name ? 'default' : $name;
	}
}

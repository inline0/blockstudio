<?php
/**
 * Storh database storage tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Database;
use Blockstudio\Db;
use Blockstudio\Db\Storh_Record_Store;
use Blockstudio\Files;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Storh bs.db storage backend.
 */
class StorhDatabaseTest extends TestCase {

	/**
	 * Temporary schema keys registered during tests.
	 *
	 * @var array<int, string>
	 */
	private array $temporary_schemas = array();

	/**
	 * Storh directories to remove after tests.
	 *
	 * @var array<int, string>
	 */
	private array $storh_directories = array();

	/**
	 * Load core schemas before injecting temporary schemas.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		Database::get_all();
	}

	/**
	 * Remove temporary schemas and generated Storh files.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->remove_temporary_schemas();

		foreach ( array_unique( $this->storh_directories ) as $directory ) {
			if ( is_dir( $directory ) ) {
				Files::delete_all_files( $directory );
			}
		}

		$this->storh_directories = array();
	}

	/**
	 * Fixture schema is discovered as Storh storage.
	 *
	 * @return void
	 */
	public function test_get_all_contains_storh_schema(): void {
		$schemas = Database::get_all();

		$this->assertArrayHasKey( 'blockstudio/type-db-storh:default', $schemas );
		$this->assertSame( 'storh', $schemas['blockstudio/type-db-storh:default']['storage'] );
	}

	/**
	 * Scoped Storh runtime is bundled and usable in packaged builds.
	 *
	 * @return void
	 */
	public function test_scoped_storh_runtime_can_read_query_and_delete(): void {
		$this->assertTrue( class_exists( \BlockstudioVendor\Storh\DocStore::class ) );

		$root = sys_get_temp_dir() . '/blockstudio-scoped-storh-' . uniqid( '', true );

		try {
			$schema = \BlockstudioVendor\Storh\Schema::collection( 'items' )->string( 'kind' )->index();
			$store  = new \BlockstudioVendor\Storh\DocStore(
				$root,
				'items',
				null,
				\BlockstudioVendor\Storh\Cache::memory(),
				$schema
			);
			$id     = '00000000-0000-7000-8000-000000000001';

			$store->put( array( 'kind' => 'alpha' ), $id );

			$hit = $store->query()->where( 'kind' )->eq( 'alpha' )->first();

			$this->assertInstanceOf( \BlockstudioVendor\Storh\StorageRecord::class, $hit );
			$this->assertSame( $id, $hit->id() );

			$store->delete( $id );

			$this->assertNull( $store->get( $id ) );
		} finally {
			if ( is_dir( $root ) ) {
				Files::delete_all_files( $root );
			}
		}
	}

	/**
	 * Storh supports public CRUD, filters, pagination, count, and indexed explains.
	 *
	 * @return void
	 */
	public function test_storh_crud_filter_pagination_count_and_explain(): void {
		$key = $this->register_storh_schema();

		$first = Database::execute(
			'create',
			$key,
			array(
				'data' => array(
					'title'  => 'Storh first',
					'status' => 'open',
					'count'  => 10,
					'score'  => 9.5,
					'active' => true,
				),
			)
		);

		$second = Database::execute(
			'create',
			$key,
			array(
				'data' => array(
					'title'  => 'Storh second',
					'status' => 'closed',
					'count'  => 20,
					'score'  => 3.25,
					'active' => false,
				),
			)
		);

		$this->assertIsArray( $first );
		$this->assertSame( 1, (int) $first['id'] );
		$this->assertSame( 2, (int) $second['id'] );
		$this->assertArrayHasKey( 'created_at', $first );
		$this->assertArrayHasKey( 'updated_at', $first );

		$fetched = Database::execute( 'get', $key, array( 'id' => (int) $first['id'] ) );
		$this->assertSame( 'Storh first', $fetched['title'] );
		$this->assertSame( 10, $fetched['count'] );
		$this->assertTrue( $fetched['active'] );

		$filtered = Database::execute(
			'list',
			$key,
			array(
				'filters' => array( 'count' => '10' ),
				'limit'   => 10,
			)
		);

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'Storh first', $filtered[0]['title'] );

		$paginated = Database::execute(
			'paginate',
			$key,
			array(
				'limit'  => 1,
				'offset' => 1,
			)
		);

		$this->assertSame( 2, $paginated['total'] );
		$this->assertCount( 1, $paginated['items'] );
		$this->assertSame( 'Storh second', $paginated['items'][0]['title'] );

		$this->assertSame( 1, Database::execute( 'count', $key, array( 'filters' => array( 'status' => 'closed' ) ) ) );

		$db = Db::get( explode( ':', $key, 2 )[0] );
		$this->assertSame( 1, $db->count( array( 'status' => 'closed' ) ) );
		$this->assertSame( 2, $db->paginate( array(), 1 )['total'] );

		$plan = Database::execute( 'explain', $key, array( 'filters' => array( 'status' => 'open' ) ) );
		$this->assertSame( 'index_scan', $plan['plan'] );
		$this->assertSame( 'status', $plan['indexes'][0]['field'] );

		$updated = Database::execute(
			'update',
			$key,
			array(
				'id'   => (int) $first['id'],
				'data' => array( 'title' => 'Storh updated' ),
			)
		);

		$this->assertSame( 'Storh updated', $updated['title'] );
		$this->assertSame( 10, $updated['count'] );

		$this->assertTrue( Database::execute( 'delete', $key, array( 'id' => (int) $first['id'] ) ) );
		$this->assertNull( Database::execute( 'get', $key, array( 'id' => (int) $first['id'] ) ) );
	}

	/**
	 * Storh stores generated files under uploads/blockstudio/db.
	 *
	 * @return void
	 */
	public function test_storh_stores_records_in_uploads_directory(): void {
		$key    = $this->register_storh_schema();
		$record = Database::execute(
			'create',
			$key,
			array(
				'data' => array(
					'title'  => 'Storh path',
					'status' => 'open',
				),
			)
		);

		$upload_dir = wp_upload_dir();
		$directory  = $this->directory_for_key( $key );

		$this->assertIsArray( $record );
		$this->assertStringStartsWith( trailingslashit( $upload_dir['basedir'] ) . 'blockstudio/db/', $directory );
		$this->assertDirectoryExists( $directory );
	}

	/**
	 * Explicit imported public IDs are preserved and advance the create sequence.
	 *
	 * @return void
	 */
	public function test_storh_preserves_explicit_public_ids(): void {
		$key    = $this->register_storh_schema();
		$schema = $this->schema_for_key( $key );
		$store  = new Storh_Record_Store( $key, $schema );

		$record = $store->put(
			42,
			array(
				'title'  => 'Imported row',
				'status' => 'seed',
				'count'  => 42,
			)
		);

		$this->assertSame( 42, (int) $record['id'] );
		$this->assertSame( 'Imported row', $store->get( 42 )['title'] );

		$created = Database::execute(
			'create',
			$key,
			array(
				'data' => array(
					'title'  => 'After import',
					'status' => 'live',
				),
			)
		);

		$this->assertSame( 43, (int) $created['id'] );
	}

	/**
	 * JSONC migration writes equivalent records to Storh.
	 *
	 * @return void
	 */
	public function test_migrate_jsonc_to_storh_preserves_ids_and_values(): void {
		$key       = 'blockstudio/type-db-jsonc:default';
		$directory = $this->directory_for_key( $key );

		if ( is_dir( $directory ) ) {
			Files::delete_all_files( $directory );
		}

		$this->storh_directories[] = $directory;

		$result = Database::migrate_to_storh( 'blockstudio/type-db-jsonc' );
		$this->assertIsArray( $result );
		$this->assertSame( 'jsonc', $result['from'] );
		$this->assertSame( 'storh', $result['to'] );
		$this->assertNotEmpty( $result['source_path'] );
		$this->assertSame( $result['source_count'], $result['target_count'] );
		$this->assertTrue( $result['verify']['ok'] );

		$source            = $this->read_jsonc_records( $result['source_path'] );
		$schema            = Database::get_all()[ $key ];
		$schema['storage'] = 'storh';
		$store             = new Storh_Record_Store( $key, $schema );
		$target            = $store->query( array(), 100, 0 );

		$this->assertSame( array_column( $source, 'id' ), array_column( $target, 'id' ) );
		$this->assertSame( array_column( $source, 'action' ), array_column( $target, 'action' ) );
	}

	/**
	 * Concurrent Storh creates allocate unique public IDs with no lost writes.
	 *
	 * @return void
	 */
	public function test_storh_concurrent_creates_do_not_lose_records(): void {
		if ( ! function_exists( 'pcntl_fork' ) || ! function_exists( 'pcntl_waitpid' ) ) {
			$this->markTestSkipped( 'pcntl is required for the Storh concurrency test.' );
		}

		$key      = $this->register_storh_schema();
		$children = 6;
		$pids     = array();

		for ( $i = 0; $i < $children; $i++ ) {
			$pid = pcntl_fork();

			if ( -1 === $pid ) {
				$this->fail( 'Unable to fork Storh concurrency worker.' );
			}

			if ( 0 === $pid ) {
				$record = Database::execute(
					'create',
					$key,
					array(
						'data' => array(
							'title'  => 'worker-' . $i,
							'status' => 'open',
							'count'  => $i,
						),
					)
				);
				exit( is_array( $record ) ? 0 : 1 );
			}

			$pids[] = $pid;
		}

		foreach ( $pids as $pid ) {
			pcntl_waitpid( $pid, $status );
			$this->assertSame( 0, pcntl_wexitstatus( $status ) );
		}

		$records = Database::execute( 'list', $key, array( 'limit' => 100 ) );
		$titles  = array_column( $records, 'title' );

		$this->assertCount( $children, $records );

		for ( $i = 0; $i < $children; $i++ ) {
			$this->assertContains( 'worker-' . $i, $titles );
		}

		$this->assertSame( range( 1, $children ), array_map( 'intval', array_column( $records, 'id' ) ) );
	}

	/**
	 * Snapshot-style JSONC writes can overwrite each other.
	 *
	 * @return void
	 */
	public function test_jsonc_snapshot_writes_can_lose_updates_documenting_storh_fix(): void {
		$path  = sys_get_temp_dir() . '/blockstudio-jsonc-race-' . uniqid( '', true ) . '.jsonc';
		$read  = static function () use ( $path ): array {
			if ( ! is_file( $path ) ) {
				return array();
			}

			return array_values(
				array_filter(
					array_map(
						static fn( $line ) => json_decode( trim( $line ), true ),
						explode( "\n", file_get_contents( $path ) ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					)
				)
			);
		};
		$write = static function ( array $records ) use ( $path ): void {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$path,
				implode( "\n", array_map( 'wp_json_encode', $records ) ) . "\n",
				LOCK_EX
			);
		};

		$snapshot_a = $read();
		$snapshot_b = $read();

		$snapshot_a[] = array(
			'id'    => 1,
			'title' => 'writer-a',
		);
		$write( $snapshot_a );

		$snapshot_b[] = array(
			'id'    => 1,
			'title' => 'writer-b',
		);
		$write( $snapshot_b );

		$final = $read();
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertCount( 1, $final );
		$this->assertSame( 'writer-b', $final[0]['title'] );
	}

	/**
	 * Register a temporary Storh schema.
	 *
	 * @return string Schema key.
	 */
	private function register_storh_schema(): string {
		$key = 'blockstudio/test-storh-' . str_replace( '.', '-', uniqid( '', true ) ) . ':default';

		$this->register_temporary_schema( $key, $this->storh_schema() );

		$directory = $this->directory_for_key( $key );

		if ( is_dir( $directory ) ) {
			Files::delete_all_files( $directory );
		}

		$this->storh_directories[] = $directory;

		return $key;
	}

	/**
	 * Get a reusable Storh schema definition.
	 *
	 * @return array<string, mixed>
	 */
	private function storh_schema(): array {
		return array(
			'storage' => 'storh',
			'fields'  => array(
				'title'  => array(
					'type'     => 'string',
					'required' => true,
				),
				'status' => array(
					'type' => 'string',
				),
				'count'  => array(
					'type' => 'integer',
				),
				'score'  => array(
					'type' => 'number',
				),
				'active' => array(
					'type' => 'boolean',
				),
			),
		);
	}

	/**
	 * Register a temporary schema in Database.
	 *
	 * @param string               $key    Schema key.
	 * @param array<string, mixed> $schema Schema definition.
	 *
	 * @return void
	 */
	private function register_temporary_schema( string $key, array $schema ): void {
		$property = new ReflectionProperty( Database::class, 'schemas' );
		$property->setAccessible( true );

		$schemas         = $property->getValue();
		$schemas[ $key ] = $schema;
		$property->setValue( null, $schemas );

		$this->temporary_schemas[] = $key;
	}

	/**
	 * Remove temporary schemas from Database.
	 *
	 * @return void
	 */
	private function remove_temporary_schemas(): void {
		$property = new ReflectionProperty( Database::class, 'schemas' );
		$property->setAccessible( true );

		$schemas = $property->getValue();

		foreach ( $this->temporary_schemas as $key ) {
			unset( $schemas[ $key ] );
		}

		$property->setValue( null, $schemas );
		$this->temporary_schemas = array();
	}

	/**
	 * Get a registered schema definition.
	 *
	 * @param string $key Schema key.
	 *
	 * @return array<string, mixed>
	 */
	private function schema_for_key( string $key ): array {
		$property = new ReflectionProperty( Database::class, 'schemas' );
		$property->setAccessible( true );

		return $property->getValue()[ $key ];
	}

	/**
	 * Resolve a Storh directory for a schema key.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	private function directory_for_key( string $key ): string {
		list( $block_name, $schema_name ) = explode( ':', $key, 2 );

		return Database::storh_directory( $block_name, $schema_name );
	}

	/**
	 * Read records from a JSONC fixture.
	 *
	 * @param string $path JSONC path.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function read_jsonc_records( string $path ): array {
		$records = array();

		foreach ( explode( "\n", file_get_contents( $path ) ) as $line ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '//' ) ) {
				continue;
			}

			$record = json_decode( $line, true );

			if ( is_array( $record ) ) {
				$records[] = $record;
			}
		}

		return $records;
	}
}

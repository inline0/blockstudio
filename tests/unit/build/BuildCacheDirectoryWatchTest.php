<?php

use Blockstudio\Build_Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BuildCacheDirectoryWatchTest extends TestCase {

	private string $directory;
	private string $storage;

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/bs-directory-watch-' . uniqid();
		$this->storage   = $this->directory . '/db';
		mkdir( $this->storage, 0777, true );
		file_put_contents( $this->directory . '/db.php', '<?php return array();' );
		file_put_contents( $this->storage . '/default.sqlite', 'fixture' );
	}

	protected function tearDown(): void {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}
		rmdir( $this->directory );
	}

	private function watch( array $files = array() ): array {
		return Build_Cache::create_watch_snapshot( array_merge( array( $this->directory . '/db.php' ), $files ), array( $this->storage ) );
	}

	private function change_directory_time(): void {
		touch( $this->storage, time() + 2 );
		clearstatcache();
	}

	public static function sqlite_sidecars(): array {
		return array( 'WAL' => array( 'wal' ), 'shared memory' => array( 'shm' ), 'rollback journal' => array( 'journal' ) );
	}

	#[DataProvider( 'sqlite_sidecars' )]
	public function test_sqlite_sidecar_creation_and_removal_keep_the_watch_valid( string $suffix ): void {
		$watch = $this->watch();
		$path  = $this->storage . '/default.sqlite-' . $suffix;
		file_put_contents( $path, 'runtime data' );
		$this->change_directory_time();
		$this->assertTrue( Build_Cache::is_watch_valid( $watch ) );
		unlink( $path );
		$this->change_directory_time();
		$this->assertTrue( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_new_source_files_in_the_data_directory_still_invalidate(): void {
		$watch = $this->watch();
		file_put_contents( $this->storage . '/block.json', '{"name":"test/new-block"}' );
		$this->change_directory_time();
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_watched_source_edits_still_invalidate(): void {
		$path = $this->storage . '/index.php';
		file_put_contents( $path, '<?php echo "old";' );
		$watch = $this->watch( array( $path ) );
		file_put_contents( $path, '<?php echo "updated source";' );
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_database_schema_edits_still_invalidate(): void {
		$watch = $this->watch();
		file_put_contents( $this->directory . '/db.php', '<?php return array("storage" => "sqlite");' );
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_atomic_source_replacements_with_preserved_metadata_still_invalidate(): void {
		$path = $this->storage . '/index.php';
		file_put_contents( $path, '<?php echo "old";' );
		$watch = $this->watch( array( $path ) );
		file_put_contents( $path . '.new', '<?php echo "new";' );
		touch( $path . '.new', filemtime( $path ) );
		rename( $path . '.new', $path );
		$this->change_directory_time();
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_source_file_removals_in_the_data_directory_still_invalidate(): void {
		$path = $this->storage . '/index.php';
		file_put_contents( $path, '<?php echo "source";' );
		$watch = $this->watch();
		unlink( $path );
		$this->change_directory_time();
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_legacy_snapshots_keep_strict_timestamp_checks(): void {
		$watch = $this->watch();
		unset( $watch['dirs'][ $this->storage ]['storageEntries'] );
		file_put_contents( $this->storage . '/default.sqlite-wal', 'runtime data' );
		$this->change_directory_time();
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_directories_named_like_sidecars_are_not_ignored(): void {
		$watch = $this->watch();
		mkdir( $this->storage . '/nested.sqlite-wal' );
		$this->change_directory_time();
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_ordinary_db_directories_keep_strict_timestamp_checks(): void {
		unlink( $this->directory . '/db.php' );
		$watch = $this->watch();
		file_put_contents( $this->storage . '/default.sqlite-wal', 'runtime data' );
		$this->change_directory_time();
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_missing_data_directories_still_invalidate(): void {
		$watch = $this->watch();
		unlink( $this->storage . '/default.sqlite' );
		rmdir( $this->storage );
		$this->assertFalse( Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_real_sqlite_connection_lifecycles_keep_the_watch_valid(): void {
		if ( ! extension_loaded( 'pdo_sqlite' ) ) {
			$this->markTestSkipped( 'PDO SQLite is unavailable.' );
		}
		$path = $this->storage . '/default.sqlite';
		unlink( $path );
		$pdo = new PDO( 'sqlite:' . $path );
		$pdo->exec( 'PRAGMA journal_mode=WAL' );
		$pdo->exec( 'CREATE TABLE records (id INTEGER PRIMARY KEY)' );
		$pdo = null;
		touch( $this->storage, time() - 5 );
		$watch = $this->watch();
		$pdo   = new PDO( 'sqlite:' . $path );
		$pdo->query( 'SELECT COUNT(*) FROM records' )->fetchColumn();
		$this->assertFileExists( $path . '-wal' );
		$this->assertTrue( Build_Cache::is_watch_valid( $watch ) );
		$pdo = null;
		$this->assertTrue( Build_Cache::is_watch_valid( $watch ) );
	}
}

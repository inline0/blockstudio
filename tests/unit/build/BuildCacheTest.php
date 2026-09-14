<?php
/**
 * Build cache tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Block_Registry;
use Blockstudio\Build;
use Blockstudio\Build_Cache;
use Blockstudio\Assets;
use Blockstudio\Files;
use Blockstudio\Single_Flight;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the file-backed build cache.
 */
class BuildCacheTest extends TestCase {

	/**
	 * Cache files to clean up.
	 *
	 * @var array
	 */
	private array $cache_files = array();

	/**
	 * Temporary directories to clean up.
	 *
	 * @var array
	 */
	private array $temporary_directories = array();

	/**
	 * Clean up cache files and temporary directories.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->cache_files as $cache_file ) {
			if ( is_file( $cache_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing temporary cache files created by this test.
				unlink( $cache_file );
			}
		}

		foreach ( $this->temporary_directories as $temporary_directory ) {
			if ( is_dir( $temporary_directory ) ) {
				Files::delete_all_files( $temporary_directory );
			}
		}

		$this->cache_files           = array();
		$this->temporary_directories = array();
	}

	/**
	 * Track a cache file written by this test.
	 *
	 * @param string $scope Cache scope.
	 * @param string $key   Cache key.
	 *
	 * @return void
	 */
	private function track_cache_file( string $scope, string $key ): void {
		$file                = Build_Cache::get_cache_dir( $scope ) . '/' . sanitize_file_name( $key ) . '.php';
		$this->cache_files[] = $file;
		$this->cache_files[] = $file . '.ok';
	}

	/**
	 * Create a temporary test directory.
	 *
	 * @return string Temporary directory path.
	 */
	private function create_temporary_directory(): string {
		$directory = BLOCKSTUDIO_DIR . '/.tmp-tests/blockstudio-cache-' . uniqid( '', true );
		wp_mkdir_p( $directory );
		$this->temporary_directories[] = $directory;

		return $directory;
	}

	/**
	 * Write a test file.
	 *
	 * @param string $path     File path.
	 * @param string $contents File contents.
	 *
	 * @return void
	 */
	private function write_file( string $path, string $contents ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing temporary test fixture files.
		file_put_contents( $path, $contents );
	}

	/**
	 * Snapshot the registry state that should match between cold and cached builds.
	 *
	 * @param string $block_name Block name.
	 *
	 * @return array Registry payload snapshot.
	 */
	private function snapshot_runtime_registry( string $block_name ): array {
		$registry = Block_Registry::instance();
		$block    = $registry->get_block( $block_name );
		$init     = array();

		foreach ( $registry->get_data() as $key => $data ) {
			if ( $data['init'] ?? false ) {
				$init[ $key ] = wp_normalize_path( $data['path'] );
			}
		}

		ksort( $init );

		$this->assertInstanceOf( \WP_Block_Type::class, $block );

		return array(
			'block'               => array(
				'name'             => $block->name,
				'attributes'       => $block->attributes,
				'uses_context'     => $block->uses_context,
				'provides_context' => $block->provides_context,
				'path'             => wp_normalize_path( $block->path ),
				'blockstudio'      => $block->blockstudio,
				'variations'       => $block->variations,
			),
			'data'                => $registry->get_block_data( $block_name ),
			'assets'              => $registry->get_assets(),
			'assets_admin'        => $registry->get_assets_admin(),
			'assets_block_editor' => $registry->get_assets_block_editor(),
			'assets_global'       => $registry->get_assets_global(),
			'init'                => $init,
		);
	}

	/**
	 * Cache directory uses a non-uploads directory under wp-content.
	 *
	 * @return void
	 */
	public function test_cache_directory_defaults_to_wordpress_content_directory(): void {
		$this->assertSame(
			wp_normalize_path( WP_CONTENT_DIR . '/blockstudio/cache' ),
			Build_Cache::get_cache_dir()
		);
	}

	public function test_relative_cache_setting_resolves_from_wordpress_content_directory(): void {
		$filter = static fn (): string => 'custom-cache/blockstudio';
		add_filter( 'blockstudio/settings/cache/path', $filter );

		try {
			$directory = Build_Cache::get_cache_dir( 'runtime' );

			$this->assertStringStartsWith(
				wp_normalize_path( WP_CONTENT_DIR . '/custom-cache/blockstudio/sites/network-' ),
				$directory
			);
			$this->assertStringEndsWith( '/runtime', $directory );
		} finally {
			remove_filter( 'blockstudio/settings/cache/path', $filter );
		}
	}

	public function test_absolute_cache_setting_is_preserved(): void {
		$path   = wp_normalize_path( sys_get_temp_dir() . '/blockstudio-custom-cache' );
		$filter = static fn (): string => $path;
		add_filter( 'blockstudio/settings/cache/path', $filter );

		try {
			$this->assertSame( $path, Build_Cache::get_cache_dir() );
		} finally {
			remove_filter( 'blockstudio/settings/cache/path', $filter );
		}
	}

	public function test_cache_directory_filter_overrides_setting(): void {
		$setting_path = static fn (): string => 'ignored-cache';
		$filter_path  = wp_normalize_path( sys_get_temp_dir() . '/blockstudio-filtered-cache' );
		$dir_filter   = static fn (): string => $filter_path;

		add_filter( 'blockstudio/settings/cache/path', $setting_path );
		add_filter( 'blockstudio/cache/dir', $dir_filter );

		try {
			$directory = Build_Cache::get_cache_dir( 'editor-assets' );

			$this->assertStringStartsWith( $filter_path . '/sites/network-', $directory );
			$this->assertStringEndsWith( '/editor-assets', $directory );
		} finally {
			remove_filter( 'blockstudio/settings/cache/path', $setting_path );
			remove_filter( 'blockstudio/cache/dir', $dir_filter );
		}
	}

	public function test_relative_cache_setting_cannot_escape_wordpress_content_directory(): void {
		$filter = static fn (): string => '../outside';
		add_filter( 'blockstudio/settings/cache/path', $filter );

		try {
			$this->assertSame(
				wp_normalize_path( WP_CONTENT_DIR . '/blockstudio/cache' ),
				Build_Cache::get_cache_dir()
			);
		} finally {
			remove_filter( 'blockstudio/settings/cache/path', $filter );
		}
	}

	public function test_failed_atomic_rename_returns_false_without_warning(): void {
		$directory  = $this->create_temporary_directory();
		$dir_filter = static fn (): string => $directory;

		add_filter( 'blockstudio/cache/dir', $dir_filter );

		try {
			$target = Build_Cache::get_cache_dir( 'runtime' ) . '/rename-collision.php';
			wp_mkdir_p( $target );

			$this->assertFalse(
				Build_Cache::write(
					'runtime',
					'rename-collision',
					array( 'value' => 'not-written' )
				)
			);
		} finally {
			remove_filter( 'blockstudio/cache/dir', $dir_filter );
		}
	}

	/**
	 * Touch a test directory.
	 *
	 * @param string $path  Directory path.
	 * @param int    $mtime Modified time.
	 *
	 * @return void
	 */
	private function touch_directory( string $path, int $mtime ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Touching temporary directory to assert cache invalidation.
		touch( $path, $mtime );
	}

	/**
	 * Cache loads while watched files remain unchanged.
	 *
	 * @return void
	 */
	public function test_cache_loads_payload_when_watch_is_valid(): void {
		$directory = $this->create_temporary_directory();

		$watched_file = $directory . '/block.json';
		$this->write_file( $watched_file, '{"name":"blockstudio-test/cache"}' );

		$key     = 'valid-watch-' . uniqid();
		$payload = array(
			'value' => 'cached',
			'watch' => Build_Cache::create_watch_snapshot( array( $watched_file ) ),
		);

		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write( 'runtime', $key, $payload );

		$this->assertSame(
			'cached',
			Build_Cache::load( 'runtime', $key )['value'] ?? null
		);
	}

	public function test_write_prunes_stale_scope_files(): void {
		$scope = 'unit-prune-' . uniqid();
		$dir   = Build_Cache::get_cache_dir( $scope );
		$this->temporary_directories[] = $dir;

		$filter = static fn(): int => 2;
		$deletions = 0;
		$media = static function ( $file ) use ( &$deletions ) {
			++$deletions;
			return $file;
		};
		add_filter( 'blockstudio/cache/max_files_per_scope', $filter );
		add_filter( 'wp_delete_file', $media );

		try {
			Build_Cache::write( $scope, 'one', array( 'watch' => array() ) );
			Build_Cache::write( $scope, 'two', array( 'watch' => array() ) );
			Build_Cache::write( $scope, 'three', array( 'watch' => array() ) );

			$files = glob( $dir . '/*.php' );
			$this->assertIsArray( $files );
			$this->assertLessThanOrEqual( 2, count( $files ) );
			$this->assertFileExists( Build_Cache::get_cache_dir( $scope ) . '/three.php' );
			$this->assertSame( 0, $deletions );
		} finally {
			remove_filter( 'blockstudio/cache/max_files_per_scope', $filter );
			remove_filter( 'wp_delete_file', $media );
		}
	}

	public function test_populate_versions_do_not_change_runtime_keys_or_lock_paths(): void {
		$before = Build_Cache::get_populate_cache_version();
		$key    = Build_Cache::get_runtime_key( '/fixed/blocks', 'default' );
		$lock   = Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' );
		try {
			for ( $i = 0; $i < 50; ++$i ) {
				update_option( 'blockstudio_populate_cache_version', wp_generate_uuid4(), false );
				$this->assertSame( $key, Build_Cache::get_runtime_key( '/fixed/blocks', 'default' ) );
				$this->assertSame( $lock, Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' ) );
			}
			$this->assertNotSame( $lock, Build_Cache::get_runtime_lock_path( '/other/blocks', 'default' ) );
			$this->assertNotSame( $lock, Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'other' ) );
		} finally {
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	public function test_populate_refresh_keeps_one_payload_and_lock_without_media_filters(): void {
		$directory = $this->create_temporary_directory();
		$root      = static fn(): string => $directory;
		$deletions = 0;
		$media     = static function ( $file ) use ( &$deletions ) {
			++$deletions;
			return $file;
		};
		$before    = Build_Cache::get_populate_cache_version();
		$prunes    = 0;
		$retention = static function ( $maximum ) use ( &$prunes ) {
			++$prunes;
			return $maximum;
		};
		$refreshes = 0;
		$refresh   = static function ( array $registered ) use ( &$refreshes ): array {
			++$refreshes;
			$registered['choices'] = $refreshes;
			return $registered;
		};
		add_filter( 'blockstudio/cache/dir', $root );
		add_filter( 'wp_delete_file', $media );
		add_filter( 'blockstudio/cache/max_files_per_scope', $retention );
		try {
			Build_Cache::write_runtime( '/fixed/blocks', 'default', array( 'registeredBlockTypes' => array(), 'store' => array() ) );
			$original = Build_Cache::load_runtime( '/fixed/blocks', 'default' );
			for ( $i = 0; $i < 50; ++$i ) {
				update_option( 'blockstudio_populate_cache_version', wp_generate_uuid4(), false );
				$payload = Build_Cache::refresh_runtime_populate( '/fixed/blocks', 'default', Build_Cache::load_runtime( '/fixed/blocks', 'default' ), $refresh );
				$this->assertSame( $original['watch'], $payload['watch'] );
				$this->assertSame( $i + 1, $payload['registeredBlockTypes']['choices'] );
				Build_Cache::refresh_runtime_populate( '/fixed/blocks', 'default', $payload, $refresh );
			}
			$this->assertSame( 50, $refreshes );
			$this->assertCount( 1, glob( Build_Cache::get_cache_dir( 'runtime' ) . '/*.php' ) );
			$this->assertCount( 1, glob( Build_Cache::get_cache_dir( 'runtime' ) . '/locks/*.lock' ) );
			$this->assertSame( 0, $deletions );
			$this->assertSame( 1, $prunes, 'Overwriting populated choices must not rescan the cache directory.' );
			$stale = Build_Cache::get_cache_dir( 'runtime' ) . '/abandoned.php.tmp-old';
			$this->write_file( $stale, 'abandoned writer' );
			touch( $stale, time() - 2 * HOUR_IN_SECONDS );
			touch( Build_Cache::get_cache_dir( 'runtime' ) . '/.pruned', time() - 2 * HOUR_IN_SECONDS );
			clearstatcache();
			Build_Cache::write( 'runtime', Build_Cache::get_runtime_key( '/fixed/blocks', 'default' ), $payload );
			$this->assertFileDoesNotExist( $stale );
			$this->assertSame( 2, $prunes, 'Periodic cleanup must still collect abandoned writers.' );
			$this->assertSame( 0, $deletions );
		} finally {
			remove_filter( 'blockstudio/cache/dir', $root );
			remove_filter( 'wp_delete_file', $media );
			remove_filter( 'blockstudio/cache/max_files_per_scope', $retention );
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	public function test_failed_populate_refresh_releases_lock_and_retains_payload(): void {
		$directory = $this->create_temporary_directory();
		$root      = static fn(): string => $directory;
		$before    = Build_Cache::get_populate_cache_version();
		add_filter( 'blockstudio/cache/dir', $root );
		try {
			Build_Cache::write_runtime( '/fixed/blocks', 'default', array( 'registeredBlockTypes' => array(), 'store' => array() ) );
			$original = Build_Cache::load_runtime( '/fixed/blocks', 'default' );
			update_option( 'blockstudio_populate_cache_version', wp_generate_uuid4(), false );
			try {
				Build_Cache::refresh_runtime_populate( '/fixed/blocks', 'default', $original, static function (): array {
					throw new RuntimeException( 'Populate failure' );
				} );
				$this->fail( 'The callback must fail.' );
			} catch ( RuntimeException $exception ) {
				$this->assertSame( 'Populate failure', $exception->getMessage() );
			}
			$this->assertSame( $original, Build_Cache::load_runtime( '/fixed/blocks', 'default' ) );
			$lock = Single_Flight::acquire( Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' ) );
			$this->assertIsResource( $lock );
			Single_Flight::release( $lock );
		} finally {
			remove_filter( 'blockstudio/cache/dir', $root );
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	public function test_last_good_populate_refresh_never_overwrites_the_builders_payload(): void {
		$directory = $this->create_temporary_directory();
		$root      = static fn(): string => $directory;
		$before    = Build_Cache::get_populate_cache_version();
		add_filter( 'blockstudio/cache/dir', $root );
		try {
			Build_Cache::write_runtime( '/fixed/blocks', 'default', array( 'registeredBlockTypes' => array(), 'store' => array() ) );
			$original = Build_Cache::load_runtime( '/fixed/blocks', 'default' );
			update_option( 'blockstudio_populate_cache_version', wp_generate_uuid4(), false );
			$result = Build_Cache::refresh_runtime_populate( '/fixed/blocks', 'default', $original, static fn(): array => array( 'request-only' => true ), false );
			$this->assertSame( array( 'request-only' => true ), $result['registeredBlockTypes'] );
			$this->assertSame( $original, Build_Cache::load_runtime( '/fixed/blocks', 'default' ) );
			$this->assertSame( array(), glob( Build_Cache::get_cache_dir( 'runtime' ) . '/locks/*.lock' ) );
		} finally {
			remove_filter( 'blockstudio/cache/dir', $root );
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	// Single_Flight::acquire() returns null, not false, when advisory locks
	// are unavailable. Treating that like a timed-out peer discarded every
	// refresh, so such hosts re-ran every populate query on every request.
	public function test_populate_refresh_persists_without_advisory_locks(): void {
		$directory = $this->create_temporary_directory();
		$root      = static fn(): string => $directory;
		$before    = Build_Cache::get_populate_cache_version();
		$refreshes = 0;
		$refresh   = static function ( array $registered ) use ( &$refreshes ): array {
			++$refreshes;
			$registered['choices'] = 'fresh';
			return $registered;
		};
		add_filter( 'blockstudio/cache/dir', $root );
		try {
			Build_Cache::write_runtime( '/fixed/blocks', 'default', array( 'registeredBlockTypes' => array( 'choices' => 'stale' ), 'store' => array() ) );
			$this->write_file( Build_Cache::get_cache_dir( 'runtime' ) . '/locks', 'a file where the lock directory belongs' );
			$this->assertNull( Single_Flight::acquire( Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' ) ) );

			$version = wp_generate_uuid4();
			update_option( 'blockstudio_populate_cache_version', $version, false );
			$payload = Build_Cache::refresh_runtime_populate( '/fixed/blocks', 'default', Build_Cache::load_runtime( '/fixed/blocks', 'default' ), $refresh );

			$this->assertSame( 'fresh', $payload['registeredBlockTypes']['choices'] );
			$stored = Build_Cache::load_runtime( '/fixed/blocks', 'default' );
			$this->assertSame( $version, $stored['populateVersion'] );
			$this->assertSame( 'fresh', $stored['registeredBlockTypes']['choices'] );

			Build_Cache::refresh_runtime_populate( '/fixed/blocks', 'default', $stored, $refresh );
			$this->assertSame( 1, $refreshes, 'The persisted refresh must serve the next request.' );
		} finally {
			remove_filter( 'blockstudio/cache/dir', $root );
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	public function test_contended_populate_refresh_is_request_only_and_bounded(): void {
		$directory = $this->create_temporary_directory();
		$root      = static fn(): string => $directory;
		$budget    = static fn(): int => 0;
		$before    = Build_Cache::get_populate_cache_version();
		$refreshes = 0;
		$refresh   = static function ( array $registered ) use ( &$refreshes ): array {
			++$refreshes;
			$registered['choices'] = 'fresh';
			return $registered;
		};
		add_filter( 'blockstudio/cache/dir', $root );
		add_filter( 'blockstudio/cache/build_wait_budget', $budget );
		$holder = null;
		try {
			Build_Cache::write_runtime( '/fixed/blocks', 'default', array( 'registeredBlockTypes' => array( 'choices' => 'stale' ), 'store' => array() ) );
			$stale  = Build_Cache::load_runtime( '/fixed/blocks', 'default' );
			$holder = Single_Flight::acquire( Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' ) );
			$this->assertIsResource( $holder );
			update_option( 'blockstudio_populate_cache_version', wp_generate_uuid4(), false );

			$started = microtime( true );
			$payload = Build_Cache::refresh_runtime_populate( '/fixed/blocks', 'default', $stale, $refresh );

			$this->assertLessThan( 1.0, microtime( true ) - $started );
			$this->assertSame( 1, $refreshes );
			$this->assertSame( 'fresh', $payload['registeredBlockTypes']['choices'] );
			$this->assertSame( $stale, Build_Cache::load_runtime( '/fixed/blocks', 'default' ), 'A waiter that lost the election must not publish over the owner.' );
		} finally {
			if ( is_resource( $holder ) ) {
				Single_Flight::release( $holder );
			}
			remove_filter( 'blockstudio/cache/dir', $root );
			remove_filter( 'blockstudio/cache/build_wait_budget', $budget );
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	// A translation sync writes meta continuously. Refreshing every language's
	// choices on each row is wasted load, so a burst coalesces into one refresh
	// on its leading edge and one more once it has settled, never losing the
	// last write.
	public function test_populate_invalidation_coalesces_a_burst_and_reflects_its_last_write(): void {
		$before = get_option( 'blockstudio_populate_cache_version', '0' );
		try {
			update_option( 'blockstudio_populate_cache_version', '0', false );

			Build_Cache::mark_populate_cache_dirty( 1000 );
			$leading = Build_Cache::get_populate_cache_version( 1000 );
			$this->assertNotSame( '0', $leading, 'The first write after a quiet period rotates immediately.' );

			Build_Cache::mark_populate_cache_dirty( 1005 );
			Build_Cache::mark_populate_cache_dirty( 1020 );
			$this->assertSame( $leading, Build_Cache::get_populate_cache_version( 1020 ), 'Writes inside the window are held.' );
			$this->assertSame( $leading, Build_Cache::get_populate_cache_version( 1029 ) );

			$trailing = Build_Cache::get_populate_cache_version( 1030 );
			$this->assertNotSame( $leading, $trailing, 'The last write is reflected once the window has passed.' );
			$this->assertSame( $trailing, Build_Cache::get_populate_cache_version( 1031 ), 'The trailing rotation happens exactly once.' );
			$this->assertSame( $trailing, Build_Cache::get_populate_cache_version( 5000 ), 'A settled state never rotates again on its own.' );

			// Racing readers derive the same trailing version from the same last write.
			update_option( 'blockstudio_populate_cache_version', array( 'version' => $trailing, 'rotated' => 1030, 'dirty' => 1040 ), false );
			$first = Build_Cache::get_populate_cache_version( 1060 );
			update_option( 'blockstudio_populate_cache_version', array( 'version' => $trailing, 'rotated' => 1030, 'dirty' => 1040 ), false );
			$this->assertSame( $first, Build_Cache::get_populate_cache_version( 1061 ) );

			// A pending trailing rotation is subsumed by a leading one, never lost.
			update_option( 'blockstudio_populate_cache_version', array( 'version' => 'held', 'rotated' => 2000, 'dirty' => 2010 ), false );
			Build_Cache::mark_populate_cache_dirty( 2030 );
			$this->assertNotSame( 'held', Build_Cache::get_populate_cache_version( 2030 ) );
		} finally {
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	public function test_populate_debounce_can_be_disabled_and_reads_legacy_versions(): void {
		$before = get_option( 'blockstudio_populate_cache_version', '0' );
		$off    = static fn(): int => 0;
		try {
			update_option( 'blockstudio_populate_cache_version', 'legacy-uuid', false );
			$this->assertSame( 'legacy-uuid', Build_Cache::get_populate_cache_version( 3000 ) );
			Build_Cache::mark_populate_cache_dirty( 3000 );
			$this->assertNotSame( 'legacy-uuid', Build_Cache::get_populate_cache_version( 3000 ), 'A legacy value reads as settled and rotates on the next write.' );

			add_filter( 'blockstudio/cache/populate_debounce', $off );
			Build_Cache::mark_populate_cache_dirty( 3001 );
			$one = Build_Cache::get_populate_cache_version( 3001 );
			Build_Cache::mark_populate_cache_dirty( 3001 );
			$this->assertNotSame( $one, Build_Cache::get_populate_cache_version( 3001 ), 'A zero window refreshes on every write.' );
		} finally {
			remove_filter( 'blockstudio/cache/populate_debounce', $off );
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	public function test_populate_write_signal_changes_on_every_write_while_the_version_is_held(): void {
		$before = get_option( 'blockstudio_populate_cache_version', '0' );
		try {
			update_option( 'blockstudio_populate_cache_version', '0', false );
			Build_Cache::mark_populate_cache_dirty( 4000 );
			$version = Build_Cache::get_populate_cache_version( 4001 );
			$first   = Build_Cache::get_populate_write_signal();
			Build_Cache::mark_populate_cache_dirty( 4001 );
			$second = Build_Cache::get_populate_write_signal();
			Build_Cache::mark_populate_cache_dirty( 4001 );
			$third = Build_Cache::get_populate_write_signal();

			$this->assertSame( $version, Build_Cache::get_populate_cache_version( 4002 ) );
			$this->assertNotSame( $first, $second );
			$this->assertNotSame( $second, $third, 'Two writes in the same second still bust request-local memoization.' );
		} finally {
			update_option( 'blockstudio_populate_cache_version', $before, false );
		}
	}

	public function test_language_specific_cache_namespaces_remain_isolated_and_repeatable(): void {
		$language = '';
		$filter = static function ( string $url ) use ( &$language ): string {
			return $url . $language;
		};
		add_filter( 'home_url', $filter );
		try {
			$english_key  = Build_Cache::get_runtime_key( '/fixed/blocks', 'default' );
			$english_lock = Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' );
			$language = 'de/';
			$this->assertNotSame( $english_key, Build_Cache::get_runtime_key( '/fixed/blocks', 'default' ) );
			$this->assertNotSame( $english_lock, Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' ) );
			$language = '';
			$this->assertSame( $english_key, Build_Cache::get_runtime_key( '/fixed/blocks', 'default' ) );
			$this->assertSame( $english_lock, Build_Cache::get_runtime_lock_path( '/fixed/blocks', 'default' ) );
		} finally {
			remove_filter( 'home_url', $filter );
		}
	}

	public function test_last_good_accepts_changed_sources_but_not_missing_sources_or_assets(): void {
		$directory = $this->create_temporary_directory();
		$root      = static fn(): string => $directory . '/cache';
		$source    = $directory . '/block.json';
		$asset     = $directory . '/style.css';
		$this->write_file( $source, 'old' );
		$this->write_file( $asset, '.old{}' );
		add_filter( 'blockstudio/cache/dir', $root );
		try {
			$key   = Build_Cache::get_runtime_key( '/fixed/blocks', 'default' );
			$watch = Build_Cache::create_watch_snapshot( array( $source ) );
			$watch['required'] = array( $asset );
			Build_Cache::write( 'runtime', $key, array( 'watch' => $watch ) );
			$this->write_file( $source, 'changed-source' );
			$this->assertNull( Build_Cache::load_runtime( '/fixed/blocks', 'default' ) );
			$this->assertIsArray( Build_Cache::load_last_good_runtime( '/fixed/blocks', 'default' ) );
			unlink( $asset );
			$this->assertNull( Build_Cache::load_last_good_runtime( '/fixed/blocks', 'default' ) );
			$this->write_file( $asset, '.new{}' );
			unlink( $source );
			$this->assertNull( Build_Cache::load_last_good_runtime( '/fixed/blocks', 'default' ) );
		} finally {
			remove_filter( 'blockstudio/cache/dir', $root );
		}
	}

	public function test_query_populate_refreshes_on_cached_build_without_registration_or_asset_processing(): void {
		Build_Cache::init();
		$directory = $this->create_temporary_directory();
		$cache     = $this->create_temporary_directory();
		$root      = static fn(): string => $cache;
		$marker    = 'Populate' . wp_generate_uuid4();
		$post_id   = wp_insert_post( array( 'post_title' => $marker . ' Before', 'post_status' => 'publish' ) );
		$field     = array(
			'id' => 'choices', 'type' => 'select', 'default' => '',
			'populate' => array( 'type' => 'query', 'query' => 'posts', 'arguments' => array( 's' => $marker, 'posts_per_page' => -1 ) ),
		);
		$tab_field = array_replace( $field, array( 'id' => 'tabChoice' ) );
		$fields    = array(
			$field,
			array( 'id' => 'group', 'type' => 'group', 'attributes' => array( $field ) ),
			array( 'id' => 'rows', 'type' => 'repeater', 'attributes' => array( $field ) ),
			array( 'type' => 'tabs', 'tabs' => array( array( 'name' => 'Options', 'attributes' => array( $tab_field ) ) ) ),
			array( 'id' => 'plain', 'type' => 'text', 'default' => 'Unchanged' ),
		);
		$name = 'blockstudio-test/cache-populate-refresh';
		wp_mkdir_p( $directory . '/block' );
		$this->write_file( $directory . '/block/block.json', wp_json_encode( array( 'name' => $name, 'title' => 'Populate Refresh', 'blockstudio' => array( 'attributes' => $fields ) ) ) );
		$this->write_file( $directory . '/block/index.php', '<div></div>' );
		$this->write_file( $directory . '/block/style.css', '.populate-refresh{}' );
		$filters = 0;
		$filter  = static function ( $attribute ) use ( &$filters ) {
			++$filters;
			return $attribute;
		};
		$assets  = 0;
		$asset_filter = static function ( $css ) use ( &$assets ) {
			++$assets;
			return $css;
		};
		$registry = Block_Registry::instance();
		$choice_labels = static function ( array $attributes ) use ( &$choice_labels ): array {
			$labels = array();
			foreach ( $attributes as $attribute ) {
				if ( ! is_array( $attribute ) ) {
					continue;
				}
				if ( isset( $attribute['populate'], $attribute['options'] ) ) {
					$labels = array_merge( $labels, array_column( $attribute['options'], 'label' ) );
				} else {
					$labels = array_merge( $labels, $choice_labels( $attribute ) );
				}
			}
			return $labels;
		};
		$no_debounce = static fn(): int => 0;
		add_filter( 'blockstudio/cache/dir', $root );
		add_filter( 'blockstudio/blocks/attributes', $filter );
		add_filter( 'blockstudio/assets/process/css/content', $asset_filter );
		add_filter( 'blockstudio/cache/populate_debounce', $no_debounce );
		try {
			$registry->reset();
			Build::init( array( 'dir' => $directory ) );
			$instance = Build::get_instance_name( $directory );
			$original = Build_Cache::load_runtime( $directory, $instance );
			$initial_filters = $filters;
			$initial_assets  = $assets;
			$this->assertGreaterThan( 0, $initial_filters );
			$this->assertSame( $marker . ' Before', $registry->get_block( $name )->attributes['choices']['options'][0]['label'] );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $marker . ' After' ) );
			for ( $i = 0; $i < 5; ++$i ) {
				update_post_meta( $post_id, '_unrelated_counter', $i );
				$registry->reset();
				Build::init( array( 'dir' => $directory ) );
				$attributes = $registry->get_block( $name )->attributes;
				$this->assertSame( $marker . ' After', $attributes['choices']['options'][0]['label'] );
				$this->assertSame( array_fill( 0, 4, $marker . ' After' ), $choice_labels( $attributes ) );
				$this->assertSame( 'Unchanged', $attributes['plain']['default'] );
				$this->assertSame( $original['watch'], Build_Cache::load_runtime( $directory, $instance )['watch'] );
			}
			$this->assertSame( $initial_filters, $filters );
			$this->assertSame( $initial_assets, $assets );
			$this->assertCount( 1, glob( Build_Cache::get_cache_dir( 'runtime' ) . '/*.php' ) );
			$this->assertCount( 1, glob( Build_Cache::get_cache_dir( 'runtime' ) . '/locks/*.lock' ) );
		} finally {
			remove_filter( 'blockstudio/cache/dir', $root );
			remove_filter( 'blockstudio/blocks/attributes', $filter );
			remove_filter( 'blockstudio/assets/process/css/content', $asset_filter );
			remove_filter( 'blockstudio/cache/populate_debounce', $no_debounce );
			wp_delete_post( $post_id, true );
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				WP_Block_Type_Registry::get_instance()->unregister( $name );
			}
			$registry->reset();
			Build::init( Build::get_build_dir() );
		}
	}

	public function test_populate_refresh_preserves_filtered_options_and_registration_kinds(): void {
		$filter = static fn(): array => array( 'unit' => array( array( 'value' => 'fresh', 'label' => 'Fresh' ) ) );
		add_filter( 'blockstudio/blocks/attributes/populate', $filter );
		try {
			foreach ( array( 'block', 'extension', 'override' ) as $kind ) {
				$item = array(
					'kind' => $kind,
					'block' => array( 'properties' => array( 'path' => '/retained/path', 'attributes' => array( 'anchor' => array( 'type' => 'string' ) ) ) ),
					'populateFields' => array( array(
						'id' => 'choice', 'type' => 'select', 'default' => 'fresh',
						'options' => array( array( 'value' => 'filtered', 'label' => 'Filtered' ) ),
						'populate' => array( 'type' => 'custom', 'custom' => 'unit' ),
					) ),
				);
				$result = Build::refresh_cached_populate_attributes( array( $item ) )[0];
				$this->assertSame( $kind, $result['kind'] );
				$this->assertSame( '/retained/path', $result['block']['properties']['path'] );
				$this->assertSame( array( 'type' => 'string' ), $result['block']['properties']['attributes']['anchor'] );
				$this->assertSame( array( 'Filtered', 'Fresh' ), array_column( $result['block']['properties']['attributes']['choice']['options'], 'label' ) );
			}
		} finally {
			remove_filter( 'blockstudio/blocks/attributes/populate', $filter );
		}
	}

	public function test_runtime_wait_timeout_never_starts_a_second_builder(): void {
		$cache = $this->create_temporary_directory();
		$root  = static fn(): string => $cache;
		$wait  = static fn(): int => 0;
		$die   = static fn(): Closure => static function ( $message, $title, $args ): void {
			throw new RuntimeException( 'HTTP ' . $args['response'] );
		};
		$registry = Block_Registry::instance();
		add_filter( 'blockstudio/cache/dir', $root );
		add_filter( 'blockstudio/cache/build_wait_budget', $wait );
		add_filter( 'wp_die_handler', $die );
		try {
			foreach ( array( false, true ) as $warm ) {
				$directory = $this->create_temporary_directory();
				$name      = 'blockstudio-test/cache-contention-' . ( $warm ? 'warm' : 'cold' );
				wp_mkdir_p( $directory . '/block' );
				$definition = array( 'name' => $name, 'title' => 'Contention', 'blockstudio' => array( 'attributes' => array( array( 'id' => 'text', 'type' => 'text', 'default' => 'Before' ) ) ) );
				$this->write_file( $directory . '/block/block.json', wp_json_encode( $definition ) );
				$this->write_file( $directory . '/block/index.php', '<div></div>' );
				$instance = Build::get_instance_name( $directory );
				$file     = Build_Cache::get_cache_file( 'runtime', Build_Cache::get_runtime_key( $directory, $instance ) );
				$registry->reset();
				$original = null;
				if ( $warm ) {
					Build::init( array( 'dir' => $directory ) );
					$original = file_get_contents( $file );
					$definition['blockstudio']['attributes'][0]['default'] = 'After changed source';
					$this->write_file( $directory . '/block/block.json', wp_json_encode( $definition ) );
					$registry->reset();
				}
				$lock = Single_Flight::acquire( Build_Cache::get_runtime_lock_path( $directory, $instance ) );
				$this->assertIsResource( $lock );
				try {
					try {
						Build::init( array( 'dir' => $directory ) );
						$this->assertTrue( $warm, 'A cold waiter must return a retryable 503.' );
						$this->assertSame( 'Before', $registry->get_block( $name )->attributes['text']['default'] );
						$this->assertSame( $original, file_get_contents( $file ) );
					} catch ( RuntimeException $exception ) {
						$this->assertFalse( $warm );
						$this->assertSame( 'HTTP 503', $exception->getMessage() );
						$this->assertFileDoesNotExist( $file );
					}
				} finally {
					Single_Flight::release( $lock );
				}
				$registry->reset();
				Build::init( array( 'dir' => $directory ) );
				$this->assertSame( $warm ? 'After changed source' : 'Before', $registry->get_block( $name )->attributes['text']['default'] );
				if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
					WP_Block_Type_Registry::get_instance()->unregister( $name );
				}
			}
		} finally {
			remove_filter( 'blockstudio/cache/dir', $root );
			remove_filter( 'blockstudio/cache/build_wait_budget', $wait );
			remove_filter( 'wp_die_handler', $die );
			$registry->reset();
			Build::init( Build::get_build_dir() );
		}
	}

	/**
	 * Cache files use a compact payload format when zlib is available.
	 *
	 * @return void
	 */
	public function test_cache_file_uses_compact_payload_format_when_available(): void {
		if ( ! function_exists( 'gzcompress' ) ) {
			$this->markTestSkipped( 'zlib is not available.' );
		}

		$key     = 'compact-payload-' . uniqid();
		$payload = array(
			'value' => str_repeat( 'cached-value-', 1000 ),
			'watch' => Build_Cache::create_watch_snapshot( array() ),
		);
		$file    = Build_Cache::get_cache_dir( 'runtime' ) . '/' . sanitize_file_name( $key ) . '.php';

		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write( 'runtime', $key, $payload );

		$this->assertFileExists( $file );
		$this->assertLessThan(
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Comparing compact cache size against the legacy exported-array shape.
			strlen( var_export( $payload, true ) ),
			filesize( $file )
		);
		$this->assertSame(
			$payload['value'],
			Build_Cache::load( 'runtime', $key )['value'] ?? null
		);
	}

	/**
	 * Cache invalidates when a watched file changes.
	 *
	 * @return void
	 */
	public function test_cache_invalidates_when_watched_file_changes(): void {
		$directory = $this->create_temporary_directory();

		$watched_file = $directory . '/index.php';
		$this->write_file( $watched_file, '<?php echo "one";' );

		$key     = 'changed-file-' . uniqid();
		$payload = array(
			'value' => 'cached',
			'watch' => Build_Cache::create_watch_snapshot( array( $watched_file ) ),
		);

		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write( 'runtime', $key, $payload );
		$this->write_file( $watched_file, '<?php echo "changed file contents";' );

		$this->assertNull( Build_Cache::load( 'runtime', $key ) );
	}

	/**
	 * Cache invalidates when a watched directory changes.
	 *
	 * @return void
	 */
	public function test_cache_invalidates_when_watched_directory_changes(): void {
		$directory = $this->create_temporary_directory();

		$watched_directory = $directory . '/blocks';
		wp_mkdir_p( $watched_directory );

		$key     = 'changed-directory-' . uniqid();
		$payload = array(
			'value' => 'cached',
			'watch' => Build_Cache::create_watch_snapshot( array(), array( $watched_directory ) ),
		);

		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write( 'runtime', $key, $payload );
		$this->write_file( $watched_directory . '/new-file.php', '<?php echo "new";' );
		$this->touch_directory( $watched_directory, time() + 2 );

		$this->assertNull( Build_Cache::load( 'runtime', $key ) );
	}

	/**
	 * Runtime cache watches block files listed in the payload.
	 *
	 * @return void
	 */
	public function test_runtime_cache_watches_block_files_from_payload(): void {
		$directory = $this->create_temporary_directory();

		$block_directory = $directory . '/example';
		wp_mkdir_p( $block_directory );

		$block_json = $block_directory . '/block.json';
		$template   = $block_directory . '/index.php';

		$this->write_file( $block_json, '{"name":"blockstudio-test/cache-runtime","title":"Cache Runtime"}' );
		$this->write_file( $template, '<?php echo "one";' );

		$payload = array(
			'store'        => array(
				'blockstudio-test/cache-runtime' => array(
					'name'       => 'blockstudio-test/cache-runtime',
					'path'       => $block_json,
					'filesPaths' => array( $block_json, $template ),
				),
			),
			'registerable' => array(),
		);

		$key = Build_Cache::get_runtime_key( $directory, 'test-instance' );
		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write_runtime( $directory, 'test-instance', $payload );

		$this->assertIsArray( Build_Cache::load_runtime( $directory, 'test-instance' ) );

		$this->write_file( $template, '<?php echo "changed runtime cache file";' );

		$this->assertNull( Build_Cache::load_runtime( $directory, 'test-instance' ) );
	}

	/**
	 * Runtime cache watches asset dependency files from the payload.
	 *
	 * @return void
	 */
	public function test_runtime_cache_watches_asset_dependencies_from_payload(): void {
		$directory = $this->create_temporary_directory();

		$block_directory = $directory . '/example';
		wp_mkdir_p( $block_directory );

		$block_json = $block_directory . '/block.json';
		$style      = $block_directory . '/style.scss';
		$dependency = $block_directory . '/_tokens.scss';

		$this->write_file( $block_json, '{"name":"blockstudio-test/cache-runtime-dependencies","title":"Cache Runtime Dependencies"}' );
		$this->write_file( $style, '@use "tokens"; .example { color: $color; }' );
		$this->write_file( $dependency, '$color: red;' );

		$payload = array(
			'store'        => array(
				'blockstudio-test/cache-runtime-dependencies' => array(
					'name'       => 'blockstudio-test/cache-runtime-dependencies',
					'path'       => $block_json,
					'filesPaths' => array( $block_json, $style ),
					'assets'     => array(
						'style-scss' => array(
							'path'         => $style,
							'dependencies' => array( $dependency ),
							'file'         => pathinfo( $style ),
						),
					),
				),
			),
			'registerable' => array(),
		);

		$key = Build_Cache::get_runtime_key( $directory, 'test-instance' );
		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write_runtime( $directory, 'test-instance', $payload );

		$this->assertIsArray( Build_Cache::load_runtime( $directory, 'test-instance' ) );

		$this->write_file( $dependency, '$color: blue; $gap: 1rem;' );

		$this->assertNull( Build_Cache::load_runtime( $directory, 'test-instance' ) );
	}

	/**
	 * A fresh debounce stamp never hides a deleted compiled asset.
	 *
	 * @return void
	 */
	public function test_runtime_cache_checks_compiled_assets_inside_debounce_window(): void {
		$directory = $this->create_temporary_directory();
		$source    = $directory . '/style.scss';
		$dist      = Assets::get_dist_folder( $source );
		$compiled  = $dist . '/style-' . str_repeat( 'a', 32 ) . '.css';

		wp_mkdir_p( $dist );
		$this->write_file( $source, '.example { color: red; }' );
		$this->write_file( $compiled, '.example{color:red}' );

		$payload = array(
			'store'        => array(
				'blockstudio-test/debounce-assets' => array(
					'name'   => 'blockstudio-test/debounce-assets',
					'path'   => $directory . '/block.json',
					'assets' => array(
						'style.scss' => array(
							'path' => $source,
						),
					),
				),
			),
			'registerable' => array(),
		);
		$key     = Build_Cache::get_runtime_key( $directory, 'debounce-assets' );
		$filter  = static fn(): int => 20;

		add_filter( 'blockstudio/cache/watch_debounce', $filter );

		try {
			$this->track_cache_file( 'runtime', $key );
			$this->assertTrue( Build_Cache::write_runtime( $directory, 'debounce-assets', $payload ) );

			$cached = Build_Cache::load_runtime( $directory, 'debounce-assets' );
			$this->assertIsArray( $cached );
			$this->assertContains( wp_normalize_path( $compiled ), $cached['watch']['required'] ?? array() );

			unlink( $compiled );

			$this->assertNull( Build_Cache::load_runtime( $directory, 'debounce-assets' ) );
		} finally {
			remove_filter( 'blockstudio/cache/watch_debounce', $filter );
		}
	}

	/**
	 * Runtime cache invalidates when a new nested block file is added.
	 *
	 * @return void
	 */
	public function test_runtime_cache_watches_nested_directories_for_new_blocks(): void {
		$directory = $this->create_temporary_directory();

		$section_directory = $directory . '/sections';
		$block_directory   = $section_directory . '/one';
		wp_mkdir_p( $block_directory );

		$block_json = $block_directory . '/block.json';

		$this->write_file( $block_json, '{"name":"blockstudio-test/cache-runtime-one","title":"Cache Runtime One"}' );

		$payload = array(
			'store'        => array(
				'blockstudio-test/cache-runtime-one' => array(
					'name'       => 'blockstudio-test/cache-runtime-one',
					'path'       => $block_json,
					'filesPaths' => array( $block_json ),
				),
			),
			'registerable' => array(),
		);

		$key = Build_Cache::get_runtime_key( $directory, 'test-instance' );
		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write_runtime( $directory, 'test-instance', $payload );

		$this->assertIsArray( Build_Cache::load_runtime( $directory, 'test-instance' ) );

		$new_block_directory = $section_directory . '/two';
		wp_mkdir_p( $new_block_directory );
		$this->write_file( $new_block_directory . '/block.json', '{"name":"blockstudio-test/cache-runtime-two","title":"Cache Runtime Two"}' );
		$this->touch_directory( $section_directory, time() + 2 );

		$this->assertNull( Build_Cache::load_runtime( $directory, 'test-instance' ) );
	}

	/**
	 * Runtime cache keys stay stable when database-backed populate sources change.
	 *
	 * @return void
	 */
	public function test_runtime_cache_key_survives_populate_source_changes(): void {
		Build_Cache::init();

		$directory = $this->create_temporary_directory();
		$instance  = 'populate-source-change';
		$before    = Build_Cache::get_runtime_key( $directory, $instance );
		$post_id   = wp_insert_post(
			array(
				'post_title'  => 'Populate cache source change',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		try {
			$this->assertGreaterThan( 0, $post_id );
			$this->assertSame(
				$before,
				Build_Cache::get_runtime_key( $directory, $instance )
			);
		} finally {
			if ( is_int( $post_id ) && $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}
		}
	}

	/**
	 * Build a registry from one block and return the populate option value.
	 *
	 * @param string $attributes Serialized blockstudio attributes.
	 *
	 * @return array{before: string, after: string} Option value around a post write.
	 */
	private function populate_version_around_post_write( string $attributes ): array {
		Build_Cache::init();

		$directory       = $this->create_temporary_directory();
		$path            = wp_normalize_path( $directory );
		$block_directory = $path . '/populate-gate';
		wp_mkdir_p( $block_directory );

		$this->write_file(
			$block_directory . '/block.json',
			'{"name":"blockstudio-test/populate-gate","title":"Populate Gate","blockstudio":{"attributes":' . $attributes . '}}'
		);
		$this->write_file( $block_directory . '/index.php', '<div></div>' );

		$registry = Block_Registry::instance();
		$post_id  = 0;

		try {
			$registry->reset();
			Build::init( array( 'dir' => $path ) );

			$this->assertNotEmpty(
				Build::blocks(),
				'The fixture block must register, or the gate falls back to invalidating.'
			);

			// Start from a settled state: the write under test is a leading edge.
			update_option( 'blockstudio_populate_cache_version', '0', false );
			$before = Build_Cache::get_populate_cache_version();

			$post_id = wp_insert_post(
				array(
					'post_title'  => 'Populate gate',
					'post_status' => 'publish',
					'post_type'   => 'post',
				)
			);
			$this->assertGreaterThan( 0, $post_id );

			return array(
				'before' => $before,
				'after'  => Build_Cache::get_populate_cache_version(),
			);
		} finally {
			if ( is_int( $post_id ) && $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}

			$registry->reset();

			$default_build_dir = Build::get_build_dir();

			if ( is_dir( $default_build_dir ) ) {
				Build::init( $default_build_dir );
			}
		}
	}

	/**
	 * Content writes do not touch the populate option when no block populates.
	 *
	 * @return void
	 */
	public function test_populate_cache_version_is_untouched_when_no_block_populates(): void {
		$version = $this->populate_version_around_post_write(
			'[{"id":"plain","type":"text","label":"Plain","default":""}]'
		);

		$this->assertSame( $version['before'], $version['after'] );
	}

	/**
	 * Content writes still bump the populate option when a block populates.
	 *
	 * @return void
	 */
	public function test_populate_cache_version_changes_when_a_block_populates(): void {
		$version = $this->populate_version_around_post_write(
			'[{"id":"posts","type":"select","label":"Posts","default":"",' .
			'"populate":{"type":"query","query":"posts",' .
			'"returnFormat":{"value":"ID","label":"post_title"}}}]'
		);

		$this->assertNotSame( $version['before'], $version['after'] );
	}

	/**
	 * A populate field nested in a repeater row still counts as in use.
	 *
	 * @return void
	 */
	public function test_populate_cache_version_changes_for_a_nested_populate_field(): void {
		$version = $this->populate_version_around_post_write(
			'[{"id":"rows","type":"repeater","label":"Rows","default":[],"min":0,"max":10,' .
			'"attributes":[{"id":"posts","type":"select","label":"Posts","default":"",' .
			'"populate":{"type":"query","query":"posts",' .
			'"returnFormat":{"value":"ID","label":"post_title"}}}]}]'
		);

		$this->assertNotSame( $version['before'], $version['after'] );
	}

	/**
	 * Runtime cache hydration matches a cold runtime build.
	 *
	 * @return void
	 */
	public function test_runtime_cache_hydrates_equivalent_registry_payload(): void {
		$directory       = $this->create_temporary_directory();
		$block_directory = $directory . '/cache-parity';
		wp_mkdir_p( $block_directory );

		$block_name = 'blockstudio-test/cache-parity';
		$block_json = $block_directory . '/block.json';
		$template   = $block_directory . '/index.php';
		$helpers    = $block_directory . '/init-helpers.php';
		$init       = $block_directory . '/init.php';
		$style      = $block_directory . '/style.css';
		$state_key  = 'blockstudio_cache_init_' . md5( $directory );

		$this->write_file(
			$block_json,
			wp_json_encode(
				array(
					'$schema'     => 'https://blockstudio.dev/schema/block',
					'name'        => $block_name,
					'title'       => 'Cache Parity',
					'category'    => 'widgets',
					'blockstudio' => array(
						'attributes' => array(
							array(
								'id'      => 'text',
								'type'    => 'text',
								'default' => 'Default text',
							),
						),
					),
				)
			)
		);
		$this->write_file( $template, '<?php echo esc_html( $a["text"] ?? "" );' );
		$this->write_file(
			$helpers,
			'<?php $GLOBALS[' . var_export( $state_key, true ) . '][] = "helpers";'
		);
		$this->write_file(
			$init,
			'<?php $GLOBALS[' . var_export( $state_key, true ) . '][] = "init";'
		);
		$this->write_file( $style, '.cache-parity { color: red; }' );

		$path     = wp_normalize_path( $directory );
		$instance = Build::get_instance_name( $path );
		$key      = Build_Cache::get_runtime_key( $path, $instance );
		$registry = Block_Registry::instance();

		$this->track_cache_file( 'runtime', $key );

		try {
			$registry->reset();

			Build::init( array( 'dir' => $path ) );
			$this->assertIsArray( Build_Cache::load_runtime( $path, $instance ) );
			$cold = $this->snapshot_runtime_registry( $block_name );
			$this->assertSame( array( 'helpers', 'init' ), $GLOBALS[ $state_key ] ?? array() );
			$this->assertFalse( $cold['data']['init'] );
			$this->assertSame( wp_normalize_path( $block_json ), $cold['data']['path'] );
			$this->assertSame(
				array(
					wp_normalize_path( $helpers ) => wp_normalize_path( $helpers ),
					wp_normalize_path( $init )    => wp_normalize_path( $init ),
				),
				$cold['init']
			);

			$registry->reset();

			Build::init( array( 'dir' => $path ) );
			$warm = $this->snapshot_runtime_registry( $block_name );

			$this->assertSame( $cold, $warm );

			$reflection = new ReflectionClass( Build::class );
			$method     = $reflection->getMethod( 'refresh_runtime_cache_is_current' );
			$this->assertTrue( $method->invoke( null, $registry ) );

			$this->write_file( $style, '.cache-parity { color: blue; }' );
			clearstatcache();

			$this->assertFalse( $method->invoke( null, $registry ) );
		} finally {
			unset( $GLOBALS[ $state_key ] );
			$registry->reset();

			$default_build_dir = Build::get_build_dir();

			if ( is_dir( $default_build_dir ) ) {
				Build::init( $default_build_dir );
			}
		}
	}

	/**
	 * Forced refresh discovers blocks hidden by coarse directory mtimes.
	 *
	 * @return void
	 */
	public function test_forced_refresh_discovers_new_block_when_runtime_cache_appears_current(): void {
		$directory       = $this->create_temporary_directory();
		$block_directory = $directory . '/existing';
		wp_mkdir_p( $block_directory );

		$existing_name = 'blockstudio-test/cache-refresh-existing';
		$new_name      = 'blockstudio-test/cache-refresh-new';

		$this->write_file(
			$block_directory . '/block.json',
			wp_json_encode(
				array(
					'$schema'     => 'https://blockstudio.dev/schema/block',
					'name'        => $existing_name,
					'title'       => 'Cache Refresh Existing',
					'category'    => 'widgets',
					'blockstudio' => true,
				)
			)
		);
		$this->write_file( $block_directory . '/index.php', '<?php echo "Existing";' );

		$path        = wp_normalize_path( $directory );
		$instance    = Build::get_instance_name( $path );
		$key         = Build_Cache::get_runtime_key( $path, $instance );
		$registry    = Block_Registry::instance();
		$path_filter = static fn(): string => $path;

		$this->track_cache_file( 'runtime', $key );
		add_filter( 'blockstudio/path', $path_filter );

		try {
			$registry->reset();
			Build::init( array( 'dir' => $path ) );

			$cached_mtime = filemtime( $directory );
			$this->assertIsInt( $cached_mtime );

			$new_block_directory = $directory . '/new';
			wp_mkdir_p( $new_block_directory );
			$this->write_file(
				$new_block_directory . '/block.json',
				wp_json_encode(
					array(
						'$schema'     => 'https://blockstudio.dev/schema/block',
						'name'        => $new_name,
						'title'       => 'Cache Refresh New',
						'category'    => 'widgets',
						'blockstudio' => true,
					)
				)
			);
			$this->write_file( $new_block_directory . '/index.php', '<?php echo "New";' );

			$this->touch_directory( $directory, $cached_mtime );
			clearstatcache( true, $directory );

			$cached_runtime = Build_Cache::load_runtime( $path, $instance );
			$this->assertIsArray( $cached_runtime );

			$reflection = new ReflectionClass( Build::class );
			$method     = $reflection->getMethod( 'refresh_runtime_cache_is_current' );
			$this->assertTrue(
				$method->invoke( null, $registry ),
				wp_json_encode(
					array(
						'cached'  => array_keys( $cached_runtime['registeredBlockTypes'] ?? array() ),
						'current' => array_keys( Build::blocks() ),
					)
				)
			);

			Build::refresh_blocks( true );
			$this->assertArrayNotHasKey( $new_name, Build::blocks() );

			Build::refresh_blocks();
			$this->assertArrayHasKey( $new_name, Build::blocks() );
		} finally {
			remove_filter( 'blockstudio/path', $path_filter );
			$registry->reset();

			$default_build_dir = Build::get_build_dir();

			if ( is_dir( $default_build_dir ) ) {
				Build::init( $default_build_dir );
			}
		}
	}

	/**
	 * Editor asset fingerprints use cached metadata before file snapshots.
	 *
	 * @return void
	 */
	public function test_editor_asset_fingerprint_uses_asset_keys_from_metadata(): void {
		$directory = $this->create_temporary_directory();

		$style = $directory . '/style.scss';
		$view  = $directory . '/view.js';

		$this->write_file( $style, '.example { color: red; }' );
		$this->write_file( $view, 'console.log("view");' );

		$reflection = new ReflectionClass( Build_Cache::class );
		$method     = $reflection->getMethod( 'get_editor_assets_fingerprint' );

		$fingerprint = $method->invoke(
			null,
			array(
				'blockstudio-test/cache-editor-assets' => array(
					'name'   => 'blockstudio-test/cache-editor-assets',
					'assets' => array(
						'style-scss' => array(
							'path'   => $style,
							'key'    => 'dependency-aware-version',
							'mtime'  => 123,
							'type'   => 'inline',
							'editor' => false,
							'file'   => array(
								'extension' => 'scss',
							),
						),
						'view-js'    => array(
							'path'   => $view,
							'mtime'  => 456,
							'type'   => 'external',
							'editor' => false,
							'file'   => array(
								'extension' => 'js',
							),
						),
					),
				),
			)
		);

		$this->assertSame( 'style-scss', $fingerprint[0]['id'] );
		$this->assertSame( 'dependency-aware-version', $fingerprint[0]['version'] );
		$this->assertSame( 'view-js', $fingerprint[1]['id'] );
		$this->assertSame( 456, $fingerprint[1]['version'] );
	}

	/**
	 * Editor asset cache keys track disabled asset filters.
	 *
	 * @return void
	 */
	public function test_editor_asset_cache_key_tracks_disabled_asset_filter(): void {
		$baseline = Build_Cache::get_editor_assets_key();
		$callback = static fn() => array( 'blockstudio-test-disabled-asset' );

		add_filter( 'blockstudio/assets/disable', $callback );

		try {
			$this->assertNotSame( $baseline, Build_Cache::get_editor_assets_key() );
		} finally {
			remove_filter( 'blockstudio/assets/disable', $callback );
		}
	}
}

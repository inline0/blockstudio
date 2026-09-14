<?php
/**
 * Shared runtime cache tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Runtime_Cache;
use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Single_Flight;
use PHPUnit\Framework\TestCase;

/**
 * Tests the common cache identity, storage, recovery, and pruning boundary.
 */
class RuntimeCacheTest extends TestCase {

	private string $root;

	private string $site_key = 'unit-site-a';

	protected function setUp(): void {
		$this->root = trailingslashit( get_temp_dir() ) . 'blockstudio-runtime-cache-' . wp_generate_uuid4();
		wp_mkdir_p( $this->root );

		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		Runtime_Cache::reset_diagnostics();
		Settings::reset();
		Runtime_Settings::reset();
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		remove_all_filters( 'blockstudio/cache/max_files_per_scope' );
		remove_all_filters( 'blockstudio/cache/legacy_cleanup_batch_size' );
		wp_clear_scheduled_hook( 'blockstudio/cache/cleanup_legacy_runtime' );
		wp_clear_scheduled_hook( 'blockstudio/cache/cleanup_legacy_build_locks' );
		delete_transient( 'blockstudio_legacy_lock_sweep' );
		Runtime_Cache::reset_diagnostics();
		Settings::reset();
		Runtime_Settings::reset();
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root;
	}

	public function filter_site_key(): string {
		return $this->site_key;
	}

	public function test_scopes_share_one_root_but_keep_site_and_runtime_namespaces(): void {
		$runtime = Runtime_Cache::directory( 'runtime' );
		$assets  = Runtime_Cache::directory( 'editor-assets' );

		$this->assertSame( $this->root, Runtime_Cache::root() );
		$this->assertStringStartsWith( $this->root . '/sites/unit-site-a/', $runtime );
		$this->assertStringEndsWith( '/runtime', $runtime );
		$this->assertStringStartsWith( $this->root . '/sites/unit-site-a/', $assets );
		$this->assertStringEndsWith( '/editor-assets', $assets );
		$this->assertNotSame( dirname( $runtime ), dirname( $assets ) );

		$this->site_key = 'unit-site-b';
		$this->assertNotSame( $runtime, Runtime_Cache::directory( 'runtime' ) );
	}

	public function test_filesystem_root_override_falls_back_to_the_configured_boundary(): void {
		$unsafe = static fn(): string => '/';
		add_filter( 'blockstudio/cache/dir', $unsafe, 20 );

		$this->assertSame( wp_normalize_path( WP_CONTENT_DIR . '/blockstudio/cache' ), Runtime_Cache::root() );

		remove_filter( 'blockstudio/cache/dir', $unsafe, 20 );
	}

	public function test_keys_are_order_independent_and_dependency_sensitive(): void {
		$first = Runtime_Cache::key(
			'render',
			array(
				'b' => 2,
				'a' => 1,
			),
			array( 'pages', 'blocks' ),
			array( 'page' => 'alpha' )
		);
		$same  = Runtime_Cache::key(
			'render',
			array(
				'a' => 1,
				'b' => 2,
			),
			array( 'blocks', 'pages' ),
			array( 'page' => 'alpha' )
		);
		$other = Runtime_Cache::key(
			'render',
			array(
				'a' => 1,
				'b' => 2,
			),
			array( 'blocks', 'pages' ),
			array( 'page' => 'beta' )
		);

		$this->assertSame( $first, $same );
		$this->assertNotSame( $first, $other );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $first );
	}

	public function test_legacy_build_lock_cleanup_is_batched_site_scoped_and_preserves_live_locks(): void {
		$old = array();
		foreach ( array( 'namespace-a', 'namespace-b' ) as $namespace ) {
			$directory = $this->root . '/sites/' . $this->site_key . '/' . $namespace . '/runtime';
			wp_mkdir_p( $directory . '/locks' );
			for ( $i = 0; $i < 3; ++$i ) {
				$file = $directory . '/' . md5( $namespace . $i ) . '.build.lock';
				file_put_contents( $file, 'old' );
				touch( $file, time() - 2 * HOUR_IN_SECONDS );
				$old[] = $file;
			}
		}
		$fresh = $directory . '/' . md5( 'fresh' ) . '.build.lock';
		file_put_contents( $fresh, 'fresh' );
		$held_path = $directory . '/' . md5( 'held' ) . '.build.lock';
		$held      = Single_Flight::acquire( $held_path );
		touch( $held_path, time() - 2 * HOUR_IN_SECONDS );
		$fixed = $directory . '/locks/build-fixed.lock';
		file_put_contents( $fixed, 'fixed' );
		touch( $fixed, time() - 2 * HOUR_IN_SECONDS );
		$payload = $directory . '/payload.php';
		file_put_contents( $payload, '<?php return array();' );
		$other = $this->root . '/sites/another-site/namespace/runtime/' . md5( 'other' ) . '.build.lock';
		wp_mkdir_p( dirname( $other ) );
		file_put_contents( $other, 'other' );
		touch( $other, time() - 2 * HOUR_IN_SECONDS );
		$deletions = 0;
		$media = static function ( $file ) use ( &$deletions ) {
			++$deletions;
			return $file;
		};
		$limit = static fn(): int => 2;
		add_filter( 'wp_delete_file', $media );
		add_filter( 'blockstudio/cache/legacy_cleanup_batch_size', $limit );
		try {
			clearstatcache();
			$this->assertSame( 2, Runtime_Cache::cleanup_legacy_build_locks_batch() );
			$this->assertSame( 2, Runtime_Cache::cleanup_legacy_build_locks_batch() );
			$this->assertSame( 2, Runtime_Cache::cleanup_legacy_build_locks_batch() );
			$this->assertSame( 0, Runtime_Cache::cleanup_legacy_build_locks_batch() );
			foreach ( $old as $file ) {
				$this->assertFileDoesNotExist( $file );
			}
			foreach ( array( $fresh, $held_path, $fixed, $payload, $other ) as $file ) {
				$this->assertFileExists( $file );
			}
			$this->assertSame( 0, $deletions );
			$this->assertNotFalse( wp_next_scheduled( 'blockstudio/cache/cleanup_legacy_build_locks' ) );
			Single_Flight::release( $held );
			$held = null;
			$this->assertSame( 1, Runtime_Cache::cleanup_legacy_build_locks_batch() );
			$this->assertFileDoesNotExist( $held_path );
		} finally {
			if ( is_resource( $held ) ) {
				Single_Flight::release( $held );
			}
			remove_filter( 'wp_delete_file', $media );
			remove_filter( 'blockstudio/cache/legacy_cleanup_batch_size', $limit );
		}
	}

	// Namespace collection only ran from the static-prerender write path, so
	// on every other site an abandoned namespace lived forever.
	public function test_quiescent_lock_cleanup_collects_stale_namespaces_and_keeps_a_daily_tick(): void {
		$site    = $this->root . '/sites/' . $this->site_key;
		$current = Runtime_Cache::directory( 'runtime' );
		$stale   = $site . '/stale-namespace';
		$fresh   = $site . '/fresh-namespace';
		wp_mkdir_p( $current );
		file_put_contents( $current . '/payload.php', '<?php return array();' );
		foreach ( array( $stale, $fresh ) as $namespace ) {
			wp_mkdir_p( $namespace . '/runtime' );
			file_put_contents( $namespace . '/runtime/payload.php', '<?php return array();' );
		}
		foreach ( array( $stale . '/runtime/payload.php', $stale . '/runtime', $stale ) as $path ) {
			touch( $path, time() - 2 * DAY_IN_SECONDS );
		}
		clearstatcache();

		$this->assertSame( 0, Runtime_Cache::cleanup_legacy_build_locks_batch() );

		$this->assertDirectoryDoesNotExist( $stale );
		$this->assertFileExists( $fresh . '/runtime/payload.php' );
		$this->assertFileExists( $current . '/payload.php' );
		$this->assertFileExists( $site . '/.namespaces-collected' );
		$next = wp_next_scheduled( 'blockstudio/cache/cleanup_legacy_build_locks' );
		$this->assertNotFalse( $next );
		$this->assertGreaterThanOrEqual( time() + DAY_IN_SECONDS - MINUTE_IN_SECONDS, $next );
	}

	// With cron not firing, the batch stays overdue forever and nothing else
	// would ever remove a lock, so the request path makes bounded progress.
	public function test_overdue_cron_batch_triggers_a_bounded_inline_sweep_once_an_hour(): void {
		$before    = get_option( 'blockstudio_build_lock_cleanup_version', '' );
		$directory = $this->root . '/sites/' . $this->site_key . '/namespace/runtime';
		$old       = static function ( string $name ) use ( $directory ): string {
			$file = $directory . '/' . md5( $name ) . '.build.lock';
			file_put_contents( $file, 'old' );
			touch( $file, time() - 2 * HOUR_IN_SECONDS );
			return $file;
		};
		wp_mkdir_p( $directory );
		$locks = array( $old( 'a' ), $old( 'b' ), $old( 'c' ) );
		update_option( 'blockstudio_build_lock_cleanup_version', BLOCKSTUDIO_VERSION, false );
		delete_transient( 'blockstudio_legacy_lock_sweep' );
		wp_schedule_single_event( time() - 20 * MINUTE_IN_SECONDS, 'blockstudio/cache/cleanup_legacy_build_locks' );
		try {
			clearstatcache();
			Runtime_Cache::init();
			foreach ( $locks as $file ) {
				$this->assertFileDoesNotExist( $file );
			}
			$this->assertNotFalse( get_transient( 'blockstudio_legacy_lock_sweep' ) );

			$later = $old( 'd' );
			clearstatcache();
			Runtime_Cache::init();
			$this->assertFileExists( $later, 'A second request within the hour must not sweep again.' );
		} finally {
			update_option( 'blockstudio_build_lock_cleanup_version', $before, false );
		}
	}

	public function test_a_batch_with_remaining_work_reschedules_within_a_minute(): void {
		$directory = $this->root . '/sites/' . $this->site_key . '/namespace/runtime';
		wp_mkdir_p( $directory );
		for ( $i = 0; $i < 3; ++$i ) {
			$file = $directory . '/' . md5( 'lock' . $i ) . '.build.lock';
			file_put_contents( $file, 'old' );
			touch( $file, time() - 2 * HOUR_IN_SECONDS );
		}
		$limit = static fn(): int => 2;
		add_filter( 'blockstudio/cache/legacy_cleanup_batch_size', $limit );
		try {
			clearstatcache();
			$this->assertSame( 2, Runtime_Cache::cleanup_legacy_build_locks_batch() );
			$next = wp_next_scheduled( 'blockstudio/cache/cleanup_legacy_build_locks' );
			$this->assertNotFalse( $next );
			$this->assertLessThanOrEqual( time() + MINUTE_IN_SECONDS, $next );
		} finally {
			remove_filter( 'blockstudio/cache/legacy_cleanup_batch_size', $limit );
		}
	}

	public function test_legacy_lock_cleanup_is_scheduled_once_per_version_and_disabled_cache_does_nothing(): void {
		$before = get_option( 'blockstudio_build_lock_cleanup_version', '' );
		delete_option( 'blockstudio_build_lock_cleanup_version' );
		wp_clear_scheduled_hook( 'blockstudio/cache/cleanup_legacy_build_locks' );
		$disabled = static fn(): bool => false;
		try {
			add_filter( 'blockstudio/settings/cache/enabled', $disabled );
			Runtime_Cache::init();
			$this->assertSame( '', get_option( 'blockstudio_build_lock_cleanup_version', '' ) );
			$this->assertFalse( wp_next_scheduled( 'blockstudio/cache/cleanup_legacy_build_locks' ) );
			$this->assertSame( 0, Runtime_Cache::cleanup_legacy_build_locks_batch() );
			remove_filter( 'blockstudio/settings/cache/enabled', $disabled );
			Settings::reset();
			Runtime_Cache::init();
			$this->assertSame( BLOCKSTUDIO_VERSION, get_option( 'blockstudio_build_lock_cleanup_version' ) );
			$scheduled = wp_next_scheduled( 'blockstudio/cache/cleanup_legacy_build_locks' );
			$this->assertNotFalse( $scheduled );
			Runtime_Cache::init();
			$this->assertSame( $scheduled, wp_next_scheduled( 'blockstudio/cache/cleanup_legacy_build_locks' ) );
		} finally {
			remove_filter( 'blockstudio/settings/cache/enabled', $disabled );
			update_option( 'blockstudio_build_lock_cleanup_version', $before, false );
		}
	}

	public function test_write_read_diagnostics_and_purge_use_the_same_boundary(): void {
		$this->assertTrue( Runtime_Cache::write( 'render', 'card', '<article>Card</article>', 'html' ) );
		$this->assertSame( '<article>Card</article>', Runtime_Cache::read( 'render', 'card', 0, 'html' ) );
		$this->assertNull( Runtime_Cache::read( 'render', 'missing', 0, 'html' ) );

		$diagnostics = Runtime_Cache::diagnostics();
		$this->assertSame( 1, $diagnostics['render']['write'] ?? 0 );
		$this->assertSame( 1, $diagnostics['render']['hit'] ?? 0 );
		$this->assertSame( 1, $diagnostics['render']['miss-absent'] ?? 0 );

		$this->assertGreaterThanOrEqual( 1, Runtime_Cache::purge( 'render' ) );
		$this->assertFileDoesNotExist( Runtime_Cache::path( 'render', 'card', 'html' ) );
	}

	public function test_remember_builds_once_and_recovers_with_stale_last_good(): void {
		$builds = 0;
		$cold   = Runtime_Cache::remember(
			'fragments',
			'hero',
			static function () use ( &$builds ): string {
				++$builds;

				return '<section>Hero</section>';
			},
			10,
			'html'
		);
		$warm   = Runtime_Cache::remember(
			'fragments',
			'hero',
			static function () use ( &$builds ): string {
				++$builds;

				return '<section>Wrong</section>';
			},
			10,
			'html'
		);

		$this->assertSame( 'built', $cold['reason'] );
		$this->assertSame( 'fresh', $warm['reason'] );
		$this->assertSame( 1, $builds );
		$this->assertSame( '<section>Hero</section>', $warm['value'] );

		$path = Runtime_Cache::path( 'fragments', 'hero', 'html' );
		touch( $path, time() - 20 );
		$stale = Runtime_Cache::remember(
			'fragments',
			'hero',
			static fn(): ?string => null,
			10,
			'html'
		);

		$this->assertSame( 'stale', $stale['state'] );
		$this->assertSame( 'build-failure', $stale['reason'] );
		$this->assertSame( '<section>Hero</section>', $stale['value'] );
	}

	public function test_pruning_is_bounded_and_preserves_the_new_object(): void {
		$maximum = static fn(): int => 2;
		add_filter( 'blockstudio/cache/max_files_per_scope', $maximum );

		Runtime_Cache::write( 'bounded', 'one', 'one' );
		Runtime_Cache::write( 'bounded', 'two', 'two' );
		Runtime_Cache::write( 'bounded', 'three', 'three' );

		$files = glob( Runtime_Cache::directory( 'bounded' ) . '/*.cache' );
		$this->assertIsArray( $files );
		$this->assertLessThanOrEqual( 2, count( $files ) );
		$this->assertFileExists( Runtime_Cache::path( 'bounded', 'three' ) );

		remove_filter( 'blockstudio/cache/max_files_per_scope', $maximum );
	}

	public function test_legacy_runtime_is_quarantined_then_deleted_in_bounded_cron_batches(): void {
		$legacy = $this->root . '/runtime';
		wp_mkdir_p( $legacy . '/nested' );
		file_put_contents( $legacy . '/one.build.lock', '' );
		file_put_contents( $legacy . '/two.build.lock', '' );
		file_put_contents( $legacy . '/nested/three.build.lock', '' );

		$batch_size = static fn(): int => 1;
		add_filter( 'blockstudio/cache/legacy_cleanup_batch_size', $batch_size );

		$this->assertTrue( Runtime_Cache::stage_legacy_runtime_cleanup() );
		$this->assertDirectoryDoesNotExist( $legacy );
		$this->assertNotFalse( wp_next_scheduled( 'blockstudio/cache/cleanup_legacy_runtime' ) );

		$quarantines = glob( $this->root . '/.legacy-runtime-*', GLOB_ONLYDIR );
		$this->assertIsArray( $quarantines );
		$this->assertCount( 1, $quarantines );

		for ( $pass = 0; $pass < 10 && is_dir( $quarantines[0] ); ++$pass ) {
			wp_clear_scheduled_hook( 'blockstudio/cache/cleanup_legacy_runtime' );
			Runtime_Cache::cleanup_legacy_runtime_batch();
		}

		$this->assertDirectoryDoesNotExist( $quarantines[0] );
		$this->assertDirectoryDoesNotExist( $legacy );

		remove_filter( 'blockstudio/cache/legacy_cleanup_batch_size', $batch_size );
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $directory );
	}
}

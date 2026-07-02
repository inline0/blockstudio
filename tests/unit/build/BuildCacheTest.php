<?php
/**
 * Build cache tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Block_Registry;
use Blockstudio\Build;
use Blockstudio\Build_Cache;
use Blockstudio\Files;
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
		$this->cache_files[] = Build_Cache::get_cache_dir( $scope ) . '/' . sanitize_file_name( $key ) . '.php';
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
		);
	}

	/**
	 * Cache directory uses the WordPress uploads directory.
	 *
	 * @return void
	 */
	public function test_cache_directory_uses_wordpress_uploads(): void {
		$uploads = wp_upload_dir();

		$this->assertStringStartsWith(
			rtrim( wp_normalize_path( $uploads['basedir'] ), '/' ),
			Build_Cache::get_cache_dir()
		);
		$this->assertStringEndsWith( '/blockstudio/cache', Build_Cache::get_cache_dir() );
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
		add_filter( 'blockstudio/cache/max_files_per_scope', $filter );

		try {
			Build_Cache::write( $scope, 'one', array( 'watch' => array() ) );
			Build_Cache::write( $scope, 'two', array( 'watch' => array() ) );
			Build_Cache::write( $scope, 'three', array( 'watch' => array() ) );

			$files = glob( $dir . '/*.php' );
			$this->assertIsArray( $files );
			$this->assertLessThanOrEqual( 2, count( $files ) );
			$this->assertFileExists( Build_Cache::get_cache_dir( $scope ) . '/three.php' );
		} finally {
			remove_filter( 'blockstudio/cache/max_files_per_scope', $filter );
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
	 * Runtime cache keys change when database-backed populate sources change.
	 *
	 * @return void
	 */
	public function test_runtime_cache_key_tracks_populate_source_changes(): void {
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
			$this->assertNotSame(
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
		$style      = $block_directory . '/style.css';

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

			$registry->reset();

			Build::init( array( 'dir' => $path ) );
			$warm = $this->snapshot_runtime_registry( $block_name );

			$this->assertSame( $cold, $warm );
		} finally {
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

<?php
/**
 * Static prerender content hasher.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Deterministic content hashing for prerender identities and dependency graphs.
 *
 * @since 7.6.0
 */
final class Static_Prerender_Content_Hasher {

	/**
	 * Request-local file hash memo.
	 *
	 * @var array<string,string>
	 */
	private static array $file_hashes = array();

	/**
	 * Number of physical file reads.
	 *
	 * @var int
	 */
	private static int $file_reads = 0;

	/**
	 * Number of recursive directory scans.
	 *
	 * @var int
	 */
	private static int $directory_scans = 0;

	/**
	 * Request-local bundled engine asset source hashes.
	 *
	 * @var array<string,string>|null
	 */
	private static ?array $engine_assets = null;

	/**
	 * Request-local shared snapshots by theme root.
	 *
	 * @var array<string,array{hash:string,hashes:array<string,string>,paths:array<string,string>}>
	 */
	private static array $shared_snapshots = array();

	/**
	 * Hash one file once per request.
	 *
	 * @param string $path File path.
	 *
	 * @return string Content hash or a stable failure sentinel.
	 */
	public static function file_hash( string $path ): string {
		$path = wp_normalize_path( $path );

		if ( isset( self::$file_hashes[ $path ] ) ) {
			return self::$file_hashes[ $path ];
		}

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			self::$file_hashes[ $path ] = 'missing';

			return self::$file_hashes[ $path ];
		}

		++self::$file_reads;
		$hash                       = hash_file( 'xxh128', $path );
		self::$file_hashes[ $path ] = is_string( $hash ) ? $hash : 'unreadable';

		return self::$file_hashes[ $path ];
	}

	/**
	 * Snapshot explicit dependency paths.
	 *
	 * @param string[]    $paths      Dependency paths.
	 * @param string|null $theme_root Optional theme root.
	 *
	 * @return array{hash:string,hashes:array<string,string>,paths:array<string,string>}
	 */
	public static function snapshot( array $paths, ?string $theme_root = null ): array {
		$hashes      = array();
		$local_paths = array();

		foreach ( self::normalize_paths( $paths ) as $path ) {
			$id                 = self::dependency_id( $path, $theme_root );
			$hashes[ $id ]      = self::file_hash( $path );
			$local_paths[ $id ] = $path;
		}

		ksort( $hashes, SORT_STRING );
		ksort( $local_paths, SORT_STRING );

		return array(
			'hash'   => self::hash_map( $hashes ),
			'hashes' => $hashes,
			'paths'  => $local_paths,
		);
	}

	/**
	 * Snapshot files that affect every rendered page.
	 *
	 * @param string|null $theme_root Theme root.
	 *
	 * @return array{hash:string,hashes:array<string,string>,paths:array<string,string>}
	 */
	public static function shared_snapshot( ?string $theme_root ): array {
		$memo = (string) $theme_root;
		if ( isset( self::$shared_snapshots[ $memo ] ) ) {
			return self::$shared_snapshots[ $memo ];
		}

		$paths            = null !== $theme_root && is_dir( $theme_root )
			? self::shared_paths( $theme_root )
			: array();
		$snapshot         = self::snapshot( $paths, $theme_root );
		$identity         = Runtime_Context::identity(
			'static-prerender-shared',
			array( 'blocks', 'pages', 'patterns', 'templates' ),
			array(
				'engineAssets' => self::engine_asset_inventory(),
				'files'        => $snapshot['hashes'],
			)
		);
		$snapshot['hash'] = hash( 'xxh128', self::encode( $identity ) );

		self::$shared_snapshots[ $memo ] = $snapshot;

		return $snapshot;
	}

	/**
	 * Snapshot all known theme sources for an explicit artifact build.
	 *
	 * @param string|null $theme_root Theme root.
	 * @param string      $config_hash Effective runtime configuration hash.
	 *
	 * @return array{hash:string,hashes:array<string,string>,paths:array<string,string>}
	 */
	public static function theme_snapshot( ?string $theme_root, string $config_hash ): array {
		$paths            = null !== $theme_root && is_dir( $theme_root )
			? self::theme_paths( $theme_root )
			: array();
		$snapshot         = self::snapshot( $paths, $theme_root );
		$snapshot['hash'] = hash( 'xxh128', self::encode( array( $config_hash, $snapshot['hashes'] ) ) );

		return $snapshot;
	}

	/**
	 * Hash a dependency map with stable keys.
	 *
	 * @param array<string,string> $hashes Dependency hashes.
	 *
	 * @return string Hash.
	 */
	public static function hash_map( array $hashes ): string {
		ksort( $hashes, SORT_STRING );

		return hash( 'xxh128', self::encode( $hashes ) );
	}

	/**
	 * Hash only dependency contents, independent of machine-local paths.
	 *
	 * @param array<string,string> $hashes Dependency hashes.
	 *
	 * @return string Hash.
	 */
	public static function content_hash( array $hashes ): string {
		$contents = array_values( $hashes );
		sort( $contents, SORT_STRING );

		return hash( 'xxh128', self::encode( $contents ) );
	}

	/**
	 * Reset request-local hash state.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$file_hashes      = array();
		self::$file_reads       = 0;
		self::$directory_scans  = 0;
		self::$engine_assets    = null;
		self::$shared_snapshots = array();
	}

	/**
	 * Return hashing instrumentation.
	 *
	 * @return array{fileReads:int,directoryScans:int}
	 */
	public static function diagnostics(): array {
		return array(
			'fileReads'      => self::$file_reads,
			'directoryScans' => self::$directory_scans,
		);
	}

	/**
	 * Resolve the local path a portable dependency ID names, as dependency_id() built it.
	 *
	 * @param string      $id         Dependency ID.
	 * @param string|null $theme_root Theme root.
	 *
	 * @return string|null Path, or null for IDs that do not name a root.
	 */
	public static function path_for_id( string $id, ?string $theme_root ): ?string {
		$roots = array();
		if ( null !== $theme_root && '' !== trim( $theme_root ) ) {
			$roots['theme'] = wp_normalize_path( $theme_root );
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$roots['plugins'] = wp_normalize_path( (string) WP_PLUGIN_DIR );
		}
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$roots['mu-plugins'] = wp_normalize_path( (string) WPMU_PLUGIN_DIR );
		}
		if ( defined( 'ABSPATH' ) ) {
			$roots['wordpress'] = wp_normalize_path( (string) ABSPATH );
		}
		$slash  = strpos( $id, '/' );
		$prefix = false === $slash ? '' : substr( $id, 0, $slash );
		if ( ! isset( $roots[ $prefix ] ) ) {
			return null;
		}
		$rest = (string) substr( $id, $slash + 1 );
		$root = rtrim( $roots[ $prefix ], '/' );

		return '' === $rest ? $root : $root . '/' . $rest;
	}

	/**
	 * Build a portable dependency ID.
	 *
	 * @param string      $path       File path.
	 * @param string|null $theme_root Theme root.
	 *
	 * @return string Dependency ID.
	 */
	private static function dependency_id( string $path, ?string $theme_root ): string {
		$path  = wp_normalize_path( $path );
		$roots = array();

		if ( null !== $theme_root && '' !== trim( $theme_root ) ) {
			$roots['theme'] = wp_normalize_path( $theme_root );
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$roots['plugins'] = wp_normalize_path( (string) WP_PLUGIN_DIR );
		}
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$roots['mu-plugins'] = wp_normalize_path( (string) WPMU_PLUGIN_DIR );
		}
		if ( defined( 'ABSPATH' ) ) {
			$roots['wordpress'] = wp_normalize_path( (string) ABSPATH );
		}

		foreach ( $roots as $prefix => $root ) {
			$root = rtrim( $root, '/' );
			if ( $path === $root || str_starts_with( $path, $root . '/' ) ) {
				return $prefix . '/' . ltrim( substr( $path, strlen( $root ) ), '/' );
			}
		}

		return 'external/' . basename( $path );
	}

	/**
	 * List files that affect every page.
	 *
	 * @param string $theme_root Theme root.
	 *
	 * @return string[] Paths.
	 */
	private static function shared_paths( string $theme_root ): array {
		$theme_root = rtrim( wp_normalize_path( $theme_root ), '/' );
		$paths      = array(
			$theme_root . '/blockstudio.json',
			$theme_root . '/theme.json',
			$theme_root . '/style.css',
			$theme_root . '/functions.php',
			$theme_root . '/header.php',
			$theme_root . '/footer.php',
		);

		foreach ( array( 'inc', 'parts', 'templates' ) as $directory ) {
			$paths = array_merge( $paths, self::directory_files( $theme_root . '/' . $directory ) );
		}

		return self::normalize_paths( $paths );
	}

	/**
	 * List all known theme runtime sources.
	 *
	 * @param string $theme_root Theme root.
	 *
	 * @return string[] Paths.
	 */
	private static function theme_paths( string $theme_root ): array {
		$theme_root = rtrim( wp_normalize_path( $theme_root ), '/' );
		$paths      = self::shared_paths( $theme_root );

		foreach ( array( 'assets', 'blocks', 'pages', 'parts', 'patterns', 'templates' ) as $directory ) {
			$paths = array_merge( $paths, self::directory_files( $theme_root . '/' . $directory ) );
		}

		return self::normalize_paths( $paths );
	}

	/**
	 * Hash the bundled engine asset sources that rendered pages embed.
	 *
	 * The engine compiles these sources into _dist directories beside them
	 * the first time bundled UI blocks build, so the identity is taken from
	 * the sources: a signature taken before that first build has to equal
	 * one taken after it, or a build's own render moves the signature that
	 * its artifact entry was derived from.
	 *
	 * @return array<string,string> Content hashes keyed by path below includes/ui/blocks.
	 */
	private static function engine_asset_inventory(): array {
		if ( null !== self::$engine_assets ) {
			return self::$engine_assets;
		}

		self::$engine_assets = array();
		if ( ! defined( 'BLOCKSTUDIO_DIR' ) || ! is_string( BLOCKSTUDIO_DIR ) || '' === trim( BLOCKSTUDIO_DIR ) ) {
			return self::$engine_assets;
		}

		$root = rtrim( wp_normalize_path( BLOCKSTUDIO_DIR ), '/' ) . '/includes/ui/blocks';
		foreach ( self::directory_files( $root ) as $path ) {
			$relative = substr( wp_normalize_path( $path ), strlen( $root ) + 1 );
			if (
				str_contains( '/' . $relative, '/_dist/' ) ||
				str_contains( '/' . $relative, '/node_modules/' ) ||
				1 !== preg_match( '/\.(css|scss|js)$/', $relative )
			) {
				continue;
			}
			self::$engine_assets[ $relative ] = self::file_hash( $path );
		}
		ksort( self::$engine_assets, SORT_STRING );

		return self::$engine_assets;
	}

	/**
	 * Recursively list regular files.
	 *
	 * @param string $root Directory.
	 *
	 * @return string[] Paths.
	 */
	private static function directory_files( string $root ): array {
		if ( ! is_dir( $root ) ) {
			return array();
		}

		++self::$directory_scans;
		$paths    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $item ) {
			if ( $item instanceof \SplFileInfo && $item->isFile() ) {
				$paths[] = $item->getPathname();
			}
		}

		return $paths;
	}

	/**
	 * Normalize and sort path lists.
	 *
	 * @param string[] $paths Paths.
	 *
	 * @return string[] Paths.
	 */
	private static function normalize_paths( array $paths ): array {
		$normalized = array();

		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || '' === trim( $path ) ) {
				continue;
			}
			$path                = wp_normalize_path( $path );
			$normalized[ $path ] = true;
		}

		$paths = array_keys( $normalized );
		sort( $paths, SORT_STRING );

		return $paths;
	}

	/**
	 * Encode stable hash input.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string JSON.
	 */
	private static function encode( mixed $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $encoded ) ? $encoded : '';
	}
}

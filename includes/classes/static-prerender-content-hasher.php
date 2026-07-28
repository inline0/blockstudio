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
		self::$file_hashes     = array();
		self::$file_reads      = 0;
		self::$directory_scans = 0;
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
	 * Inventory generated engine assets whose names contain content hashes.
	 *
	 * @return string[] Asset names.
	 */
	private static function engine_asset_inventory(): array {
		if ( ! defined( 'BLOCKSTUDIO_DIR' ) || ! is_string( BLOCKSTUDIO_DIR ) || '' === trim( BLOCKSTUDIO_DIR ) ) {
			return array();
		}

		$names  = array();
		$assets = glob( rtrim( wp_normalize_path( BLOCKSTUDIO_DIR ), '/' ) . '/includes/ui/blocks/_dist/*' );
		foreach ( is_array( $assets ) ? $assets : array() as $asset ) {
			$names[] = basename( $asset );
		}
		sort( $names, SORT_STRING );

		return $names;
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

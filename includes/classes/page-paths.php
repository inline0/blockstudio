<?php
/**
 * Portable source locations for synced pages.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Translate persisted page locations without request-time discovery or writes.
 */
final class Page_Paths {

	/**
	 * Real paths of configured theme roots, indexed by configured root.
	 *
	 * @var array<string, string>
	 */
	private static array $real_roots = array();

	/**
	 * Store theme files independently of the current installation root.
	 *
	 * @param string $path Source path or logical page identity.
	 * @return string Portable path, or the original external/logical identity.
	 */
	public static function encode( string $path ): string {
		$path = wp_normalize_path( $path );
		foreach ( self::locations() as $location ) {
			if ( self::within( $path, $location['root'] ) ) {
				return $location['prefix'] . ltrim( substr( $path, strlen( $location['root'] ) ), '/' );
			}
		}
		return $path;
	}

	/**
	 * Resolve a stored source before any filesystem operation.
	 *
	 * Legacy absolute theme paths are rebased lexically, never probed first.
	 * External paths remain supported when allowed by the current PHP host.
	 *
	 * @param string $path Stored source path or logical page identity.
	 * @return string Current path, or an empty string for unsafe locations.
	 */
	public static function resolve( string $path ): string {
		$path = wp_normalize_path( $path );
		if ( str_contains( $path, "\0" ) || preg_match( '#(?:^|[:/])(?:\.|\.\.)(?:/|$)#', $path ) ) {
			return '';
		}
		$roots = self::roots();
		foreach ( $roots as $prefix => $root ) {
			if ( str_starts_with( $path, $prefix ) ) {
				$resolved = $root . ( '' !== substr( $path, strlen( $prefix ) ) ? '/' . ltrim( substr( $path, strlen( $prefix ) ), '/' ) : '' );
				return self::allowed( $resolved ) ? $resolved : '';
			}
		}
		foreach ( self::locations() as $location ) {
			if ( self::within( $path, $location['root'] ) ) {
				$resolved = $location['configured'] . substr( $path, strlen( $location['root'] ) );
				return self::allowed( $resolved ) ? $resolved : '';
			}
		}

		if ( str_starts_with( $path, '/' ) || preg_match( '#^[a-z]:/#i', $path ) ) {
			foreach ( $roots as $root ) {
				$marker   = '/' . basename( dirname( $root ) ) . '/' . basename( $root ) . '/';
				$position = strpos( $path, $marker );
				if ( false === $position ) {
					$marker   = '/' . basename( $root ) . '/';
					$position = strpos( $path, $marker );
				}
				if ( false !== $position || str_ends_with( $path, '/' . basename( $root ) ) ) {
					$relative = false !== $position ? substr( $path, $position + strlen( $marker ) ) : '';
					$resolved = $root . ( '' !== $relative ? '/' . $relative : '' );
					return self::allowed( $resolved ) ? $resolved : '';
				}
			}
			return self::allowed( $path ) ? $path : '';
		}

		// A source identity such as docs/index.md is not a filesystem URL.
		return preg_match( '#^[a-z][a-z0-9+.-]*:#i', $path ) ? '' : $path;
	}

	/**
	 * Read and resolve one synced page location.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string Current source location.
	 */
	public static function from_meta( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );
		return is_string( $value ) ? self::resolve( $value ) : '';
	}

	/**
	 * Get distinct child and parent theme locations without scanning files.
	 *
	 * @return array<string, string> Prefixes mapped to theme roots.
	 */
	private static function roots(): array {
		return array(
			'theme:'        => untrailingslashit( wp_normalize_path( get_stylesheet_directory() ) ),
			'parent-theme:' => untrailingslashit( wp_normalize_path( get_template_directory() ) ),
		);
	}

	/**
	 * Get every directory that stands for a theme root.
	 *
	 * A theme directory can be a symlink into a checkout. Discovery reads some
	 * sources through realpath(), so the target of the symlink maps to the same
	 * prefix and resolves back to the configured root.
	 *
	 * @return array<int, array{prefix:string,root:string,configured:string}> Theme locations in match order.
	 */
	private static function locations(): array {
		$locations = array();
		foreach ( self::roots() as $prefix => $root ) {
			$locations[] = array(
				'prefix'     => $prefix,
				'root'       => $root,
				'configured' => $root,
			);
		}
		foreach ( self::roots() as $prefix => $root ) {
			$real = self::real_root( $root );
			if ( '' !== $real && $real !== $root ) {
				$locations[] = array(
					'prefix'     => $prefix,
					'root'       => $real,
					'configured' => $root,
				);
			}
		}
		return $locations;
	}

	/**
	 * Resolve the symlink target of a theme root once per request.
	 *
	 * @param string $root Configured theme root.
	 * @return string Real root, or an empty string when it cannot be read.
	 */
	private static function real_root( string $root ): string {
		if ( ! array_key_exists( $root, self::$real_roots ) ) {
			$real                      = '' !== $root && self::allowed( $root ) ? realpath( $root ) : false;
			self::$real_roots[ $root ] = false !== $real ? untrailingslashit( wp_normalize_path( $real ) ) : '';
		}
		return self::$real_roots[ $root ];
	}

	/**
	 * Forget the resolved symlink targets of theme roots.
	 *
	 * @since 7.7.11
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$real_roots = array();
	}

	/**
	 * Check a path boundary without touching the filesystem.
	 *
	 * @param string $path Path to check.
	 * @param string $root Root directory.
	 * @return bool Whether the path is inside this root.
	 */
	private static function within( string $path, string $root ): bool {
		return $path === $root || str_starts_with( $path, $root . '/' );
	}

	/**
	 * Avoid open_basedir warnings before a caller probes a stored path.
	 *
	 * @param string $path Absolute source path.
	 * @return bool Whether PHP allows this location.
	 */
	private static function allowed( string $path ): bool {
		$restriction = (string) ini_get( 'open_basedir' );
		if ( '' === $restriction ) {
			return true;
		}
		foreach ( explode( PATH_SEPARATOR, $restriction ) as $root ) {
			if ( '' === $root ) {
				continue;
			}
			$root = '.' === $root ? (string) getcwd() : $root;
			if ( self::within( $path, untrailingslashit( wp_normalize_path( $root ) ) ) ) {
				return true;
			}
		}
		return false;
	}
}

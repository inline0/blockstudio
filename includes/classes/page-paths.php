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
		$install = self::install_root();
		if ( '' !== $install && self::within( $path, $install ) ) {
			return self::INSTALL_PREFIX . ltrim( substr( $path, strlen( $install ) ), '/' );
		}
		return $path;
	}

	/**
	 * Prefix of portable paths inside the WordPress installation but outside the themes.
	 */
	public const INSTALL_PREFIX = 'wordpress:';

	/**
	 * Return the normalized WordPress installation root.
	 *
	 * @return string Root without a trailing slash, or an empty string.
	 */
	private static function install_root(): string {
		return defined( 'ABSPATH' ) ? untrailingslashit( wp_normalize_path( (string) ABSPATH ) ) : '';
	}

	/**
	 * Whether a stored location is portable.
	 *
	 * @param string $path Stored location.
	 * @return bool Whether it names a theme or installation root.
	 */
	public static function is_portable( string $path ): bool {
		return str_starts_with( $path, 'theme:' ) || str_starts_with( $path, 'parent-theme:' ) || str_starts_with( $path, self::INSTALL_PREFIX );
	}

	/**
	 * Resolve a stored source before any filesystem operation.
	 *
	 * Known legacy theme locations are rebased without probing the old host.
	 * When a path inside this installation only resembles a theme location,
	 * an existing source takes precedence over the legacy name match.
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
		$install = self::install_root();
		if ( '' !== $install && str_starts_with( $path, self::INSTALL_PREFIX ) ) {
			$relative = ltrim( substr( $path, strlen( self::INSTALL_PREFIX ) ), '/' );
			$resolved = $install . ( '' !== $relative ? '/' . $relative : '' );
			return self::allowed( $resolved ) ? $resolved : '';
		}
		foreach ( self::locations() as $location ) {
			if ( self::within( $path, $location['root'] ) ) {
				$resolved = $location['configured'] . substr( $path, strlen( $location['root'] ) );
				return self::allowed( $resolved ) ? $resolved : '';
			}
		}
		if ( str_starts_with( $path, '/' ) || preg_match( '#^[a-z]:/#i', $path ) ) {
			$inside_install = '' !== $install && self::within( $path, $install );
			if ( $inside_install && defined( 'WP_CONTENT_DIR' ) ) {
				$default_themes = untrailingslashit( wp_normalize_path( (string) WP_CONTENT_DIR ) ) . '/themes';
				foreach ( $roots as $root ) {
					$legacy_root = $default_themes . '/' . basename( $root );
					if ( self::within( $path, $legacy_root ) ) {
						$resolved = $root . substr( $path, strlen( $legacy_root ) );
						return self::allowed( $resolved ) ? $resolved : '';
					}
				}
			}

			// The installation directory can itself have the same name as the theme.
			$legacy_path = $inside_install ? substr( $path, strlen( $install ) ) : $path;
			foreach ( $roots as $root ) {
				$marker   = '/' . basename( dirname( $root ) ) . '/' . basename( $root ) . '/';
				$position = strpos( $legacy_path, $marker );
				if ( false === $position ) {
					$marker   = '/' . basename( $root ) . '/';
					$position = strpos( $legacy_path, $marker );
				}
				if ( false !== $position || str_ends_with( $legacy_path, '/' . basename( $root ) ) ) {
					if ( $inside_install && self::allowed( $path ) && @file_exists( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An otherwise allowed path can contain a symlink outside open_basedir.
						return $path;
					}
					$relative = false !== $position ? substr( $legacy_path, $position + strlen( $marker ) ) : '';
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

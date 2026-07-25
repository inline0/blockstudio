<?php
/**
 * Static prerender identity.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Cheap file-backed identity for static-prerender cache keys.
 *
 * Explicit graph builds calculate full content hashes. Frontend requests read
 * this activated identity and therefore never walk the theme on a cache hit.
 *
 * @since 7.6.0
 */
final class Static_Prerender_Identity {

	/**
	 * Identity document format.
	 *
	 * @var string
	 */
	private const FORMAT = 'blockstudio-static-prerender-identity-v1';

	/**
	 * Request-local identity.
	 *
	 * @var string|null
	 */
	private static ?string $identity = null;

	/**
	 * Get the currently activated identity.
	 *
	 * @return string 32-character hexadecimal identity.
	 */
	public static function current(): string {
		if ( null !== self::$identity ) {
			return self::$identity;
		}

		$stored = self::read( self::identity_path() );
		if ( null !== $stored ) {
			self::$identity = $stored;

			return self::$identity;
		}

		self::$identity = self::bootstrap_identity();
		self::write( self::$identity, 'bootstrap' );

		return self::$identity;
	}

	/**
	 * Activate an identity produced by an explicit artifact build.
	 *
	 * @param string $identity Identity.
	 *
	 * @return bool Whether activation succeeded.
	 */
	public static function activate( string $identity ): bool {
		$identity = strtolower( trim( $identity ) );

		if ( ! self::valid( $identity ) || ! self::write( $identity, 'artifact' ) ) {
			return false;
		}

		self::$identity = $identity;

		return true;
	}

	/**
	 * Rotate the runtime identity after a site-level invalidation.
	 *
	 * @param string $reason Rotation reason.
	 *
	 * @return string Active identity.
	 */
	public static function rotate( string $reason ): string {
		$current = self::current();
		$entropy = function_exists( 'wp_generate_uuid4' )
			? wp_generate_uuid4()
			: bin2hex( random_bytes( 16 ) );
		$next    = hash( 'xxh128', $current . '|' . $reason . '|' . $entropy . '|' . microtime( true ) );

		if ( self::write( $next, $reason ) ) {
			self::$identity = $next;
		}

		return self::$identity ?? $current;
	}

	/**
	 * Reset request-local state.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$identity = null;
	}

	/**
	 * Build a cheap bootstrap identity without recursively scanning the theme.
	 *
	 * @return string Identity.
	 */
	private static function bootstrap_identity(): string {
		$theme_root = function_exists( 'get_stylesheet_directory' )
			? wp_normalize_path( (string) get_stylesheet_directory() )
			: '';
		$files      = array();

		if ( '' !== $theme_root && is_dir( $theme_root ) ) {
			foreach ( array( 'blockstudio.json', 'theme.json', 'style.css', 'functions.php' ) as $file ) {
				$path           = rtrim( $theme_root, '/' ) . '/' . $file;
				$files[ $file ] = is_file( $path )
					? array( (int) filemtime( $path ), (int) filesize( $path ) )
					: array( 0, 0 );
			}
		}

		return hash(
			'xxh128',
			(string) wp_json_encode(
				Runtime_Context::identity(
					'static-prerender',
					array( 'blocks', 'pages', 'patterns', 'templates' ),
					array( 'themeFiles' => $files )
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Resolve the identity document path.
	 *
	 * @return string Path.
	 */
	private static function identity_path(): string {
		return Runtime_Cache::directory( 'static-prerender-state' ) . '/runtime-identity.json';
	}

	/**
	 * Read a valid persisted identity.
	 *
	 * @param string $path Path.
	 *
	 * @return string|null Identity.
	 */
	private static function read( string $path ): ?string {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local runtime state.
		$decoded  = json_decode( (string) file_get_contents( $path ), true );
		$identity = is_array( $decoded ) && is_string( $decoded['identity'] ?? null )
			? strtolower( trim( $decoded['identity'] ) )
			: '';

		return self::valid( $identity ) ? $identity : null;
	}

	/**
	 * Persist an identity atomically.
	 *
	 * @param string $identity Identity.
	 * @param string $source   Activation source.
	 *
	 * @return bool Whether persistence succeeded.
	 */
	private static function write( string $identity, string $source ): bool {
		$encoded = wp_json_encode(
			array(
				'format'      => self::FORMAT,
				'identity'    => $identity,
				'source'      => $source,
				'activatedAt' => gmdate( DATE_ATOM ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return is_string( $encoded )
			&& Single_Flight::publish( self::identity_path(), $encoded . "\n" );
	}

	/**
	 * Validate an identity.
	 *
	 * @param string $identity Identity.
	 *
	 * @return bool Whether valid.
	 */
	private static function valid( string $identity ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $identity );
	}
}

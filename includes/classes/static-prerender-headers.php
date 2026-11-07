<?php
/**
 * Static prerender response headers.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Stores allowlisted response headers beside prerendered documents.
 *
 * A prerendered document is replayed without the request that rendered it,
 * so headers a theme or plugin sent with `header()`, such as `Link`, would
 * be lost on every hit. The headers named by
 * `performance.staticPrerender.replayHeaders` are captured when a document is
 * stored, written to a small JSON file beside it, and sent again by the PHP
 * cache and the early-serve drop-in. Headers in NEVER are neither stored nor
 * replayed, whatever the allowlist says.
 *
 * @since 7.7.13
 */
final class Static_Prerender_Headers {

	/**
	 * Suffix of the header file stored beside a `.html` document.
	 *
	 * @var string
	 */
	public const SUFFIX = '.headers.json';

	/**
	 * Lowercase header names that are never stored or replayed.
	 *
	 * Cookies, caching, framing, and transport headers belong to the response
	 * that serves the document, not to the render that produced it.
	 *
	 * @var string[]
	 */
	public const NEVER = array(
		'age',
		'cache-control',
		'connection',
		'content-encoding',
		'content-length',
		'content-type',
		'date',
		'etag',
		'expires',
		'keep-alive',
		'last-modified',
		'location',
		'pragma',
		'proxy-authenticate',
		'set-cookie',
		'set-cookie2',
		'status',
		'te',
		'trailer',
		'transfer-encoding',
		'upgrade',
		'vary',
		'www-authenticate',
		'x-blockstudio-static-prerender',
	);

	/**
	 * Stored file format.
	 *
	 * @var int
	 */
	private const FORMAT = 1;

	/**
	 * Header names the current configuration stores and replays.
	 *
	 * @return string[] Lowercase header names.
	 */
	public static function allowed(): array {
		return self::names(
			Runtime_Settings::current()->value( 'staticPrerender/replayHeaders', array( 'Link' ) )
		);
	}

	/**
	 * Normalize a list of header names into an allowlist.
	 *
	 * Invalid names and names in NEVER are dropped.
	 *
	 * @param mixed $names Header names.
	 *
	 * @return string[] Unique lowercase header names.
	 */
	public static function names( mixed $names ): array {
		$allowed = array();

		foreach ( is_array( $names ) ? $names : array() as $name ) {
			if ( ! is_string( $name ) || ! self::is_name( trim( $name ) ) ) {
				continue;
			}

			$name = strtolower( trim( $name ) );
			if ( ! in_array( $name, self::NEVER, true ) ) {
				$allowed[ $name ] = true;
			}
		}

		return array_keys( $allowed );
	}

	/**
	 * Whether a string is a valid header field name.
	 *
	 * @param string $name Candidate name.
	 *
	 * @return bool Whether valid.
	 */
	public static function is_name( string $name ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name );
	}

	/**
	 * Select the allowlisted headers of one response.
	 *
	 * Accepts the shapes WordPress produces: a list of `Name: value` lines
	 * from `headers_list()`, or a name to value map, such as the one
	 * `wp_remote_retrieve_headers()` returns, whose values are strings or
	 * lists of strings for repeated headers. A list of name and value pairs,
	 * the shape this method returns, is accepted as well.
	 *
	 * @param mixed         $headers Response headers.
	 * @param string[]|null $allowed Lowercase allowlist, or null for the configured one.
	 *
	 * @return array<int,array{0:string,1:string}> Name and value pairs in response order.
	 */
	public static function select( mixed $headers, ?array $allowed = null ): array {
		$allowed  = self::names( $allowed ?? self::allowed() );
		$selected = array();

		if ( array() === $allowed || ! is_iterable( $headers ) ) {
			return array();
		}

		foreach ( $headers as $name => $value ) {
			if ( is_int( $name ) ) {
				if ( is_array( $value ) && is_string( $value[0] ?? null ) && is_string( $value[1] ?? null ) ) {
					list( $name, $value ) = array( $value[0], $value[1] );
				} elseif ( is_string( $value ) && str_contains( $value, ':' ) ) {
					list( $name, $value ) = explode( ':', $value, 2 );
				} else {
					continue;
				}
			}
			if ( ! is_string( $name ) ) {
				continue;
			}

			$name = trim( $name );
			if ( ! self::is_name( $name ) || ! in_array( strtolower( $name ), $allowed, true ) ) {
				continue;
			}

			foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}

				$item = trim( $item );
				if ( '' === $item || false !== strpbrk( $item, "\r\n\0" ) ) {
					continue;
				}

				$pair = array( self::canonical_name( $name ), $item );
				if ( ! in_array( $pair, $selected, true ) ) {
					$selected[] = $pair;
				}
			}
		}

		return $selected;
	}

	/**
	 * Resolve the header file of one stored document.
	 *
	 * @param string $document Path of the `.html` document.
	 *
	 * @return string Header file path.
	 */
	public static function path( string $document ): string {
		return ( str_ends_with( $document, '.html' ) ? substr( $document, 0, -5 ) : $document ) . self::SUFFIX;
	}

	/**
	 * Store the headers of one document, or remove stale ones.
	 *
	 * An empty header list removes the document's header file, so a document
	 * re-rendered without headers never replays the previous render's.
	 *
	 * @param string                              $document Path of the `.html` document.
	 * @param array<int,array{0:string,1:string}> $headers  Selected headers.
	 *
	 * @return bool Whether the stored state matches the given headers.
	 */
	public static function store( string $document, array $headers ): bool {
		if ( array() === $headers ) {
			self::remove( $document );

			return ! is_file( self::path( $document ) );
		}

		$encoded = wp_json_encode(
			array(
				'format'  => self::FORMAT,
				'headers' => array_values( $headers ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return is_string( $encoded ) && Single_Flight::publish( self::path( $document ), $encoded );
	}

	/**
	 * Header lines to send with one stored document.
	 *
	 * Stored headers are filtered again against the allowlist, so narrowing
	 * the configuration stops replaying headers stored before the change.
	 *
	 * @param string        $document Path of the `.html` document.
	 * @param string[]|null $allowed  Lowercase allowlist, or null for the configured one.
	 *
	 * @return string[] `Name: value` lines.
	 */
	public static function replay( string $document, ?array $allowed = null ): array {
		$allowed = self::names( $allowed ?? self::allowed() );
		$path    = self::path( $document );

		if ( array() === $allowed || ! is_file( $path ) || ! is_readable( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local cache file.
		$contents = file_get_contents( $path );
		$decoded  = is_string( $contents ) ? json_decode( $contents, true ) : null;
		$stored   = is_array( $decoded ) && is_array( $decoded['headers'] ?? null ) ? $decoded['headers'] : array();
		$lines    = array();

		foreach ( self::select( array_values( $stored ), $allowed ) as $pair ) {
			$lines[] = $pair[0] . ': ' . $pair[1];
		}

		return $lines;
	}

	/**
	 * Send the stored headers of one document.
	 *
	 * A line the current response already carries, because a plugin sent it
	 * before the cache answered, is not sent twice.
	 *
	 * @param string $document Path of the `.html` document.
	 *
	 * @return string[] Lines sent.
	 */
	public static function send( string $document ): array {
		if ( headers_sent() ) {
			return array();
		}

		$sent  = array_map( 'strtolower', headers_list() );
		$lines = array_values(
			array_filter(
				self::replay( $document ),
				static fn( string $line ): bool => ! in_array( strtolower( $line ), $sent, true )
			)
		);

		foreach ( $lines as $line ) {
			header( $line, false );
		}

		return $lines;
	}

	/**
	 * Remove the header file of one document.
	 *
	 * @param string $document Path of the `.html` document.
	 *
	 * @return void
	 */
	public static function remove( string $document ): void {
		$path = self::path( $document );

		if ( is_file( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
		}
	}

	/**
	 * Make a destination document's headers match its source document's.
	 *
	 * Runs whether or not the document bytes differ, because a document can
	 * be re-rendered byte for byte with different headers.
	 *
	 * @param string $source      Source `.html` document.
	 * @param string $destination Destination `.html` document.
	 *
	 * @return bool Whether the destination headers match afterwards.
	 */
	public static function promote( string $source, string $destination ): bool {
		$from = self::path( $source );
		$to   = self::path( $destination );

		if ( ! is_file( $from ) ) {
			self::remove( $destination );

			return ! is_file( $to );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local cache file.
		$contents = file_get_contents( $from );
		if ( ! is_string( $contents ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local cache file.
		if ( is_file( $to ) && file_get_contents( $to ) === $contents ) {
			return true;
		}

		return Single_Flight::publish( $to, $contents );
	}

	/**
	 * Remove header files whose document no longer exists.
	 *
	 * @param string $directory Cache directory.
	 *
	 * @return int Number of header files removed.
	 */
	public static function collect_orphans( string $directory ): int {
		$removed = 0;
		$files   = glob( rtrim( $directory, '/' ) . '/*' . self::SUFFIX );

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			if ( is_file( substr( $file, 0, -strlen( self::SUFFIX ) ) . '.html' ) ) {
				continue;
			}

			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
			if ( ! is_file( $file ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Whether a path is a stored header file.
	 *
	 * @param string $path Path.
	 *
	 * @return bool Whether it is a header file.
	 */
	public static function is_header_file( string $path ): bool {
		return str_ends_with( $path, self::SUFFIX );
	}

	/**
	 * Spell a header name the conventional way, such as `X-Robots-Tag`.
	 *
	 * @param string $name Header name.
	 *
	 * @return string Canonical name.
	 */
	private static function canonical_name( string $name ): string {
		return implode( '-', array_map( 'ucfirst', explode( '-', strtolower( $name ) ) ) );
	}
}

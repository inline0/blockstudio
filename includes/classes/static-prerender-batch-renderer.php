<?php
/**
 * Static prerender batch renderer.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders deterministic URL batches through HTTP or a host-provided transport.
 *
 * @since 7.6.0
 */
final class Static_Prerender_Batch_Renderer {

	/**
	 * Whether a batch is currently rendering.
	 *
	 * @var bool
	 */
	private static bool $rendering = false;

	/**
	 * Render a URL batch.
	 *
	 * Internal rendering is exposed as a generic filter contract so a host can
	 * reuse its already-booted request environment without Blockstudio knowing
	 * that host's orchestration model.
	 *
	 * @param string[]            $urls    URLs.
	 * @param array<string,mixed> $options Options: transport, shard, fallback.
	 *
	 * @return array<string,mixed> Batch report.
	 */
	public static function render( array $urls, array $options = array() ): array {
		$transport = in_array( $options['transport'] ?? null, array( 'internal', 'fork' ), true ) ? (string) $options['transport'] : 'http';
		if ( 'fork' === $transport && ! self::can_fork() ) {
			$transport = 'http';
		}
		$fallback = 'none' !== ( $options['fallback'] ?? 'http' );
		$shard    = self::parse_shard( is_string( $options['shard'] ?? null ) ? $options['shard'] : null );
		$urls     = self::select_shard( $urls, $shard[0], $shard[1] );
		$started  = hrtime( true );
		$results  = array();
		$rendered = 0;
		$skipped  = 0;
		$failed   = 0;

		self::$rendering = true;

		if ( 'fork' === $transport ) {
			Tailwind::warm();
			do_action( 'blockstudio/static_prerender/fork_prepare', $urls, $options );
		}

		try {
			foreach ( $urls as $url ) {
				$item_started = hrtime( true );
				$parsed_path  = wp_parse_url( $url, PHP_URL_PATH );
				$path         = is_string( $parsed_path ) && '' !== $parsed_path ? $parsed_path : '/';

				if ( Static_Prerender_Runtime::is_dynamic_path( $path ) ) {
					$item = array(
						'status' => 'skipped',
						'reason' => 'dynamic-path',
					);
				} elseif ( 'fork' === $transport ) {
					$item = self::render_fork( $url, $options );

					if ( 'failed' === ( $item['status'] ?? null ) && $fallback ) {
						$item             = self::render_http( $url, $options );
						$item['fallback'] = 'http';
					}
				} elseif ( 'internal' === $transport ) {
					$item = self::render_internal( $url, $options );

					if ( 'failed' === ( $item['status'] ?? null ) && $fallback ) {
						$item             = self::render_http( $url, $options );
						$item['fallback'] = 'http';
					}
				} else {
					$item = self::render_http( $url, $options );
				}

				$item['url'] = $url;
				$item['ms']  = round( ( hrtime( true ) - $item_started ) / 1_000_000, 2 );
				$results[]   = $item;

				if ( 'rendered' === ( $item['status'] ?? null ) ) {
					++$rendered;
				} elseif ( 'skipped' === ( $item['status'] ?? null ) ) {
					++$skipped;
				} else {
					++$failed;
				}
			}
		} finally {
			self::$rendering = false;
		}

		$duration_ms = round( ( hrtime( true ) - $started ) / 1_000_000, 2 );
		$processed   = count( $urls );

		return array(
			'transport'  => $transport,
			'shard'      => $shard[0] . '/' . $shard[1],
			'workers'    => 1,
			'processed'  => $processed,
			'rendered'   => $rendered,
			'skipped'    => $skipped,
			'failed'     => $failed,
			'durationMs' => $duration_ms,
			'msPerPage'  => $processed > 0 ? round( $duration_ms / $processed, 2 ) : 0.0,
			'results'    => $results,
		);
	}

	/**
	 * Whether a batch owns the current request.
	 *
	 * @return bool Whether rendering.
	 */
	public static function is_rendering(): bool {
		return self::$rendering;
	}

	/**
	 * Select a stable shard from a URL list.
	 *
	 * @param string[] $urls   URLs.
	 * @param int      $number One-based shard number.
	 * @param int      $total  Total shards.
	 *
	 * @return string[] Shard URLs.
	 */
	public static function select_shard( array $urls, int $number, int $total ): array {
		$normalized = array();

		foreach ( $urls as $url ) {
			if ( is_string( $url ) && '' !== trim( $url ) ) {
				$normalized[ trim( $url ) ] = true;
			}
		}

		$urls = array_keys( $normalized );
		sort( $urls, SORT_STRING );
		$total  = max( 1, $total );
		$number = min( $total, max( 1, $number ) );

		return array_values(
			array_filter(
				$urls,
				static fn( string $url, int $index ): bool => $index % $total === $number - 1,
				ARRAY_FILTER_USE_BOTH
			)
		);
	}

	/**
	 * Parse a one-based shard expression.
	 *
	 * @param string|null $shard Shard expression.
	 *
	 * @return array{0:int,1:int} Number and total.
	 */
	public static function parse_shard( ?string $shard ): array {
		if (
			null === $shard ||
			! preg_match( '/^([1-9][0-9]*)\/([1-9][0-9]*)$/', $shard, $matches )
		) {
			return array( 1, 1 );
		}

		$number = (int) $matches[1];
		$total  = (int) $matches[2];

		return $number <= $total ? array( $number, $total ) : array( 1, 1 );
	}

	/**
	 * Render through the WordPress HTTP client.
	 *
	 * @param string              $url     URL.
	 * @param array<string,mixed> $options Options.
	 *
	 * @return array<string,mixed> Item report.
	 */
	private static function render_http( string $url, array $options ): array {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return array(
				'status' => 'failed',
				'error'  => 'WordPress HTTP transport is unavailable.',
			);
		}

		$token        = Static_Prerender_Runtime::dependency_report_token();
		$response     = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array(
					Static_Prerender_Runtime::WARM_HEADER => '1',
					Static_Prerender_Runtime::DEPENDENCY_HEADER => $token,
				),
				'user-agent'  => 'Blockstudio static prerender batch renderer',
			)
		);
		$report       = Static_Prerender_Runtime::take_render_report( $token );
		$dependencies = $report['files'];

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return array(
				'status' => 'failed',
				'error'  => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$html = (string) wp_remote_retrieve_body( $response );

		if ( 200 !== $code || ! Static_Prerender_Runtime::cacheable_html( $html ) ) {
			$reason   = 200 !== $code ? 'http-status' : 'not-html';
			$recorded = Static_Prerender_Runtime::persist_skipped_response(
				$url,
				$reason,
				$code,
				$dependencies,
				array(),
				self::string_option( $options, 'targetHost' ),
				self::string_option( $options, 'targetHome' )
			);

			return $recorded
				? array(
					'status'   => 'skipped',
					'httpCode' => $code,
					'reason'   => $reason,
				)
				: array(
					'status' => 'failed',
					'error'  => 'Skipped response metadata could not be persisted.',
				);
		}

		$persisted = Static_Prerender_Runtime::persist_built_response(
			$url,
			$html,
			$dependencies,
			array(),
			self::string_option( $options, 'targetHost' ),
			self::string_option( $options, 'targetHome' ),
			$report['headers'] ?? self::headers( wp_remote_retrieve_headers( $response ) )
		);

		return $persisted
			? array(
				'status'       => 'rendered',
				'httpCode'     => $code,
				'dependencies' => count( $dependencies ),
			)
			: array(
				'status' => 'failed',
				'error'  => 'Rendered HTML could not be persisted.',
			);
	}

		/**
		 * Whether this process can render in forked children.
		 *
		 * @return bool Whether forking is available.
		 */
	public static function can_fork(): bool {
		return function_exists( 'pcntl_fork' ) && function_exists( 'pcntl_waitpid' ) && function_exists( 'posix_kill' ) && function_exists( 'stream_socket_pair' ) && defined( 'ABSPATH' );
	}

	/**
	 * Render one URL in a child forked from this booted process.
	 *
	 * @param string              $url     URL.
	 * @param array<string,mixed> $options Options.
	 *
	 * @return array<string,mixed> Item result.
	 */
	private static function render_fork( string $url, array $options ): array {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! is_string( $parts['host'] ?? null ) ) {
			return array(
				'status' => 'failed',
				'error'  => 'The URL has no host.',
			);
		}

		$token = Static_Prerender_Runtime::dependency_report_token();
		$pair  = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP );
		if ( false === $pair ) {
			return array(
				'status' => 'failed',
				'error'  => 'The render socket pair could not be created.',
			);
		}

		$pid = pcntl_fork();
		if ( -1 === $pid ) {
			fclose( $pair[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Render socket.
			fclose( $pair[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Render socket.

			return array(
				'status' => 'failed',
				'error'  => 'The render child could not be forked.',
			);
		}

		if ( 0 === $pid ) {
			fclose( $pair[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Render socket.
			self::render_child( $parts, $token, $pair[1] );
		}

		fclose( $pair[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Render socket.
		$payload = stream_get_contents( $pair[0] );
		fclose( $pair[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Render socket.
		pcntl_waitpid( $pid, $child_status );

		$result = is_string( $payload ) && '' !== $payload ? json_decode( $payload, true ) : null;
		if ( ! is_array( $result ) ) {
			return array(
				'status' => 'failed',
				'error'  => 'The render child returned no result.',
			);
		}

		$report       = Static_Prerender_Runtime::take_render_report( $token );
		$dependencies = $report['files'];
		$code         = is_numeric( $result['code'] ?? null ) ? (int) $result['code'] : 200;
		$html         = is_string( $result['html'] ?? null ) ? $result['html'] : '';
		$headers      = is_array( $result['headers'] ?? null ) ? $result['headers'] : array();

		if ( 200 === $code && '' === $html ) {
			return array(
				'status' => 'failed',
				'error'  => 'The render child produced no document.',
			);
		}

		if ( 200 !== $code || ! Static_Prerender_Runtime::cacheable_html( $html ) ) {
			$reason = 200 !== $code ? 'http-status' : 'not-html';

			return Static_Prerender_Runtime::persist_skipped_response(
				$url,
				$reason,
				$code,
				$dependencies,
				array(),
				self::string_option( $options, 'targetHost' ),
				self::string_option( $options, 'targetHome' )
			)
				? array(
					'status'   => 'skipped',
					'httpCode' => $code,
					'reason'   => $reason,
				)
				: array(
					'status' => 'failed',
					'error'  => 'Skipped response metadata could not be persisted.',
				);
		}

		return Static_Prerender_Runtime::persist_built_response(
			$url,
			$html,
			$dependencies,
			array(),
			self::string_option( $options, 'targetHost' ),
			self::string_option( $options, 'targetHome' ),
			$report['headers'] ?? $headers
		)
			? array(
				'status'       => 'rendered',
				'httpCode'     => $code,
				'dependencies' => count( $dependencies ),
			)
			: array(
				'status' => 'failed',
				'error'  => 'Rendered HTML could not be persisted.',
			);
	}

	/**
	 * Run one front-end request in a forked child and report it to the parent.
	 *
	 * @param array<string,mixed> $parts  Parsed URL.
	 * @param string              $token  Dependency report token.
	 * @param resource            $socket Socket to the parent.
	 *
	 * @return never
	 */
	private static function render_child( array $parts, string $token, $socket ): void {
		self::$rendering = false;
		gc_disable();

		$path  = is_string( $parts['path'] ?? null ) && '' !== $parts['path'] ? $parts['path'] : '/';
		$query = is_string( $parts['query'] ?? null ) ? $parts['query'] : '';
		$https = 'https' === ( $parts['scheme'] ?? 'https' );
		$host  = (string) $parts['host'];
		$port  = is_int( $parts['port'] ?? null ) ? $parts['port'] : ( $https ? 443 : 80 );
		$uri   = (string) preg_replace_callback(
			'/[^\x21-\x7E]/',
			static fn( array $byte ): string => '%' . strtoupper( bin2hex( $byte[0] ) ),
			$path . ( '' !== $query ? '?' . $query : '' )
		);
		$get   = array();
		parse_str( $query, $get );

		$_SERVER['REQUEST_METHOD']     = 'GET';
		$_SERVER['REQUEST_URI']        = $uri;
		$_SERVER['QUERY_STRING']       = $query;
		$_SERVER['HTTP_HOST']          = $host;
		$_SERVER['SERVER_NAME']        = $host;
		$_SERVER['SERVER_PORT']        = (string) $port;
		$_SERVER['HTTPS']              = $https ? 'on' : '';
		$_SERVER['SCRIPT_NAME']        = '/index.php';
		$_SERVER['PHP_SELF']           = '/index.php';
		$_SERVER['SCRIPT_FILENAME']    = ABSPATH . 'index.php';
		$_SERVER['REMOTE_ADDR']        = '127.0.0.1';
		$_SERVER['HTTP_USER_AGENT']    = 'Blockstudio static prerender batch renderer';
		$_SERVER['REQUEST_TIME']       = time();
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
		$_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', Static_Prerender_Runtime::WARM_HEADER ) ) ]       = '1';
		$_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', Static_Prerender_Runtime::DEPENDENCY_HEADER ) ) ] = $token;
		$_GET     = $get;
		$_POST    = array();
		$_COOKIE  = array();
		$_REQUEST = $get;

		do_action( 'blockstudio/static_prerender/fork_child', $uri );

		$state = array(
			'code'     => 200,
			'html'     => '',
			'headers'  => array(),
			'location' => '',
		);

		add_filter(
			'status_header',
			static function ( $header, $code ) use ( &$state ) {
				$state['code'] = (int) $code;

				return $header;
			},
			PHP_INT_MAX,
			2
		);
		add_filter(
			'wp_redirect',
			static function ( $location, $code ) use ( &$state ) {
				$state['code']     = (int) $code;
				$state['location'] = is_string( $location ) ? $location : '';

				return $location;
			},
			PHP_INT_MAX,
			2
		);

		register_shutdown_function(
			static function () use ( &$state, $socket ): void {
				while ( ob_get_level() > 0 ) {
					$state['html'] .= (string) ob_get_clean();
				}
				$headers          = apply_filters( 'blockstudio/static_prerender/child_headers', array(), $state );
				$state['headers'] = is_array( $headers ) ? $headers : array();
				$encoded          = wp_json_encode( $state );
				fwrite( $socket, is_string( $encoded ) ? $encoded : '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Render socket.
				fclose( $socket ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Render socket.
				posix_kill( getmypid(), SIGKILL );
			}
		);

		ob_start(
			static function ( string $buffer ) use ( &$state ): string {
				$state['html'] .= $buffer;

				return '';
			}
		);

		add_filter( 'wp_using_themes', '__return_true', PHP_INT_MAX );
		wp();
		require ABSPATH . WPINC . '/template-loader.php';

		exit;
	}

	/**
	 * Render through the generic host callback.
	 *
	 * @param string              $url     URL.
	 * @param array<string,mixed> $options Options.
	 *
	 * @return array<string,mixed> Item report.
	 */
	private static function render_internal( string $url, array $options ): array {
		/**
		 * Filter an internal static-prerender result.
		 *
		 * Return an array with `html`, optional `status`, `dependencies`,
		 * `virtualHashes`, and `headers` (`Name: value` lines or a name to
		 * value map), or null when the host does not provide this transport.
		 *
		 * @since 7.6.0
		 *
		 * @param array<string,mixed>|null $result  Result.
		 * @param string                   $url     URL.
		 * @param array<string,mixed>      $options Options.
		 */
		$result = apply_filters( 'blockstudio/static_prerender/render_internal', null, $url, $options );

		if ( ! is_array( $result ) ) {
			return array(
				'status' => 'failed',
				'error'  => 'No internal static prerender transport is registered.',
			);
		}

		$code         = is_numeric( $result['status'] ?? null ) ? (int) $result['status'] : 200;
		$html         = is_string( $result['html'] ?? null ) ? $result['html'] : '';
		$dependencies = array_values( array_filter( (array) ( $result['dependencies'] ?? array() ), 'is_string' ) );
		$virtual      = is_array( $result['virtualHashes'] ?? null ) ? $result['virtualHashes'] : array();

		if ( 200 !== $code || ! Static_Prerender_Runtime::cacheable_html( $html ) ) {
			$reason = 200 !== $code ? 'http-status' : 'not-html';

			return Static_Prerender_Runtime::persist_skipped_response(
				$url,
				$reason,
				$code,
				$dependencies,
				$virtual,
				self::string_option( $options, 'targetHost' ),
				self::string_option( $options, 'targetHome' )
			)
				? array(
					'status'   => 'skipped',
					'httpCode' => $code,
					'reason'   => $reason,
				)
				: array(
					'status' => 'failed',
					'error'  => 'Skipped response metadata could not be persisted.',
				);
		}

		return Static_Prerender_Runtime::persist_built_response(
			$url,
			$html,
			$dependencies,
			$virtual,
			self::string_option( $options, 'targetHost' ),
			self::string_option( $options, 'targetHome' ),
			self::headers( $result['headers'] ?? array() )
		)
			? array(
				'status'       => 'rendered',
				'httpCode'     => $code,
				'dependencies' => count( $dependencies ),
			)
			: array(
				'status' => 'failed',
				'error'  => 'Rendered HTML could not be persisted.',
			);
	}

	/**
	 * Read rendered response headers in a shape the runtime accepts.
	 *
	 * @param mixed $headers Headers from the HTTP client or the internal transport.
	 *
	 * @return iterable<mixed> Headers, or an empty list.
	 */
	private static function headers( mixed $headers ): iterable {
		return is_iterable( $headers ) ? $headers : array();
	}

	/**
	 * Read one optional non-empty string.
	 *
	 * @param array<string,mixed> $options Options.
	 * @param string              $key     Key.
	 *
	 * @return string|null Value.
	 */
	private static function string_option( array $options, string $key ): ?string {
		$value = $options[ $key ] ?? null;

		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;
	}
}

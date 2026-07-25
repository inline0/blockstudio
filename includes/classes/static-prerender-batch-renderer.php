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
		$transport = 'internal' === ( $options['transport'] ?? null ) ? 'internal' : 'http';
		$fallback  = 'none' !== ( $options['fallback'] ?? 'http' );
		$shard     = self::parse_shard( is_string( $options['shard'] ?? null ) ? $options['shard'] : null );
		$urls      = self::select_shard( $urls, $shard[0], $shard[1] );
		$started   = hrtime( true );
		$results   = array();
		$rendered  = 0;
		$skipped   = 0;
		$failed    = 0;

		self::$rendering = true;

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

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array( Static_Prerender_Runtime::WARM_HEADER => '1' ),
				'user-agent'  => 'Blockstudio static prerender batch renderer',
			)
		);

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
				array(),
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
			array(),
			array(),
			self::string_option( $options, 'targetHost' ),
			self::string_option( $options, 'targetHome' )
		);

		return $persisted
			? array(
				'status'       => 'rendered',
				'httpCode'     => $code,
				'dependencies' => 0,
			)
			: array(
				'status' => 'failed',
				'error'  => 'Rendered HTML could not be persisted.',
			);
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
		 * Return an array with `html`, optional `status`, `dependencies`, and
		 * `virtualHashes`, or null when the host does not provide this transport.
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
			self::string_option( $options, 'targetHome' )
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

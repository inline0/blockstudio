<?php
/**
 * Performance measurement class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Provides opt-in runtime measurements and diagnostic response headers.
 *
 * @since 7.6.0
 */
final class Performance_Measurement {

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Request start time.
	 *
	 * @var float
	 */
	private static float $started_at = 0.0;

	/**
	 * Register configured measurement hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		if ( ! self::enabled() ) {
			return;
		}

		self::$started_at = microtime( true );
		if ( Runtime_Settings::current()->enabled( 'measurement/headers' ) ) {
			add_action( 'send_headers', array( self::class, 'send_headers' ) );
		}

		do_action( 'blockstudio/performance/measurement_enabled', Runtime_Settings::current() );
	}

	/**
	 * Whether measurements are enabled.
	 *
	 * @return bool Whether measurements are enabled.
	 */
	public static function enabled(): bool {
		return Runtime_Settings::current()->enabled( 'measurement/enabled' );
	}

	/**
	 * Send opt-in diagnostic headers.
	 *
	 * @return void
	 */
	public static function send_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		$config = Runtime_Settings::current();
		header( 'X-Blockstudio-Performance-Profile: ' . (string) $config->value( 'profile', 'compat' ) );
		header( 'X-Blockstudio-Performance-Config: ' . $config->hash() );

		if ( $config->enabled( 'measurement/timings' ) && self::$started_at > 0.0 ) {
			header(
				'X-Blockstudio-Performance-Time: ' .
				number_format( ( microtime( true ) - self::$started_at ) * 1000, 3, '.', '' ) .
				'ms'
			);
		}
	}

	/**
	 * Capture the current request measurements.
	 *
	 * @return array<string, mixed> Measurement snapshot.
	 */
	public static function snapshot(): array {
		$config      = Runtime_Settings::current();
		$wpdb        = $GLOBALS['wpdb'] ?? null;
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$snapshot    = array(
			'profile'         => (string) $config->value( 'profile', 'compat' ),
			'configHash'      => $config->hash(),
			'requestUri'      => $request_uri,
			'timeMs'          => self::$started_at > 0.0 ? round( ( microtime( true ) - self::$started_at ) * 1000, 3 ) : 0.0,
			'memoryPeakBytes' => memory_get_peak_usage( true ),
			'queryCount'      => self::query_count( $wpdb ),
			'capturedAt'      => current_time( 'mysql', true ),
		);

		if ( $config->enabled( 'measurement/queryMonitor' ) ) {
			$snapshot['slowQueries'] = self::slow_queries( $wpdb );
		}

		return $snapshot;
	}

	/**
	 * Read the WordPress query count.
	 *
	 * @param mixed $wpdb WordPress database object.
	 *
	 * @return int Query count.
	 */
	private static function query_count( $wpdb ): int {
		if ( is_object( $wpdb ) && isset( $wpdb->num_queries ) && is_scalar( $wpdb->num_queries ) ) {
			return max( 0, (int) $wpdb->num_queries );
		}
		if ( is_object( $wpdb ) && isset( $wpdb->queries ) && is_array( $wpdb->queries ) ) {
			return count( $wpdb->queries );
		}

		return 0;
	}

	/**
	 * Read queries taking at least 50 milliseconds.
	 *
	 * @param mixed $wpdb WordPress database object.
	 *
	 * @return list<array{sql:string,timeMs:float,caller:string}> Slow queries.
	 */
	private static function slow_queries( $wpdb ): array {
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->queries ) || ! is_array( $wpdb->queries ) ) {
			return array();
		}

		$slow = array();
		foreach ( $wpdb->queries as $query ) {
			if ( ! is_array( $query ) || ! isset( $query[0], $query[1] ) ) {
				continue;
			}
			$time_ms = round( (float) $query[1] * 1000, 3 );
			if ( $time_ms < 50.0 ) {
				continue;
			}
			$slow[] = array(
				'sql'    => (string) $query[0],
				'timeMs' => $time_ms,
				'caller' => (string) ( $query[2] ?? '' ),
			);
		}

		return $slow;
	}
}

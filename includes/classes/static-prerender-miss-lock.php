<?php
/**
 * Static prerender miss lock.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Short-lived single-flight ownership for identical uncached web requests.
 *
 * @since 7.6.0
 */
final class Static_Prerender_Miss_Lock {

	/**
	 * Lock option prefix.
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'blockstudio_static_miss_';

	/**
	 * Age after which a database lock is stale.
	 *
	 * @var int
	 */
	private const STALE_SECONDS = 15;

	/**
	 * Default waiter budget.
	 *
	 * @var int
	 */
	private const WAIT_MILLISECONDS = 1500;

	/**
	 * Wait poll interval.
	 *
	 * @var int
	 */
	private const POLL_MICROSECONDS = 20000;

	/**
	 * Locks owned by this request.
	 *
	 * @var array<string,true>
	 */
	private static array $owned = array();

	/**
	 * Acquire ownership or wait for a peer publication.
	 *
	 * @param string   $cache_key        Cache key.
	 * @param callable $cache_ready      Returns true after peer publication.
	 * @param int|null $wait_milliseconds Optional wait budget.
	 *
	 * @return 'owner'|'ready'|'timeout' Outcome.
	 */
	public static function acquire( string $cache_key, callable $cache_ready, ?int $wait_milliseconds = null ): string {
		$option = self::option_key( $cache_key );

		if ( isset( self::$owned[ $option ] ) ) {
			return 'owner';
		}

		$locked_at = (int) get_option( $option, 0 );
		if ( $locked_at > 0 && $locked_at < time() - self::STALE_SECONDS ) {
			delete_option( $option );
		}

		if ( add_option( $option, time(), '', false ) ) {
			self::$owned[ $option ] = true;

			return 'owner';
		}

		$wait_milliseconds = null === $wait_milliseconds
			? self::WAIT_MILLISECONDS
			: max( 0, $wait_milliseconds );
		$deadline          = microtime( true ) + ( $wait_milliseconds / 1000 );

		do {
			if ( $cache_ready() ) {
				return 'ready';
			}
			if ( microtime( true ) >= $deadline ) {
				break;
			}
			usleep( self::POLL_MICROSECONDS );
		} while ( true );

		return 'timeout';
	}

	/**
	 * Release one owned lock.
	 *
	 * @param string $cache_key Cache key.
	 *
	 * @return void
	 */
	public static function release( string $cache_key ): void {
		$option = self::option_key( $cache_key );

		if ( ! isset( self::$owned[ $option ] ) ) {
			return;
		}

		delete_option( $option );
		unset( self::$owned[ $option ] );
	}

	/**
	 * Release all locks owned by this request.
	 *
	 * @return void
	 */
	public static function release_all(): void {
		foreach ( array_keys( self::$owned ) as $option ) {
			delete_option( $option );
		}

		self::$owned = array();
	}

	/**
	 * Build a multisite-safe option key.
	 *
	 * @param string $cache_key Cache key.
	 *
	 * @return string Option key.
	 */
	private static function option_key( string $cache_key ): string {
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		return self::OPTION_PREFIX . $blog . '_' . substr( hash( 'sha256', $cache_key ), 0, 32 );
	}
}

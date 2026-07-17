<?php
/**
 * Single Flight class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Single-flight boundary for expensive deterministic file caches.
 *
 * On a cold cache key, one request is elected builder via a per-key advisory
 * flock lock. The builder writes to a unique temp file and publishes it
 * atomically via rename, so partial files are never visible as the current
 * cache entry. Concurrent requests on the same cold key wait a short bounded
 * time for the publish instead of duplicating the build, then degrade
 * gracefully when it does not arrive.
 *
 * The lock file records the owner pid and start time for diagnostics only.
 * Correctness rests on the OS advisory lock: the kernel releases flock when
 * its owner exits, so a crashed builder can never wedge a key (stale
 * recovery). On filesystems where flock() fails instead of contending, the
 * would-block flag distinguishes contention from failure and callers build
 * unguarded, matching pre-election behavior. The election path never
 * deletes lock files, because unlinking a file another process is about to
 * lock would let two processes hold locks on different inodes of the same
 * path and elect two builders; callers may sweep long-idle lock files,
 * where that worst case is one duplicate build, never a torn publish.
 *
 * @since 7.5.0
 */
class Single_Flight {

	/**
	 * Poll interval for waiters in microseconds.
	 *
	 * @var int
	 */
	private const WAIT_INTERVAL_US = 25000;

	/**
	 * Read a published cache file or build it exactly once across processes.
	 *
	 * Fast path: return the cache file contents when present. Cold path: try
	 * to acquire the per-key lock. The elected builder runs the callable and
	 * publishes atomically; losers wait up to the budget for the publish,
	 * then re-attempt the election once so a crashed builder's key is
	 * rebuilt by exactly one waiter, and return null when they lose again
	 * so the caller can degrade gracefully. When locking is unavailable the
	 * builder runs unguarded, matching pre-election behavior.
	 *
	 * @param string        $cache_path Absolute path of the cache file.
	 * @param callable      $builder    Returns the content to publish, or an empty string on failure.
	 * @param int           $wait_ms    Wait budget for losers in milliseconds.
	 * @param callable|null $on_publish Called by the builder after a successful publish.
	 *
	 * @return string|null Published content, or null when the caller should degrade.
	 */
	public static function read_or_build( string $cache_path, callable $builder, int $wait_ms = 4000, ?callable $on_publish = null ): ?string {
		$content = self::read( $cache_path );

		if ( null !== $content ) {
			return $content;
		}

		$lock = self::acquire( $cache_path . '.lock' );

		if ( false === $lock ) {
			$content = self::wait(
				static fn (): ?string => self::read( $cache_path ),
				$wait_ms
			);

			if ( null !== $content ) {
				return $content;
			}

			$lock = self::acquire( $cache_path . '.lock' );

			if ( false === $lock ) {
				return null;
			}
		}

		try {
			$content = self::read( $cache_path );

			if ( null !== $content ) {
				return $content;
			}

			$content = (string) $builder();

			if ( '' === $content ) {
				return null;
			}

			if ( self::publish( $cache_path, $content ) && null !== $on_publish ) {
				$on_publish();
			}

			return $content;
		} finally {
			if ( is_resource( $lock ) ) {
				self::release( $lock );
			}
		}
	}

	/**
	 * Try to acquire the per-key builder lock without blocking.
	 *
	 * The flock() would-block flag separates real contention from a failing
	 * flock() syscall, so filesystems without working advisory locks degrade
	 * to unguarded builds instead of electing no builder at all.
	 *
	 * @param string $lock_path Absolute path of the lock file.
	 *
	 * @return resource|false|null Lock handle on election, false when another
	 *                             process holds the lock, null when locking is
	 *                             unavailable and the caller should proceed
	 *                             unguarded.
	 */
	public static function acquire( string $lock_path ) {
		$dir = dirname( $lock_path );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A native handle is required for cross-process flock().
		$lock = fopen( $lock_path, 'c' );

		if ( false === $lock ) {
			return null;
		}

		$would_block = 0;

		if ( ! flock( $lock, LOCK_EX | LOCK_NB, $would_block ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the native flock() handle.
			fclose( $lock );

			return 1 === $would_block ? false : null;
		}

		ftruncate( $lock, 0 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing diagnostic lock metadata.
		fwrite( $lock, getmypid() . ' ' . time() );

		return $lock;
	}

	/**
	 * Release a builder lock acquired via acquire().
	 *
	 * @param resource $lock Lock handle.
	 *
	 * @return void
	 */
	public static function release( $lock ): void {
		flock( $lock, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the native flock() handle.
		fclose( $lock );
	}

	/**
	 * Poll a probe until it returns a non-null result or the budget expires.
	 *
	 * @param callable $probe   Returns the published result or null while cold.
	 * @param int      $wait_ms Wait budget in milliseconds.
	 *
	 * @return mixed Probe result, or null when the budget expired.
	 */
	public static function wait( callable $probe, int $wait_ms ): mixed {
		$deadline = microtime( true ) + ( max( 0, $wait_ms ) / 1000 );

		while ( true ) {
			$result = $probe();

			if ( null !== $result ) {
				return $result;
			}

			if ( microtime( true ) >= $deadline ) {
				return null;
			}

			usleep( self::WAIT_INTERVAL_US );
			clearstatcache();
		}
	}

	/**
	 * Publish content atomically by writing a unique temp file and renaming it.
	 *
	 * @param string $path    Absolute path of the destination file.
	 * @param string $content Content to publish.
	 *
	 * @return bool Whether the content was published.
	 */
	public static function publish( string $path, string $content ): bool {
		$dir = dirname( $path );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$tmp = $path . '.tmp-' . wp_generate_uuid4();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local cache file.
		if ( strlen( $content ) !== file_put_contents( $tmp, $content ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return false;
		}

		if ( ! rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return false;
		}

		return true;
	}

	/**
	 * Read a cache file, treating unreadable or empty files as a miss.
	 *
	 * @param string $cache_path Absolute path of the cache file.
	 *
	 * @return string|null File contents or null on miss.
	 */
	private static function read( string $cache_path ): ?string {
		if ( ! is_file( $cache_path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local cache file.
		$content = file_get_contents( $cache_path );

		return false === $content || '' === $content ? null : $content;
	}
}

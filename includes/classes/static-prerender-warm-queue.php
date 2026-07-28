<?php
/**
 * Static prerender warm queue.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * URL-coalescing durable warm queue with one JSON record per normalized URL.
 *
 * @since 7.6.0
 */
final class Static_Prerender_Warm_Queue {

	/**
	 * Priority of coalesced invalidation reasons.
	 *
	 * @var array<string,int>
	 */
	private const REASON_PRIORITY = array(
		'interval'       => 0,
		'site-change'    => 1,
		'content-change' => 2,
	);

	/**
	 * Queue records directory.
	 *
	 * @var string
	 */
	private string $records_root;

	/**
	 * Queue state root.
	 *
	 * @var string
	 */
	private string $state_root;

	/**
	 * Persistent queue lock handle.
	 *
	 * @var resource|null
	 */
	private mixed $lock_handle = null;

	/**
	 * Constructor.
	 *
	 * @param string|null $state_root Optional test/custom state root.
	 */
	public function __construct( ?string $state_root = null ) {
		$this->state_root   = rtrim(
			wp_normalize_path(
				$state_root ?? Runtime_Cache::directory( 'static-prerender-queue' )
			),
			'/'
		);
		$this->records_root = $this->state_root . '/records';

		wp_mkdir_p( $this->records_root );
	}

	/**
	 * Close the retained lock handle.
	 */
	public function __destruct() {
		if ( is_resource( $this->lock_handle ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Releases the native queue coordination handle.
			fclose( $this->lock_handle );
		}
	}

	/**
	 * Coalesce and enqueue jobs.
	 *
	 * @param array<int,array<string,mixed>> $jobs Jobs.
	 *
	 * @return int Number of records written.
	 */
	public function enqueue_many( array $jobs ): int {
		return $this->with_lock(
			function () use ( $jobs ): int {
				$incoming = array();

				foreach ( $jobs as $job ) {
					$normalized = $this->normalize_payload( $job );
					if ( null === $normalized ) {
						continue;
					}

					$id              = self::record_id( $normalized['urlHash'] );
					$incoming[ $id ] = $this->merge_payload( $incoming[ $id ] ?? null, $normalized );
				}

				$writes = 0;
				foreach ( $incoming as $id => $normalized ) {
					$current = $this->read_record( $id );

					if ( is_array( $current ) && 'processing' === ( $current['state'] ?? null ) ) {
						$next = is_array( $current['next'] ?? null ) ? $current['next'] : null;

						if (
							null === $next &&
							'interval' === $normalized['reason'] &&
							( $current['signature'] ?? null ) === $normalized['signature']
						) {
							continue;
						}

						$merged = $this->merge_payload( $next, $normalized );
						if ( $next === $merged ) {
							continue;
						}

						$current['next'] = $merged;
						if ( $this->write_record( $id, $current ) ) {
							++$writes;
						}
						continue;
					}

					$merged = $this->merge_payload( $current, $normalized );
					$stored = array_merge( $merged, array( 'state' => 'pending' ) );
					unset( $stored['claimedAt'], $stored['claimToken'], $stored['next'] );

					if ( $current === $stored ) {
						continue;
					}
					if ( $this->write_record( $id, $stored ) ) {
						++$writes;
					}
				}

				return $writes;
			}
		);
	}

	/**
	 * Claim the oldest pending job.
	 *
	 * @return array{id:string,token:string,data:array<string,mixed>}|null Claim.
	 */
	public function claim(): ?array {
		return $this->with_lock(
			function (): ?array {
				$records = $this->records();
				usort(
					$records,
					static fn( array $left, array $right ): int => (int) ( $left['data']['enqueuedAt'] ?? 0 )
						<=> (int) ( $right['data']['enqueuedAt'] ?? 0 )
				);

				foreach ( $records as $record ) {
					$data = $record['data'];
					if ( 'pending' !== ( $data['state'] ?? null ) ) {
						continue;
					}

					$token              = bin2hex( random_bytes( 16 ) );
					$data['state']      = 'processing';
					$data['claimedAt']  = time();
					$data['claimToken'] = $token;

					if ( ! $this->write_record( $record['id'], $data ) ) {
						continue;
					}

					return array(
						'id'    => $record['id'],
						'token' => $token,
						'data'  => $data,
					);
				}

				return null;
			}
		);
	}

	/**
	 * Complete a claimed job.
	 *
	 * @param string $id    Record ID.
	 * @param string $token Claim token.
	 *
	 * @return void
	 */
	public function complete( string $id, string $token ): void {
		$this->with_lock(
			function () use ( $id, $token ): void {
				$data = $this->read_record( $id );

				if (
					null === $data ||
					'processing' !== ( $data['state'] ?? null ) ||
					! hash_equals( (string) ( $data['claimToken'] ?? '' ), $token )
				) {
					return;
				}

				$next = is_array( $data['next'] ?? null ) ? $data['next'] : null;
				if ( null === $next ) {
					$this->delete_record( $id );

					return;
				}

				$next['state'] = 'pending';
				$this->write_record( $id, $next );
			}
		);
	}

	/**
	 * Return abandoned processing jobs to pending state.
	 *
	 * @param int $timeout_seconds Claim timeout.
	 *
	 * @return int Number requeued.
	 */
	public function requeue_timed_out( int $timeout_seconds ): int {
		return $this->with_lock(
			function () use ( $timeout_seconds ): int {
				$requeued = 0;
				$cutoff   = time() - max( 1, $timeout_seconds );

				foreach ( $this->records() as $record ) {
					$data = $record['data'];
					if (
						'processing' !== ( $data['state'] ?? null ) ||
						(int) ( $data['claimedAt'] ?? 0 ) > $cutoff
					) {
						continue;
					}

					$current          = $this->payload_only( $data );
					$next             = is_array( $data['next'] ?? null )
						? $this->payload_only( $data['next'] )
						: $current;
					$pending          = $this->merge_payload( $current, $next );
					$pending['state'] = 'pending';

					if ( $this->write_record( $record['id'], $pending ) ) {
						++$requeued;
					}
				}

				return $requeued;
			}
		);
	}

	/**
	 * Count queue states.
	 *
	 * @return array{pending:int,processing:int,done:int}
	 */
	public function counts(): array {
		return $this->with_lock(
			function (): array {
				$pending    = 0;
				$processing = 0;

				foreach ( $this->records() as $record ) {
					$state       = $record['data']['state'] ?? null;
					$pending    += 'pending' === $state ? 1 : 0;
					$processing += 'processing' === $state ? 1 : 0;
				}

				return array(
					'pending'    => $pending,
					'processing' => $processing,
					'done'       => 0,
				);
			}
		);
	}

	/**
	 * Return queue storage statistics.
	 *
	 * @return array{pending:int,processing:int,done:int,unique:int,bytes:int}
	 */
	public function stats(): array {
		$counts = $this->counts();
		$bytes  = 0;
		$paths  = glob( $this->records_root . '/*.json' );

		foreach ( is_array( $paths ) ? $paths : array() as $path ) {
			$bytes += is_file( $path ) ? (int) filesize( $path ) : 0;
		}

		return array_merge(
			$counts,
			array(
				'unique' => $counts['pending'] + $counts['processing'],
				'bytes'  => $bytes,
			)
		);
	}

	/**
	 * Remove all queue records.
	 *
	 * @return array{removed:int}
	 */
	public function reset(): array {
		return $this->with_lock(
			function (): array {
				$removed = 0;
				$paths   = glob( $this->records_root . '/*.json' );

				foreach ( is_array( $paths ) ? $paths : array() as $path ) {
					if ( ! is_file( $path ) ) {
						continue;
					}

					wp_delete_file( $path );
					if ( ! is_file( $path ) ) {
						++$removed;
					}
				}

				return array( 'removed' => $removed );
			}
		);
	}

	/**
	 * Validate every record and discard malformed files.
	 *
	 * @return array{ok:bool,records:int,removed:int}
	 */
	public function compact(): array {
		return $this->with_lock(
			function (): array {
				$records = 0;
				$removed = 0;
				$paths   = glob( $this->records_root . '/*.json' );

				foreach ( is_array( $paths ) ? $paths : array() as $path ) {
					$id = basename( $path, '.json' );
					if ( null === $this->read_record( $id ) ) {
						wp_delete_file( $path );
						++$removed;
					} else {
						++$records;
					}
				}

				return array(
					'ok'      => true,
					'records' => $records,
					'removed' => $removed,
				);
			}
		);
	}

	/**
	 * Merge a new payload into an existing payload.
	 *
	 * @param array<string,mixed>|null $current  Current payload.
	 * @param array<string,mixed>      $incoming Incoming payload.
	 *
	 * @return array<string,mixed> Merged payload.
	 */
	private function merge_payload( ?array $current, array $incoming ): array {
		if ( null === $current ) {
			return $incoming;
		}
		if ( ( $current['signature'] ?? null ) !== $incoming['signature'] ) {
			return (int) ( $current['enqueuedAt'] ?? 0 ) > (int) $incoming['enqueuedAt']
				? $this->payload_only( $current )
				: $incoming;
		}

		$current  = $this->payload_only( $current );
		$priority = self::REASON_PRIORITY[ (string) ( $incoming['reason'] ?? 'interval' ) ] ?? 0;
		$existing = self::REASON_PRIORITY[ (string) ( $current['reason'] ?? 'interval' ) ] ?? 0;

		if ( $priority > $existing ) {
			$current['reason']     = $incoming['reason'];
			$current['enqueuedAt'] = $incoming['enqueuedAt'];
		} elseif ( $priority === $existing && $priority > self::REASON_PRIORITY['interval'] ) {
			$current['enqueuedAt'] = max( (int) $current['enqueuedAt'], (int) $incoming['enqueuedAt'] );
		}

		return $current;
	}

	/**
	 * Validate and normalize a job.
	 *
	 * @param array<string,mixed> $payload Job payload.
	 *
	 * @return array<string,mixed>|null Normalized payload.
	 */
	private function normalize_payload( array $payload ): ?array {
		$url       = is_string( $payload['url'] ?? null ) ? $payload['url'] : '';
		$url_hash  = is_string( $payload['urlHash'] ?? null ) ? strtolower( $payload['urlHash'] ) : '';
		$signature = is_string( $payload['signature'] ?? null ) ? strtolower( $payload['signature'] ) : '';
		$reason    = is_string( $payload['reason'] ?? null ) && isset( self::REASON_PRIORITY[ $payload['reason'] ] )
			? $payload['reason']
			: 'interval';

		if (
			'' === $url ||
			1 !== preg_match( '/^[a-f0-9]{64}$/', $url_hash ) ||
			1 !== preg_match( '/^[a-f0-9]{32}$/', $signature )
		) {
			return null;
		}

		return array(
			'url'        => $url,
			'urlHash'    => $url_hash,
			'signature'  => $signature,
			'reason'     => $reason,
			'enqueuedAt' => is_numeric( $payload['enqueuedAt'] ?? null )
				? (int) $payload['enqueuedAt']
				: time(),
		);
	}

	/**
	 * Strip claim state from a record.
	 *
	 * @param array<string,mixed> $data Record.
	 *
	 * @return array<string,mixed> Payload.
	 */
	private function payload_only( array $data ): array {
		return array(
			'url'        => (string) ( $data['url'] ?? '' ),
			'urlHash'    => (string) ( $data['urlHash'] ?? '' ),
			'signature'  => (string) ( $data['signature'] ?? '' ),
			'reason'     => (string) ( $data['reason'] ?? 'interval' ),
			'enqueuedAt' => (int) ( $data['enqueuedAt'] ?? 0 ),
		);
	}

	/**
	 * Build a deterministic record ID.
	 *
	 * @param string $url_hash URL hash.
	 *
	 * @return string Record ID.
	 */
	private static function record_id( string $url_hash ): string {
		return substr( $url_hash, 0, 8 ) . '-'
			. substr( $url_hash, 8, 4 ) . '-7'
			. substr( $url_hash, 12, 3 ) . '-8'
			. substr( $url_hash, 15, 3 ) . '-'
			. substr( $url_hash, 18, 12 );
	}

	/**
	 * Read all valid records.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}> Records.
	 */
	private function records(): array {
		$records = array();
		$paths   = glob( $this->records_root . '/*.json' );

		foreach ( is_array( $paths ) ? $paths : array() as $path ) {
			$id   = basename( $path, '.json' );
			$data = $this->read_record( $id );
			if ( null !== $data ) {
				$records[] = array(
					'id'   => $id,
					'data' => $data,
				);
			}
		}

		return $records;
	}

	/**
	 * Read one record.
	 *
	 * @param string $id Record ID.
	 *
	 * @return array<string,mixed>|null Record.
	 */
	private function read_record( string $id ): ?array {
		$path = $this->record_path( $id );

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local queue state.
		$decoded = json_decode( (string) file_get_contents( $path ), true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Atomically write one record.
	 *
	 * @param string              $id   Record ID.
	 * @param array<string,mixed> $data Record.
	 *
	 * @return bool Whether published.
	 */
	private function write_record( string $id, array $data ): bool {
		$encoded = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $encoded )
			&& Single_Flight::publish( $this->record_path( $id ), $encoded . "\n" );
	}

	/**
	 * Delete one record.
	 *
	 * @param string $id Record ID.
	 *
	 * @return void
	 */
	private function delete_record( string $id ): void {
		$path = $this->record_path( $id );

		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Resolve one safe record path.
	 *
	 * @param string $id Record ID.
	 *
	 * @return string Path.
	 */
	private function record_path( string $id ): string {
		return $this->records_root . '/' . sanitize_file_name( $id ) . '.json';
	}

	/**
	 * Execute a callback under the queue lock.
	 *
	 * @template T
	 * @param callable():T $callback Callback.
	 *
	 * @return T Callback result.
	 * @throws \RuntimeException When the queue lock cannot be acquired.
	 */
	private function with_lock( callable $callback ): mixed {
		$handle = $this->lock_handle();

		if ( ! flock( $handle, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock -- Coordinates queue claims.
			throw new \RuntimeException( 'Could not lock the static prerender warm queue.' );
		}

		try {
			return $callback();
		} finally {
			flock( $handle, LOCK_UN ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock -- Releases queue coordination.
		}
	}

	/**
	 * Open the retained queue lock handle.
	 *
	 * @return resource Lock handle.
	 * @throws \RuntimeException When the queue lock file cannot be opened.
	 */
	private function lock_handle(): mixed {
		if ( is_resource( $this->lock_handle ) ) {
			return $this->lock_handle;
		}

		if ( ! is_dir( $this->state_root ) ) {
			wp_mkdir_p( $this->state_root );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flock requires a native stream.
		$handle = fopen( $this->state_root . '/queue.lock', 'c+b' );
		if ( false === $handle ) {
			throw new \RuntimeException( 'Could not open the static prerender warm queue lock.' );
		}

		$this->lock_handle = $handle;

		return $handle;
	}
}

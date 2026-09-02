<?php
/**
 * Runtime cache class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Shared filesystem boundary for Blockstudio runtime caches and generated state.
 *
 * Every persistent runtime scope resolves beneath one configured writable root
 * and a site/context namespace. The class provides the common atomic-write,
 * single-flight, stale-last-good, pruning, and diagnostic behavior used by
 * build, Tailwind, render, fragment, and static-prerender caches.
 *
 * @since 7.6.0
 */
final class Runtime_Cache {

	/**
	 * Cron hook used to remove pre-7.6 flat runtime caches in bounded batches.
	 *
	 * @var string
	 */
	private const LEGACY_CLEANUP_HOOK = 'blockstudio/cache/cleanup_legacy_runtime';

	/**
	 * Prefix for atomically quarantined pre-7.6 runtime directories.
	 *
	 * @var string
	 */
	private const LEGACY_QUARANTINE_PREFIX = '.legacy-runtime-';

	/**
	 * Default number of filesystem entries removed by one cron invocation.
	 *
	 * @var int
	 */
	private const LEGACY_CLEANUP_BATCH_SIZE = 500;

	/**
	 * Default maximum number of objects retained per scope.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_OBJECTS = 1000;

	/**
	 * Grace period before an unused namespace directory is collected.
	 *
	 * The namespace folds in the active plugin list and the WordPress and PHP
	 * versions, so activating a plugin or taking a point release moves every
	 * path at once. The previous tree is dead from that moment, but a request
	 * already in flight may still be reading it, so it is kept for a day.
	 */
	private const NAMESPACE_GRACE = DAY_IN_SECONDS;

	/**
	 * Wait budget for a concurrent builder.
	 *
	 * @var int
	 */
	private const WAIT_BUDGET_MS = 4000;

	/**
	 * Request-local diagnostics.
	 *
	 * @var array<string, array<string, int>>
	 */
	private static array $diagnostics = array();

	/**
	 * Register and stage cleanup of the pre-7.6 flat runtime directory.
	 *
	 * The rename is atomic and removes a potentially huge directory from the
	 * active cache path immediately. Deletion then happens only from WP-Cron in
	 * bounded batches, never during the page request that detected it.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! Settings::get_bool( 'cache/enabled', true ) ) {
			return;
		}

		if ( false === has_action( self::LEGACY_CLEANUP_HOOK, array( __CLASS__, 'cleanup_legacy_runtime_batch' ) ) ) {
			add_action( self::LEGACY_CLEANUP_HOOK, array( __CLASS__, 'cleanup_legacy_runtime_batch' ) );
		}

		self::stage_legacy_runtime_cleanup();
	}

	/**
	 * Move the legacy flat runtime directory aside and schedule its cleanup.
	 *
	 * @return bool Whether legacy cleanup work exists.
	 */
	public static function stage_legacy_runtime_cleanup(): bool {
		$root       = self::root();
		$legacy     = $root . '/runtime';
		$quarantine = self::legacy_quarantine_directories();

		if ( is_dir( $legacy ) ) {
			$suffix      = gmdate( 'YmdHis' ) . '-' . substr( hash( 'sha256', wp_generate_uuid4() ), 0, 12 );
			$destination = $root . '/' . self::LEGACY_QUARANTINE_PREFIX . $suffix;

			if ( @rename( $legacy, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-filesystem atomic quarantine; failure is retried by cron.
				$quarantine[] = $destination;
			}
		}

		$pending = is_dir( $legacy ) || array() !== array_values( array_filter( $quarantine, 'is_dir' ) );

		if ( $pending ) {
			self::schedule_legacy_cleanup();
		}

		return $pending;
	}

	/**
	 * Remove a bounded number of entries from quarantined legacy caches.
	 *
	 * @return void
	 */
	public static function cleanup_legacy_runtime_batch(): void {
		if ( ! Settings::get_bool( 'cache/enabled', true ) ) {
			return;
		}

		$remaining = max(
			1,
			(int) apply_filters(
				'blockstudio/cache/legacy_cleanup_batch_size',
				self::LEGACY_CLEANUP_BATCH_SIZE
			)
		);
		$paths     = self::legacy_quarantine_directories();
		$legacy    = self::root() . '/runtime';

		if ( is_dir( $legacy ) ) {
			$paths[] = $legacy;
		}

		foreach ( array_values( array_unique( $paths ) ) as $path ) {
			if ( $remaining <= 0 ) {
				break;
			}

			$remaining -= self::delete_tree_batch( $path, $remaining );
		}

		if ( is_dir( $legacy ) || array() !== self::legacy_quarantine_directories() ) {
			self::schedule_legacy_cleanup();
		}
	}

	/**
	 * Resolve the configured writable cache root.
	 *
	 * Relative paths are contained within WP_CONTENT_DIR. Absolute paths are
	 * supported for hosts with a dedicated cache volume.
	 *
	 * @return string Normalized cache root.
	 */
	public static function root(): string {
		$default = wp_normalize_path( WP_CONTENT_DIR . '/blockstudio/cache' );
		$base    = self::resolve_root( Settings::get( 'cache/path', 'blockstudio/cache' ), $default );

		/**
		 * Filter the shared Blockstudio runtime cache root.
		 *
		 * @since 7.6.0
		 *
		 * @param string $base Resolved cache root.
		 */
		$filtered = apply_filters( 'blockstudio/cache/dir', $base );

		return self::resolve_root( $filtered, $base );
	}

	/**
	 * Resolve one cache scope beneath the current site/context namespace.
	 *
	 * @param string $scope Cache scope.
	 *
	 * @return string Scope directory.
	 */
	public static function directory( string $scope ): string {
		$scope = sanitize_key( $scope );

		if ( '' === $scope ) {
			return self::root();
		}

		return self::root()
			. '/sites/'
			. self::site_key()
			. '/'
			. Runtime_Context::namespace( $scope )
			. '/'
			. $scope;
	}

	/**
	 * Resolve a safe object path in one scope.
	 *
	 * @param string $scope     Cache scope.
	 * @param string $key       Cache key.
	 * @param string $extension File extension.
	 *
	 * @return string Object path.
	 */
	public static function path( string $scope, string $key, string $extension = 'cache' ): string {
		$key       = sanitize_file_name( $key );
		$extension = sanitize_key( $extension );

		if ( '' === $key ) {
			$key = hash( 'sha256', $scope );
		}
		if ( '' === $extension ) {
			$extension = 'cache';
		}

		return self::directory( $scope ) . '/' . $key . '.' . $extension;
	}

	/**
	 * Build a deterministic object key under the shared runtime identity.
	 *
	 * @param string              $scope        Cache scope.
	 * @param array<string,mixed> $inputs       Object-specific identity.
	 * @param string[]            $discovery    Discovery context names.
	 * @param array<string,mixed> $dependencies Optional dependency identities.
	 *
	 * @return string SHA-256 key.
	 */
	public static function key( string $scope, array $inputs, array $discovery = array(), array $dependencies = array() ): string {
		$identity = array(
			'runtime' => Runtime_Context::identity( $scope, $discovery, $dependencies ),
			'inputs'  => self::normalize_identity( $inputs ),
		);
		$encoded  = wp_json_encode( $identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * Atomically publish one cache object.
	 *
	 * @param string $scope     Cache scope.
	 * @param string $key       Cache key.
	 * @param string $contents  Object contents.
	 * @param string $extension File extension.
	 *
	 * @return bool Whether the object was published.
	 */
	public static function write( string $scope, string $key, string $contents, string $extension = 'cache' ): bool {
		$path = self::path( $scope, $key, $extension );

		if ( ! Single_Flight::publish( $path, $contents ) ) {
			self::record( $scope, 'write-failure' );

			return false;
		}

		self::record( $scope, 'write' );
		self::prune( $scope, $path );

		return true;
	}

	/**
	 * Read one cache object.
	 *
	 * @param string $scope     Cache scope.
	 * @param string $key       Cache key.
	 * @param int    $ttl       Maximum age in seconds. Zero disables expiry.
	 * @param string $extension File extension.
	 *
	 * @return string|null Object contents or null on miss/expiry/read failure.
	 */
	public static function read( string $scope, string $key, int $ttl = 0, string $extension = 'cache' ): ?string {
		$path = self::path( $scope, $key, $extension );

		if ( ! is_file( $path ) ) {
			self::record( $scope, 'miss-absent' );

			return null;
		}

		if ( self::is_stale_file( $path, $ttl ) ) {
			self::record( $scope, 'miss-stale' );

			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local runtime cache object.
		$contents = file_get_contents( $path );

		if ( false === $contents || '' === $contents ) {
			self::record( $scope, 'miss-unreadable' );

			return null;
		}

		self::record( $scope, 'hit' );

		return $contents;
	}

	/**
	 * Read or build one deterministic object with stale-last-good recovery.
	 *
	 * The first request to a cold or expired key becomes the builder. Concurrent
	 * requests wait for its atomic publish. If the builder fails, expired content
	 * remains available for this request as last-good output.
	 *
	 * @param string        $scope     Cache scope.
	 * @param string        $key       Cache key.
	 * @param callable      $builder   Returns object contents or null/empty on failure.
	 * @param int           $ttl       Maximum age in seconds. Zero disables expiry.
	 * @param string        $extension File extension.
	 * @param callable|null $validate  Optional content validator.
	 *
	 * @return array{value:string|null,state:string,reason:string}
	 */
	public static function remember(
		string $scope,
		string $key,
		callable $builder,
		int $ttl = 0,
		string $extension = 'cache',
		?callable $validate = null
	): array {
		$path     = self::path( $scope, $key, $extension );
		$current  = self::read_file( $path );
		$is_stale = null !== $current && self::is_stale_file( $path, $ttl );

		if ( null !== $current && ! $is_stale && self::valid( $current, $validate ) ) {
			self::record( $scope, 'hit' );

			return array(
				'value'  => $current,
				'state'  => 'hit',
				'reason' => 'fresh',
			);
		}

		self::record( $scope, $is_stale ? 'miss-stale' : 'miss-absent' );
		$lock = Single_Flight::acquire( $path . '.lock' );

		if ( false === $lock ) {
			$published = Single_Flight::wait(
				static function () use ( $path, $ttl, $validate ): ?string {
					$value = self::read_file( $path );

					if ( null === $value ) {
						return null;
					}
					if ( self::is_stale_file( $path, $ttl ) ) {
						return null;
					}

					return self::valid( $value, $validate ) ? $value : null;
				},
				self::WAIT_BUDGET_MS
			);

			if ( is_string( $published ) ) {
				self::record( $scope, 'hit-peer' );

				return array(
					'value'  => $published,
					'state'  => 'hit',
					'reason' => 'peer-publish',
				);
			}

			if ( null !== $current && self::valid( $current, $validate ) ) {
				self::record( $scope, 'stale-last-good' );

				return array(
					'value'  => $current,
					'state'  => 'stale',
					'reason' => 'builder-timeout',
				);
			}

			self::record( $scope, 'build-timeout' );

			return array(
				'value'  => null,
				'state'  => 'miss',
				'reason' => 'builder-timeout',
			);
		}

		try {
			$peer = self::read_file( $path );
			if (
				null !== $peer &&
				! self::is_stale_file( $path, $ttl ) &&
				self::valid( $peer, $validate )
			) {
				self::record( $scope, 'hit-peer' );

				return array(
					'value'  => $peer,
					'state'  => 'hit',
					'reason' => 'peer-publish',
				);
			}

			try {
				$built = $builder();
			} catch ( \Throwable ) {
				$built = null;
			}

			if ( is_string( $built ) && '' !== $built && self::valid( $built, $validate ) ) {
				if ( Single_Flight::publish( $path, $built ) ) {
					self::record( $scope, 'build' );
					self::prune( $scope, $path );

					return array(
						'value'  => $built,
						'state'  => 'miss',
						'reason' => 'built',
					);
				}

				self::record( $scope, 'write-failure' );
			} else {
				self::record( $scope, 'build-failure' );
			}

			if ( null !== $current && self::valid( $current, $validate ) ) {
				self::record( $scope, 'stale-last-good' );

				return array(
					'value'  => $current,
					'state'  => 'stale',
					'reason' => 'build-failure',
				);
			}

			return array(
				'value'  => null,
				'state'  => 'miss',
				'reason' => 'build-failure',
			);
		} finally {
			if ( is_resource( $lock ) ) {
				Single_Flight::release( $lock );
			}
		}
	}

	/**
	 * Delete one scope or the entire current site namespace.
	 *
	 * @param string|null $scope Optional scope.
	 *
	 * @return int Number of files removed.
	 */
	public static function purge( ?string $scope = null ): int {
		$directory = null === $scope
			? self::root() . '/sites/' . self::site_key()
			: self::directory( $scope );
		$removed   = self::delete_tree( $directory );

		self::record( null === $scope ? 'all' : $scope, 'purge' );

		return $removed;
	}

	/**
	 * Delete one scope in every namespace this site has ever written.
	 *
	 * A plain purge() only reaches the namespace the current request resolves
	 * to, so a
	 * namespace that stopped being current keeps whatever it holds. That matters
	 * for anything served before WordPress loads: the identity rolls the moment
	 * a stylesheet changes, and collect_stale_namespaces() will not touch the
	 * abandoned tree until it is a day old, so an explicit purge appears to
	 * succeed while the previous build carries on being served.
	 *
	 * @param string $scope Cache scope.
	 *
	 * @return int Number of files removed.
	 */
	public static function purge_every_namespace( string $scope ): int {
		$scope = sanitize_key( $scope );

		if ( '' === $scope ) {
			return 0;
		}

		$site_directory = self::root() . '/sites/' . self::site_key();
		$namespaces     = glob( $site_directory . '/*', GLOB_ONLYDIR );
		$removed        = 0;

		foreach ( is_array( $namespaces ) ? $namespaces : array() as $namespace ) {
			$removed += self::delete_tree( $namespace . '/' . $scope );

			$remaining = glob( $namespace . '/*' );
			if ( is_array( $remaining ) && array() === $remaining ) {
				self::delete_tree( $namespace );
			}
		}

		self::record( $scope, 'purge' );

		return $removed;
	}

	/**
	 * Return request-local cache outcomes.
	 *
	 * @return array<string, array<string, int>> Diagnostics by scope and reason.
	 */
	public static function diagnostics(): array {
		return self::$diagnostics;
	}

	/**
	 * Reset request-local cache outcomes.
	 *
	 * @return void
	 */
	public static function reset_diagnostics(): void {
		self::$diagnostics = array();
	}

	/**
	 * Remove namespace directories this installation no longer resolves to.
	 *
	 * Nothing else collects them: prune() bounds object count inside a single
	 * scope, and delete_tree() is only reachable from an explicit purge. Left
	 * alone, every plugin activation and every WordPress or PHP point release
	 * orphans a whole tree that is never read again.
	 *
	 * @param string $scope_directory Current scope directory.
	 *
	 * @return void
	 */
	private static function collect_stale_namespaces( string $scope_directory ): void {
		$current_namespace = dirname( $scope_directory );
		$site_directory    = dirname( $current_namespace );

		if ( ! is_dir( $site_directory ) || $site_directory === $current_namespace ) {
			return;
		}

		$siblings = glob( $site_directory . '/*', GLOB_ONLYDIR );

		if ( ! is_array( $siblings ) ) {
			return;
		}

		$threshold = time() - self::NAMESPACE_GRACE;

		foreach ( $siblings as $sibling ) {
			if ( $sibling === $current_namespace ) {
				continue;
			}

			$mtime = self::newest_mtime( $sibling );

			if ( 0 === $mtime || $mtime >= $threshold ) {
				continue;
			}

			self::delete_tree( $sibling );
		}
	}

	/**
	 * Whether a cache file is older than its ttl.
	 *
	 * Prune sweeps run concurrently with readers, so a file can vanish
	 * between the existence check and the stat; a failed stat therefore
	 * counts as stale instead of raising a warning into the response.
	 *
	 * @param string $path Cache file path.
	 * @param int    $ttl  Time to live in seconds.
	 *
	 * @return bool Whether the entry must be treated as expired.
	 */
	private static function is_stale_file( string $path, int $ttl ): bool {
		if ( $ttl <= 0 ) {
			return false;
		}

		$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent prunes can remove the file between check and stat.

		return false === $mtime || (int) $mtime < time() - $ttl;
	}

	/**
	 * Newest modification time anywhere beneath a directory.
	 *
	 * @param string $directory Directory to scan.
	 *
	 * @return int Newest mtime, or 0 when the directory cannot be read.
	 */
	private static function newest_mtime( string $directory ): int {
		if ( ! is_dir( $directory ) ) {
			return 0;
		}

		$directory_mtime = @filemtime( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent prunes can remove entries between check and stat.
		$newest          = false === $directory_mtime ? 0 : (int) $directory_mtime;

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $entry ) {
				$mtime = (int) $entry->getMTime();

				if ( $mtime > $newest ) {
					$newest = $mtime;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );

			return 0;
		}

		return $newest;
	}

	/**
	 * Prune old objects and abandoned temporary files in one scope.
	 *
	 * @param string $scope     Cache scope.
	 * @param string $keep_path Object that must remain.
	 *
	 * @return void
	 */
	public static function prune( string $scope, string $keep_path = '' ): void {
		$directory = self::directory( $scope );

		self::collect_stale_namespaces( $directory );

		if ( ! is_dir( $directory ) ) {
			return;
		}

		$now             = time();
		$temporary_files = glob( $directory . '/*.tmp-*' );
		foreach ( is_array( $temporary_files ) ? $temporary_files : array() as $temporary ) {
			$mtime = (int) ( @filemtime( $temporary ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove the file between listing and stat.
			if ( $mtime > 0 && $mtime < $now - HOUR_IN_SECONDS ) {
				wp_delete_file( $temporary );
			}
		}

		$protected = class_exists( '\Blockstudio\Static_Prerender_Early_Serve' )
			? Static_Prerender_Early_Serve::protected_cache_paths( $directory )
			: array();

		/**
		 * Filters cache paths retention pruning must never evict.
		 *
		 * @since 7.6.1
		 *
		 * @param string[] $protected Absolute protected paths.
		 * @param string   $scope     Cache scope.
		 * @param string   $directory Scope directory.
		 */
		$protected     = apply_filters( 'blockstudio/cache/protected_paths', $protected, $scope, $directory );
		$protected_map = array();
		foreach ( is_array( $protected ) ? $protected : array() as $protected_path ) {
			if ( is_string( $protected_path ) ) {
				$protected_map[ wp_normalize_path( $protected_path ) ] = true;
			}
		}

		$scope_files = glob( $directory . '/*' );
		$objects     = array_values(
			array_filter(
				is_array( $scope_files ) ? $scope_files : array(),
				static fn( string $path ): bool => is_file( $path )
					&& ! str_ends_with( $path, '.lock' )
					&& ! str_contains( basename( $path ), '.tmp-' )
					&& $path !== $keep_path
					&& ! isset( $protected_map[ wp_normalize_path( $path ) ] )
			)
		);
		usort(
			$objects,
			static fn( string $left, string $right ): int => (int) ( @filemtime( $right ) ) <=> (int) ( @filemtime( $left ) ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove files between listing and stat.
		);

		$maximum = max(
			1,
			(int) apply_filters(
				'blockstudio/cache/max_files_per_scope',
				self::DEFAULT_MAX_OBJECTS,
				$scope
			)
		);

		foreach ( array_slice( $objects, max( 0, $maximum - ( '' === $keep_path ? 0 : 1 ) ) ) as $object ) {
			wp_delete_file( $object );
		}
	}

	/**
	 * Find every atomically quarantined legacy runtime directory.
	 *
	 * @return string[] Directory paths.
	 */
	private static function legacy_quarantine_directories(): array {
		$paths = glob( self::root() . '/' . self::LEGACY_QUARANTINE_PREFIX . '*', GLOB_ONLYDIR );

		return is_array( $paths ) ? array_values( $paths ) : array();
	}

	/**
	 * Schedule one cleanup pass unless one is already pending.
	 *
	 * @return void
	 */
	private static function schedule_legacy_cleanup(): void {
		if ( false === wp_next_scheduled( self::LEGACY_CLEANUP_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::LEGACY_CLEANUP_HOOK );
		}
	}

	/**
	 * Delete at most a fixed number of entries from one tree.
	 *
	 * @param string $directory Directory.
	 * @param int    $limit     Maximum filesystem entries to remove.
	 *
	 * @return int Number of entries removed.
	 */
	private static function delete_tree_batch( string $directory, int $limit ): int {
		if ( $limit <= 0 || ! is_dir( $directory ) ) {
			return 0;
		}

		$removed = 0;

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $item ) {
				if ( $removed >= $limit ) {
					break;
				}

				$path    = $item->getPathname();
				$deleted = $item->isDir()
					? @rmdir( $path ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Bounded cache cleanup tolerates concurrent removals.
					: @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Bounded cache cleanup tolerates concurrent removals.

				if ( $deleted ) {
					++$removed;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}

		if ( $removed < $limit && @rmdir( $directory ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing an empty quarantine root.
			++$removed;
		}

		return $removed;
	}

	/**
	 * Resolve a configured cache root.
	 *
	 * @param mixed  $path     Configured root.
	 * @param string $fallback Fallback root.
	 *
	 * @return string Normalized root.
	 */
	private static function resolve_root( mixed $path, string $fallback ): string {
		if ( ! is_string( $path ) || '' === trim( $path ) || str_contains( $path, "\0" ) ) {
			return rtrim( wp_normalize_path( $fallback ), '/' );
		}

		$path        = wp_normalize_path( trim( $path ) );
		$is_absolute = str_starts_with( $path, '/' )
			|| (bool) preg_match( '/^[A-Za-z]:\//', $path )
			|| str_starts_with( $path, '//' );

		if ( ! $is_absolute ) {
			if ( in_array( '..', explode( '/', $path ), true ) ) {
				return rtrim( wp_normalize_path( $fallback ), '/' );
			}

			$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
		}

		$normalized = rtrim( wp_normalize_path( $path ), '/' );
		if ( '' === $normalized || (bool) preg_match( '/^[A-Za-z]:$/', $normalized ) ) {
			return rtrim( wp_normalize_path( $fallback ), '/' );
		}

		return $normalized;
	}

	/**
	 * Build a filesystem-safe multisite key.
	 *
	 * @return string Site key.
	 */
	private static function site_key(): string {
		$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 0;
		$blog    = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		$key     = sprintf( 'network-%d-blog-%d', $network, $blog );

		/**
		 * Filter the filesystem-safe cache site identity.
		 *
		 * This supports hosts with a custom tenant boundary and isolated tests.
		 * The value is sanitized before it becomes part of a path.
		 *
		 * @since 7.6.0
		 *
		 * @param string $key     Default network/blog identity.
		 * @param int    $network Current network ID.
		 * @param int    $blog    Current blog ID.
		 */
		$filtered = apply_filters( 'blockstudio/cache/site_key', $key, $network, $blog );
		$filtered = is_string( $filtered ) ? sanitize_file_name( $filtered ) : '';

		return '' === $filtered ? $key : $filtered;
	}

	/**
	 * Read a non-empty local file.
	 *
	 * @param string $path File path.
	 *
	 * @return string|null File contents.
	 */
	private static function read_file( string $path ): ?string {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local runtime cache object.
		$contents = file_get_contents( $path );

		return false === $contents || '' === $contents ? null : $contents;
	}

	/**
	 * Validate cached or generated contents.
	 *
	 * @param string        $contents Contents.
	 * @param callable|null $validate Optional validator.
	 *
	 * @return bool Whether contents are valid.
	 */
	private static function valid( string $contents, ?callable $validate ): bool {
		if ( null === $validate ) {
			return true;
		}

		try {
			return true === $validate( $contents );
		} catch ( \Throwable ) {
			return false;
		}
	}

	/**
	 * Recursively delete a cache tree.
	 *
	 * @param string $directory Directory.
	 *
	 * @return int Number of files removed.
	 */
	private static function delete_tree( string $directory ): int {
		if ( ! is_dir( $directory ) ) {
			return 0;
		}

		$removed = 0;

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $item ) {
				$path = $item->getPathname();
				if ( $item->isDir() ) {
					@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				} elseif ( @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
					++$removed;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}

		@rmdir( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir

		return $removed;
	}

	/**
	 * Normalize nested identity input before hashing.
	 *
	 * @param mixed $value Identity value.
	 *
	 * @return mixed Normalized identity.
	 */
	private static function normalize_identity( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::normalize_identity( $item );
		}

		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}

		return $value;
	}

	/**
	 * Record one request-local cache outcome.
	 *
	 * @param string $scope  Cache scope.
	 * @param string $reason Outcome reason.
	 *
	 * @return void
	 */
	private static function record( string $scope, string $reason ): void {
		self::$diagnostics[ $scope ]          ??= array();
		self::$diagnostics[ $scope ][ $reason ] = ( self::$diagnostics[ $scope ][ $reason ] ?? 0 ) + 1;

		/**
		 * Fires after a Blockstudio runtime cache outcome.
		 *
		 * @since 7.6.0
		 *
		 * @param string $scope  Cache scope.
		 * @param string $reason Outcome reason.
		 */
		do_action( 'blockstudio/cache/outcome', $scope, $reason );
	}
}

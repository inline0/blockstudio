<?php
/**
 * Static prerender runtime.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Anonymous-safe HTML response cache and incremental static-prerender graph.
 *
 * @since 7.6.0
 */
final class Static_Prerender_Runtime {

	/**
	 * Public header used to request an uncached warm render.
	 *
	 * @var string
	 */
	public const WARM_HEADER = 'X-Blockstudio-Static-Prerender-Warm';

	/**
	 * Cron hook.
	 *
	 * @var string
	 */
	private const CRON_HOOK = 'blockstudio_static_prerender_warm';

	/**
	 * Cron schedule name.
	 *
	 * @var string
	 */
	private const CRON_SCHEDULE = 'blockstudio_static_prerender_interval';

	/**
	 * Last configured side-effecting prerender features.
	 */
	private const FEATURE_STATE_OPTION = 'blockstudio_static_prerender_feature_state';

	/**
	 * Warm-request server key.
	 *
	 * @var string
	 */
	private const WARM_SERVER_HEADER = 'HTTP_X_BLOCKSTUDIO_STATIC_PRERENDER_WARM';

	/**
	 * Request header that asks a warm render to report the files it read.
	 *
	 * @var string
	 */
	public const DEPENDENCY_HEADER = 'X-Blockstudio-Static-Prerender-Dependencies';

	/**
	 * Dependency-report server key.
	 *
	 * @var string
	 */
	private const DEPENDENCY_SERVER_HEADER = 'HTTP_X_BLOCKSTUDIO_STATIC_PRERENDER_DEPENDENCIES';

	/**
	 * Queue claim timeout.
	 *
	 * @var int
	 */
	private const QUEUE_TIMEOUT_SECONDS = 300;

	/**
	 * Whether hooks were registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Whether the feature hooks were decided.
	 *
	 * @var bool
	 */
	private static bool $features_registered = false;

	/**
	 * Buffered response key.
	 *
	 * @var string|null
	 */
	private static ?string $buffer_key = null;

	/**
	 * Buffered request URL.
	 *
	 * @var string|null
	 */
	private static ?string $buffer_url = null;

	/**
	 * Last HTTP status WordPress sent for this response.
	 *
	 * @var int|null
	 */
	private static ?int $response_status = null;

	/**
	 * Request-local warm queue.
	 *
	 * @var Static_Prerender_Warm_Queue|null
	 */
	private static ?Static_Prerender_Warm_Queue $warm_queue = null;

	/**
	 * Request-local URL index record cache.
	 *
	 * @var array<string,array<string,mixed>|null>
	 */
	private static array $index_record_cache = array();

	/**
	 * Dependency chunks read in this process, by content hash.
	 *
	 * @var array<string,array{hashes:array<string,string>,paths:array<string,string>}|null>
	 */
	private static array $dependency_set_cache = array();

	/**
	 * Report ID of the dependency report the current warm request writes.
	 *
	 * @var string|null
	 */
	private static ?string $dependency_report = null;

	/**
	 * Whether this request leaves persistence to the batch renderer.
	 *
	 * @var bool
	 */
	private static bool $capture_suppressed = false;

	/**
	 * Files the current warm request read, keyed by path.
	 *
	 * @var array<string,true>
	 */
	private static array $dependencies = array();

	/**
	 * Search result path prefixes by blog ID.
	 *
	 * @var array<int,string>
	 */
	private static array $search_path_prefixes = array();

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		self::register_wp_cli_commands();

		// A deleted site's entry lives in the network-wide map, whatever the
		// settings of the site that deletes it.
		add_action( 'wp_delete_site', array( Static_Prerender_Early_Serve::class, 'remove_deleted_site' ) );

		// Blockstudio boots from a plugin or from the theme's Composer autoload,
		// before the theme's functions.php has added its settings filters, so a
		// theme that switches the feature off, for example per environment,
		// would otherwise be ignored by hooks registered from stale settings.
		if ( function_exists( 'did_action' ) && ! did_action( 'after_setup_theme' ) ) {
			add_action( 'after_setup_theme', array( self::class, 'register_features' ), PHP_INT_MAX );

			return;
		}

		self::register_features();
	}

	/**
	 * Register the request, content, admin, and cron hooks the settings enable.
	 *
	 * Runs at after_setup_theme, once plugin and theme settings filters exist.
	 *
	 * @return void
	 */
	public static function register_features(): void {
		if ( self::$features_registered ) {
			return;
		}

		self::$features_registered = true;
		self::reconcile_feature_state();

		if ( ! self::enabled( 'staticPrerender/enabled' ) ) {
			return;
		}

		add_filter( 'status_header', array( self::class, 'record_status_header' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'maybe_serve_or_buffer' ), 0 );
		add_action( 'save_post', array( self::class, 'handle_content_changed' ), 10, 3 );
		add_action( 'deleted_post', array( self::class, 'handle_content_changed' ), 10, 2 );
		add_action( 'switch_theme', array( self::class, 'handle_site_changed' ) );
		add_action( 'blockstudio/runtime/changed', array( self::class, 'handle_site_changed' ) );

		if ( self::enabled( 'staticPrerender/earlyServe' ) ) {
			add_action( 'admin_init', array( Static_Prerender_Early_Serve::class, 'maybe_sync' ), 50 );
		}

		if ( self::warm_enabled() ) {
			add_filter( 'cron_schedules', array( self::class, 'filter_cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Interval is validated runtime configuration.
			add_action( self::CRON_HOOK, array( self::class, 'run_cron_warmer' ) );
			self::schedule_cron();
		}
	}

	/**
	 * Release runtime resources and uninstall the current early-serve entry.
	 *
	 * Cached HTML is retained for a later activation, but it can no longer be
	 * served before WordPress while Blockstudio is inactive.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		remove_filter( 'status_header', array( self::class, 'record_status_header' ), 10 );
		remove_filter( 'cron_schedules', array( self::class, 'filter_cron_schedules' ) );
		remove_action( 'template_redirect', array( self::class, 'maybe_serve_or_buffer' ), 0 );
		remove_action( 'save_post', array( self::class, 'handle_content_changed' ), 10 );
		remove_action( 'deleted_post', array( self::class, 'handle_content_changed' ), 10 );
		remove_action( 'switch_theme', array( self::class, 'handle_site_changed' ) );
		remove_action( 'blockstudio/runtime/changed', array( self::class, 'handle_site_changed' ) );
		remove_action( 'admin_init', array( Static_Prerender_Early_Serve::class, 'maybe_sync' ), 50 );
		remove_action( self::CRON_HOOK, array( self::class, 'run_cron_warmer' ) );
		remove_action( 'after_setup_theme', array( self::class, 'register_features' ), PHP_INT_MAX );
		remove_action( 'wp_delete_site', array( Static_Prerender_Early_Serve::class, 'remove_deleted_site' ) );
		self::unschedule_cron();
		self::reset_request_cache();
		Static_Prerender_Early_Serve::remove_current_site();
		delete_option( self::FEATURE_STATE_OPTION );
		self::$registered          = false;
		self::$features_registered = false;
	}

	/**
	 * Clean side effects left by pre-7.6.6 disabled configurations once.
	 *
	 * Normal requests use the persisted transition state below; this method is
	 * reserved for the version migration where no prior state option exists.
	 *
	 * @return void
	 */
	public static function migrate_inactive_features(): void {
		if (
			! self::enabled( 'staticPrerender/enabled' ) ||
			! self::enabled( 'staticPrerender/earlyServe' )
		) {
			Static_Prerender_Early_Serve::remove_current_site();
		}

		if ( ! self::warm_enabled() ) {
			self::unschedule_cron();
		}
	}

	/**
	 * Whether the current request renders inside a batch prerender.
	 *
	 * @return bool
	 */
	private static function in_batch_render(): bool {
		return class_exists( Static_Prerender_Batch_Renderer::class, false ) && Static_Prerender_Batch_Renderer::is_rendering();
	}

	/**
	 * Serve a warm hit or begin capturing a cold anonymous response.
	 *
	 * @return void
	 */
	public static function maybe_serve_or_buffer(): void {
		if ( self::in_batch_render() ) {
			return;
		}

		$server  = is_array( $_SERVER ) ? $_SERVER : array();
		$cookies = is_array( $_COOKIE ) ? $_COOKIE : array();

		if ( self::should_bypass_request( $server, $cookies, true ) ) {
			return;
		}

		$key = self::cache_key_for_request( $server );
		if ( null === $key ) {
			return;
		}

		if ( ! self::is_warm_request( $server ) && self::serve_cached_response( $key ) ) {
			// @codeCoverageIgnoreStart
			exit;
			// @codeCoverageIgnoreEnd
		}

		// User requests may consume anonymous HTML when explicitly allowed, but
		// they never author a cache entry from personalized output.
		if ( self::request_belongs_to_user( $cookies ) ) {
			return;
		}

		if ( ! self::is_warm_request( $server ) ) {
			$miss = Static_Prerender_Miss_Lock::acquire(
				$key,
				static fn(): bool => is_file( self::cache_file( $key ) )
			);

			if ( 'ready' === $miss && self::serve_cached_response( $key ) ) {
				// @codeCoverageIgnoreStart
				exit;
				// @codeCoverageIgnoreEnd
			}
		}

		if ( self::is_warm_request( $server ) ) {
			self::begin_dependency_report( $server );
		}

		self::$buffer_key = $key;
		self::$buffer_url = self::url_from_request( $server );
		ob_start( array( self::class, 'capture_response' ) );
	}

	/**
	 * Determine whether a request is unsafe for static caching.
	 *
	 * @param array<string,mixed> $server              Server values.
	 * @param array<string,mixed> $cookies             Cookie values.
	 * @param bool                $use_wordpress_state Include live WordPress request flags.
	 *
	 * @return bool Whether to bypass.
	 */
	public static function should_bypass_request(
		array $server,
		array $cookies,
		bool $use_wordpress_state = false
	): bool {
		$method = strtoupper( (string) ( $server['REQUEST_METHOD'] ?? 'GET' ) );
		$bypass = 'GET' !== $method || self::wants_markdown( $server );

		if ( ! $bypass ) {
			foreach ( array( 'HTTP_X_BLOCKSTUDIO_PREVIEW', 'HTTP_X_BLOCKSTUDIO_CANVAS', 'HTTP_X_WP_NONCE' ) as $header ) {
				if ( '' !== trim( (string) ( $server[ $header ] ?? '' ) ) ) {
					$bypass = true;
					break;
				}
			}
		}

		if ( ! $bypass && $use_wordpress_state && function_exists( 'is_admin' ) && is_admin() ) {
			$bypass = true;
		}
		if ( ! $bypass && $use_wordpress_state && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$bypass = true;
		}
		if ( ! $bypass && $use_wordpress_state && function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			$bypass = true;
		}
		if ( ! $bypass && $use_wordpress_state && function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			$bypass = true;
		}
		if ( ! $bypass && $use_wordpress_state && defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			$bypass = true;
		}

		$uri = (string) ( $server['REQUEST_URI'] ?? '/' );
		if ( ! $bypass && ( '' === $uri || str_contains( $uri, '?' ) ) ) {
			$bypass = true;
		}

		$path = self::url_path( $uri );
		if ( ! $bypass && ( self::is_bypassed_path( $path ) || self::is_dynamic_path( $path ) ) ) {
			$bypass = true;
		}

		if (
			! $bypass &&
			self::request_belongs_to_user( $cookies ) &&
			! (bool) self::value( 'staticPrerender/serveLoggedIn', false )
		) {
			$bypass = true;
		}

		/**
		 * Filter whether a request bypasses static prerendering.
		 *
		 * @since 7.6.0
		 *
		 * @param bool                $bypass  Current decision.
		 * @param array<string,mixed> $server  Server values.
		 * @param array<string,mixed> $cookies Cookie values.
		 */
		return (bool) apply_filters( 'blockstudio/static_prerender/request_bypass', $bypass, $server, $cookies );
	}

	/**
	 * Whether a request negotiates Markdown through its Accept header.
	 *
	 * Stored documents are HTML, so such a request is never answered from
	 * the prerender store or the early-serve drop-in and reaches the page's
	 * Markdown handler instead.
	 *
	 * @since 7.7.0
	 *
	 * @param array<string,mixed> $server Server values.
	 *
	 * @return bool Whether the Accept header asks for text/markdown.
	 */
	public static function wants_markdown( array $server ): bool {
		$accept = $server['HTTP_ACCEPT'] ?? '';

		return is_string( $accept ) && false !== stripos( $accept, 'text/markdown' );
	}

	/**
	 * Detect a WordPress user or personalized WordPress cookie.
	 *
	 * @param array<string,mixed> $cookies Cookies.
	 *
	 * @return bool Whether personalized.
	 */
	public static function request_belongs_to_user( array $cookies ): bool {
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return true;
		}

		foreach ( array_keys( $cookies ) as $name ) {
			$name = strtolower( (string) $name );
			if (
				str_starts_with( $name, 'wordpress_logged_in_' ) ||
				str_starts_with( $name, 'wp-postpass_' ) ||
				str_starts_with( $name, 'comment_author_email_' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a path matches a declared dynamic prefix.
	 *
	 * @param string $path Request path.
	 *
	 * @return bool Whether dynamic.
	 */
	public static function is_dynamic_path( string $path ): bool {
		$prefixes = (array) self::value( 'staticPrerender/dynamicPaths', array() );

		if ( array() === $prefixes ) {
			return false;
		}

		$home_path = function_exists( 'home_url' )
			? self::url_path( (string) home_url( '/' ) )
			: '/';
		$home_path = '/' . trim( $home_path, '/' );
		$home_path = '/' === $home_path ? '/' : $home_path . '/';
		$path      = strtolower( '/' . ltrim( $path, '/' ) );

		foreach ( $prefixes as $prefix ) {
			if ( ! is_string( $prefix ) || '' === trim( $prefix ) ) {
				continue;
			}

			$dynamic = rtrim( strtolower( $home_path . trim( $prefix, '/' ) ), '/' );
			if ( str_starts_with( $path . '/', $dynamic . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the current request cache key.
	 *
	 * @param array<string,mixed> $server Server values.
	 *
	 * @return string|null Cache key.
	 */
	public static function cache_key_for_request( array $server ): ?string {
		$uri = (string) ( $server['REQUEST_URI'] ?? '/' );
		if ( '' === $uri || str_contains( $uri, '?' ) ) {
			return null;
		}

		$host = strtolower( (string) ( $server['HTTP_HOST'] ?? 'localhost' ) );
		$path = self::url_path( $uri );

		return self::cache_key_for_host_path( $host, $path );
	}

	/**
	 * Build a cache key for a URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string|null Cache key.
	 */
	public static function cache_key_for_url( string $url ): ?string {
		$url = self::normalize_url_for_cache( $url );
		if ( null === $url ) {
			return null;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return null;
		}

		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( is_int( $parts['port'] ?? null ) ) {
			$host .= ':' . $parts['port'];
		}

		return self::cache_key_for_host_path( $host, (string) ( $parts['path'] ?? '/' ) );
	}

	/**
	 * Leave this request's persistence to the batch renderer that forked it.
	 *
	 * @return void
	 */
	public static function suppress_capture(): void {
		self::$capture_suppressed = true;
	}

	/**
	 * Persist a captured anonymous response.
	 *
	 * @param string $buffer Response buffer.
	 *
	 * @return string Original buffer.
	 */
	public static function capture_response( string $buffer ): string {
		$key    = self::$buffer_key;
		$url    = self::$buffer_url;
		$status = self::$response_status ?? http_response_code();

		self::$buffer_key = null;
		self::$buffer_url = null;

		try {
			self::finish_dependency_report();

			if (
				self::$capture_suppressed ||
				null === $key ||
				! self::cacheable_html( $buffer, is_int( $status ) && $status > 0 ? $status : null )
			) {
				return $buffer;
			}

			// Graph records are authored only by explicit builds. A normal web
			// miss may fill its identity-keyed fallback without mutating the graph.
			self::persist_prerendered_html(
				$key,
				$buffer,
				self::graph_enabled() ? null : $url,
				headers_list()
			);

			return $buffer;
		} finally {
			if ( null !== $key ) {
				Static_Prerender_Miss_Lock::release( $key );
			}
		}
	}

	/**
	 * Warm one URL through HTTP.
	 *
	 * @param string $url URL.
	 *
	 * @return bool Whether cached.
	 */
	public static function prerender_url( string $url ): bool {
		$url = self::normalize_url_for_cache( $url );
		if ( null === $url || ! function_exists( 'wp_remote_get' ) ) {
			return false;
		}

		$key = self::cache_key_for_url( $url );
		if ( null === $key ) {
			return false;
		}

		$token    = self::dependency_report_token();
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					self::WARM_HEADER       => '1',
					self::DEPENDENCY_HEADER => $token,
				),
				'user-agent'  => 'Blockstudio static prerender warmer',
			)
		);
		$report   = self::take_render_report( $token );

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		return 200 === $code
			&& self::cacheable_html( $body )
			&& self::persist_prerendered_html(
				$key,
				$body,
				self::graph_enabled() ? null : $url,
				$report['headers'] ?? wp_remote_retrieve_headers( $response )
			);
	}

	/**
	 * Stream a fresh cache entry.
	 *
	 * @param string $key Cache key.
	 *
	 * @return bool Whether served.
	 */
	public static function serve_cached_response( string $key ): bool {
		$path = self::cache_file( $key );

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			self::record_outcome( 'miss-absent' );

			return false;
		}

		$ttl   = (int) self::value( 'staticPrerender/ttl', 86400 );
		$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove the file between listing and stat.
		if ( $ttl > 0 && ( false === $mtime || (int) $mtime < time() - $ttl ) ) {
			self::record_outcome( 'miss-stale' );

			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading captured HTML.
		$contents = file_get_contents( $path );
		if ( false === $contents || '' === $contents ) {
			self::record_outcome( 'miss-unreadable' );

			return false;
		}

		if ( ! self::document_assets_resolve( $contents ) ) {
			self::record_outcome( 'miss-assets' );

			return false;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'Vary: Accept', false );
			header( 'X-Blockstudio-Static-Prerender: HIT' );
			Static_Prerender_Headers::send( $path );
		}

		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Verbatim HTML generated by WordPress.
		self::record_outcome( 'hit' );

		return true;
	}

	/**
	 * Check whether a cached document still resolves its linked build assets.
	 *
	 * A stored document pins content-hashed cache files by URL while those
	 * files keep their own retention. Serving a document whose stylesheet has
	 * since been pruned renders the page unstyled, and the cached path never
	 * reaches the compiler that would rebuild it, so the page stays broken
	 * until something else invalidates it. Treating the document as a miss
	 * lets one live render republish the asset and the document together.
	 *
	 * @param string $html Document markup.
	 *
	 * @return bool Whether the document is safe to serve.
	 */
	public static function document_assets_resolve( string $html ): bool {
		if ( ! class_exists( Tailwind::class ) ) {
			return true;
		}

		return Tailwind::document_stylesheet_resolves( $html );
	}

	/**
	 * Purge cached HTML and graph state for the current site/context.
	 *
	 * @return int Number of files removed.
	 */
	public static function purge(): int {
		$removed  = Runtime_Cache::purge_every_namespace( 'static-prerender' );
		$removed += Runtime_Cache::purge_every_namespace( 'static-prerender-state' );
		$removed += Runtime_Cache::purge_every_namespace( 'static-prerender-queue' );

		self::reset_request_cache();

		return $removed;
	}

	/**
	 * Add the configured warm interval to cron schedules.
	 *
	 * @param array<string,mixed> $schedules Schedules.
	 *
	 * @return array<string,mixed> Schedules.
	 */
	public static function filter_cron_schedules( array $schedules ): array {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => self::warm_interval(),
			'display'  => 'Blockstudio static prerender interval',
		);

		return $schedules;
	}

	/**
	 * Record the status WordPress declared for the current response.
	 *
	 * @param string $header Status header.
	 * @param int    $code   Status code.
	 *
	 * @return string Status header.
	 */
	public static function record_status_header( string $header, int $code ): string {
		self::$response_status = $code;

		return $header;
	}

	/**
	 * Invalidate or warm URLs affected by a content change.
	 *
	 * @param mixed $post_id Post ID.
	 * @param mixed ...$unused Unused hook args.
	 *
	 * @return void
	 */
	public static function handle_content_changed( mixed $post_id = null, mixed ...$unused ): void {
		unset( $unused );

		$urls = array();
		if ( function_exists( 'home_url' ) ) {
			$urls[] = home_url( '/' );
		}
		if ( is_scalar( $post_id ) && function_exists( 'get_permalink' ) ) {
			$permalink = get_permalink( (int) $post_id );
			if ( is_string( $permalink ) && '' !== trim( $permalink ) ) {
				$urls[] = $permalink;
			}
		}

		$urls = self::unique_cacheable_urls( $urls );

		// File-backed content reconciliation may emit save_post while an
		// explicit WP-CLI build is assembling its replacement graph. That build
		// owns the atomic cutover, so bootstrap-side mutations must not purge or
		// enqueue the graph beneath it.
		if ( self::wp_cli_is_running() ) {
			return;
		}

		if ( self::warm_enabled() ) {
			self::enqueue_urls( $urls, 'content-change' );
			self::work_queue( self::warm_concurrency() );

			return;
		}

		self::purge_urls( $urls );
	}

	/**
	 * Rotate site-wide identity and synchronize early serving.
	 *
	 * @return void
	 */
	public static function handle_site_changed(): void {
		Static_Prerender_Identity::rotate( 'site-change' );
		if ( ! self::graph_enabled() && self::enabled( 'staticPrerender/earlyServe' ) ) {
			Static_Prerender_Early_Serve::maybe_sync();
		}

		if ( self::warm_enabled() ) {
			$queued = self::enqueue_stale_urls( null, 'site-change' );
			if ( $queued > 0 ) {
				self::work_queue( self::warm_concurrency() );
			}
		}
	}

	/**
	 * Run the scheduled warmer.
	 *
	 * @return void
	 */
	public static function run_cron_warmer(): void {
		if ( ! self::warm_enabled() ) {
			self::unschedule_cron();

			return;
		}

		self::warm_stale_urls();
	}

	/**
	 * Enqueue and process stale public URLs.
	 *
	 * @return array{queued:int,processed:int,warmed:int,skipped:int,failed:int}
	 */
	public static function warm_stale_urls(): array {
		$queued = self::enqueue_stale_urls();
		$result = self::work_queue( self::warm_concurrency() );

		return array_merge( array( 'queued' => $queued ), $result );
	}

	/**
	 * Enqueue stale public URLs.
	 *
	 * @param int|null $limit  Optional limit.
	 * @param string   $reason Queue reason.
	 *
	 * @return int Number enqueued.
	 */
	public static function enqueue_stale_urls( ?int $limit = null, string $reason = 'interval' ): int {
		if ( self::graph_enabled() ) {
			return 0;
		}

		$urls = array();
		foreach ( self::public_urls() as $url ) {
			if ( ! self::url_is_stale( $url ) ) {
				continue;
			}

			$urls[] = $url;
			if ( null !== $limit && count( $urls ) >= $limit ) {
				break;
			}
		}

		return self::enqueue_urls( $urls, $reason );
	}

	/**
	 * Process queued warm jobs.
	 *
	 * @param int|null $limit Optional limit.
	 *
	 * @return array{processed:int,warmed:int,skipped:int,failed:int}
	 */
	public static function work_queue( ?int $limit = null ): array {
		$limit = null === $limit ? self::warm_concurrency() : max( 1, $limit );
		$queue = self::queue();
		$queue->requeue_timed_out( self::QUEUE_TIMEOUT_SECONDS );
		$result = array(
			'processed' => 0,
			'warmed'    => 0,
			'skipped'   => 0,
			'failed'    => 0,
		);

		for ( $index = 0; $index < $limit; ++$index ) {
			$job = $queue->claim();
			if ( null === $job ) {
				break;
			}

			++$result['processed'];
			$data = $job['data'];
			$url  = is_string( $data['url'] ?? null ) ? $data['url'] : '';

			if ( ! self::queue_job_requires_warm( $data ) ) {
				++$result['skipped'];
				$queue->complete( $job['id'], $job['token'] );
				continue;
			}

			if ( 'internal' === self::warm_transport() ) {
				$render = Static_Prerender_Batch_Renderer::render(
					array( $url ),
					array(
						'transport' => 'internal',
						'fallback'  => 'http',
					)
				);
				$warmed = 1 === (int) ( $render['rendered'] ?? 0 );
			} else {
				$warmed = self::prerender_url( $url );
			}

			if ( $warmed ) {
				++$result['warmed'];
			} else {
				++$result['failed'];
			}

			$queue->complete( $job['id'], $job['token'] );
		}

		return $result;
	}

	/**
	 * Discover public site URLs.
	 *
	 * @return string[] URLs.
	 */
	public static function public_urls(): array {
		$urls = array();

		if ( function_exists( 'home_url' ) ) {
			$urls[] = home_url( '/' );
		}

		$urls = array_merge( $urls, self::public_post_urls(), self::public_term_urls() );
		$urls = array_values(
			array_filter(
				self::unique_cacheable_urls( $urls ),
				static function ( string $url ): bool {
					$path = self::url_path( $url );

					return ! self::is_dynamic_path( $path ) && ! self::is_bypassed_path( $path );
				}
			)
		);

		/**
		 * Filter public URLs included by static prerender builds.
		 *
		 * @since 7.6.0
		 *
		 * @param string[] $urls Public URLs.
		 */
		$filtered = apply_filters( 'blockstudio/static_prerender/public_urls', $urls );
		$filtered = self::unique_cacheable_urls(
			is_array( $filtered ) ? array_values( array_filter( $filtered, 'is_string' ) ) : $urls
		);

		return array_values(
			array_filter(
				$filtered,
				static function ( string $url ): bool {
					$path = self::url_path( $url );

					return ! self::is_dynamic_path( $path ) && ! self::is_bypassed_path( $path );
				}
			)
		);
	}

	/**
	 * Return warm queue counts.
	 *
	 * @return array{pending:int,processing:int,done:int}
	 */
	public static function queue_counts(): array {
		return self::queue()->counts();
	}

	/**
	 * Return warm queue statistics.
	 *
	 * @return array<string,int>
	 */
	public static function queue_stats(): array {
		return self::queue()->stats();
	}

	/**
	 * Reset the warm queue.
	 *
	 * @return array{removed:int}
	 */
	public static function reset_queue(): array {
		return self::queue()->reset();
	}

	/**
	 * Compute an incremental dependency-graph build plan.
	 *
	 * @param string[]    $source_urls Source URLs.
	 * @param string|null $target_host Optional target host.
	 * @param string|null $target_home Optional target home path.
	 * @param bool        $force       Force every URL.
	 *
	 * @return array<string,mixed> Build plan.
	 */
	public static function build_plan(
		array $source_urls,
		?string $target_host = null,
		?string $target_home = null,
		bool $force = false
	): array {
		$source_urls = self::unique_cacheable_urls( $source_urls );
		sort( $source_urls, SORT_STRING );
		$live          = array();
		$affected      = array();
		$unchanged     = array();
		$changed_files = array();
		$reasons       = array();
		$shared        = Static_Prerender_Content_Hasher::shared_snapshot( self::theme_root() );

		foreach ( $source_urls as $source_url ) {
			$target_url = self::target_url( $source_url, $target_host, $target_home );
			if ( null === $target_url ) {
				continue;
			}

			$live[ $target_url ] = $source_url;
			$record              = self::index_record_for_url( $target_url );
			$url_reasons         = array();

			if ( $force ) {
				$url_reasons[] = 'forced';
			}
			if ( null === $record ) {
				$url_reasons[] = 'new';
			} else {
				$state = self::graph_state_for_record( $record );

				foreach ( $state['changedFiles'] as $file ) {
					$changed_files[ $file ] = true;
				}

				$previous_shared = is_array( $record['sharedHashes'] ?? null )
					? self::string_map( $record['sharedHashes'] )
					: array();
				foreach ( array_unique( array_merge( array_keys( $previous_shared ), array_keys( $shared['hashes'] ) ) ) as $file ) {
					if ( ( $previous_shared[ $file ] ?? null ) !== ( $shared['hashes'][ $file ] ?? null ) ) {
						$changed_files[ $file ] = true;
					}
				}

				$mode = is_string( $record['mode'] ?? null ) ? $record['mode'] : '';
				if ( ! in_array( $mode, array( 'graph', 'skip' ), true ) ) {
					$url_reasons[] = 'legacy-key';
				}
				if ( ( $record['sharedHash'] ?? null ) !== $shared['hash'] ) {
					$url_reasons[] = 'shared';
				}
				if (
					array() !== $state['changedFiles'] ||
					( $record['pageHash'] ?? null ) !== $state['pageHash']
				) {
					$url_reasons[] = 'page';
				}

				if ( 'skip' !== $mode ) {
					$key         = is_string( $record['cacheKey'] ?? null ) ? $record['cacheKey'] : '';
					$desired_key = self::cache_key_for_url_hashes(
						$target_url,
						$shared['hash'],
						$state['pageHash']
					);

					if (
						null === $desired_key ||
						$key !== $desired_key ||
						'' === $key ||
						( ! is_file( self::cache_file( $key ) ) && ! is_file( self::artifact_file( $key ) ) )
					) {
						$url_reasons[] = 'cache';
					}
				}

				$ttl         = (int) self::value( 'staticPrerender/ttl', 86400 );
				$last_warmed = is_numeric( $record['lastWarmed'] ?? null )
					? (int) $record['lastWarmed']
					: 0;
				if ( $ttl > 0 && $last_warmed < time() - $ttl ) {
					$url_reasons[] = 'ttl';
				}
			}

			$url_reasons = array_values( array_unique( $url_reasons ) );
			if ( array() === $url_reasons ) {
				$unchanged[] = $source_url;
			} else {
				$affected[]             = $source_url;
				$reasons[ $source_url ] = $url_reasons;
			}
		}

		$removed = array();
		$scope   = self::target_scope( $source_urls, $target_host, $target_home );

		foreach ( self::all_index_records() as $record ) {
			$url = is_string( $record['url'] ?? null ) ? $record['url'] : '';
			if ( '' !== $url && self::url_in_scope( $url, $scope ) && ! isset( $live[ $url ] ) ) {
				$removed[] = $url;
			}
		}

		sort( $affected, SORT_STRING );
		sort( $unchanged, SORT_STRING );
		sort( $removed, SORT_STRING );
		$changed_files = array_keys( $changed_files );
		sort( $changed_files, SORT_STRING );

		return array(
			'sharedHash'    => $shared['hash'],
			'total'         => count( $live ),
			'affected'      => count( $affected ),
			'unchanged'     => count( $unchanged ),
			'removed'       => count( $removed ),
			'changedFiles'  => $changed_files,
			'affectedUrls'  => $affected,
			'unchangedUrls' => $unchanged,
			'removedUrls'   => $removed,
			'reasons'       => $reasons,
			'live'          => $live,
			'targetHost'    => $scope['host'],
			'targetHome'    => $scope['home'],
		);
	}

	/**
	 * Create a signed token that asks one warm request for a dependency report.
	 *
	 * Graph builds fetch every route over HTTP, so the files a page reads are
	 * only known inside that warm request, which is a separate PHP process. A
	 * request carrying this token in the DEPENDENCY_HEADER writes the files it
	 * read to a report in the site's cache directory, and
	 * take_dependency_report() collects it once the response has arrived. The
	 * signature keeps visitors from making a site write reports.
	 *
	 * @return string Token for the dependency header.
	 */
	public static function dependency_report_token(): string {
		$id = bin2hex( random_bytes( 16 ) );

		return $id . '.' . self::dependency_report_signature( $id );
	}

	/**
	 * Collect and remove the dependency report of one warm request.
	 *
	 * @param string $token Token the request was sent with.
	 *
	 * @return string[] Dependency paths, empty when the request reported none.
	 */
	public static function take_dependency_report( string $token ): array {
		return self::take_render_report( $token )['files'];
	}

	/**
	 * Collect and remove the full report of one warm request.
	 *
	 * Besides the files the request read, the report carries the allowlisted
	 * headers the request sent through PHP. They are taken from the rendering
	 * process rather than from the HTTP response, so headers a web server adds
	 * on its own, and adds again to every served document, are never stored.
	 * `headers` is null when the request wrote no report.
	 *
	 * @since 7.7.13
	 *
	 * @param string $token Token the request was sent with.
	 *
	 * @return array{files:string[],headers:array<int,array{0:string,1:string}>|null} Report.
	 */
	public static function take_render_report( string $token ): array {
		$id = self::dependency_report_id( $token );
		if ( null === $id ) {
			return array(
				'files'   => array(),
				'headers' => null,
			);
		}

		$directory = self::dependency_report_directory();
		$path      = $directory . '/' . $id . '.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local report.
		$contents = is_file( $path ) ? file_get_contents( $path ) : false;
		@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal report; it may not exist.

		// A renderer that died before collecting leaves its reports behind.
		$reports = glob( $directory . '/*.json' );
		foreach ( is_array( $reports ) ? $reports : array() as $report ) {
			$mtime = @filemtime( $report ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent renderers can remove the report.
			if ( false !== $mtime && $mtime < time() - HOUR_IN_SECONDS ) {
				@unlink( $report ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal report.
			}
		}

		$decoded = is_string( $contents ) ? json_decode( $contents, true ) : null;
		$files   = is_array( $decoded ) && is_array( $decoded['files'] ?? null ) ? $decoded['files'] : array();

		return array(
			'files'   => array_values(
				array_filter(
					$files,
					static fn( mixed $file ): bool => is_string( $file ) && '' !== $file
				)
			),
			'headers' => is_array( $decoded ) && is_array( $decoded['headers'] ?? null )
				? Static_Prerender_Headers::select( array_values( $decoded['headers'] ) )
				: null,
		);
	}

	/**
	 * Record files the current warm render read.
	 *
	 * Does nothing outside a warm request that was asked for a dependency
	 * report, so ordinary requests pay one null check.
	 *
	 * @param array<mixed> $paths Dependency paths.
	 *
	 * @return void
	 */
	public static function record_dependencies( array $paths ): void {
		if ( null === self::$dependency_report ) {
			return;
		}

		foreach ( $paths as $path ) {
			if ( is_string( $path ) && '' !== $path ) {
				self::$dependencies[ wp_normalize_path( $path ) ] = true;
			}
		}
	}

	/**
	 * Record the template WordPress loads for a reporting warm request.
	 *
	 * @param mixed $template Template path.
	 *
	 * @return mixed Unchanged template path.
	 */
	public static function record_template_dependency( mixed $template ): mixed {
		if ( is_string( $template ) && '' !== $template ) {
			self::record_dependencies( array( $template ) );
		}

		return $template;
	}

	/**
	 * Persist one successful graph render.
	 *
	 * @param string              $source_url     Source URL.
	 * @param string              $html           Full HTML document.
	 * @param string[]            $dependencies   Dependency files.
	 * @param array<string,mixed> $virtual_hashes Non-file dependency hashes.
	 * @param string|null         $target_host     Optional target host.
	 * @param string|null         $target_home     Optional target home path.
	 * @param iterable            $headers         Response headers, as `Name: value` lines or a name to value map. Only the `staticPrerender.replayHeaders` names are stored.
	 *
	 * @return bool Whether the graph record and HTML were published.
	 */
	public static function persist_built_response(
		string $source_url,
		string $html,
		array $dependencies,
		array $virtual_hashes,
		?string $target_host = null,
		?string $target_home = null,
		iterable $headers = array()
	): bool {
		$source_url = self::normalize_url_for_cache( $source_url );
		if ( null === $source_url || ! self::cacheable_html( $html ) ) {
			return false;
		}

		$target_url = self::target_url( $source_url, $target_host, $target_home );
		if ( null === $target_url ) {
			return false;
		}

		$theme_root = self::theme_root();
		$snapshot   = Static_Prerender_Content_Hasher::snapshot( $dependencies, $theme_root );
		$virtual    = self::string_map( $virtual_hashes );
		$hashes     = array_merge( $snapshot['hashes'], $virtual );
		ksort( $hashes, SORT_STRING );
		$page_hash = Static_Prerender_Content_Hasher::content_hash( $hashes );
		$shared    = Static_Prerender_Content_Hasher::shared_snapshot( $theme_root );
		$key       = self::cache_key_for_url_hashes( $target_url, $shared['hash'], $page_hash );

		$persisted = null !== $key && (
			self::in_batch_render()
				? self::persist_build_artifact( $key, $html, $headers )
				: self::persist_prerendered_html( $key, $html, null, $headers )
		);
		if ( null === $key || ! $persisted ) {
			return false;
		}

		$record = array(
			'format'           => 1,
			'mode'             => 'graph',
			'url'              => $target_url,
			'sourceUrl'        => $source_url,
			'cacheKey'         => $key,
			'sharedHash'       => $shared['hash'],
			'sharedHashes'     => $shared['hashes'],
			'pageHash'         => $page_hash,
			'dependencyHashes' => $hashes,
			'dependencyPaths'  => $snapshot['paths'],
			'virtualHashes'    => $virtual,
			'lastWarmed'       => time(),
		);

		if ( ! self::write_index_record( $target_url, $record ) ) {
			return false;
		}

		self::record_outcome( 'graph-write' );

		return true;
	}

	/**
	 * Persist one known non-cacheable graph result.
	 *
	 * @param string              $source_url     Source URL.
	 * @param string              $reason         Skip reason.
	 * @param int                 $status         HTTP status.
	 * @param string[]            $dependencies   Dependency files.
	 * @param array<string,mixed> $virtual_hashes Non-file hashes.
	 * @param string|null         $target_host     Optional target host.
	 * @param string|null         $target_home     Optional target home.
	 *
	 * @return bool Whether recorded.
	 */
	public static function persist_skipped_response(
		string $source_url,
		string $reason,
		int $status,
		array $dependencies,
		array $virtual_hashes,
		?string $target_host = null,
		?string $target_home = null
	): bool {
		$source_url = self::normalize_url_for_cache( $source_url );
		if ( null === $source_url ) {
			return false;
		}

		$target_url = self::target_url( $source_url, $target_host, $target_home );
		if ( null === $target_url ) {
			return false;
		}

		$theme_root = self::theme_root();
		$snapshot   = Static_Prerender_Content_Hasher::snapshot( $dependencies, $theme_root );
		$virtual    = self::string_map( $virtual_hashes );
		$hashes     = array_merge( $snapshot['hashes'], $virtual );
		$shared     = Static_Prerender_Content_Hasher::shared_snapshot( $theme_root );

		return self::write_index_record(
			$target_url,
			array(
				'format'           => 1,
				'mode'             => 'skip',
				'url'              => $target_url,
				'sourceUrl'        => $source_url,
				'reason'           => sanitize_key( $reason ),
				'httpStatus'       => $status,
				'sharedHash'       => $shared['hash'],
				'sharedHashes'     => $shared['hashes'],
				'pageHash'         => Static_Prerender_Content_Hasher::content_hash( $hashes ),
				'dependencyHashes' => $hashes,
				'dependencyPaths'  => $snapshot['paths'],
				'virtualHashes'    => $virtual,
				'lastWarmed'       => time(),
			)
		);
	}

	/**
	 * Garbage collect removed graph records and unreferenced HTML.
	 *
	 * @param string[] $live_target_urls Live targets.
	 *
	 * @return array{deletedFiles:int,deletedRecords:int,liveFiles:int}
	 */
	public static function garbage_collect( array $live_target_urls ): array {
		$lock = Single_Flight::acquire( self::state_root() . '/.build.lock' );
		if ( false === $lock ) {
			return array(
				'deletedFiles'   => 0,
				'deletedRecords' => 0,
				'liveFiles'      => count( self::all_index_records() ),
			);
		}

		try {
			$live_target_urls = self::unique_cacheable_urls( $live_target_urls );
			$live_urls        = array_fill_keys( $live_target_urls, true );
			$scope            = self::target_scope_from_targets( $live_target_urls );
			$deleted_records  = 0;

			foreach ( self::all_index_records() as $record ) {
				$url = is_string( $record['url'] ?? null ) ? $record['url'] : '';
				if ( '' !== $url && self::url_in_scope( $url, $scope ) && ! isset( $live_urls[ $url ] ) ) {
					if ( self::delete_index_record( $url ) ) {
						++$deleted_records;
					}
				}
			}

			$live_keys = array();
			$live_sets = array();
			foreach ( self::all_index_records() as $record ) {
				$key = is_string( $record['cacheKey'] ?? null ) ? $record['cacheKey'] : '';
				if ( '' !== $key ) {
					$live_keys[ $key ] = true;
				}
				foreach ( is_array( $record['dependencyChunks'] ?? null ) ? $record['dependencyChunks'] : array() as $chunk ) {
					if ( is_string( $chunk ) ) {
						$live_sets[ $chunk ] = true;
					}
				}
			}
			self::collect_dependency_sets( $live_sets );

			$deleted_files = 0;
			$files         = glob( self::cache_root() . '/*.html' );
			foreach ( is_array( $files ) ? $files : array() as $file ) {
				$key = basename( $file, '.html' );
				if ( isset( $live_keys[ $key ] ) ) {
					continue;
				}

				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
				if ( ! is_file( $file ) ) {
					Static_Prerender_Headers::remove( $file );
					++$deleted_files;
				}
			}
			Static_Prerender_Headers::collect_orphans( self::cache_root() );

			return array(
				'deletedFiles'   => $deleted_files,
				'deletedRecords' => $deleted_records,
				'liveFiles'      => count( $live_keys ),
			);
		} finally {
			if ( is_resource( $lock ) ) {
				Single_Flight::release( $lock );
			}
		}
	}

	/**
	 * Build an early-serve route map.
	 *
	 * @param string[] $live_target_urls Live targets.
	 *
	 * @return array<string,string> Path to cache key.
	 */
	public static function route_map( array $live_target_urls ): array {
		$live   = array_fill_keys( self::unique_cacheable_urls( $live_target_urls ), true );
		$routes = array();

		foreach ( self::all_index_records() as $record ) {
			$url = is_string( $record['url'] ?? null ) ? $record['url'] : '';
			$key = is_string( $record['cacheKey'] ?? null ) ? $record['cacheKey'] : '';
			if ( ! isset( $live[ $url ] ) || '' === $key || ( ! is_file( self::cache_file( $key ) ) && ! is_file( self::artifact_file( $key ) ) ) ) {
				continue;
			}

			$path            = strtolower( '/' . ltrim( self::url_path( $url ), '/' ) );
			$routes[ $path ] = $key;
		}

		ksort( $routes, SORT_STRING );

		return $routes;
	}

	/**
	 * Build a validated early-serve artifact entry.
	 *
	 * @param string[]    $live_target_urls Live targets.
	 * @param string|null $target_host      Optional target host.
	 * @param string|null $target_home      Optional target home.
	 *
	 * @return array<string,mixed> Entry.
	 */
	public static function artifact_entry(
		array $live_target_urls,
		?string $target_host = null,
		?string $target_home = null
	): array {
		$scope   = null !== $target_host || null !== $target_home
			? self::target_scope( self::public_urls(), $target_host, $target_home )
			: self::target_scope_from_targets( $live_target_urls );
		$dynamic = array();

		foreach ( (array) self::value( 'staticPrerender/dynamicPaths', array() ) as $prefix ) {
			if ( is_string( $prefix ) && '' !== trim( $prefix ) ) {
				$dynamic[] = strtolower( $scope['home'] . trim( $prefix, '/' ) );
			}
		}

		$routes   = self::route_map( $live_target_urls );
		$build_id = hash(
			'xxh128',
			(string) wp_json_encode(
				array(
					self::shared_signature(),
					$routes,
					$dynamic,
					(int) self::value( 'staticPrerender/ttl', 86400 ),
				)
			)
		);

		return array(
			'host'            => $scope['host'],
			'home_path'       => $scope['home'],
			'site_id'         => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0,
			'dir'             => self::cache_root(),
			'mode'            => 'graph',
			'build_id'        => $build_id,
			'signature'       => self::shared_signature(),
			'ttl'             => (int) self::value( 'staticPrerender/ttl', 86400 ),
			'serve_logged_in' => (bool) self::value( 'staticPrerender/serveLoggedIn', false ),
			'dynamic'         => $dynamic,
			'routes'          => $routes,
		);
	}

	/**
	 * Expose the HTML cache root.
	 *
	 * @return string Cache root.
	 */
	public static function cache_root_path(): string {
		return self::cache_root();
	}

	/**
	 * Return static-prerender status and storage diagnostics.
	 *
	 * @return array<string,mixed> Status.
	 */
	public static function status(): array {
		$files = glob( self::cache_root() . '/*.html' );
		$files = is_array( $files ) ? $files : array();
		$bytes = 0;
		foreach ( $files as $file ) {
			$bytes += (int) ( @filesize( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove the file between listing and stat.
		}

		return array(
			'enabled'     => self::enabled( 'staticPrerender/enabled' ),
			'invalidate'  => (string) self::value( 'staticPrerender/invalidate', 'signature' ),
			'identity'    => Static_Prerender_Identity::current(),
			'cacheRoot'   => self::cache_root(),
			'files'       => count( $files ),
			'bytes'       => $bytes,
			'records'     => count( self::all_index_records() ),
			'queue'       => self::queue_stats(),
			'map'         => Static_Prerender_Early_Serve::map_report(),
			'diagnostics' => Runtime_Cache::diagnostics(),
		);
	}

	/**
	 * Determine whether a response is a complete cacheable HTML document.
	 *
	 * Only a successful response may be persisted. An observed status other
	 * than 200 is never cacheable, so a themed error document cannot be
	 * replayed as a success by either serve path.
	 *
	 * @param string   $html   Response.
	 * @param int|null $status Observed HTTP status, or null when unknown.
	 *
	 * @return bool Whether cacheable.
	 */
	public static function cacheable_html( string $html, ?int $status = null ): bool {
		$trimmed   = ltrim( $html );
		$cacheable = ( null === $status || 200 === $status )
			&& '' !== $trimmed
			&& (
				str_starts_with( strtolower( $trimmed ), '<!doctype html' ) ||
				str_starts_with( strtolower( $trimmed ), '<html' )
			)
			&& ! str_contains( $html, '<!-- blockstudio:no-cache -->' )
			&& self::document_assets_resolve( $html );

		/**
		 * Filter whether a complete response may be cached.
		 *
		 * @since 7.6.0
		 *
		 * @param bool     $cacheable Current decision.
		 * @param string   $html      Response HTML.
		 * @param int|null $status    Observed HTTP status.
		 */
		return (bool) apply_filters( 'blockstudio/static_prerender/cacheable_html', $cacheable, $html, $status );
	}

	/**
	 * Rewrite a source URL into an optional deployment target scope.
	 *
	 * @param string      $source_url  Source URL.
	 * @param string|null $target_host Target host URL or hostname.
	 * @param string|null $target_home Target home path.
	 *
	 * @return string|null Target URL.
	 */
	public static function target_url(
		string $source_url,
		?string $target_host = null,
		?string $target_home = null
	): ?string {
		$source_url = self::normalize_url_for_cache( $source_url );
		if ( null === $source_url ) {
			return null;
		}
		if ( null === $target_host && null === $target_home ) {
			return $source_url;
		}

		$source_parts = wp_parse_url( $source_url );
		if ( ! is_array( $source_parts ) ) {
			return null;
		}

		$host_input = null === $target_host || '' === trim( $target_host )
			? (string) ( $source_parts['scheme'] ?? 'https' ) . '://' . (string) ( $source_parts['host'] ?? '' )
			: trim( $target_host );
		if ( ! str_contains( $host_input, '://' ) ) {
			$host_input = 'https://' . $host_input;
		}

		$host_parts = wp_parse_url( $host_input );
		if ( ! is_array( $host_parts ) || ! is_string( $host_parts['host'] ?? null ) || '' === $host_parts['host'] ) {
			return null;
		}

		$source_home = function_exists( 'home_url' )
			? self::url_path( (string) home_url( '/' ) )
			: '/';
		$source_home = '/' . trim( $source_home, '/' );
		$source_home = '/' === $source_home ? '/' : $source_home . '/';
		$source_path = '/' . ltrim( (string) ( $source_parts['path'] ?? '/' ), '/' );
		$relative    = str_starts_with( strtolower( $source_path ), strtolower( $source_home ) )
			? ltrim( substr( $source_path, strlen( $source_home ) ), '/' )
			: ltrim( $source_path, '/' );
		$home        = null === $target_home ? '/' : '/' . trim( $target_home, '/' );
		$home        = '/' === $home ? '/' : $home . '/';
		$path        = $home . $relative;
		$scheme      = is_string( $host_parts['scheme'] ?? null ) ? strtolower( $host_parts['scheme'] ) : 'https';
		$host        = strtolower( $host_parts['host'] );
		$port        = is_int( $host_parts['port'] ?? null ) ? ':' . $host_parts['port'] : '';

		return self::normalize_url_for_cache( $scheme . '://' . $host . $port . '/' . ltrim( $path, '/' ) );
	}

	/**
	 * Hash the active theme and runtime configuration.
	 *
	 * @return string Theme signature.
	 */
	public static function theme_signature(): string {
		return Static_Prerender_Content_Hasher::theme_snapshot(
			self::theme_root(),
			Runtime_Settings::current()->hash()
		)['hash'];
	}

	/**
	 * Hash shared runtime/page dependencies.
	 *
	 * @return string Shared signature.
	 */
	public static function shared_signature(): string {
		return Static_Prerender_Content_Hasher::shared_snapshot( self::theme_root() )['hash'];
	}

	/**
	 * Reset request-local static-prerender state.
	 *
	 * @return void
	 */
	public static function reset_request_cache(): void {
		self::$buffer_key           = null;
		self::$buffer_url           = null;
		self::$response_status      = null;
		self::$warm_queue           = null;
		self::$index_record_cache   = array();
		self::$dependency_set_cache = array();
		self::$dependency_report    = null;
		self::$dependencies         = array();
		self::$search_path_prefixes = array();
		remove_filter( 'template_include', array( self::class, 'record_template_dependency' ), PHP_INT_MAX );
		Static_Prerender_Identity::reset();
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Miss_Lock::release_all();
	}

	/**
	 * Persist one HTML object atomically.
	 *
	 * The allowlisted response headers are written beside the document before
	 * the document itself, so a reader that sees the new document also sees
	 * the headers it was rendered with.
	 *
	 * @param string      $key     Cache key.
	 * @param string      $html    HTML.
	 * @param string|null $url     Optional URL for signature index state.
	 * @param mixed       $headers Response headers.
	 *
	 * @return bool Whether published.
	 */
	private static function persist_prerendered_html( string $key, string $html, ?string $url, mixed $headers = array() ): bool {
		if ( ! self::cacheable_html( $html ) ) {
			return false;
		}

		$file = self::cache_file( $key );
		if ( ! Static_Prerender_Headers::store( $file, Static_Prerender_Headers::select( $headers ) ) ) {
			self::record_outcome( 'write-failure' );

			return false;
		}

		$published = Single_Flight::publish( $file, $html ) || Single_Flight::publish( $file, $html );

		if ( $published ) {
			Runtime_Cache::prune( 'static-prerender', $file );
			self::record_outcome( 'write' );
		} elseif ( is_file( $file ) ) {
			self::record_outcome( 'write-peer' );
		} else {
			self::record_outcome( 'write-failure' );

			return false;
		}

		if ( null !== $url ) {
			self::write_index_record(
				$url,
				array(
					'format'     => 1,
					'mode'       => 'signature',
					'url'        => $url,
					'cacheKey'   => $key,
					'lastWarmed' => time(),
				)
			);
		}

		return true;
	}

	/**
	 * Publish one build render straight into the uncapped artifact scope.
	 *
	 * @param string $key     Cache key.
	 * @param string $html    HTML.
	 * @param mixed  $headers Response headers.
	 *
	 * @return bool Whether published.
	 */
	private static function persist_build_artifact( string $key, string $html, mixed $headers = array() ): bool {
		$file = self::artifact_file( $key );
		$dir  = dirname( $file );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			self::record_outcome( 'write-failure' );

			return false;
		}
		if ( ! Static_Prerender_Headers::store( $file, Static_Prerender_Headers::select( $headers ) ) ) {
			self::record_outcome( 'write-failure' );

			return false;
		}
		if ( Single_Flight::publish( $file, $html ) || Single_Flight::publish( $file, $html ) ) {
			self::record_outcome( 'write' );

			return true;
		}
		if ( is_file( $file ) ) {
			self::record_outcome( 'write-peer' );

			return true;
		}
		self::record_outcome( 'write-failure' );

		return false;
	}

	/**
	 * Resolve one build artifact path.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string Path.
	 */
	private static function artifact_file( string $key ): string {
		return rtrim( wp_normalize_path( Runtime_Cache::directory( 'static-prerender-artifact' ) ), '/' ) . '/' . sanitize_file_name( $key ) . '.html';
	}

	/**
	 * Resolve one HTML object path.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string Path.
	 */
	private static function cache_file( string $key ): string {
		return self::cache_root() . '/' . sanitize_file_name( $key ) . '.html';
	}

	/**
	 * Resolve the static HTML root.
	 *
	 * @return string Root.
	 */
	private static function cache_root(): string {
		return Runtime_Cache::directory( 'static-prerender' );
	}

	/**
	 * Resolve static-prerender state root.
	 *
	 * @return string Root.
	 */
	private static function state_root(): string {
		$root = Runtime_Cache::directory( 'static-prerender-state' );
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}

		return $root;
	}

	/**
	 * Get the request-local warm queue.
	 *
	 * @return Static_Prerender_Warm_Queue Queue.
	 */
	private static function queue(): Static_Prerender_Warm_Queue {
		if ( null === self::$warm_queue ) {
			self::$warm_queue = new Static_Prerender_Warm_Queue();
		}

		return self::$warm_queue;
	}

	/**
	 * Read a graph record for a URL.
	 *
	 * @param string $url URL.
	 *
	 * @return array<string,mixed>|null Record.
	 */
	private static function index_record_for_url( string $url ): ?array {
		$id = self::url_hash( $url );
		if ( array_key_exists( $id, self::$index_record_cache ) ) {
			return self::$index_record_cache[ $id ];
		}

		$path = self::index_path( $id );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			self::$index_record_cache[ $id ] = null;

			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local graph state.
		$decoded                         = json_decode( (string) file_get_contents( $path ), true );
		$record                          = is_array( $decoded ) ? self::inflate_index_record( $decoded ) : null;
		self::$index_record_cache[ $id ] = $record;

		return $record;
	}

	/**
	 * Read every valid graph record as stored.
	 *
	 * Every caller reads URLs, cache keys and chunk hashes only, so the
	 * dependency maps stay unrestored; index_record_for_url() restores them
	 * for the one record that needs them.
	 *
	 * @return array<int,array<string,mixed>> Records.
	 */
	private static function all_index_records(): array {
		$root    = self::state_root() . '/url-index';
		$records = array();

		$paths = glob( $root . '/*.json' );
		foreach ( is_array( $paths ) ? $paths : array() as $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local graph state.
			$decoded = json_decode( (string) file_get_contents( $path ), true );
			if ( is_array( $decoded ) ) {
				$records[] = $decoded;
			}
		}

		return $records;
	}

	/**
	 * Publish one graph record.
	 *
	 * @param string              $url    URL.
	 * @param array<string,mixed> $record Record.
	 *
	 * @return bool Whether published.
	 */
	private static function write_index_record( string $url, array $record ): bool {
		$id     = self::url_hash( $url );
		$stored = self::deflate_index_record( $record );
		if ( null === $stored ) {
			return false;
		}
		$encoded = wp_json_encode( $stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $encoded ) || ! Single_Flight::publish( self::index_path( $id ), $encoded . "\n" ) ) {
			return false;
		}

		self::$index_record_cache[ $id ] = $record;

		return true;
	}

	/**
	 * Store a record's dependency hashes in shared chunks.
	 *
	 * Routes of one family read nearly the same files, so the hashes are
	 * split by directory into content-addressed chunks that many records
	 * name, paths are derived from their IDs, and only paths that differ from
	 * that derivation stay in the record.
	 *
	 * @param array<string,mixed> $record Record with inline dependency maps.
	 *
	 * @return array<string,mixed>|null Record to store, or null when a chunk could not be written.
	 */
	private static function deflate_index_record( array $record ): ?array {
		unset( $record['dependencySet'], $record['dependencyChunks'], $record['dependencyPathOverrides'] );
		if ( ! is_array( $record['dependencyHashes'] ?? null ) || ! is_array( $record['dependencyPaths'] ?? null ) ) {
			return $record;
		}
		$hashes  = self::string_map( $record['dependencyHashes'] );
		$paths   = self::string_map( $record['dependencyPaths'] );
		$virtual = is_array( $record['virtualHashes'] ?? null ) ? self::string_map( $record['virtualHashes'] ) : array();
		$root    = self::theme_root();
		$groups  = array();
		foreach ( $hashes as $id => $hash ) {
			if ( isset( $virtual[ $id ] ) && $virtual[ $id ] === $hash && ! isset( $paths[ $id ] ) ) {
				continue;
			}
			$groups[ self::dependency_group( $id ) ][ $id ] = $hash;
		}
		ksort( $groups, SORT_STRING );
		$chunks = array();
		foreach ( $groups as $group ) {
			ksort( $group, SORT_STRING );
			$encoded = wp_json_encode( $group, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( ! is_string( $encoded ) ) {
				return null;
			}
			$hash = hash( 'sha256', $encoded );
			$path = self::dependency_set_path( $hash );
			if ( ! is_file( $path ) && ! Single_Flight::publish( $path, $encoded . "\n" ) ) {
				return null;
			}
			$chunks[] = $hash;
		}
		$overrides = array();
		foreach ( $paths as $id => $path ) {
			if ( Static_Prerender_Content_Hasher::path_for_id( $id, $root ) !== wp_normalize_path( $path ) ) {
				$overrides[ $id ] = $path;
			}
		}
		unset( $record['dependencyHashes'], $record['dependencyPaths'] );
		$record['dependencyChunks'] = $chunks;
		if ( array() !== $overrides ) {
			$record['dependencyPathOverrides'] = $overrides;
		}

		return $record;
	}

	/**
	 * Group a dependency ID by its directory, at most four segments deep.
	 *
	 * @param string $id Dependency ID.
	 *
	 * @return string Group.
	 */
	private static function dependency_group( string $id ): string {
		$segments = explode( '/', $id );
		array_pop( $segments );

		return implode( '/', array_slice( $segments, 0, 4 ) );
	}

	/**
	 * Read one dependency chunk with the paths its IDs name, derived once per chunk.
	 *
	 * @param string $hash Chunk content hash.
	 *
	 * @return array{hashes:array<string,string>,paths:array<string,string>}|null Chunk, or null when it is gone.
	 */
	private static function dependency_chunk( string $hash ): ?array {
		if ( ! array_key_exists( $hash, self::$dependency_set_cache ) ) {
			$path    = self::dependency_set_path( $hash );
			$decoded = is_file( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local graph state.
			if ( ! is_array( $decoded ) ) {
				self::$dependency_set_cache[ $hash ] = null;
			} else {
				$hashes = self::string_map( $decoded );
				$paths  = array();
				$root   = self::theme_root();
				foreach ( array_keys( $hashes ) as $id ) {
					$resolved = Static_Prerender_Content_Hasher::path_for_id( (string) $id, $root );
					if ( null !== $resolved ) {
						$paths[ $id ] = $resolved;
					}
				}
				self::$dependency_set_cache[ $hash ] = array(
					'hashes' => $hashes,
					'paths'  => $paths,
				);
			}
		}

		return self::$dependency_set_cache[ $hash ];
	}

	/**
	 * Restore a stored record's dependency maps from its chunks.
	 *
	 * @param array<string,mixed> $record Stored record.
	 *
	 * @return array<string,mixed>|null Record with inline maps, or null when a chunk is gone.
	 */
	private static function inflate_index_record( array $record ): ?array {
		$chunks = $record['dependencyChunks'] ?? null;
		if ( ! is_array( $chunks ) ) {
			return $record;
		}
		$virtual = is_array( $record['virtualHashes'] ?? null ) ? self::string_map( $record['virtualHashes'] ) : array();
		$hashes  = $virtual;
		$paths   = array();
		foreach ( $chunks as $hash ) {
			$chunk = is_string( $hash ) ? self::dependency_chunk( $hash ) : null;
			if ( null === $chunk ) {
				return null;
			}
			$hashes = array_merge( $hashes, $chunk['hashes'] );
			$paths  = array_merge( $paths, $chunk['paths'] );
		}
		$overrides = is_array( $record['dependencyPathOverrides'] ?? null ) ? self::string_map( $record['dependencyPathOverrides'] ) : array();
		foreach ( $overrides as $id => $path ) {
			$paths[ $id ] = $path;
		}
		ksort( $hashes, SORT_STRING );
		ksort( $paths, SORT_STRING );
		unset( $record['dependencyPathOverrides'] );
		$record['dependencyHashes'] = $hashes;
		$record['dependencyPaths']  = $paths;

		return $record;
	}

	/**
	 * Delete dependency chunks no record names, once they are an hour old.
	 *
	 * @param array<string,true> $live Chunk hashes the live records name.
	 */
	private static function collect_dependency_sets( array $live ): void {
		$paths  = glob( self::state_root() . '/dependency-sets/*.json' );
		$cutoff = time() - 3600;
		foreach ( is_array( $paths ) ? $paths : array() as $path ) {
			if ( isset( $live[ basename( $path, '.json' ) ] ) || (int) filemtime( $path ) > $cutoff ) {
				continue;
			}
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
		}
	}

	/**
	 * Resolve one dependency chunk path.
	 *
	 * @param string $hash Chunk content hash.
	 *
	 * @return string Path.
	 */
	private static function dependency_set_path( string $hash ): string {
		$root = self::state_root() . '/dependency-sets';
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}

		return $root . '/' . sanitize_file_name( $hash ) . '.json';
	}

	/**
	 * Delete one graph record.
	 *
	 * @param string $url URL.
	 *
	 * @return bool Whether absent after deletion.
	 */
	private static function delete_index_record( string $url ): bool {
		$id   = self::url_hash( $url );
		$path = self::index_path( $id );

		if ( is_file( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
		}
		unset( self::$index_record_cache[ $id ] );

		return ! is_file( $path );
	}

	/**
	 * Resolve one graph record path.
	 *
	 * @param string $id URL hash.
	 *
	 * @return string Path.
	 */
	private static function index_path( string $id ): string {
		$root = self::state_root() . '/url-index';
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}

		return $root . '/' . sanitize_file_name( $id ) . '.json';
	}

	/**
	 * Start collecting dependencies when a warm request carries a valid token.
	 *
	 * @param array<string,mixed> $server Server values.
	 *
	 * @return void
	 */
	private static function begin_dependency_report( array $server ): void {
		self::$dependencies      = array();
		self::$dependency_report = self::dependency_report_id( (string) ( $server[ self::DEPENDENCY_SERVER_HEADER ] ?? '' ) );

		if ( null !== self::$dependency_report ) {
			add_filter( 'template_include', array( self::class, 'record_template_dependency' ), PHP_INT_MAX );
		}
	}

	/**
	 * Write the dependency report of the current warm request.
	 *
	 * Runs inside the response buffer callback, so the report exists before the
	 * renderer receives the response.
	 *
	 * @return void
	 */
	private static function finish_dependency_report(): void {
		$id = self::$dependency_report;
		if ( null === $id ) {
			return;
		}

		self::record_dependencies( self::page_source_dependencies() );
		$files = array_keys( self::$dependencies );
		sort( $files, SORT_STRING );
		self::$dependency_report = null;
		self::$dependencies      = array();
		remove_filter( 'template_include', array( self::class, 'record_template_dependency' ), PHP_INT_MAX );

		$encoded = wp_json_encode(
			array(
				'files'   => $files,
				'headers' => Static_Prerender_Headers::select( headers_list() ),
			),
			JSON_UNESCAPED_SLASHES
		);
		if ( is_string( $encoded ) ) {
			Single_Flight::publish( self::dependency_report_directory() . '/' . $id . '.json', $encoded );
		}
	}

	/**
	 * Source files of the synced page a warm request renders.
	 *
	 * Page content reaches the database through page synchronization, so the
	 * page definition, template, content, and layout files stand in for the
	 * stored content the render read. A component page adds its renderer
	 * entry and data loader, so a route stays tied to them even when a render
	 * fails before its renderer reports dependencies.
	 *
	 * @return string[] Existing source paths.
	 */
	private static function page_source_dependencies(): array {
		if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
			return array();
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return array();
		}

		$paths = array();
		foreach ( array( '_blockstudio_page_template_path', '_blockstudio_page_content_path', '_blockstudio_page_layout' ) as $meta_key ) {
			$paths[] = Page_Paths::from_meta( $post_id, $meta_key );
		}

		$render = Page_Sync::stored_render( $post_id );
		if ( null !== $render ) {
			$paths[] = $render['entry'];
			$paths[] = (string) $render['data'];
		}

		$ids = get_post_meta( $post_id, '_blockstudio_page_dependencies', true );
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			if ( ! is_string( $id ) || ! ( str_starts_with( $id, 'theme:' ) || str_starts_with( $id, 'parent-theme:' ) ) ) {
				continue;
			}
			$resolved = Page_Paths::resolve( $id );
			$paths[]  = $resolved;
			// Older dependency identities did not distinguish child and parent themes.
			if ( str_starts_with( $id, 'theme:' ) && ( '' === $resolved || ! is_file( $resolved ) ) ) {
				$paths[] = Page_Paths::resolve( 'parent-theme:' . substr( $id, 6 ) );
			}
		}

		return array_values(
			array_filter(
				$paths,
				static fn( string $path ): bool => '' !== $path && is_file( $path )
			)
		);
	}

	/**
	 * Directory that holds dependency reports between a warm request and its renderer.
	 *
	 * The two run in different processes whose cache context can differ, a
	 * renderer under WP-CLI and a warm request for a translated route for
	 * example, so reports live beside the site's namespaces instead of inside
	 * one. The leading dot keeps namespace collection from treating it as a
	 * namespace.
	 *
	 * @return string Directory path.
	 */
	private static function dependency_report_directory(): string {
		return dirname( Runtime_Cache::directory( 'static-prerender-state' ), 2 ) . '/.static-prerender-dependencies';
	}

	/**
	 * Validate a dependency-report token.
	 *
	 * @param string $token Token.
	 *
	 * @return string|null Report ID, or null when the token is invalid.
	 */
	private static function dependency_report_id( string $token ): ?string {
		if ( ! preg_match( '/^([a-f0-9]{32})\.([a-f0-9]{32})$/', trim( $token ), $matches ) ) {
			return null;
		}

		return hash_equals( self::dependency_report_signature( $matches[1] ), $matches[2] ) ? $matches[1] : null;
	}

	/**
	 * Sign a dependency-report ID with this installation's salt.
	 *
	 * @param string $id Report ID.
	 *
	 * @return string Signature.
	 */
	private static function dependency_report_signature( string $id ): string {
		return substr( hash_hmac( 'sha256', 'static-prerender-dependencies|' . $id, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Recalculate one record's page dependency state.
	 *
	 * @param array<string,mixed> $record Record.
	 *
	 * @return array{pageHash:string,changedFiles:string[]}
	 */
	private static function graph_state_for_record( array $record ): array {
		$previous = is_array( $record['dependencyHashes'] ?? null )
			? self::string_map( $record['dependencyHashes'] )
			: array();
		$paths    = is_array( $record['dependencyPaths'] ?? null )
			? self::string_map( $record['dependencyPaths'] )
			: array();
		$virtual  = is_array( $record['virtualHashes'] ?? null )
			? self::string_map( $record['virtualHashes'] )
			: array();
		$current  = $virtual;

		foreach ( $paths as $id => $path ) {
			$current[ $id ] = Static_Prerender_Content_Hasher::file_hash( $path );
		}
		ksort( $current, SORT_STRING );

		$changed = array();
		foreach ( array_unique( array_merge( array_keys( $previous ), array_keys( $current ) ) ) as $id ) {
			if ( ( $previous[ $id ] ?? null ) !== ( $current[ $id ] ?? null ) ) {
				$changed[] = $id;
			}
		}
		sort( $changed, SORT_STRING );

		return array(
			'pageHash'     => Static_Prerender_Content_Hasher::content_hash( $current ),
			'changedFiles' => $changed,
		);
	}

	/**
	 * Build an identity-mode key from host and path.
	 *
	 * Host and path are folded exactly like the generated early-serve drop-in,
	 * so both engines resolve one request to one key.
	 *
	 * @param string $host Host.
	 * @param string $path Path.
	 *
	 * @return string Key.
	 */
	private static function cache_key_for_host_path( string $host, string $path ): string {
		$host = strtolower( trim( $host ) );
		$path = strtolower( '/' . ltrim( self::url_path( $path ), '/' ) );
		$path = preg_replace( '#/+#', '/', $path );
		$path = is_string( $path ) && '' !== $path ? $path : '/';

		return hash( 'sha256', $host . '|' . $path . '|' . Static_Prerender_Identity::current() );
	}

	/**
	 * Build a graph-mode key.
	 *
	 * @param string $url         URL.
	 * @param string $shared_hash Shared dependency hash.
	 * @param string $page_hash   Page dependency hash.
	 *
	 * @return string|null Key.
	 */
	private static function cache_key_for_url_hashes(
		string $url,
		string $shared_hash,
		string $page_hash
	): ?string {
		$url = self::normalize_url_for_cache( $url );
		if ( null === $url ) {
			return null;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! is_string( $parts['host'] ?? null ) ) {
			return null;
		}

		$host = strtolower( $parts['host'] );
		if ( is_int( $parts['port'] ?? null ) ) {
			$host .= ':' . $parts['port'];
		}
		$path = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );

		return hash( 'sha256', $host . '|' . $path . '|' . $shared_hash . '|' . $page_hash );
	}

	/**
	 * Build an absolute URL from server values.
	 *
	 * @param array<string,mixed> $server Server values.
	 *
	 * @return string|null URL.
	 */
	private static function url_from_request( array $server ): ?string {
		$uri = (string) ( $server['REQUEST_URI'] ?? '/' );
		if ( '' === $uri || str_contains( $uri, '?' ) ) {
			return null;
		}

		$host   = (string) ( $server['HTTP_HOST'] ?? 'localhost' );
		$scheme = 'on' === strtolower( (string) ( $server['HTTPS'] ?? '' ) )
			|| '443' === (string) ( $server['SERVER_PORT'] ?? '' )
			? 'https'
			: 'http';

		return self::normalize_url_for_cache( $scheme . '://' . $host . '/' . ltrim( $uri, '/' ) );
	}

	/**
	 * Normalize an HTTP(S) URL for deterministic cache identity.
	 *
	 * @param string $url URL.
	 *
	 * @return string|null URL.
	 */
	private static function normalize_url_for_cache( string $url ): ?string {
		$url   = trim( $url );
		$parts = wp_parse_url( $url );

		if (
			'' === $url ||
			! is_array( $parts ) ||
			! is_string( $parts['host'] ?? null ) ||
			'' === $parts['host']
		) {
			return null;
		}

		$scheme = strtolower( is_string( $parts['scheme'] ?? null ) ? $parts['scheme'] : 'https' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}
		if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}

		$host = strtolower( $parts['host'] );
		$port = is_int( $parts['port'] ?? null ) ? ':' . $parts['port'] : '';
		$path = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$path = preg_replace( '#/+#', '/', $path );
		$path = is_string( $path ) && '' !== $path ? $path : '/';

		return $scheme . '://' . $host . $port . $path;
	}

	/**
	 * Parse a URL/path and return a safe path.
	 *
	 * @param string $value URL or path.
	 *
	 * @return string Path.
	 */
	private static function url_path( string $value ): string {
		$path = wp_parse_url( $value, PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? rawurldecode( $path ) : '/';
	}

	/**
	 * Resolve a target scope from source URLs and optional overrides.
	 *
	 * @param string[]    $source_urls Source URLs.
	 * @param string|null $target_host Target host.
	 * @param string|null $target_home Target home.
	 *
	 * @return array{host:string,home:string}
	 */
	private static function target_scope(
		array $source_urls,
		?string $target_host,
		?string $target_home
	): array {
		$source_home = function_exists( 'home_url' )
			? (string) home_url( '/' )
			: ( self::unique_cacheable_urls( $source_urls )[0] ?? 'http://localhost/' );
		$target      = self::target_url( $source_home, $target_host, $target_home ) ?? $source_home;

		return self::scope_from_url( $target );
	}

	/**
	 * Resolve a target scope from normalized targets.
	 *
	 * @param string[] $target_urls Target URLs.
	 *
	 * @return array{host:string,home:string}
	 */
	private static function target_scope_from_targets( array $target_urls ): array {
		$targets = self::unique_cacheable_urls( $target_urls );
		if ( array() !== $targets ) {
			$parts = wp_parse_url( $targets[0] );
			$host  = is_array( $parts ) && is_string( $parts['host'] ?? null )
				? strtolower( $parts['host'] )
				: 'localhost';
			if ( is_array( $parts ) && is_int( $parts['port'] ?? null ) ) {
				$host .= ':' . $parts['port'];
			}

			$home_path = function_exists( 'home_url' )
				? self::url_path( (string) home_url( '/' ) )
				: '/';
			$home_path = self::normalize_home_path( $home_path );

			return array(
				'host' => $host,
				'home' => $home_path,
			);
		}

		$fallback = function_exists( 'home_url' ) ? home_url( '/' ) : 'http://localhost/';

		return self::scope_from_url( $fallback );
	}

	/**
	 * Resolve host/home path for one URL.
	 *
	 * @param string $url URL.
	 *
	 * @return array{host:string,home:string}
	 */
	private static function scope_from_url( string $url ): array {
		$parts = wp_parse_url( $url );
		$host  = is_array( $parts ) && is_string( $parts['host'] ?? null )
			? strtolower( $parts['host'] )
			: 'localhost';

		if ( is_array( $parts ) && is_int( $parts['port'] ?? null ) ) {
			$host .= ':' . $parts['port'];
		}

		$path = is_array( $parts ) ? (string) ( $parts['path'] ?? '/' ) : '/';

		return array(
			'host' => $host,
			'home' => self::normalize_home_path( $path ),
		);
	}

	/**
	 * Normalize a site home path.
	 *
	 * @param string $path Home path.
	 *
	 * @return string Normalized lowercase path with trailing slash.
	 */
	private static function normalize_home_path( string $path ): string {
		$home = '/' . trim( $path, '/' );

		return strtolower( '/' === $home ? '/' : $home . '/' );
	}

	/**
	 * Determine whether a URL belongs to a target scope.
	 *
	 * @param string                         $url   URL.
	 * @param array{host:string,home:string} $scope Scope.
	 *
	 * @return bool Whether in scope.
	 */
	private static function url_in_scope( string $url, array $scope ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! is_string( $parts['host'] ?? null ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );
		if ( is_int( $parts['port'] ?? null ) ) {
			$host .= ':' . $parts['port'];
		}
		$path = strtolower( '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' ) );

		return $host === $scope['host'] && str_starts_with( $path . '/', $scope['home'] );
	}

	/**
	 * Hash one normalized URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string Hash.
	 */
	private static function url_hash( string $url ): string {
		return hash( 'sha256', strtolower( $url ) );
	}

	/**
	 * Normalize and deduplicate cacheable URLs.
	 *
	 * @param string[] $urls URLs.
	 *
	 * @return string[] URLs.
	 */
	private static function unique_cacheable_urls( array $urls ): array {
		$normalized = array();

		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) ) {
				continue;
			}
			$url = self::normalize_url_for_cache( $url );
			if ( null !== $url ) {
				$normalized[ $url ] = true;
			}
		}

		$urls = array_keys( $normalized );
		sort( $urls, SORT_STRING );

		return $urls;
	}

	/**
	 * Enqueue normalized URLs.
	 *
	 * @param string[] $urls   URLs.
	 * @param string   $reason Queue reason.
	 *
	 * @return int Number written.
	 */
	private static function enqueue_urls( array $urls, string $reason ): int {
		$jobs      = array();
		$signature = Static_Prerender_Identity::current();

		foreach ( self::unique_cacheable_urls( $urls ) as $url ) {
			$jobs[] = array(
				'url'        => $url,
				'urlHash'    => self::url_hash( $url ),
				'signature'  => $signature,
				'reason'     => $reason,
				'enqueuedAt' => time(),
			);
		}

		return self::queue()->enqueue_many( $jobs );
	}

	/**
	 * Determine whether a queue job remains stale.
	 *
	 * @param array<string,mixed> $job Job.
	 *
	 * @return bool Whether to warm.
	 */
	private static function queue_job_requires_warm( array $job ): bool {
		$url       = is_string( $job['url'] ?? null ) ? $job['url'] : '';
		$signature = is_string( $job['signature'] ?? null ) ? $job['signature'] : '';

		$reason = is_string( $job['reason'] ?? null ) ? $job['reason'] : 'interval';

		return '' !== $url
			&& Static_Prerender_Identity::current() === $signature
			&& ( 'interval' !== $reason || self::url_is_stale( $url ) );
	}

	/**
	 * Determine whether a URL is cold or expired.
	 *
	 * @param string $url URL.
	 *
	 * @return bool Whether stale.
	 */
	private static function url_is_stale( string $url ): bool {
		$key = self::cache_key_for_url( $url );
		if ( null === $key ) {
			return false;
		}

		$file = self::cache_file( $key );
		if ( ! is_file( $file ) ) {
			return true;
		}

		$ttl = (int) self::value( 'staticPrerender/ttl', 86400 );

		$mtime = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove the file between listing and stat.

		return $ttl > 0 && ( false === $mtime || (int) $mtime < time() - $ttl );
	}

	/**
	 * Purge targeted URLs without invalidating unrelated entries.
	 *
	 * @param string[] $urls URLs.
	 *
	 * @return void
	 */
	private static function purge_urls( array $urls ): void {
		foreach ( self::unique_cacheable_urls( $urls ) as $url ) {
			$key = self::cache_key_for_url( $url );
			if ( null !== $key && is_file( self::cache_file( $key ) ) ) {
				@unlink( self::cache_file( $key ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
				Static_Prerender_Headers::remove( self::cache_file( $key ) );
			}

			$record = self::index_record_for_url( $url );
			if ( is_array( $record ) && is_string( $record['cacheKey'] ?? null ) ) {
				$file = self::cache_file( $record['cacheKey'] );
				if ( is_file( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
					Static_Prerender_Headers::remove( $file );
				}
			}
			self::delete_index_record( $url );
		}
	}

	/**
	 * Normalize a string map.
	 *
	 * @param array<mixed> $values Values.
	 *
	 * @return array<string,string> String map.
	 */
	private static function string_map( array $values ): array {
		$normalized = array();

		foreach ( $values as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) ) {
				$normalized[ $key ] = $value;
			}
		}
		ksort( $normalized, SORT_STRING );

		return $normalized;
	}

	/**
	 * Discover public post permalinks.
	 *
	 * @return string[] URLs.
	 */
	private static function public_post_urls(): array {
		if ( ! function_exists( 'get_post_types' ) || ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$types = get_post_types( array( 'public' => true ), 'names' );
		$types = is_array( $types )
			? array_values( array_diff( array_filter( $types, 'is_string' ), array( 'attachment' ) ) )
			: array();
		$urls  = array();

		if ( array() === $types ) {
			return $urls;
		}

		$ids = get_posts(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			if ( ! is_numeric( $id ) || ! function_exists( 'get_permalink' ) ) {
				continue;
			}
			$url = get_permalink( (int) $id );
			if ( is_string( $url ) && '' !== trim( $url ) ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Discover public taxonomy term URLs.
	 *
	 * @return string[] URLs.
	 */
	private static function public_term_urls(): array {
		if ( ! function_exists( 'get_taxonomies' ) || ! function_exists( 'get_terms' ) ) {
			return array();
		}

		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		$urls       = array();

		foreach ( is_array( $taxonomies ) ? $taxonomies : array() as $taxonomy ) {
			if ( ! is_string( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
				if ( ! is_numeric( $term_id ) || ! function_exists( 'get_term_link' ) ) {
					continue;
				}
				$url = get_term_link( (int) $term_id, $taxonomy );
				if ( is_string( $url ) && '' !== trim( $url ) ) {
					$urls[] = $url;
				}
			}
		}

		return $urls;
	}

	/**
	 * Detect built-in dynamic/control paths.
	 *
	 * @param string $path Path.
	 *
	 * @return bool Whether bypassed.
	 */
	private static function is_bypassed_path( string $path ): bool {
		$path = strtolower( '/' . ltrim( $path, '/' ) );

		foreach ( array( '/wp-admin', '/wp-json', '/wp-login.php', '/xmlrpc.php' ) as $prefix ) {
			if ( str_starts_with( $path . '/', $prefix . '/' ) ) {
				return true;
			}
		}

		if ( str_contains( $path, '/feed/' ) || '/feed' === substr( rtrim( $path, '/' ), -5 ) ) {
			return true;
		}

		return str_starts_with( $path, self::search_path_prefix() );
	}

	/**
	 * Return the path prefix of WordPress search result pages.
	 *
	 * Search results live below the search base at the site's home path,
	 * such as `/search/term/` or `/sub-site/search/term/`; a page whose path
	 * merely contains a `search` segment deeper down is ordinary content.
	 *
	 * @return string Lowercase prefix with leading and trailing slashes.
	 */
	private static function search_path_prefix(): string {
		$blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
		if ( isset( self::$search_path_prefixes[ $blog_id ] ) ) {
			return self::$search_path_prefixes[ $blog_id ];
		}

		global $wp_rewrite;
		$base = 'search';
		if ( $wp_rewrite instanceof \WP_Rewrite && is_string( $wp_rewrite->search_base ) && '' !== trim( $wp_rewrite->search_base, '/' ) ) {
			$base = trim( $wp_rewrite->search_base, '/' );
		}

		$home = function_exists( 'home_url' ) ? wp_parse_url( home_url( '/' ), PHP_URL_PATH ) : '/';
		$home = trim( is_string( $home ) ? $home : '', '/' );

		self::$search_path_prefixes[ $blog_id ] = strtolower( '/' . ( '' === $home ? '' : $home . '/' ) . $base . '/' );

		return self::$search_path_prefixes[ $blog_id ];
	}

	/**
	 * Whether this is an explicit warm request.
	 *
	 * @param array<string,mixed> $server Server values.
	 *
	 * @return bool Whether warm.
	 */
	private static function is_warm_request( array $server ): bool {
		return '1' === (string) ( $server[ self::WARM_SERVER_HEADER ] ?? '' );
	}

	/**
	 * Return active theme root.
	 *
	 * @return string|null Root.
	 */
	private static function theme_root(): ?string {
		if ( ! function_exists( 'get_stylesheet_directory' ) ) {
			return null;
		}

		$root = wp_normalize_path( (string) get_stylesheet_directory() );

		return '' !== $root && is_dir( $root ) ? $root : null;
	}

	/**
	 * Read one runtime setting.
	 *
	 * @param string $path    Setting path.
	 * @param mixed  $default Default.
	 *
	 * @return mixed Value.
	 */
	private static function value( string $path, mixed $default = null ): mixed {
		return Runtime_Settings::current()->value( $path, $default );
	}

	/**
	 * Test one runtime setting.
	 *
	 * @param string $path Setting path.
	 *
	 * @return bool Whether enabled.
	 */
	private static function enabled( string $path ): bool {
		return Runtime_Settings::current()->enabled( $path );
	}

	/**
	 * Whether graph invalidation is selected.
	 *
	 * @return bool Whether graph mode.
	 */
	private static function graph_enabled(): bool {
		return 'graph' === self::value( 'staticPrerender/invalidate', 'signature' );
	}

	/**
	 * Whether automatic warming is enabled.
	 *
	 * @return bool Whether enabled.
	 */
	private static function warm_enabled(): bool {
		// Graph artifacts are built and activated as one atomic unit. The
		// per-URL warmer cannot safely edit their route map.
		return self::enabled( 'staticPrerender/enabled' )
			&& ! self::graph_enabled()
			&& self::enabled( 'staticPrerender/warm/enabled' );
	}

	/**
	 * Get warm interval.
	 *
	 * @return int Seconds.
	 */
	private static function warm_interval(): int {
		return max( 60, (int) self::value( 'staticPrerender/warm/interval', 3600 ) );
	}

	/**
	 * Get warm concurrency.
	 *
	 * @return int Concurrency.
	 */
	private static function warm_concurrency(): int {
		return max( 1, (int) self::value( 'staticPrerender/warm/concurrency', 2 ) );
	}

	/**
	 * Get warm transport.
	 *
	 * @return string Transport.
	 */
	private static function warm_transport(): string {
		return 'internal' === self::value( 'staticPrerender/warm/transport', 'http' )
			? 'internal'
			: 'http';
	}

	/**
	 * Reconcile cleanup only when an enabled side-effecting feature changes.
	 *
	 * Disabled sites perform one cached option lookup and no filesystem or cron
	 * work. Early-serve files and warm schedules are cleaned once when their
	 * previously enabled setting becomes inactive. An inactive state is stored
	 * as an empty array instead of deleting the row: a missing option is not
	 * autoloaded, so every request without an object cache would query for it.
	 *
	 * @return void
	 */
	private static function reconcile_feature_state(): void {
		$enabled = self::enabled( 'staticPrerender/enabled' );
		$warm    = self::warm_enabled();
		$current = array(
			'earlyServe'   => $enabled && self::enabled( 'staticPrerender/earlyServe' ),
			'warm'         => $warm,
			'warmInterval' => $warm
				? self::warm_interval()
				: 0,
		);
		$stored  = get_option( self::FEATURE_STATE_OPTION, array() );
		$stored  = is_array( $stored ) ? $stored : array();

		if ( ! $current['earlyServe'] && ! empty( $stored['earlyServe'] ) ) {
			Static_Prerender_Early_Serve::remove_current_site();
		}

		$warm_changed = ! empty( $stored['warm'] ) && (
			! $current['warm'] ||
			(int) ( $stored['warmInterval'] ?? 0 ) !== $current['warmInterval']
		);
		if ( $warm_changed ) {
			self::unschedule_cron();
		}

		if ( $current['earlyServe'] || $current['warm'] ) {
			if ( $stored !== $current ) {
				update_option( self::FEATURE_STATE_OPTION, $current, true );
			}

			return;
		}

		if ( array() !== $stored ) {
			update_option( self::FEATURE_STATE_OPTION, array(), true );
		}
	}

	/**
	 * Schedule the warm cron event.
	 *
	 * @return void
	 */
	private static function schedule_cron(): void {
		if (
			! function_exists( 'wp_next_scheduled' ) ||
			! function_exists( 'wp_schedule_event' ) ||
			wp_next_scheduled( self::CRON_HOOK )
		) {
			return;
		}

		wp_schedule_event( time() + self::warm_interval(), self::CRON_SCHEDULE, self::CRON_HOOK );
	}

	/**
	 * Remove scheduled warm events.
	 *
	 * @return void
	 */
	private static function unschedule_cron(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_unschedule_event' ) ) {
			return;
		}

		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Register generic WP-CLI maintenance commands.
	 *
	 * @return void
	 */
	private static function register_wp_cli_commands(): void {
		if (
			! defined( 'WP_CLI' ) ||
			! WP_CLI ||
			! class_exists( '\WP_CLI' ) ||
			! method_exists( '\WP_CLI', 'add_command' )
		) {
			return;
		}

		\WP_CLI::add_command(
			'bs prerender warm',
			static function (): void {
				$result = self::warm_stale_urls();
				\WP_CLI::line( (string) wp_json_encode( $result ) );
			}
		);
		\WP_CLI::add_command(
			'bs prerender purge',
			static function (): void {
				\WP_CLI::success( sprintf( 'Removed %d static prerender files.', self::purge() ) );
			}
		);
		\WP_CLI::add_command(
			'bs prerender status',
			static function (): void {
				\WP_CLI::line( (string) wp_json_encode( self::status(), JSON_PRETTY_PRINT ) );
			}
		);
		\WP_CLI::add_command(
			'bs prerender map status',
			static function (): void {
				\WP_CLI::line( (string) wp_json_encode( Static_Prerender_Early_Serve::map_report(), JSON_PRETTY_PRINT ) );
			},
			array( 'shortdesc' => 'Report early-serve map entries whose site or directory is gone.' )
		);
		\WP_CLI::add_command(
			'bs prerender map prune',
			static function ( array $args, array $assoc_args ): void {
				unset( $args );
				$outcome = Static_Prerender_Early_Serve::prune_map( ! empty( $assoc_args['dry-run'] ) );
				\WP_CLI::line( (string) wp_json_encode( $outcome, JSON_PRETTY_PRINT ) );
			},
			array(
				'shortdesc' => 'Remove early-serve map entries whose site or directory is gone.',
				'synopsis'  => array(
					array(
						'type'        => 'flag',
						'name'        => 'dry-run',
						'description' => 'Report what would be removed without writing the map.',
						'optional'    => true,
					),
				),
			)
		);
	}

	/**
	 * Whether a real WP-CLI runner owns this process.
	 *
	 * @return bool Whether WP-CLI is running.
	 */
	private static function wp_cli_is_running(): bool {
		return defined( 'WP_CLI' )
			&& WP_CLI
			&& class_exists( '\WP_CLI', false )
			&& ( defined( 'WP_CLI_ROOT' ) || class_exists( 'WP_CLI\Runner', false ) );
	}

	/**
	 * Record a static-prerender cache outcome.
	 *
	 * @param string $reason Outcome.
	 *
	 * @return void
	 */
	private static function record_outcome( string $reason ): void {
		/**
		 * Fires after a static-prerender cache outcome.
		 *
		 * @since 7.6.0
		 *
		 * @param string $reason Outcome reason.
		 */
		do_action( 'blockstudio/static_prerender/outcome', $reason );
	}
}

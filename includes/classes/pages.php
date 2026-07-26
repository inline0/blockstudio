<?php
/**
 * Pages class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Main orchestration class for file-based pages.
 *
 * This class provides the public API for the file-based pages feature,
 * handling discovery, registration, and syncing of pages.
 *
 * @since 7.0.0
 */
class Pages {

	/**
	 * Per-site option containing the last fully verified deployment source.
	 */
	private const SOURCE_IDENTITY_OPTION = 'blockstudio_pages_successful_source_identity';

	/**
	 * Whether pages have been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Whether an explicit reconciliation owns the current request.
	 *
	 * @var bool
	 */
	private static bool $reconciling = false;

	/**
	 * Whether discovery-dependent page hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Whether request-safe frontend and editor runtime hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $runtime_hooks_registered = false;

	/**
	 * Whether collection URL hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $collection_url_hooks_registered = false;

	/**
	 * Whether a collection rewrite flush has been scheduled.
	 *
	 * @var bool
	 */
	private static bool $collection_rewrite_flush_scheduled = false;

	/**
	 * Per-request collection manifest cache.
	 *
	 * @var array<int, array>|null
	 */
	private static ?array $collection_manifests_cache = null;

	/**
	 * Per-request collection manifest cache indexed by slug.
	 *
	 * @var array<string, array>|null
	 */
	private static ?array $collection_manifests_by_slug_cache = null;

	/**
	 * Current page while rendering a layout.
	 *
	 * @var array|null
	 */
	private static ?array $current_page = null;

	/**
	 * Current page content while rendering a layout.
	 *
	 * @var string
	 */
	private static string $current_page_content = '';

	/**
	 * Whether a layout is currently rendering.
	 *
	 * @var bool
	 */
	private static bool $rendering_layout = false;

	/**
	 * Reset frontend page layout state without rebuilding the page registry.
	 *
	 * @return void
	 */
	public static function reset_request_state(): void {
		self::$current_page         = null;
		self::$current_page_content = '';
		self::$rendering_layout     = false;
	}

	/**
	 * Initialize the pages system.
	 *
	 * @param array $args Optional arguments.
	 *
	 * @return void
	 */
	public static function init( array $args = array() ): void {
		self::register_runtime_hooks();
		if ( ! self::can_init_in_current_context( $args ) ) {
			return;
		}

		if ( self::$initialized && empty( $args['force'] ) ) {
			return;
		}

		$report   = self::reconcile(
			array(
				'authoritative' => false,
				'full'          => ! empty( $args['force'] ),
				'plan_valid'    => false,
			)
		);
		$registry = Page_Registry::instance();

		if ( ! self::$hooks_registered ) {
			self::register_template_for_hooks();

			self::$hooks_registered = true;
		}

		self::$initialized = true;

		/**
		 * Fires after pages have been synced.
		 *
		 * @param Page_Registry $registry The page registry instance.
		 * @param array         $report   Reconciliation report and source identity.
		 */
		do_action( 'blockstudio/pages/synced', $registry, $report );
	}

	/**
	 * Reconcile the complete desired filesystem inventory with managed posts.
	 *
	 * This is the only deployment/CLI entry point that performs discovery and
	 * synchronization. Git data in $args is an optimization and audit hint; the
	 * desired content fingerprints and managed inventory remain authoritative.
	 *
	 * @param array $args Reconciliation and source-plan arguments.
	 *
	 * @return array Reconciliation report and source identity.
	 */
	public static function reconcile( array $args = array() ): array {
		$was_reconciling   = self::$reconciling;
		self::$reconciling = true;

		try {
			self::register_collection_post_types();

			$registry = self::discover_registry();
			$sync     = new Page_Sync();
			$pages    = $registry->get_registered_pages();
			$managed  = $sync->managed_posts();
			$indexes  = self::managed_post_indexes( $managed );
			$engine   = Page_Sync::engine_fingerprint();
			$desired  = array();

			foreach ( $pages as $name => $page_data ) {
				$desired[ $name ] = array(
					'fingerprint'  => $sync->fingerprint( $page_data ),
					'dependencies' => $sync->dependency_ids( $page_data ),
					'key'          => (string) ( $page_data['key'] ?? $name ),
					'source'       => (string) ( $page_data['source_path'] ?? '' ),
				);
			}

			if ( empty( $pages ) && ! empty( $managed ) && empty( $registry->get_paths() ) ) {
				$registry->add_errors(
					array(
						array(
							'code'    => 'no_page_paths',
							'message' => 'No valid page discovery path was available; managed posts were preserved.',
						),
					)
				);
			}

			$source_identity = self::build_source_identity( $desired, $engine, $args );
			$previous        = self::successful_source_identity();
			$full            = ! empty( $args['full'] )
				|| empty( $args['plan_valid'] )
				|| ! empty( $args['broad'] )
				|| empty( $previous )
				|| ! hash_equals( (string) ( $previous['engineFingerprint'] ?? '' ), $engine );

			$report = array(
				'discovered'         => count( $pages ),
				'created'            => 0,
				'updated'            => 0,
				'unchanged'          => 0,
				'removed'            => 0,
				'failed'             => count( $registry->get_errors() ),
				'fullReconciliation' => $full,
				'sourceId'           => $source_identity['hash'],
				'sourceIdentity'     => $source_identity,
				'pages'              => array(),
				'errors'             => $registry->get_errors(),
			);

			$used_post_ids = array();
			foreach ( $pages as $name => $page_data ) {
				$identity = $desired[ $name ];
				$existing = self::resolve_managed_post( $page_data, $identity, $indexes, $args, $used_post_ids );
				$result   = $sync->reconcile(
					$page_data,
					array(
						'existing'           => $existing,
						'fingerprint'        => $identity['fingerprint'],
						'engine_fingerprint' => $engine,
						'authoritative'      => ! empty( $args['authoritative'] ),
						'prune_duplicates'   => count( $indexes['key'][ $identity['key'] ] ?? array() ) > 1
							|| count( $indexes['source'][ $identity['source'] ] ?? array() ) > 1,
					)
				);

				$status = in_array( $result['status'], array( 'created', 'updated', 'unchanged', 'failed' ), true ) ? $result['status'] : 'failed';
				++$report[ $status ];
				$report['pages'][ $name ] = array(
					'status' => $status,
					'postId' => $result['post_id'],
					'locked' => $result['locked'],
				);

				if ( $result['error'] instanceof \WP_Error ) {
					$report['errors'][] = array(
						'page'    => $name,
						'code'    => $result['error']->get_error_code(),
						'message' => $result['error']->get_error_message(),
					);
				} elseif ( 'failed' === $status ) {
					$report['errors'][] = array(
						'page'    => $name,
						'code'    => 'reconcile_failed',
						'message' => 'The managed page could not be reconciled.',
					);
				}

				if ( $result['post_id'] > 0 ) {
					$used_post_ids[ $result['post_id'] ] = true;
					self::hydrate_registry_page( $registry, $name, $page_data, $result['post_id'] );
				}
			}

			if ( 0 === count( $registry->get_errors() ) ) {
				$report['removed'] = $sync->prune_missing(
					array_column( $desired, 'key' ),
					array_column( $desired, 'source' ),
					$managed
				);
			}

			/**
			 * Fires after one explicit page reconciliation pass.
			 *
			 * @param array         $report   Reconciliation report.
			 * @param Page_Registry $registry Discovered registry.
			 */
			do_action( 'blockstudio/pages/reconciled', $report, $registry );

			return $report;
		} finally {
			self::$reconciling = $was_reconciling;
		}
	}

	/**
	 * Read the last source identity stored after a verified deployment.
	 *
	 * @return array<string, string> Successful source identity or an empty array.
	 */
	public static function successful_source_identity(): array {
		$identity = get_option( self::SOURCE_IDENTITY_OPTION, array() );

		return is_array( $identity ) ? $identity : array();
	}

	/**
	 * Store a source identity after reconciliation, artifact activation, and route verification.
	 *
	 * Reconcile deliberately never calls this method. Deployment orchestration owns
	 * the success boundary so a later artifact failure cannot advance the marker.
	 *
	 * @param array $identity Source identity returned by reconcile().
	 *
	 * @return bool Whether a valid identity was stored.
	 */
	public static function store_successful_source_identity( array $identity ): bool {
		$required = array( 'hash', 'inventoryHash', 'engineFingerprint' );

		foreach ( $required as $key ) {
			if ( ! isset( $identity[ $key ] ) || ! is_string( $identity[ $key ] ) || '' === $identity[ $key ] ) {
				return false;
			}
		}

		$normalized = array(
			'commit'            => isset( $identity['commit'] ) && is_string( $identity['commit'] ) ? $identity['commit'] : '',
			'dirtyHash'         => isset( $identity['dirtyHash'] ) && is_string( $identity['dirtyHash'] ) ? $identity['dirtyHash'] : '',
			'inventoryHash'     => $identity['inventoryHash'],
			'engineFingerprint' => $identity['engineFingerprint'],
			'hash'              => $identity['hash'],
		);

		return update_option( self::SOURCE_IDENTITY_OPTION, $normalized, false )
			|| self::successful_source_identity() === $normalized;
	}

	/**
	 * Discover the desired page inventory into a fresh registry.
	 *
	 * @return Page_Registry Discovered registry.
	 */
	private static function discover_registry(): Page_Registry {
		$registry = Page_Registry::instance();
		$registry->reset();

		$paths = self::get_paths();

		/**
		 * Filter the pages discovery paths.
		 *
		 * @param array $paths Array of directory paths to scan for pages.
		 */
		$paths = apply_filters( 'blockstudio/pages/paths', $paths );

		foreach ( Discovery_Sources::for_paths( 'pages', $paths ) as $source ) {
			$discovery = new Page_Discovery();
			$registry->add_path( $source->root() );
			$pages = $discovery->discover( $source );

			foreach ( $discovery->get_collections() as $collection => $collection_data ) {
				$registry->register_collection( $collection, $collection_data );
			}

			$registry->add_errors( $discovery->get_errors() );

			foreach ( $pages as $name => $page_data ) {
				$registry->register( $name, $page_data );
			}
		}

		return $registry;
	}

	/**
	 * Index a preloaded managed post inventory by stable identity.
	 *
	 * @param array $posts Managed posts.
	 *
	 * @return array<string, array> Post indexes.
	 */
	private static function managed_post_indexes( array $posts ): array {
		$indexes = array(
			'id'     => array(),
			'key'    => array(),
			'source' => array(),
		);

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$indexes['id'][ $post->ID ] = $post;
			$key                        = (string) get_post_meta( $post->ID, '_blockstudio_page_key', true );
			$source                     = (string) get_post_meta( $post->ID, '_blockstudio_page_source', true );

			if ( '' !== $key ) {
				$indexes['key'][ $key ][] = $post;
			}
			if ( '' !== $source ) {
				$indexes['source'][ $source ][] = $post;
			}
		}

		return $indexes;
	}

	/**
	 * Resolve one managed post, including an explicit Git rename rebind.
	 *
	 * @param array $page_data     Desired page data.
	 * @param array $identity      Desired fingerprint identity.
	 * @param array $indexes       Managed post indexes.
	 * @param array $args          Reconciliation arguments.
	 * @param array $used_post_ids Post IDs already claimed this pass.
	 *
	 * @return \WP_Post|null Existing managed post.
	 */
	private static function resolve_managed_post( array $page_data, array $identity, array $indexes, array $args, array $used_post_ids ): ?\WP_Post {
		$candidates = array();

		if ( ! empty( $page_data['postId'] ) && isset( $indexes['id'][ (int) $page_data['postId'] ] ) ) {
			$candidates[] = $indexes['id'][ (int) $page_data['postId'] ];
		}
		foreach ( $indexes['key'][ $identity['key'] ] ?? array() as $post ) {
			$candidates[] = $post;
		}
		foreach ( $indexes['source'][ $identity['source'] ] ?? array() as $post ) {
			$candidates[] = $post;
		}

		foreach ( $candidates as $post ) {
			if ( $post instanceof \WP_Post && empty( $used_post_ids[ $post->ID ] ) ) {
				return $post;
			}
		}

		$renames = self::normalize_renames( $args['renames'] ?? array() );
		if ( empty( $renames ) ) {
			return null;
		}

		$desired_dependencies = array_fill_keys(
			array_map( array( __CLASS__, 'normalize_plan_path' ), $identity['dependencies'] ),
			true
		);
		$desired_dependencies[ self::normalize_plan_path( $identity['source'] ) ] = true;

		foreach ( $renames as $rename ) {
			if ( ! isset( $desired_dependencies[ $rename['to'] ] ) ) {
				continue;
			}

			foreach ( $indexes['id'] as $post ) {
				if ( ! $post instanceof \WP_Post || ! empty( $used_post_ids[ $post->ID ] ) ) {
					continue;
				}

				$dependencies   = get_post_meta( $post->ID, '_blockstudio_page_dependencies', true );
				$dependencies   = is_array( $dependencies ) ? $dependencies : array();
				$dependencies[] = (string) get_post_meta( $post->ID, '_blockstudio_page_source', true );
				$dependencies   = array_map( array( __CLASS__, 'normalize_plan_path' ), $dependencies );

				if ( in_array( $rename['from'], $dependencies, true ) ) {
					return $post;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize Git rename records into comparable dependency paths.
	 *
	 * @param mixed $renames Rename map or record list.
	 *
	 * @return array<int, array{from:string,to:string}> Rename records.
	 */
	private static function normalize_renames( mixed $renames ): array {
		if ( ! is_array( $renames ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $renames as $from => $to ) {
			if ( is_array( $to ) ) {
				$from_value = $to['from'] ?? $to['old'] ?? null;
				$to_value   = $to['to'] ?? $to['new'] ?? null;
			} else {
				$from_value = $from;
				$to_value   = $to;
			}

			if ( ! is_scalar( $from_value ) || ! is_scalar( $to_value ) ) {
				continue;
			}

			$normalized[] = array(
				'from' => self::normalize_plan_path( (string) $from_value ),
				'to'   => self::normalize_plan_path( (string) $to_value ),
			);
		}

		return $normalized;
	}

	/**
	 * Normalize local, production, and stored dependency paths for plan matching.
	 *
	 * @param mixed $path Path value.
	 *
	 * @return string Comparable path.
	 */
	private static function normalize_plan_path( mixed $path ): string {
		$path = wp_normalize_path( is_scalar( $path ) ? (string) $path : '' );
		$path = preg_replace( '#^\./#', '', $path ) ?? $path;

		if ( str_starts_with( $path, 'theme:' ) ) {
			return $path;
		}

		if ( preg_match( '#/(pages|assets|blocks|patterns|templates|inc)/(.+)$#', $path, $matches ) ) {
			return 'theme:' . $matches[1] . '/' . $matches[2];
		}

		if ( preg_match( '#^(pages|assets|blocks|patterns|templates|inc)/(.+)$#', ltrim( $path, '/' ), $matches ) ) {
			return 'theme:' . $matches[1] . '/' . $matches[2];
		}

		return $path;
	}

	/**
	 * Build the deterministic desired-inventory and deployment source identity.
	 *
	 * @param array  $desired Desired page fingerprint records.
	 * @param string $engine  Sync-engine fingerprint.
	 * @param array  $args    Reconciliation arguments.
	 *
	 * @return array<string, string> Source identity.
	 */
	private static function build_source_identity( array $desired, string $engine, array $args ): array {
		$inventory = array();
		foreach ( $desired as $name => $identity ) {
			$inventory[ (string) $name ] = array(
				'key'         => $identity['key'],
				'source'      => $identity['source'],
				'fingerprint' => $identity['fingerprint'],
			);
		}
		ksort( $inventory, SORT_STRING );

		$inventory_json = wp_json_encode( $inventory );
		$source         = isset( $args['source'] ) && is_array( $args['source'] ) ? $args['source'] : array();
		$identity       = array(
			'commit'            => isset( $source['commit'] ) && is_scalar( $source['commit'] ) ? (string) $source['commit'] : '',
			'dirtyHash'         => isset( $source['dirtyHash'] ) && is_scalar( $source['dirtyHash'] ) ? (string) $source['dirtyHash'] : '',
			'inventoryHash'     => hash( 'sha256', false === $inventory_json ? '' : $inventory_json ),
			'engineFingerprint' => $engine,
		);

		$identity_json    = wp_json_encode( $identity );
		$identity['hash'] = hash( 'sha256', false === $identity_json ? '' : $identity_json );

		return $identity;
	}

	/**
	 * Hydrate one discovered registry entry from its managed post.
	 *
	 * @param Page_Registry $registry  Registry.
	 * @param string        $name      Registry key.
	 * @param array         $page_data Page data.
	 * @param int           $post_id   Managed post ID.
	 *
	 * @return void
	 */
	private static function hydrate_registry_page( Page_Registry $registry, string $name, array $page_data, int $post_id ): void {
		$registry->set_synced_post( (string) $page_data['source_path'], $post_id );
		$registry->update_page_data( $name, 'post_id', $post_id );
		$registry->update_page_data( $name, 'post_parent', (int) get_post_field( 'post_parent', $post_id ) );
		$registry->update_page_data( $name, 'permalink', get_permalink( $post_id ) );
	}

	/**
	 * Register collection post types during WordPress bootstrap.
	 *
	 * Collection routes must also exist during arbitrary WP-CLI commands because
	 * those commands may rebuild rewrite rules. Generic CLI bootstrap reads the
	 * persistent manifest cache; explicit reconciliation refreshes it from disk.
	 *
	 * @return void
	 */
	public static function maybe_register_collection_post_types(): void {
		self::register_collection_post_types();
	}

	/**
	 * Register custom post types declared by page collection manifests.
	 *
	 * @return void
	 */
	public static function register_collection_post_types(): void {
		self::register_collection_url_hooks();

		$collections = self::get_collection_manifests( self::should_refresh_collection_manifest_cache() );

		foreach ( $collections as $collection ) {
			self::register_collection_post_type( $collection );
			self::add_collection_rewrite_rules( $collection );
		}

		self::maybe_flush_collection_rewrite_rules( $collections );
		self::set_collection_manifests_cache( $collections, false );
	}

	/**
	 * Register one collection post type when needed.
	 *
	 * @param array $collection Collection data.
	 *
	 * @return void
	 */
	private static function register_collection_post_type( array $collection ): void {
		$post_type = $collection['postType'] ?? 'page';

		if ( 'page' === $post_type || post_type_exists( $post_type ) ) {
			return;
		}

		$rewrite_slug = $collection['slug'] ?? $post_type;
		$args         = wp_parse_args(
			$collection['postTypeArgs'] ?? array(),
			array(
				'label'        => $collection['title'] ?? Page_Discovery::title_from_value( $collection['slug'] ?? $post_type ),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'page-attributes', 'thumbnail', 'excerpt', 'revisions' ),
				'rewrite'      => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
			)
		);

		if ( true === ( $args['rewrite'] ?? true ) ) {
			$args['rewrite'] = array(
				'slug'       => $rewrite_slug,
				'with_front' => false,
			);
		} elseif ( is_array( $args['rewrite'] ?? null ) ) {
			$args['rewrite'] = wp_parse_args(
				$args['rewrite'],
				array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				)
			);
		}

		/**
		 * Filter post type args for a page collection.
		 *
		 * @param array $args       Post type args.
		 * @param array $collection Collection data.
		 */
		$args = apply_filters( 'blockstudio/pages/collection_post_type_args', $args, $collection );

		register_post_type( $post_type, is_array( $args ) ? $args : array() );
	}

	/**
	 * Register URL hooks for collection CPT routing.
	 *
	 * @return void
	 */
	private static function register_collection_url_hooks(): void {
		if ( self::$collection_url_hooks_registered ) {
			return;
		}

		self::$collection_url_hooks_registered = true;

		add_filter( 'post_type_link', array( __CLASS__, 'filter_collection_post_type_link' ), 10, 2 );
		add_filter( 'query_vars', array( __CLASS__, 'register_collection_query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'resolve_collection_request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_collection_urls' ), 0 );
	}

	/**
	 * Add rewrite rules for a collection CPT.
	 *
	 * @param array $collection Collection data.
	 *
	 * @return void
	 */
	private static function add_collection_rewrite_rules( array $collection ): void {
		$post_type = isset( $collection['postType'] ) ? (string) $collection['postType'] : 'page';
		$slug      = isset( $collection['slug'] ) ? (string) $collection['slug'] : '';

		if ( 'page' === $post_type || '' === $slug ) {
			return;
		}

		$base = preg_quote( trim( $slug, '/' ), '#' );

		add_rewrite_rule(
			'^' . $base . '/(?!(?:feed|rdf|rss|rss2|atom|page|embed)(?:/|$))(.+?)/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/(.+?)/embed/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=$matches[1]&embed=true',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/(.+?)/page/?([0-9]{1,})/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=$matches[1]&paged=$matches[2]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=$matches[1]&feed=$matches[2]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/(.+?)/(feed|rdf|rss|rss2|atom)/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=$matches[1]&feed=$matches[2]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=.',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/embed/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=.&embed=true',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/page/?([0-9]{1,})/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=.&paged=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/feed/(feed|rdf|rss|rss2|atom)/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=.&feed=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/(feed|rdf|rss|rss2|atom)/?$',
			'index.php?blockstudio_collection=' . $slug . '&blockstudio_collection_path=.&feed=$matches[1]',
			'top'
		);
	}

	/**
	 * Flush rewrite rules when collection CPT routing changes.
	 *
	 * @param array $collections Collection data.
	 *
	 * @return void
	 */
	private static function maybe_flush_collection_rewrite_rules( array $collections ): void {
		$signature = self::collection_rewrite_signature( $collections );
		$option    = 'blockstudio_collection_post_types_signature';

		if ( get_option( $option ) === $signature ) {
			return;
		}

		update_option( $option, $signature, false );
		self::schedule_collection_rewrite_flush();
	}

	/**
	 * Flush rewrite rules after post types have registered.
	 *
	 * @return void
	 */
	private static function schedule_collection_rewrite_flush(): void {
		if ( did_action( 'wp_loaded' ) ) {
			flush_rewrite_rules( false );
			return;
		}

		if ( self::$collection_rewrite_flush_scheduled ) {
			return;
		}

		self::$collection_rewrite_flush_scheduled = true;

		add_action(
			'wp_loaded',
			static function (): void {
				flush_rewrite_rules( false );
				self::$collection_rewrite_flush_scheduled = false;
			}
		);
	}

	/**
	 * Build a stable signature for collection CPT routing.
	 *
	 * @param array $collections Collection data.
	 *
	 * @return string Signature.
	 */
	private static function collection_rewrite_signature( array $collections ): string {
		$items = array();

		foreach ( $collections as $collection ) {
			$post_type = isset( $collection['postType'] ) ? (string) $collection['postType'] : 'page';
			if ( 'page' === $post_type ) {
				continue;
			}

			$items[] = array(
				'slug'         => (string) ( $collection['slug'] ?? '' ),
				'postType'     => $post_type,
				'postTypeArgs' => $collection['postTypeArgs'] ?? array(),
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return ( $a['slug'] . ':' . $a['postType'] ) <=> ( $b['slug'] . ':' . $b['postType'] );
			}
		);

		$encoded = wp_json_encode( $items );

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * Register collection routing query vars.
	 *
	 * @param array $query_vars Query vars.
	 *
	 * @return array Query vars.
	 */
	public static function register_collection_query_vars( array $query_vars ): array {
		$query_vars[] = 'blockstudio_collection';
		$query_vars[] = 'blockstudio_collection_path';

		return $query_vars;
	}

	/**
	 * Filter permalinks for Blockstudio collection CPT pages.
	 *
	 * @param string   $post_link Permalink.
	 * @param \WP_Post $post      Post object.
	 *
	 * @return string Permalink.
	 */
	public static function filter_collection_post_type_link( string $post_link, \WP_Post $post ): string {
		$collection_slug = (string) get_post_meta( $post->ID, '_blockstudio_page_collection', true );

		if ( '' === $collection_slug ) {
			return $post_link;
		}

		$collection = self::get_collection_manifest( $collection_slug );

		if ( ! $collection || 'page' === ( $collection['postType'] ?? 'page' ) || ( $collection['postType'] ?? '' ) !== $post->post_type ) {
			return $post_link;
		}

		$path = (string) get_post_meta( $post->ID, '_blockstudio_page_path', true );

		return self::collection_page_url( $collection_slug, '' === $path ? '.' : $path );
	}

	/**
	 * Resolve collection CPT requests by collection path.
	 *
	 * @param \WP $wp WordPress request object.
	 *
	 * @return void
	 */
	public static function resolve_collection_request( \WP $wp ): void {
		$collection_slug = isset( $wp->query_vars['blockstudio_collection'] ) ? sanitize_key( (string) $wp->query_vars['blockstudio_collection'] ) : '';
		$path            = isset( $wp->query_vars['blockstudio_collection_path'] ) ? (string) $wp->query_vars['blockstudio_collection_path'] : '';

		if ( '' === $collection_slug || '' === $path ) {
			return;
		}

		$path = self::normalize_collection_request_path( rawurldecode( $path ) );
		$post = self::find_collection_post_by_path( $collection_slug, $path );

		if ( ! $post ) {
			self::set_collection_request_404( $wp );
			return;
		}

		unset( $wp->query_vars['blockstudio_collection'], $wp->query_vars['blockstudio_collection_path'] );

		$wp->query_vars['p']         = $post->ID;
		$wp->query_vars['post_type'] = $post->post_type;
	}

	/**
	 * Mark an unresolved collection route as a deterministic 404.
	 *
	 * @param \WP $wp WordPress request object.
	 *
	 * @return void
	 */
	private static function set_collection_request_404( \WP $wp ): void {
		$wp->query_vars = array( 'error' => '404' );
	}

	/**
	 * Redirect legacy doubled collection CPT URLs to canonical URLs.
	 *
	 * @return void
	 */
	public static function redirect_legacy_collection_urls(): void {
		$relative = self::current_request_relative_path();

		if ( '' === $relative ) {
			return;
		}

		$is_markdown = str_ends_with( $relative, '.md' );
		$path        = $is_markdown ? substr( $relative, 0, -3 ) : trim( $relative, '/' );
		$segments    = array_values( array_filter( explode( '/', $path ), static fn ( string $segment ): bool => '' !== $segment ) );

		if ( count( $segments ) < 2 || $segments[0] !== $segments[1] ) {
			return;
		}

		$collection_slug = sanitize_key( $segments[0] );
		$collection      = self::get_collection_manifest( $collection_slug );

		if ( ! $collection || 'page' === ( $collection['postType'] ?? 'page' ) ) {
			return;
		}

		$target_path = count( $segments ) > 2 ? implode( '/', array_slice( $segments, 2 ) ) : '.';
		$post        = self::find_collection_post_by_path( $collection_slug, $target_path, $is_markdown );

		if ( ! $post ) {
			return;
		}

		$target = self::collection_page_url( $collection_slug, $target_path, $is_markdown );

		if ( home_url( '/' . $relative ) === $target ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * Find a collection manifest by slug.
	 *
	 * @param string $slug Collection slug.
	 *
	 * @return array|null Collection data.
	 */
	private static function get_collection_manifest( string $slug ): ?array {
		return self::get_collection_manifests_by_slug()[ $slug ] ?? null;
	}

	/**
	 * Get discovered collection manifests.
	 *
	 * @param bool $refresh Whether to refresh the cached manifests.
	 *
	 * @return array<int, array> Collection manifests.
	 */
	private static function get_collection_manifests( bool $refresh = false ): array {
		if ( ! $refresh && null !== self::$collection_manifests_cache ) {
			return self::$collection_manifests_cache;
		}

		$paths = self::get_paths();

		/** This filter is documented in init(). */
		$paths = apply_filters( 'blockstudio/pages/paths', $paths );

		$cache_key = self::collection_manifests_cache_key( $paths );

		if ( ! $refresh ) {
			$cached = wp_cache_get( $cache_key, 'blockstudio' );

			if ( self::collection_manifests_payload_fresh( $cached ) ) {
				self::set_collection_manifests_cache( $cached['collections'], false );
				return self::$collection_manifests_cache ?? array();
			}

			$transient = get_transient( $cache_key );

			if ( self::collection_manifests_payload_fresh( $transient ) ) {
				self::set_collection_manifests_cache( $transient['collections'], false );
				wp_cache_set( $cache_key, $transient, 'blockstudio', HOUR_IN_SECONDS );
				return self::$collection_manifests_cache ?? array();
			}
		}

		$sources     = Discovery_Sources::for_paths( 'pages', $paths );
		$collections = array();

		foreach ( $sources as $source ) {
			foreach ( Page_Discovery::discover_manifests( $source ) as $collection ) {
				$collections[] = $collection;
			}
		}

		self::set_collection_manifests_cache( $collections );

		return self::$collection_manifests_cache ?? array();
	}

	/**
	 * Check whether a persistent manifest payload is still trustworthy.
	 *
	 * Manifest edits and removals are caught by the payload watch snapshot,
	 * which stats only the known manifest files and source roots. Brand-new
	 * collections in previously manifest-free trees are only visible to a
	 * scan, so payloads older than the scan interval rebuild. Admin and
	 * reconcile requests bypass this cache entirely.
	 *
	 * @param mixed $payload Cached payload.
	 *
	 * @return bool Whether the payload may be served.
	 */
	private static function collection_manifests_payload_fresh( mixed $payload ): bool {
		if ( ! is_array( $payload ) || ! is_array( $payload['collections'] ?? null ) ) {
			return false;
		}

		$scanned_at = (int) ( $payload['scannedAt'] ?? 0 );
		$interval   = self::collection_manifest_scan_interval();
		$age        = time() - $scanned_at;

		if ( $age < 0 || $age >= $interval ) {
			return false;
		}

		return Build_Cache::is_watch_valid( is_array( $payload['watch'] ?? null ) ? $payload['watch'] : array() );
	}

	/**
	 * Get the collection manifest scan interval in seconds.
	 *
	 * @return int Scan interval.
	 */
	private static function collection_manifest_scan_interval(): int {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$default     = in_array( $environment, array( 'local', 'development' ), true ) ? 5 : 20;

		/**
		 * Filter how often frontend requests rescan page trees for new collection manifests.
		 *
		 * @since 7.5.0
		 *
		 * @param int $seconds Scan interval in seconds.
		 */
		return max( 1, (int) apply_filters( 'blockstudio/pages/manifest_scan_interval', $default ) );
	}

	/**
	 * Determine whether manifest caches should be refreshed from disk.
	 *
	 * @return bool Whether to bypass persistent manifest caches.
	 */
	private static function should_refresh_collection_manifest_cache(): bool {
		return is_admin() || self::$reconciling;
	}

	/**
	 * Get collection manifests indexed by slug.
	 *
	 * @return array<string, array> Collection manifests.
	 */
	private static function get_collection_manifests_by_slug(): array {
		if ( null !== self::$collection_manifests_by_slug_cache ) {
			return self::$collection_manifests_by_slug_cache;
		}

		self::set_collection_manifests_cache( self::get_collection_manifests(), false );

		return self::$collection_manifests_by_slug_cache ?? array();
	}

	/**
	 * Store collection manifests in request and persistent caches.
	 *
	 * @param array $collections Collection manifests.
	 * @param bool  $persistent  Whether to update persistent caches.
	 *
	 * @return void
	 */
	private static function set_collection_manifests_cache( array $collections, bool $persistent = true ): void {
		$indexed = array();

		foreach ( $collections as $collection ) {
			$slug = (string) ( $collection['slug'] ?? '' );

			if ( '' !== $slug ) {
				$indexed[ $slug ] = $collection;
			}
		}

		self::$collection_manifests_cache         = array_values( $collections );
		self::$collection_manifests_by_slug_cache = $indexed;

		if ( ! $persistent ) {
			return;
		}

		$paths = self::get_paths();

		/** This filter is documented in init(). */
		$paths = apply_filters( 'blockstudio/pages/paths', $paths );

		$watch_files = array();
		$watch_dirs  = $paths;

		foreach ( self::$collection_manifests_cache as $collection ) {
			if ( is_string( $collection['manifest_path'] ?? null ) && '' !== $collection['manifest_path'] ) {
				$watch_files[] = $collection['manifest_path'];
				$watch_dirs[]  = dirname( $collection['manifest_path'] );
			}
		}

		$payload = array(
			'collections' => self::$collection_manifests_cache,
			'watch'       => Build_Cache::create_watch_snapshot( $watch_files, $watch_dirs ),
			'scannedAt'   => time(),
		);

		$cache_key = self::collection_manifests_cache_key( $paths );
		wp_cache_set( $cache_key, $payload, 'blockstudio', HOUR_IN_SECONDS );
		set_transient( $cache_key, $payload, HOUR_IN_SECONDS );
	}

	/**
	 * Build a persistent collection manifest cache key.
	 *
	 * @param array $paths Discovery paths.
	 *
	 * @return string Cache key.
	 */
	private static function collection_manifests_cache_key( array $paths ): string {
		$paths = array_values(
			array_map(
				static fn ( mixed $path ): string => is_scalar( $path ) ? wp_normalize_path( (string) $path ) : '',
				$paths
			)
		);

		$encoded = wp_json_encode(
			array(
				'paths'     => $paths,
				'signature' => (string) get_option( 'blockstudio_collection_post_types_signature', '' ),
				'context'   => Runtime_Context::hash( 'page-manifests', array( 'pages' ) ),
			)
		);

		return 'blockstudio_collection_manifests_' . md5( false === $encoded ? '' : $encoded );
	}

	/**
	 * Find a synced collection post by logical collection path.
	 *
	 * @param string $collection_slug Collection slug.
	 * @param string $path            Logical collection path.
	 * @param bool   $markdown_only   Require a markdown source file.
	 *
	 * @return \WP_Post|null Post object.
	 */
	private static function find_collection_post_by_path( string $collection_slug, string $path, bool $markdown_only = false ): ?\WP_Post {
		$collection = self::get_collection_manifest( $collection_slug );

		if ( ! $collection ) {
			return null;
		}

		$post_type = (string) ( $collection['postType'] ?? 'page' );
		$path      = self::normalize_collection_request_path( $path );
		$meta      = array(
			'relation' => 'AND',
			array(
				'key'   => '_blockstudio_page_collection',
				'value' => $collection_slug,
			),
			array(
				'key'   => '_blockstudio_page_path',
				'value' => $path,
			),
		);

		if ( $markdown_only ) {
			$meta[] = array(
				'key'   => '_blockstudio_page_content_type',
				'value' => 'markdown',
			);
		}

		$posts = get_posts(
			array(
				'meta_key'       => '_blockstudio_page_route', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $collection_slug . ':' . $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		if ( ! empty( $posts ) && $posts[0] instanceof \WP_Post ) {
			return $posts[0];
		}

		$posts = get_posts(
			array(
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		return ! empty( $posts ) && $posts[0] instanceof \WP_Post ? $posts[0] : null;
	}

	/**
	 * Find a synced collection post by request-relative path.
	 *
	 * @param string $relative_path Request path without home path.
	 * @param bool   $markdown_only Require a markdown source file.
	 *
	 * @return \WP_Post|null Post object.
	 */
	private static function find_collection_post_by_relative_path( string $relative_path, bool $markdown_only = false ): ?\WP_Post {
		$relative_path = trim( $relative_path, '/' );

		foreach ( self::get_collection_manifests() as $collection ) {
			$slug = (string) ( $collection['slug'] ?? '' );

			if ( '' === $slug ) {
				continue;
			}

			if ( $relative_path === $slug ) {
				return self::find_collection_post_by_path( $slug, '.', $markdown_only );
			}

			if ( str_starts_with( $relative_path, $slug . '/' ) ) {
				return self::find_collection_post_by_path( $slug, substr( $relative_path, strlen( $slug ) + 1 ), $markdown_only );
			}
		}

		return null;
	}

	/**
	 * Build a canonical collection page URL.
	 *
	 * @param string $collection_slug Collection slug.
	 * @param string $path            Logical collection path.
	 * @param bool   $markdown        Whether to build the raw markdown URL.
	 *
	 * @return string URL.
	 */
	private static function collection_page_url( string $collection_slug, string $path, bool $markdown = false ): string {
		$path     = self::normalize_collection_request_path( $path );
		$relative = $collection_slug;

		if ( '.' !== $path ) {
			$relative .= '/' . $path;
		}

		if ( $markdown ) {
			return home_url( '/' . $relative . '.md' );
		}

		return home_url( user_trailingslashit( '/' . $relative ) );
	}

	/**
	 * Normalize a collection request path.
	 *
	 * @param string $path Raw path.
	 *
	 * @return string Logical path.
	 */
	private static function normalize_collection_request_path( string $path ): string {
		$path = trim( wp_normalize_path( $path ), '/' );

		return '' === $path ? '.' : $path;
	}

	/**
	 * Get the current request path relative to home_url().
	 *
	 * @return string Relative path.
	 */
	private static function current_request_relative_path(): string {
		$request_path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
		$home_path    = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$relative     = trim( rawurldecode( $request_path ), '/' );

		if ( '' !== $home_path && 0 === strpos( $relative, $home_path . '/' ) ) {
			return substr( $relative, strlen( $home_path ) + 1 );
		}

		if ( $relative === $home_path ) {
			return '';
		}

		return $relative;
	}

	/**
	 * Determine whether pages should initialize in the current request.
	 *
	 * Normal automatic initialization stays limited to admin requests.
	 * Explicit force initialization is allowed for trusted callers that need to
	 * sync pages from controlled frontend contexts, such as local dev tooling.
	 *
	 * @param array     $args Optional arguments.
	 * @param bool|null $is_admin_request Optional admin context override for tests.
	 * @param bool|null $is_cli_request Optional WP-CLI context override for tests.
	 *
	 * @return bool Whether initialization is allowed.
	 */
	private static function can_init_in_current_context( array $args = array(), ?bool $is_admin_request = null, ?bool $is_cli_request = null ): bool {
		if ( ! empty( $args['force'] ) ) {
			return true;
		}

		$is_admin_request ??= is_admin();
		$is_cli_request   ??= defined( 'WP_CLI' ) && WP_CLI;

		return $is_admin_request && ! $is_cli_request;
	}

	/**
	 * Get default pages paths.
	 *
	 * @return array<string> Array of directory paths.
	 */
	public static function get_paths(): array {
		return Utils::theme_subdir_paths( 'pages' );
	}

	/**
	 * Register hooks for template-for functionality.
	 *
	 * @return void
	 */
	private static function register_template_for_hooks(): void {
		$registry = Page_Registry::instance();

		$all_template_for = $registry->get_all_template_for();

		if ( empty( $all_template_for ) ) {
			return;
		}

		$parser = Html_Parser::from_settings();

		// Apply to already-registered post types (since Pages::init runs late).
		foreach ( $all_template_for as $post_type => $template_page ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( ! $post_type_object ) {
				continue;
			}

			$template = self::build_post_type_template( $parser, $template_page );

			if ( ! $template ) {
				continue;
			}

			$post_type_object->template      = $template;
			$post_type_object->template_lock = $template_page['templateLock'];
		}

		// Also hook for any post types registered after this point.
		add_filter(
			'register_post_type_args',
			function ( array $args, string $post_type ) use ( $registry, $parser ): array {
				$template_page = $registry->get_template_for( $post_type );

				if ( ! $template_page ) {
					return $args;
				}

				$template = self::build_post_type_template( $parser, $template_page );

				if ( ! $template ) {
					return $args;
				}

				$args['template']      = $template;
				$args['template_lock'] = $template_page['templateLock'];

				return $args;
			},
			10,
			2
		);
	}

	/**
	 * Build a post type template array from a page's template file.
	 *
	 * Parses the HTML template and converts parsed blocks to the
	 * WordPress post type template format: [blockName, attrs, innerBlocks].
	 *
	 * @param Html_Parser $parser        The HTML parser instance.
	 * @param array       $template_page The template page data.
	 *
	 * @return array|null The template array or null on failure.
	 */
	private static function build_post_type_template( Html_Parser $parser, array $template_page ): ?array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
		$template_content = file_get_contents( $template_page['template_path'] );

		if ( false === $template_content ) {
			return null;
		}

		if ( 'markdown' === ( $template_page['contentType'] ?? '' ) ) {
			$parts            = Page_Markdown::split_frontmatter( $template_content );
			$template_content = Page_Markdown::to_html( $parts['body'] );
		}

		$blocks = $parser->parse_to_array( $template_content );

		return self::blocks_to_template( $blocks );
	}

	/**
	 * Convert parsed blocks to WordPress post type template format.
	 *
	 * WordPress expects: [ [blockName, attrs, innerBlocks], ... ]
	 * parse_to_array returns: [ ['blockName' => ..., 'attrs' => ..., ...], ... ]
	 *
	 * Blocks like core/heading and core/paragraph store their text in innerHTML,
	 * but the template format needs it in attrs['content'].
	 *
	 * @param array $blocks Parsed blocks from Html_Parser.
	 *
	 * @return array Template-format blocks.
	 */
	private static function blocks_to_template( array $blocks ): array {
		$template = array();

		foreach ( $blocks as $block ) {
			$attrs = $block['attrs'];
			$inner = ! empty( $block['innerBlocks'] )
				? self::blocks_to_template( $block['innerBlocks'] )
				: array();

			// WordPress template format needs text in attrs['content'], not innerHTML.
			if ( ! isset( $attrs['content'] ) && ! empty( $block['innerHTML'] ) ) {
				$content = self::extract_block_content( $block['innerHTML'] );

				if ( '' !== $content ) {
					$attrs['content'] = $content;
				}
			}

			$template[] = array( $block['blockName'], $attrs, $inner );
		}

		return $template;
	}

	/**
	 * Extract inner text content from block innerHTML markup.
	 *
	 * Strips the outermost HTML tag wrapper to get the rich-text content.
	 * E.g. '<h1 class="wp-block-heading">Title</h1>' → 'Title'
	 *
	 * @param string $inner_html The block innerHTML.
	 *
	 * @return string The extracted content.
	 */
	private static function extract_block_content( string $inner_html ): string {
		$inner_html = trim( $inner_html );

		if ( preg_match( '/^<[^>]+>(.*)<\/[^>]+>$/s', $inner_html, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Register hooks for template lock on individual posts.
	 *
	 * @return void
	 */
	private static function register_template_lock_hooks(): void {
		add_filter(
			'block_editor_settings_all',
			function ( array $settings, \WP_Block_Editor_Context $context ): array {
				if ( empty( $context->post ) ) {
					return $settings;
				}

				$template_lock = get_post_meta( $context->post->ID, '_blockstudio_template_lock', true );

				if ( empty( $template_lock ) ) {
					return $settings;
				}

				$settings['templateLock']  = $template_lock;
				$settings['canLockBlocks'] = false;

				return $settings;
			},
			10,
			2
		);
	}

	/**
	 * Register hooks for block editing mode support.
	 *
	 * @return void
	 */
	private static function register_block_editing_mode_hooks(): void {
		add_filter(
			'block_editor_settings_all',
			function ( array $settings, \WP_Block_Editor_Context $context ): array {
				if ( empty( $context->post ) ) {
					return $settings;
				}

				$block_editing_mode = get_post_meta( $context->post->ID, '_blockstudio_block_editing_mode', true );

				if ( ! empty( $block_editing_mode ) ) {
					$settings['blockstudioBlockEditingMode'] = $block_editing_mode;
				}

				return $settings;
			},
			10,
			2
		);

		add_action(
			'enqueue_block_editor_assets',
			function (): void {
				$asset_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/pages/index.asset.php';

				if ( ! file_exists( $asset_file ) ) {
					return;
				}

				$asset = include $asset_file;

				wp_enqueue_script(
					'blockstudio-pages',
					BLOCKSTUDIO_URL . 'includes/admin/assets/pages/index.js',
					$asset['dependencies'],
					$asset['version'],
					true
				);
			}
		);
	}

	/**
	 * Register frontend layout rendering.
	 *
	 * @return void
	 */
	private static function register_runtime_hooks(): void {
		if ( self::$runtime_hooks_registered ) {
			return;
		}
		self::$runtime_hooks_registered = true;
		self::register_template_lock_hooks();
		self::register_block_editing_mode_hooks();
		add_filter( 'the_content', array( __CLASS__, 'render_layout_content' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'serve_markdown' ), 1 );
	}

	/**
	 * Serve the raw markdown source for a markdown-backed page.
	 *
	 * Responds when the request appends `.md` to a page permalink or sends an
	 * `Accept: text/markdown` header, for any synced page whose content is a
	 * markdown file, so documentation pages are readable by agents and tools.
	 *
	 * @return void
	 */
	public static function serve_markdown(): void {
		if ( ! apply_filters( 'blockstudio/pages/serve_markdown', true ) ) {
			return;
		}

		if ( 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
			return;
		}

		$accept       = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ?? '' ) );
		$wants_accept = false !== stripos( $accept, 'text/markdown' );
		$uri_path     = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
		$is_md_ext    = '.md' === substr( $uri_path, -3 );

		if ( ! $wants_accept && ! $is_md_ext ) {
			return;
		}

		$post = null;
		if ( $is_md_ext ) {
			$relative = preg_replace( '/\.md$/', '', self::current_request_relative_path() );
			$post     = self::find_collection_post_by_relative_path( (string) $relative, true );

			if ( ! $post ) {
				$post = get_page_by_path( (string) $relative, OBJECT, get_post_types() );
			}
		} else {
			$queried = (int) get_queried_object_id();
			$post    = $queried > 0 ? get_post( $queried ) : null;
		}

		$file = $post ? (string) get_post_meta( $post->ID, '_blockstudio_page_content_path', true ) : '';
		if ( ! $post || ! self::can_serve_markdown_post( $post ) ) {
			if ( $is_md_ext ) {
				self::serve_markdown_not_found();
			}
			return;
		}

		if ( '' === $file || ! is_file( $file ) ) {
			if ( $is_md_ext ) {
				self::serve_markdown_not_found();
			}
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$markdown = file_get_contents( $file );
		if ( false === $markdown ) {
			return;
		}

		$parts    = Page_Markdown::split_frontmatter( (string) $markdown );
		$markdown = ltrim( (string) $parts['body'] );

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex', true );

		$canonical = self::canonical_url_for_markdown_post( $post );
		if ( '' !== $canonical ) {
			header( 'Link: <' . esc_url_raw( $canonical ) . '>; rel="canonical"', false );
		}

		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Check whether a raw markdown response may expose this post source.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool
	 */
	private static function can_serve_markdown_post( \WP_Post $post ): bool {
		if ( '' !== (string) $post->post_password ) {
			return current_user_can( 'read_post', $post->ID );
		}

		return is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Send a real 404 response for an unresolved raw markdown route.
	 *
	 * @return void
	 */
	private static function serve_markdown_not_found(): void {
		global $wp_query;

		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();

		$template = get_404_template();

		if ( $template ) {
			include $template;
		}

		exit;
	}

	/**
	 * Build the canonical HTML URL for a markdown source response.
	 *
	 * @param \WP_Post|null $post Post object.
	 *
	 * @return string Canonical URL.
	 */
	private static function canonical_url_for_markdown_post( ?\WP_Post $post ): string {
		if ( ! $post ) {
			return '';
		}

		$collection_slug = (string) get_post_meta( $post->ID, '_blockstudio_page_collection', true );
		$path            = (string) get_post_meta( $post->ID, '_blockstudio_page_path', true );

		if ( '' !== $collection_slug ) {
			return self::collection_page_url( $collection_slug, '' === $path ? '.' : $path );
		}

		$permalink = get_permalink( $post );

		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * Render a page layout around arbitrary content.
	 *
	 * This is the programmatic counterpart to the frontend `the_content`
	 * integration. It exposes the same `page_content()` and `current_page()`
	 * context to `layout.php` without requiring a singular WordPress request.
	 *
	 * @since 7.6.0
	 *
	 * @param string $content     Rendered page content.
	 * @param array  $page        Canonical page data.
	 * @param string $layout_path Optional explicit layout path.
	 *
	 * @return string Rendered layout, or the original content when unavailable.
	 */
	public static function render_layout( string $content, array $page, string $layout_path = '' ): string {
		if ( '' === $layout_path ) {
			$layout_path = is_string( $page['layout_path'] ?? null )
				? $page['layout_path']
				: '';
		}

		if ( '' === $layout_path || ! is_file( $layout_path ) ) {
			return $content;
		}

		$previous_rendering_layout = self::$rendering_layout;
		$previous_current_page     = self::$current_page;
		$previous_page_content     = self::$current_page_content;
		$initial_buffer_level      = ob_get_level();

		try {
			self::$rendering_layout     = true;
			self::$current_page         = $page;
			self::$current_page_content = $content;

			ob_start();
			include $layout_path;
			$rendered = ob_get_clean();

			return false === $rendered ? $content : $rendered;
		} catch ( \Throwable $throwable ) {
			while ( ob_get_level() > $initial_buffer_level ) {
				ob_end_clean();
			}

			/**
			 * Fires when a programmatic page layout cannot be rendered.
			 *
			 * @since 7.6.0
			 *
			 * @param \Throwable $throwable  Rendering failure.
			 * @param string     $layout_path Layout file.
			 * @param array      $page         Canonical page data.
			 */
			do_action( 'blockstudio/pages/layout_error', $throwable, $layout_path, $page );

			return $content;
		} finally {
			self::$rendering_layout     = $previous_rendering_layout;
			self::$current_page         = $previous_current_page;
			self::$current_page_content = $previous_page_content;
		}
	}

	/**
	 * Render collection layout.php around frontend page content.
	 *
	 * @param string $content Original post content.
	 *
	 * @return string Content.
	 */
	public static function render_layout_content( string $content ): string {
		if ( is_admin() || self::$rendering_layout || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = (int) get_the_ID();

		if ( $post_id <= 0 ) {
			return $content;
		}

		$layout_path = (string) get_post_meta( $post_id, '_blockstudio_page_layout', true );

		if ( '' === $layout_path || ! file_exists( $layout_path ) ) {
			return $content;
		}

		$page = self::page_for_post_id( $post_id );

		return self::render_layout( $content, is_array( $page ) ? $page : array(), $layout_path );
	}

	/**
	 * Get the content currently being wrapped by a page layout.
	 *
	 * @return string Page content.
	 */
	public static function page_content(): string {
		return self::$current_page_content;
	}

	/**
	 * Get current Blockstudio page data.
	 *
	 * @return array|null Page data.
	 */
	public static function current_page(): ?array {
		if ( null !== self::$current_page ) {
			return self::$current_page;
		}

		$post_id = (int) get_queried_object_id();

		return $post_id > 0 ? self::page_for_post_id( $post_id ) : null;
	}

	/**
	 * Get Blockstudio page data by post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null Page data.
	 */
	public static function page_for_post_id( int $post_id ): ?array {
		return Page_Registry::instance()->get_page_by_post_id( $post_id );
	}

	/**
	 * Get all registered pages.
	 *
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<string, array> The pages.
	 */
	public static function pages( ?string $collection = null ): array {
		$registry = Page_Registry::instance();

		return null === $collection ? $registry->get_pages() : $registry->in_collection( $collection );
	}

	/**
	 * Discover the current page sources without synchronizing WordPress posts.
	 *
	 * Canvas and other read-only consumers need the source-backed registry even
	 * when the ordinary frontend registry was hydrated from persisted posts.
	 * Existing post IDs and permalinks are merged back into matching discovered
	 * records without writing content or advancing reconciliation state.
	 *
	 * @since 7.6.0
	 *
	 * @return array<string, array> Source-backed pages.
	 */
	public static function discover(): array {
		$registry = Page_Registry::instance();

		if ( array() !== $registry->get_paths() ) {
			self::hydrate_source_registry_pages( $registry );

			return $registry->get_registered_pages();
		}

		$persisted = $registry->get_pages();
		$registry  = self::discover_registry();

		foreach ( $registry->get_registered_pages() as $name => $page_data ) {
			$key      = is_string( $page_data['key'] ?? null ) ? $page_data['key'] : (string) $name;
			$existing = is_array( $persisted[ $key ] ?? null )
				? $persisted[ $key ]
				: ( is_array( $persisted[ $name ] ?? null ) ? $persisted[ $name ] : array() );
			$post_id  = isset( $existing['post_id'] ) && is_numeric( $existing['post_id'] )
				? (int) $existing['post_id']
				: 0;

			if ( $post_id > 0 ) {
				self::hydrate_registry_page( $registry, (string) $name, $page_data, $post_id );
			}
		}

		return $registry->get_registered_pages();
	}

	/**
	 * Merge managed post identity into an already source-backed registry.
	 *
	 * Logical discovery sources can populate the registry before Canvas asks for
	 * pages. The registry then deliberately skips lazy database hydration because
	 * its source records are already present, so resolve their stable keys or
	 * source identities against the existing managed post inventory without
	 * synchronizing content.
	 *
	 * @param Page_Registry $registry Source-backed registry.
	 *
	 * @return void
	 */
	private static function hydrate_source_registry_pages( Page_Registry $registry ): void {
		$pages = $registry->get_registered_pages();

		if ( array() === $pages ) {
			return;
		}

		$unresolved = array_filter(
			$pages,
			static fn ( array $page ): bool => ! isset( $page['post_id'] )
				|| ! is_numeric( $page['post_id'] )
				|| (int) $page['post_id'] <= 0
		);

		if ( array() === $unresolved ) {
			return;
		}

		$indexes = self::managed_post_indexes( ( new Page_Sync() )->managed_posts() );

		foreach ( $unresolved as $name => $page_data ) {
			$key        = is_scalar( $page_data['key'] ?? null )
				? (string) $page_data['key']
				: (string) $name;
			$source     = is_scalar( $page_data['source_path'] ?? null )
				? (string) $page_data['source_path']
				: '';
			$candidates = array_merge(
				$indexes['key'][ $key ] ?? array(),
				'' !== $source ? ( $indexes['source'][ $source ] ?? array() ) : array()
			);

			foreach ( $candidates as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				self::hydrate_registry_page( $registry, (string) $name, $page_data, $post->ID );
				break;
			}
		}
	}

	/**
	 * Get pages in a collection.
	 *
	 * @param string $collection Collection slug.
	 *
	 * @return array<string, array> Pages.
	 */
	public static function in_collection( string $collection ): array {
		return Page_Registry::instance()->in_collection( $collection );
	}

	/**
	 * Get a nested page tree.
	 *
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<int, array> Page tree.
	 */
	public static function tree( ?string $collection = null ): array {
		return Page_Registry::instance()->tree( $collection );
	}

	/**
	 * Get direct child pages.
	 *
	 * @param string      $name       Page name or key.
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<string, array> Child pages.
	 */
	public static function children( string $name, ?string $collection = null ): array {
		return Page_Registry::instance()->children( $name, $collection );
	}

	/**
	 * Get collection data.
	 *
	 * @param string $collection Collection slug.
	 *
	 * @return array|null Collection data.
	 */
	public static function collection( string $collection ): ?array {
		return Page_Registry::instance()->get_collection( $collection );
	}

	/**
	 * Get a page by name.
	 *
	 * @param string $name The page name.
	 *
	 * @return array|null The page data or null.
	 */
	public static function get_page( string $name ): ?array {
		return Page_Registry::instance()->get_page( $name );
	}

	/**
	 * Get synced post ID for a page.
	 *
	 * @param string $name The page name.
	 *
	 * @return int|null The post ID or null.
	 */
	public static function get_post_id( string $name ): ?int {
		$page = Page_Registry::instance()->get_page( $name );

		return $page['post_id'] ?? null;
	}

	/**
	 * Get page discovery and sync errors.
	 *
	 * @since 7.6.0
	 *
	 * @return array<int, array> Errors.
	 */
	public static function errors(): array {
		return Page_Registry::instance()->get_errors();
	}

	/**
	 * Force sync all pages.
	 *
	 * @return array<string, int|\WP_Error> Results indexed by page name.
	 */
	public static function force_sync_all(): array {
		$results  = array();
		$registry = Page_Registry::instance();
		$sync     = new Page_Sync();

		foreach ( $registry->get_pages() as $name => $page_data ) {
			$results[ $name ] = $sync->force_sync( $page_data );
		}

		return $results;
	}

	/**
	 * Force sync a single page.
	 *
	 * @param string $name The page name.
	 *
	 * @return int|\WP_Error|null The post ID, WP_Error, or null if page not found.
	 */
	public static function force_sync( string $name ): int|\WP_Error|null {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page ) {
			return null;
		}

		$sync = new Page_Sync();

		return $sync->force_sync( $page );
	}

	/**
	 * Lock a page to prevent automatic updates.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool Whether the page was locked.
	 */
	public static function lock( string $name ): bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return false;
		}

		$sync = new Page_Sync();
		$sync->lock_post( $page['post_id'] );

		return true;
	}

	/**
	 * Unlock a page to allow automatic updates.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool Whether the page was unlocked.
	 */
	public static function unlock( string $name ): bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return false;
		}

		$sync = new Page_Sync();
		$sync->unlock_post( $page['post_id'] );

		return true;
	}

	/**
	 * Check if a page is locked.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool|null Whether the page is locked, or null if page not found.
	 */
	public static function is_locked( string $name ): ?bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return null;
		}

		return (bool) get_post_meta( $page['post_id'], '_blockstudio_page_locked', true );
	}

	/**
	 * Get registered paths.
	 *
	 * @return array<string> The paths.
	 */
	public static function get_registered_paths(): array {
		return Page_Registry::instance()->get_paths();
	}

	/**
	 * Reset the pages system (mainly for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		Page_Registry::instance()->reset();
		self::$initialized                        = false;
		self::$reconciling                        = false;
		self::$collection_manifests_cache         = null;
		self::$collection_manifests_by_slug_cache = null;
		self::$current_page                       = null;
		self::$current_page_content               = '';
		self::$rendering_layout                   = false;
	}
}

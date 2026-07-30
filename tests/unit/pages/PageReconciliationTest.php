<?php
/**
 * Page reconciliation tests.
 *
 * @package Blockstudio
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.DB.SlowDBQuery

use Blockstudio\Canvas;
use Blockstudio\Page_Sync;
use Blockstudio\Pages;
use Blockstudio\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Focused coverage for the deployment reconciliation boundary.
 */
class PageReconciliationTest extends TestCase {

	/**
	 * Temporary page discovery root.
	 *
	 * @var string
	 */
	private string $pages_path;

	/**
	 * Controllable sync-engine test input.
	 *
	 * @var string
	 */
	private string $engine_version = 'a';

	/**
	 * Create one isolated desired inventory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->pages_path = wp_normalize_path( get_temp_dir() . 'blockstudio-reconcile-' . wp_generate_uuid4() );
		wp_mkdir_p( $this->pages_path );
		$this->delete_managed_posts();
		delete_option( 'blockstudio_pages_successful_source_identity' );
		delete_option( 'blockstudio_pages_collection_manifests' );
		delete_option( 'blockstudio_pages_template_for' );
		delete_option( 'blockstudio_collection_post_types_signature' );
		Pages::reset();
		add_filter( 'blockstudio/pages/paths', array( $this, 'filter_paths' ), PHP_INT_MAX );
	}

	/**
	 * Remove the isolated desired and managed inventories.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_filter( 'blockstudio/pages/paths', array( $this, 'filter_paths' ), PHP_INT_MAX );
		remove_filter( 'blockstudio/pages/sync_engine_inputs', array( $this, 'filter_engine' ) );
		$this->delete_managed_posts();
		$this->remove_directory( $this->pages_path );
		delete_option( 'blockstudio_pages_successful_source_identity' );
		delete_option( 'blockstudio_pages_collection_manifests' );
		delete_option( 'blockstudio_pages_template_for' );
		delete_option( 'blockstudio_collection_post_types_signature' );
		Pages::reset();
	}

	/**
	 * Limit discovery to the temporary fixture.
	 *
	 * @return array<int, string> Fixture paths.
	 */
	public function filter_paths(): array {
		return array( $this->pages_path );
	}

	/**
	 * Add a controllable migration input.
	 *
	 * @param array $inputs Engine inputs.
	 *
	 * @return array Engine inputs.
	 */
	public function filter_engine( array $inputs ): array {
		$inputs['testMapping'] = $this->engine_version;

		return $inputs;
	}

	/**
	 * Equal page and engine fingerprints are a literal zero-write path.
	 *
	 * @return void
	 */
	public function test_no_change_reconciliation_writes_zero_posts_or_meta(): void {
		$this->write_page( 'alpha', 'Alpha' );
		$this->write_page( 'beta', 'Beta' );

		$first = $this->reconcile( true );
		$this->assertSame( 2, $first['created'] );
		$this->assertTrue( Pages::store_successful_source_identity( $first['sourceIdentity'] ) );

		$writes     = 0;
		$post_ids   = array_filter( array_column( $first['pages'], 'postId' ) );
		$save_post  = static function ( int $post_id ) use ( &$writes, $post_ids ): void {
			if ( in_array( $post_id, $post_ids, true ) ) {
				++$writes;
			}
		};
		$meta_write = static function ( mixed $meta_id, int $post_id ) use ( &$writes, $post_ids ): void {
			unset( $meta_id );
			if ( in_array( $post_id, $post_ids, true ) ) {
				++$writes;
			}
		};

		add_action( 'save_post', $save_post );
		add_action( 'added_post_meta', $meta_write, 10, 2 );
		add_action( 'updated_post_meta', $meta_write, 10, 2 );
		add_action( 'deleted_post_meta', $meta_write, 10, 2 );

		$second = $this->reconcile();

		remove_action( 'save_post', $save_post );
		remove_action( 'added_post_meta', $meta_write, 10 );
		remove_action( 'updated_post_meta', $meta_write, 10 );
		remove_action( 'deleted_post_meta', $meta_write, 10 );

		$this->assertSame( 0, $second['created'] );
		$this->assertSame( 0, $second['updated'] );
		$this->assertSame( 2, $second['unchanged'] );
		$this->assertSame( 0, $second['removed'] );
		$this->assertSame( 0, $second['failed'] );
		$this->assertFalse( $second['fullReconciliation'] );
		$this->assertSame( 0, $writes );
	}

	/**
	 * An unchanged source refreshes release-local runtime paths without updating the post.
	 *
	 * @return void
	 */
	public function test_no_change_reconciliation_refreshes_relocated_runtime_paths(): void {
		$this->write_page( 'alpha', 'Alpha' );
		$first     = $this->reconcile( true );
		$post_id   = $first['pages']['alpha']['postId'];
		$page_data = Pages::get_page( 'alpha' );
		$before    = get_post( $post_id );

		$this->assertIsArray( $page_data );
		$this->assertInstanceOf( WP_Post::class, $before );

		$release_directory = $this->pages_path . '/release-two/alpha';
		wp_mkdir_p( $release_directory );
		copy( $page_data['json_path'], $release_directory . '/page.json' );
		copy( $page_data['template_path'], $release_directory . '/index.php' );

		$page_data['json_path']          = $release_directory . '/page.json';
		$page_data['template_path']      = $release_directory . '/index.php';
		$page_data['content_path']       = $release_directory . '/index.php';
		$page_data['directory']          = $release_directory;
		$page_data['source_mtime_paths'] = array(
			$page_data['json_path'],
			$page_data['content_path'],
		);

		$post_writes = 0;
		$save_post   = static function ( int $saved_post_id ) use ( &$post_writes, $post_id ): void {
			if ( $post_id === $saved_post_id ) {
				++$post_writes;
			}
		};

		add_action( 'save_post', $save_post );
		$result = ( new Page_Sync() )->reconcile( $page_data );
		remove_action( 'save_post', $save_post );

		$after = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $after );
		$this->assertSame( 'unchanged', $result['status'] );
		$this->assertSame( 0, $post_writes );
		$this->assertSame( $before->post_content, $after->post_content );
		$this->assertSame( $before->post_modified_gmt, $after->post_modified_gmt );
		$this->assertSame( $page_data['template_path'], get_post_meta( $post_id, '_blockstudio_page_template_path', true ) );
		$this->assertSame( $page_data['directory'], get_post_meta( $post_id, '_blockstudio_page_directory', true ) );
	}

	/**
	 * A matching source fingerprint must not hide database-row drift.
	 *
	 * @return void
	 */
	public function test_reconciliation_restores_trashed_fingerprint_match(): void {
		$this->write_page( 'alpha', 'Alpha' );
		$first   = $this->reconcile( true );
		$post_id = $first['pages']['alpha']['postId'];

		wp_trash_post( $post_id );
		$this->assertSame( 'trash', get_post_status( $post_id ) );
		$this->assertSame( 'alpha__trashed', get_post_field( 'post_name', $post_id ) );

		$second = $this->reconcile();

		$this->assertSame( 0, $second['created'] );
		$this->assertSame( 1, $second['updated'] );
		$this->assertSame( 0, $second['unchanged'] );
		$this->assertSame( $post_id, $second['pages']['alpha']['postId'] );
		$this->assertSame( 'publish', get_post_status( $post_id ) );
		$this->assertSame( 'alpha', get_post_field( 'post_name', $post_id ) );
	}

	/**
	 * A stale preloaded inventory rechecks identity before creating a duplicate.
	 *
	 * @return void
	 */
	public function test_reconciliation_rechecks_identity_after_stale_inventory(): void {
		$this->write_page( 'alpha', 'Alpha' );
		$first     = $this->reconcile( true );
		$post_id   = $first['pages']['alpha']['postId'];
		$page_data = Pages::get_page( 'alpha' );

		$result = ( new Page_Sync() )->reconcile(
			$page_data,
			array( 'existing' => null )
		);

		$this->assertSame( 'unchanged', $result['status'] );
		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Create-only post data customizations remain valid on unchanged sources.
	 *
	 * @return void
	 */
	public function test_reconciliation_accepts_create_post_data_customization(): void {
		$filter = static function ( array $post_data ): array {
			$post_data['post_title'] = 'Customized Alpha';

			return $post_data;
		};
		add_filter( 'blockstudio/pages/create_post_data', $filter );

		try {
			$this->write_page( 'alpha', 'Alpha' );
			$first   = $this->reconcile( true );
			$post_id = $first['pages']['alpha']['postId'];
			$second  = $this->reconcile();

			$this->assertSame( 'Customized Alpha', get_post_field( 'post_title', $post_id ) );
			$this->assertSame( 0, $second['updated'] );
			$this->assertSame( 1, $second['unchanged'] );
			$this->assertSame( $post_id, $second['pages']['alpha']['postId'] );
		} finally {
			remove_filter( 'blockstudio/pages/create_post_data', $filter );
		}
	}

	/**
	 * Force sync remains an explicit override for sync-disabled pages.
	 *
	 * @return void
	 */
	public function test_force_sync_creates_and_updates_sync_disabled_page(): void {
		$this->write_page( 'alpha', 'Alpha', false );
		$initial = $this->reconcile( true );

		$this->assertSame( 0, $initial['created'] );
		$this->assertSame( 1, $initial['unchanged'] );
		$this->assertSame( 0, $initial['pages']['alpha']['postId'] );

		$post_id = Pages::force_sync( 'alpha' );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertStringContainsString( 'Alpha', (string) get_post_field( 'post_content', $post_id ) );

		$this->write_page( 'alpha', 'Alpha changed', false );
		$normal = $this->reconcile( false, 'changed-source' );
		$this->assertSame( 0, $normal['updated'] );
		$this->assertSame( 1, $normal['unchanged'] );
		$this->assertStringNotContainsString( 'Alpha changed', (string) get_post_field( 'post_content', $post_id ) );

		$forced = Pages::force_sync( 'alpha' );
		$this->assertSame( $post_id, $forced );
		$this->assertStringContainsString( 'Alpha changed', (string) get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * A single changed source updates only its managed post.
	 *
	 * @return void
	 */
	public function test_one_source_change_updates_exactly_one_page(): void {
		$this->write_page( 'alpha', 'Alpha' );
		$this->write_page( 'beta', 'Beta' );
		$first = $this->reconcile( true );
		Pages::store_successful_source_identity( $first['sourceIdentity'] );

		$this->write_page( 'alpha', 'Alpha changed' );
		$second = $this->reconcile( false, 'dirty-a' );

		$this->assertSame( 0, $second['created'] );
		$this->assertSame( 1, $second['updated'] );
		$this->assertSame( 1, $second['unchanged'] );
		$this->assertSame( 0, $second['removed'] );
		$this->assertSame( 'updated', $second['pages']['alpha']['status'] );
		$this->assertSame( 'unchanged', $second['pages']['beta']['status'] );
	}

	/**
	 * Inventory additions, deletions, and rename rebinding stay distinct.
	 *
	 * @return void
	 */
	public function test_add_delete_and_explicit_rename_rebind_managed_identity(): void {
		$this->write_page( 'alpha', 'Alpha' );
		$this->write_page( 'beta', 'Beta' );
		$first = $this->reconcile( true );

		$this->remove_directory( $this->pages_path . '/beta' );
		$this->write_page( 'gamma', 'Gamma' );
		$second = $this->reconcile();

		$this->assertSame( 1, $second['created'] );
		$this->assertSame( 1, $second['unchanged'] );
		$this->assertSame( 1, $second['removed'] );

		$gamma_id = $second['pages']['gamma']['postId'];
		rename( $this->pages_path . '/gamma', $this->pages_path . '/delta' );
		$this->write_page( 'delta', 'Delta' );
		$third = $this->reconcile(
			false,
			'',
			array(
				'gamma' => 'delta',
			)
		);

		$this->assertSame( 0, $third['created'] );
		$this->assertSame( 1, $third['updated'] );
		$this->assertSame( 0, $third['removed'] );
		$this->assertSame( $gamma_id, $third['pages']['delta']['postId'] );
		$this->assertSame( 'delta', get_post_meta( $gamma_id, '_blockstudio_page_source', true ) );
	}

	/**
	 * An engine migration broadens exactly one write pass.
	 *
	 * @return void
	 */
	public function test_engine_fingerprint_broadens_once_then_returns_to_noop(): void {
		add_filter( 'blockstudio/pages/sync_engine_inputs', array( $this, 'filter_engine' ) );
		$this->write_page( 'alpha', 'Alpha' );
		$first = $this->reconcile( true );
		Pages::store_successful_source_identity( $first['sourceIdentity'] );

		$this->engine_version = 'b';
		$second               = $this->reconcile();

		$this->assertTrue( $second['fullReconciliation'] );
		$this->assertSame( 1, $second['updated'] );
		Pages::store_successful_source_identity( $second['sourceIdentity'] );

		$third = $this->reconcile();
		$this->assertFalse( $third['fullReconciliation'] );
		$this->assertSame( 0, $third['updated'] );
		$this->assertSame( 1, $third['unchanged'] );
	}

	/**
	 * Dirty deployed bytes participate in identity without moving success state.
	 *
	 * @return void
	 */
	public function test_dirty_inventory_changes_source_id_without_advancing_success_marker(): void {
		$this->write_page( 'alpha', 'Alpha' );
		$first = $this->reconcile( true );
		Pages::store_successful_source_identity( $first['sourceIdentity'] );
		$stored = Pages::successful_source_identity();

		$this->write_page( 'alpha', 'Alpha dirty' );
		$second = $this->reconcile( false, 'working-tree-hash' );

		$this->assertSame( 1, $second['updated'] );
		$this->assertNotSame( $first['sourceId'], $second['sourceId'] );
		$this->assertSame( 'working-tree-hash', $second['sourceIdentity']['dirtyHash'] );
		$this->assertSame( $stored, Pages::successful_source_identity() );
	}

	/**
	 * Parent changes update the child after its new parent is created.
	 *
	 * @return void
	 */
	public function test_collection_hierarchy_reconciles_parent_change(): void {
		$this->write_collection_page( '.', 'docs-home', 'Docs' );
		$this->write_collection_page( 'guide', 'docs-guide', 'Guide' );
		$first = $this->reconcile( true );

		$root_id  = $first['pages']['docs:docs-home']['postId'];
		$guide_id = $first['pages']['docs:docs-guide']['postId'];
		$this->assertSame( $root_id, (int) get_post_field( 'post_parent', $guide_id ) );

		$this->write_collection_page( 'api', 'docs-api', 'API' );
		$this->write_collection_page( 'guide', 'docs-guide', 'Guide', 'api/guide' );
		$second = $this->reconcile();
		$api_id = $second['pages']['docs:docs-api']['postId'];

		$this->assertSame( 1, $second['created'] );
		$this->assertSame( 1, $second['updated'] );
		$this->assertSame( $api_id, (int) get_post_field( 'post_parent', $guide_id ) );
	}

	/**
	 * Pages initialization never reconciles sources or loads managed inventory.
	 *
	 * @return void
	 */
	public function test_init_never_reconciles_or_inventories_page_sources(): void {
		$this->write_page( 'implicit-sync-probe', 'Implicit Sync Probe' );

		$reconciliations = 0;
		$post_writes     = 0;
		$inventory_sql   = 0;
		$reconciled      = static function () use ( &$reconciliations ): void {
			++$reconciliations;
		};
		$saved           = static function () use ( &$post_writes ): void {
			++$post_writes;
		};
		$query           = static function ( string $sql ) use ( &$inventory_sql ): string {
			if ( str_contains( $sql, '_blockstudio_page_key' ) && str_contains( $sql, '_blockstudio_page_source' ) ) {
				++$inventory_sql;
			}

			return $sql;
		};

		add_action( 'blockstudio/pages/reconciled', $reconciled );
		add_action( 'save_post', $saved );
		add_filter( 'query', $query );

		Pages::maybe_register_collection_post_types();
		Pages::init( array( 'force' => true ) );

		remove_action( 'blockstudio/pages/reconciled', $reconciled );
		remove_action( 'save_post', $saved );
		remove_filter( 'query', $query );

		$this->assertSame( 0, $reconciliations );
		$this->assertSame( 0, $post_writes );
		$this->assertSame( 0, $inventory_sql );
		$this->assertNull( get_page_by_path( 'implicit-sync-probe' ) );
	}

	/**
	 * Canvas refresh is an explicit authoring boundary for newly added pages.
	 *
	 * @return void
	 */
	public function test_canvas_refresh_reconciles_new_page_sources_explicitly(): void {
		$this->write_page( 'existing', 'Existing' );
		$initial = $this->reconcile( true );
		$this->assertSame( 1, $initial['created'] );

		$this->write_page( 'added', 'Added' );
		Pages::reset();
		Pages::init();
		$this->assertNull( Pages::get_page( 'added' ) );

		$report  = null;
		$capture = static function ( array $value ) use ( &$report ): void {
			$report = $value;
		};

		add_action( 'blockstudio/pages/reconciled', $capture );
		add_filter( 'blockstudio/settings/dev/canvas/enabled', '__return_true' );
		add_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );
		$buffer_level = ob_get_level();

		try {
			$response = ( new Canvas() )->refresh(
				new WP_REST_Request( 'GET', '/blockstudio/v1/canvas/refresh' )
			);
		} finally {
			remove_action( 'blockstudio/pages/reconciled', $capture );
			remove_filter( 'blockstudio/settings/dev/canvas/enabled', '__return_true' );
			remove_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );
		}

		$this->assertSame( $buffer_level, ob_get_level() );

		$data  = $response->get_data();
		$pages = is_array( $data['pages'] ?? null ) ? $data['pages'] : array();
		$added = array_values(
			array_filter(
				$pages,
				static fn ( array $page ): bool => 'added' === ( $page['name'] ?? '' )
			)
		);

		$this->assertIsArray( $report );
		$this->assertSame( 2, $report['discovered'], wp_json_encode( $report ) );
		$this->assertSame( 1, $report['created'], wp_json_encode( $report ) );
		$this->assertCount( 1, $added );
		$this->assertStringContainsString( 'Added', $added[0]['content'] );
		$this->assertSame( 'page', get_post_type( Pages::get_post_id( 'added' ) ) );
	}

	/**
	 * Explicit reconciliation persists templateFor without runtime discovery.
	 *
	 * @return void
	 */
	public function test_reconciliation_persists_template_for_runtime_bootstrap(): void {
		$this->write_page( 'template-probe', 'Template Probe' );
		$definition_path = $this->pages_path . '/template-probe/page.json';
		$definition      = Utils::read_json_file( $definition_path );
		$this->assertIsArray( $definition );
		$definition['templateFor']  = 'bs_runtime_template';
		$definition['templateLock'] = 'insert';
		file_put_contents( $definition_path, wp_json_encode( $definition, JSON_PRETTY_PRINT ) );

		register_post_type(
			'bs_runtime_template',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);

		$report = $this->reconcile( true );
		$this->assertSame( 0, $report['failed'] );

		$configuration = get_option( 'blockstudio_pages_template_for' );
		$this->assertIsArray( $configuration );
		$this->assertArrayHasKey( 'bs_runtime_template', $configuration );

		$post_type = get_post_type_object( 'bs_runtime_template' );
		$this->assertNotNull( $post_type );
		$this->assertSame( 'insert', $post_type->template_lock );
		$this->assertSame( 'core/heading', $post_type->template[0][0] );

		$post_type->template      = array();
		$post_type->template_lock = false;
		Pages::reset();
		Pages::init();

		$this->assertSame( 'insert', $post_type->template_lock );
		$this->assertSame( 'core/heading', $post_type->template[0][0] );
	}

	/**
	 * A failed explicit sync cannot activate partially discovered runtime state.
	 *
	 * @return void
	 */
	public function test_failed_reconciliation_preserves_runtime_configuration(): void {
		$this->write_collection_page( '.', 'docs-home', 'Docs' );
		$manifest_path = $this->pages_path . '/docs/pages.json';
		$manifest      = Utils::read_json_file( $manifest_path );
		$this->assertIsArray( $manifest );
		$manifest['postType'] = 'bs_failed_docs';
		file_put_contents( $manifest_path, wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

		$previous_manifests = array( 'sentinel' => 'manifests' );
		$previous_templates = array( 'sentinel' => 'templates' );
		$previous_signature = 'existing-rewrite-signature';
		update_option( 'blockstudio_pages_collection_manifests', $previous_manifests, false );
		update_option( 'blockstudio_pages_template_for', $previous_templates, false );
		update_option( 'blockstudio_collection_post_types_signature', $previous_signature, false );

		$fail_create = static function ( bool $is_empty, array $post_data ): bool {
			return 'bs_failed_docs' === ( $post_data['post_type'] ?? '' ) ? true : $is_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $fail_create, 10, 2 );

		try {
			$report = $this->reconcile( true );
		} finally {
			remove_filter( 'wp_insert_post_empty_content', $fail_create, 10 );
			unregister_post_type( 'bs_failed_docs' );
		}

		$this->assertSame( 1, $report['failed'] );
		$this->assertNotEmpty( $report['errors'] );
		$this->assertSame( $previous_manifests, get_option( 'blockstudio_pages_collection_manifests' ) );
		$this->assertSame( $previous_templates, get_option( 'blockstudio_pages_template_for' ) );
		$this->assertSame( $previous_signature, get_option( 'blockstudio_collection_post_types_signature' ) );
	}

	/**
	 * A failed page write cannot prune another managed post.
	 *
	 * @return void
	 */
	public function test_failed_reconciliation_preserves_missing_managed_posts(): void {
		$this->write_page( 'keep', 'Keep' );
		$this->write_page( 'orphan', 'Orphan' );
		$initial   = $this->reconcile( true );
		$orphan_id = $initial['pages']['orphan']['postId'];

		$this->remove_directory( $this->pages_path . '/orphan' );
		$this->write_page( 'failure', 'Failure' );

		$fail_create = static function ( bool $is_empty, array $post_data ): bool {
			return 'Failure' === ( $post_data['post_title'] ?? '' ) ? true : $is_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $fail_create, 10, 2 );

		try {
			$report = $this->reconcile();
		} finally {
			remove_filter( 'wp_insert_post_empty_content', $fail_create, 10 );
		}

		$this->assertSame( 1, $report['failed'] );
		$this->assertSame( 0, $report['removed'] );
		$this->assertSame( 'publish', get_post_status( $orphan_id ) );
		$this->assertSame( '', get_post_meta( $orphan_id, '_blockstudio_page_stale', true ) );
	}

	/**
	 * Normal bootstrap keeps last-good routes until explicit reconciliation.
	 *
	 * @return void
	 */
	public function test_runtime_bootstrap_does_not_rediscover_changed_collection_manifests(): void {
		$this->write_collection_page( '.', 'docs-home', 'Docs' );
		$manifest_path = $this->pages_path . '/docs/pages.json';
		$manifest      = Utils::read_json_file( $manifest_path );
		$this->assertIsArray( $manifest );
		$manifest['postType'] = 'bs_runtime_old';
		file_put_contents( $manifest_path, wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

		try {
			$initial = $this->reconcile( true );
			$this->assertSame( 0, $initial['failed'] );
			$this->assertTrue( post_type_exists( 'bs_runtime_old' ) );

			$manifest['postType'] = 'bs_runtime_new';
			file_put_contents( $manifest_path, wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
			unregister_post_type( 'bs_runtime_old' );
			Pages::reset();

			Pages::register_collection_post_types();

			$this->assertTrue( post_type_exists( 'bs_runtime_old' ) );
			$this->assertFalse( post_type_exists( 'bs_runtime_new' ) );
			$this->assertSame( array(), Pages::get_registered_paths() );
			$this->assertSame( 'bs_runtime_old', Pages::collection( 'docs' )['postType'] ?? null );

			unregister_post_type( 'bs_runtime_old' );
			$updated = $this->reconcile( true );

			$this->assertSame( 0, $updated['failed'] );
			$this->assertTrue( post_type_exists( 'bs_runtime_new' ) );
		} finally {
			if ( post_type_exists( 'bs_runtime_old' ) ) {
				unregister_post_type( 'bs_runtime_old' );
			}
			if ( post_type_exists( 'bs_runtime_new' ) ) {
				unregister_post_type( 'bs_runtime_new' );
			}
		}
	}

	/**
	 * Force sync never falls back to source-less records hydrated from the database.
	 *
	 * @return void
	 */
	public function test_force_sync_all_ignores_database_records_without_discovered_sources(): void {
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Database-only page',
				'post_content' => '<!-- wp:paragraph --><p>Keep me</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
		$this->assertIsInt( $post_id );
		update_post_meta( $post_id, '_blockstudio_page_key', 'database-only' );
		update_post_meta( $post_id, '_blockstudio_page_name', 'database-only' );
		update_post_meta( $post_id, '_blockstudio_page_source', '/missing/database-only/index.php' );

		$before  = get_post( $post_id );
		$results = Pages::force_sync_all();
		$after   = get_post( $post_id );

		$this->assertSame( array(), $results );
		$this->assertInstanceOf( WP_Post::class, $before );
		$this->assertInstanceOf( WP_Post::class, $after );
		$this->assertSame( $before->post_title, $after->post_title );
		$this->assertSame( $before->post_content, $after->post_content );
		$this->assertSame( $before->post_status, $after->post_status );
	}

	/**
	 * Managed inventory bypasses WP_Query and uses one indexed meta-key lookup.
	 *
	 * @return void
	 */
	public function test_managed_inventory_uses_an_exact_unfiltered_meta_lookup(): void {
		$key_post    = wp_insert_post(
			array(
				'post_title'  => 'Managed Key',
				'post_status' => 'draft',
				'post_type'   => 'page',
			)
		);
		$source_post = wp_insert_post(
			array(
				'post_title'  => 'Managed Source',
				'post_status' => 'private',
				'post_type'   => 'post',
			)
		);
		update_post_meta( $key_post, '_blockstudio_page_key', 'managed-key' );
		update_post_meta( $source_post, '_blockstudio_page_source', '/managed/source' );

		$wp_query_filters = 0;
		$inventory_query  = '';
		$clauses          = static function ( array $sql ) use ( &$wp_query_filters ): array {
			++$wp_query_filters;
			return $sql;
		};
		$query            = static function ( string $sql ) use ( &$inventory_query ): string {
			if ( str_contains( $sql, '_blockstudio_page_key' ) && str_contains( $sql, '_blockstudio_page_source' ) ) {
				$inventory_query = $sql;
			}

			return $sql;
		};

		add_filter( 'posts_clauses', $clauses );
		add_filter( 'query', $query );

		$posts = ( new Page_Sync() )->managed_posts();

		remove_filter( 'posts_clauses', $clauses );
		remove_filter( 'query', $query );

		$this->assertSame( 0, $wp_query_filters );
		$this->assertNotSame( '', $inventory_query );
		$this->assertStringContainsString( 'FROM ' . $GLOBALS['wpdb']->postmeta, $inventory_query );
		$this->assertStringContainsString( 'meta_key IN', $inventory_query );
		$this->assertStringNotContainsString( ' JOIN ', strtoupper( $inventory_query ) );
		$this->assertSame(
			array( $key_post, $source_post ),
			array_values(
				array_intersect(
					array_map( static fn ( WP_Post $post ): int => $post->ID, $posts ),
					array( $key_post, $source_post )
				)
			)
		);
	}

	/**
	 * Run one explicit deployment-style reconciliation.
	 *
	 * @param bool   $full       Full-comparison recovery mode.
	 * @param string $dirty_hash Dirty overlay hash.
	 * @param array  $renames    Rename hints.
	 *
	 * @return array Reconciliation report.
	 */
	private function reconcile( bool $full = false, string $dirty_hash = '', array $renames = array() ): array {
		return Pages::reconcile(
			array(
				'authoritative' => true,
				'full'          => $full,
				'plan_valid'    => true,
				'renames'       => $renames,
				'source'        => array(
					'commit'    => '0123456789abcdef',
					'dirtyHash' => $dirty_hash,
				),
			)
		);
	}

	/**
	 * Write one legacy page fixture.
	 *
	 * @param string $name  Page name.
	 * @param string $title Page title/content.
	 * @param bool   $sync  Whether explicit synchronization updates this page.
	 *
	 * @return void
	 */
	private function write_page( string $name, string $title, bool $sync = true ): void {
		$directory = $this->pages_path . '/' . $name;
		wp_mkdir_p( $directory );
		$definition = array(
			'name'       => $name,
			'title'      => $title,
			'slug'       => $name,
			'postType'   => 'page',
			'postStatus' => 'publish',
		);
		if ( ! $sync ) {
			$definition['sync'] = false;
		}
		file_put_contents(
			$directory . '/page.json',
			wp_json_encode( $definition, JSON_PRETTY_PRINT )
		);
		file_put_contents( $directory . '/index.php', '<h1>' . esc_html( $title ) . '</h1>' );
	}

	/**
	 * Write one markdown collection page fixture.
	 *
	 * @param string $directory_path Relative directory or dot.
	 * @param string $name           Page name.
	 * @param string $title          Page title.
	 * @param string $logical_path   Optional logical path override.
	 *
	 * @return void
	 */
	private function write_collection_page( string $directory_path, string $name, string $title, string $logical_path = '' ): void {
		$root = $this->pages_path . '/docs';
		wp_mkdir_p( $root );
		file_put_contents(
			$root . '/pages.json',
			wp_json_encode(
				array(
					'slug'     => 'docs',
					'title'    => 'Docs',
					'postType' => 'page',
				),
				JSON_PRETTY_PRINT
			)
		);

		$directory = '.' === $directory_path ? $root : $root . '/' . $directory_path;
		wp_mkdir_p( $directory );
		$path_line = '' !== $logical_path ? "path: {$logical_path}\n" : '';
		file_put_contents(
			$directory . '/index.md',
			"---\nname: {$name}\ntitle: {$title}\n{$path_line}---\n\n# {$title}\n"
		);
	}

	/**
	 * Permanently remove managed posts from this isolated test site.
	 *
	 * @return void
	 */
	private function delete_managed_posts(): void {
		foreach ( ( new Page_Sync() )->managed_posts() as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * Remove a temporary fixture tree.
	 *
	 * @param string $path Path to remove.
	 *
	 * @return void
	 */
	private function remove_directory( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $path );
	}
}

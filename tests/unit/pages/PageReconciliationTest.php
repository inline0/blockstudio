<?php
/**
 * Page reconciliation tests.
 *
 * @package Blockstudio
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.DB.SlowDBQuery

use Blockstudio\Page_Sync;
use Blockstudio\Pages;
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
	 * Generic WP-CLI bootstrap is not an implicit filesystem sync context.
	 *
	 * @return void
	 */
	public function test_arbitrary_cli_bootstrap_is_not_an_implicit_sync_context(): void {
		$method = new ReflectionMethod( Pages::class, 'can_init_in_current_context' );

		$this->assertTrue( $method->invoke( null, array(), true, false ) );
		$this->assertFalse( $method->invoke( null, array(), false, true ) );
		$this->assertFalse( $method->invoke( null, array(), true, true ) );
		$this->assertTrue( $method->invoke( null, array( 'force' => true ), false, true ) );
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
	 * @param bool   $sync  Whether automatic synchronization is enabled.
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
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => array_values( get_post_stati( array(), 'names' ) ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_blockstudio_page_key',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
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

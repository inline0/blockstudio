<?php

use Blockstudio\Page_Discovery;
use Blockstudio\Page_Registry;
use Blockstudio\Page_Sync;
use Blockstudio\Pages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class PagesTest extends TestCase {

	private string $pages_path;

	protected function setUp(): void {
		$this->pages_path = get_template_directory() . '/pages';

		$this->load_pages();
	}

	private function load_pages(): void {
		Pages::reset();

		$discovery = new Page_Discovery();
		$registry  = Page_Registry::instance();
		$sync      = new Page_Sync();

		Pages::register_collection_post_types();

		$registry->add_path( $this->pages_path );

		$pages = $discovery->discover( $this->pages_path );

		foreach ( $discovery->get_collections() as $collection => $collection_data ) {
			$registry->register_collection( $collection, $collection_data );
		}

		$registry->add_errors( $discovery->get_errors() );

		foreach ( $pages as $name => $page_data ) {
			$registry->register( $name, $page_data );

			$post_id = $sync->sync( $page_data );

			if ( is_int( $post_id ) && $post_id > 0 ) {
				$registry->set_synced_post( $page_data['source_path'], $post_id );
				$registry->update_page_data( $name, 'post_id', $post_id );
				$registry->update_page_data( $name, 'post_parent', (int) get_post_field( 'post_parent', $post_id ) );
				$registry->update_page_data( $name, 'permalink', get_permalink( $post_id ) );
			}
		}
	}

	protected function tearDown(): void {
		Pages::reset();
	}

	// pages()

	public function test_pages_returns_array(): void {
		$pages = Pages::pages();
		$this->assertIsArray( $pages );
	}

	public function test_pages_is_not_empty(): void {
		$pages = Pages::pages();
		$this->assertNotEmpty( $pages );
	}

	public function test_pages_contains_test_page(): void {
		$pages = Pages::pages();
		$this->assertArrayHasKey( 'blockstudio-e2e-test', $pages );
	}

	public function test_pages_contains_sync_test_page(): void {
		$pages = Pages::pages();
		$this->assertArrayHasKey( 'blockstudio-sync-test', $pages );
	}

	public function test_pages_contains_standalone_markdown_page(): void {
		$pages = Pages::pages();
		$this->assertArrayHasKey( 'blockstudio-markdown-test', $pages );
	}

	// get_page()

	public function test_get_page_returns_array_for_known_page(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertIsArray( $page );
	}

	public function test_get_page_has_expected_title(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'Blockstudio E2E Test Page', $page['title'] );
	}

	public function test_get_page_has_expected_slug(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'blockstudio-e2e-test', $page['slug'] );
	}

	public function test_get_page_has_template_path(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertArrayHasKey( 'template_path', $page );
		$this->assertStringEndsWith( '/index.php', $page['template_path'] );
	}

	public function test_get_page_has_post_type(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'page', $page['postType'] );
	}

	public function test_get_page_has_template_lock(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'all', $page['templateLock'] );
	}

	public function test_get_markdown_page_has_markdown_content_type(): void {
		$page = Pages::get_page( 'blockstudio-markdown-test' );
		$this->assertSame( 'markdown', $page['contentType'] );
		$this->assertTrue( $page['is_markdown'] );
	}

	public function test_get_page_returns_null_for_unknown(): void {
		$page = Pages::get_page( 'nonexistent-page' );
		$this->assertNull( $page );
	}

	// get_post_id()

	public function test_get_post_id_returns_int_for_synced_page(): void {
		$post_id = Pages::get_post_id( 'blockstudio-e2e-test' );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_get_post_id_returns_null_for_unknown(): void {
		$post_id = Pages::get_post_id( 'nonexistent-page' );
		$this->assertNull( $post_id );
	}

	public function test_get_post_id_corresponds_to_real_post(): void {
		$post_id = Pages::get_post_id( 'blockstudio-e2e-test' );
		$post    = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'page', $post->post_type );
	}

	// is_locked()

	public function test_is_locked_returns_bool_for_synced_page(): void {
		$locked = Pages::is_locked( 'blockstudio-e2e-test' );
		$this->assertIsBool( $locked );
	}

	public function test_is_locked_returns_false_by_default(): void {
		$locked = Pages::is_locked( 'blockstudio-e2e-test' );
		$this->assertFalse( $locked );
	}

	public function test_is_locked_returns_null_for_unknown(): void {
		$locked = Pages::is_locked( 'nonexistent-page' );
		$this->assertNull( $locked );
	}

	public function test_lock_then_is_locked_returns_true(): void {
		Pages::lock( 'blockstudio-e2e-test' );
		$this->assertTrue( Pages::is_locked( 'blockstudio-e2e-test' ) );

		Pages::unlock( 'blockstudio-e2e-test' );
	}

	public function test_unlock_after_lock_returns_false(): void {
		Pages::lock( 'blockstudio-e2e-test' );
		Pages::unlock( 'blockstudio-e2e-test' );
		$this->assertFalse( Pages::is_locked( 'blockstudio-e2e-test' ) );
	}

	// force_sync()

	public function test_force_sync_returns_int_for_known_page(): void {
		$result = Pages::force_sync( 'blockstudio-e2e-test' );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	public function test_force_sync_returns_null_for_unknown(): void {
		$result = Pages::force_sync( 'nonexistent-page' );
		$this->assertNull( $result );
	}

	public function test_force_sync_preserves_post_id(): void {
		$original = Pages::get_post_id( 'blockstudio-e2e-test' );
		$synced   = Pages::force_sync( 'blockstudio-e2e-test' );
		$this->assertSame( $original, $synced );
	}

	public function test_force_sync_skips_unrelated_post_with_same_slug(): void {
		$manual_post_id = wp_insert_post(
			array(
				'post_title'   => 'Manual Collision',
				'post_name'    => 'blockstudio-slug-collision-test',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Manual content should stay untouched.',
			)
		);

		$this->assertIsInt( $manual_post_id );
		$this->assertGreaterThan( 0, $manual_post_id );

		$page_data                = Pages::get_page( 'blockstudio-e2e-test' );
		$page_data['name']        = 'blockstudio-slug-collision-test';
		$page_data['title']       = 'Blockstudio Slug Collision Test';
		$page_data['slug']        = 'blockstudio-slug-collision-test';
		$page_data['source_path'] = 'pages/blockstudio-slug-collision-test';
		$page_data['postId']      = null;

		try {
			$result = ( new Page_Sync() )->force_sync( $page_data );

			$this->assertSame( 0, $result );

			$manual_post = get_post( $manual_post_id );
			$this->assertInstanceOf( WP_Post::class, $manual_post );
			$this->assertSame( 'Manual Collision', $manual_post->post_title );
			$this->assertSame( 'Manual content should stay untouched.', $manual_post->post_content );

			$posts = get_posts(
				array(
					'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $page_data['source_path'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'post_type'      => 'page',
					'posts_per_page' => 1,
					'post_status'    => 'any',
				)
			);

			$this->assertEmpty( $posts );
		} finally {
			wp_delete_post( $manual_post_id, true );
		}
	}

	public function test_force_sync_rebinds_existing_page_by_name_when_source_path_changes(): void {
		$page_data        = Pages::get_page( 'blockstudio-e2e-test' );
		$original_post_id = Pages::get_post_id( 'blockstudio-e2e-test' );
		$original_source  = get_post_meta( $original_post_id, '_blockstudio_page_source', true );

		$page_data['source_path'] = 'pages/moved/blockstudio-e2e-test';

		try {
			$result = ( new Page_Sync() )->force_sync( $page_data );

			$this->assertSame( $original_post_id, $result );
			$this->assertSame(
				$page_data['source_path'],
				get_post_meta( $original_post_id, '_blockstudio_page_source', true )
			);
		} finally {
			update_post_meta( $original_post_id, '_blockstudio_page_source', $original_source );
		}
	}

	// Discovery

	public function test_discovery_finds_multiple_pages(): void {
		$pages = Pages::pages();
		$this->assertGreaterThanOrEqual( 3, count( $pages ) );
	}

	public function test_page_data_has_required_keys(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$expected_keys = array( 'name', 'title', 'slug', 'postType', 'templateLock', 'template_path', 'json_path', 'directory', 'source_path' );

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $page, "Missing key: {$key}" );
		}
	}

	public function test_collection_manifest_is_registered(): void {
		$collection = Pages::collection( 'docs' );

		$this->assertIsArray( $collection );
		$this->assertSame( 'Documentation', $collection['title'] );
		$this->assertSame( 'bs_docs', $collection['postType'] );
		$this->assertSame( 'primary', $collection['meta']['navigation'] );
	}

	public function test_collection_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'bs_docs' ) );
		$this->assertTrue( is_post_type_hierarchical( 'bs_docs' ) );
		$this->assertTrue( post_type_exists( 'bs_flat_docs' ) );
		$this->assertFalse( is_post_type_hierarchical( 'bs_flat_docs' ) );
		$this->assertTrue( post_type_exists( 'bs_no_index_docs' ) );
	}

	public function test_collection_pages_are_namespaced(): void {
		$pages = Pages::in_collection( 'docs' );

		$this->assertArrayHasKey( 'docs:docs-home', $pages );
		$this->assertArrayHasKey( 'docs:docs-getting-started', $pages );
		$this->assertArrayHasKey( 'docs:docs-reference', $pages );
		$this->assertArrayHasKey( 'docs:docs-loader-api', $pages );
	}

	public function test_indexless_collection_registers_only_source_pages(): void {
		$pages = Pages::in_collection( 'no-index-docs' );

		$this->assertNull( Pages::get_page( 'no-index-docs' ) );
		$this->assertArrayHasKey( 'no-index-docs:no-index-docs-topic', $pages );
	}

	public function test_collection_pages_inherit_collection_post_type(): void {
		$this->assertSame( 'bs_docs', get_post_type( Pages::get_post_id( 'docs-home' ) ) );
		$this->assertSame( 'bs_docs', get_post_type( Pages::get_post_id( 'docs-getting-started' ) ) );
		$this->assertSame( 'bs_docs', get_post_type( Pages::get_post_id( 'docs-install' ) ) );
		$this->assertSame( 'bs_flat_docs', get_post_type( Pages::get_post_id( 'flat-docs-home' ) ) );
		$this->assertSame( 'bs_flat_docs', get_post_type( Pages::get_post_id( 'flat-docs-getting-started' ) ) );
		$this->assertSame( 'bs_flat_docs', get_post_type( Pages::get_post_id( 'flat-docs-install' ) ) );
		$this->assertSame( 'bs_no_index_docs', get_post_type( Pages::get_post_id( 'no-index-docs-topic' ) ) );
	}

	public function test_collection_cpt_permalinks_use_collection_paths(): void {
		$this->assertSame( home_url( '/docs/' ), get_permalink( Pages::get_post_id( 'docs-home' ) ) );
		$this->assertSame( home_url( '/docs/getting-started/' ), get_permalink( Pages::get_post_id( 'docs-getting-started' ) ) );
		$this->assertSame( home_url( '/docs/guide/install/' ), get_permalink( Pages::get_post_id( 'docs-install' ) ) );
		$this->assertSame( home_url( '/flat-docs/' ), get_permalink( Pages::get_post_id( 'flat-docs-home' ) ) );
		$this->assertSame( home_url( '/flat-docs/getting-started/' ), get_permalink( Pages::get_post_id( 'flat-docs-getting-started' ) ) );
		$this->assertSame( home_url( '/flat-docs/guide/install/' ), get_permalink( Pages::get_post_id( 'flat-docs-install' ) ) );
		$this->assertSame( home_url( '/flat-docs/setup/install/' ), get_permalink( Pages::get_post_id( 'flat-docs-setup-install' ) ) );
		$this->assertSame( home_url( '/no-index-docs/topic/' ), get_permalink( Pages::get_post_id( 'no-index-docs-topic' ) ) );
	}

	public function test_collection_generates_missing_container_pages(): void {
		$guide = Pages::get_page( 'docs-guide' );
		$api   = Pages::get_page( 'docs-api' );

		$this->assertIsArray( $guide );
		$this->assertTrue( $guide['generated'] );
		$this->assertSame( 'guide', $guide['path'] );
		$this->assertIsArray( $api );
		$this->assertTrue( $api['generated'] );
		$this->assertSame( 'api', $api['path'] );
	}

	public function test_non_hierarchical_collections_do_not_generate_empty_containers(): void {
		$this->assertNull( Pages::get_page( 'flat-docs-guide' ) );
		$this->assertNull( Pages::get_page( 'flat-docs-setup' ) );
	}

	public function test_non_hierarchical_nested_pages_use_route_slugs_without_parents(): void {
		$install_id = Pages::get_post_id( 'flat-docs-install' );
		$setup_id   = Pages::get_post_id( 'flat-docs-setup-install' );
		$install    = get_post( $install_id );
		$setup      = get_post( $setup_id );

		$this->assertInstanceOf( WP_Post::class, $install );
		$this->assertInstanceOf( WP_Post::class, $setup );
		$this->assertSame( 0, (int) $install->post_parent );
		$this->assertSame( 0, (int) $setup->post_parent );
		$this->assertSame( 'guide-install', $install->post_name );
		$this->assertSame( 'setup-install', $setup->post_name );
		$this->assertStringContainsString( 'Flat Install', $install->post_content );
		$this->assertStringContainsString( 'Flat Setup Install', $setup->post_content );
	}

	public function test_collection_children_use_logical_paths(): void {
		$children = Pages::children( 'docs-home', 'docs' );
		$names    = array_column( $children, 'name' );

		$this->assertContains( 'docs-guide', $names );
		$this->assertContains( 'docs-getting-started', $names );
		$this->assertContains( 'docs-reference', $names );
		$this->assertContains( 'docs-api', $names );
	}

	public function test_collection_tree_nests_generated_containers(): void {
		$tree = Pages::tree( 'docs' );

		$this->assertCount( 1, $tree );
		$this->assertSame( 'docs-home', $tree[0]['name'] );

		$guide = array_values(
			array_filter(
				$tree[0]['children'],
				static fn ( array $child ): bool => 'docs-guide' === $child['name']
			)
		);

		$this->assertCount( 1, $guide );
		$this->assertSame( 'docs-install', $guide[0]['children'][0]['name'] );
	}

	public function test_markdown_content_syncs_to_heading_blocks_with_anchor(): void {
		$post_id = Pages::get_post_id( 'docs-home' );
		$post    = get_post( $post_id );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertStringContainsString( 'wp:heading', $post->post_content );
		$this->assertStringContainsString( 'documentation-home', $post->post_content );
	}

	public function test_page_json_can_use_index_markdown_content(): void {
		$post_id = Pages::get_post_id( 'docs-reference' );
		$post    = get_post( $post_id );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertStringContainsString( 'Reference', $post->post_content );
	}

	public function test_index_markdown_frontmatter_overrides_page_json(): void {
		$page = Pages::get_page( 'docs-reference' );

		$this->assertSame( 'Reference From Frontmatter', $page['title'] );
		$this->assertSame( 12, $page['order'] );
		$this->assertSame( 'API', $page['meta']['section'] );
	}

	public function test_loader_markdown_page_syncs_and_is_sanitized_generated_content(): void {
		$page    = Pages::get_page( 'docs-loader-api' );
		$post_id = Pages::get_post_id( 'docs-loader-api' );
		$post    = get_post( $post_id );

		$this->assertIsArray( $page );
		$this->assertTrue( $page['generated'] );
		$this->assertSame( 'markdown', $page['contentType'] );
		$this->assertSame( "# Loader API\n\nThis page comes from loader.php.", $page['content'] );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertStringContainsString( 'Loader API', $post->post_content );
	}

	public function test_loader_paths_discover_allowed_external_local_pages(): void {
		$temp_dir        = sys_get_temp_dir() . '/blockstudio-page-loader-' . uniqid();
		$collection_root = $temp_dir . '/docs';
		$external_root   = $temp_dir . '/external-pages';
		$page_root       = $external_root . '/local';

		mkdir( $collection_root, 0755, true );
		mkdir( $page_root, 0755, true );

		file_put_contents(
			$collection_root . '/pages.json',
			wp_json_encode(
				array(
					'collection' => 'docs',
					'title'      => 'Docs',
					'postType'   => 'page',
					'defaults'   => array(
						'postStatus' => 'publish',
					),
				)
			)
		);

		file_put_contents(
			$collection_root . '/loader.php',
			"<?php\nreturn array(\n\t'paths' => array( " . var_export( $external_root, true ) . " ),\n);\n"
		);

		file_put_contents(
			$page_root . '/page.json',
			wp_json_encode(
				array(
					'name'  => 'docs-loader-local',
					'title' => 'Loader Local',
					'path'  => 'loader/local',
				)
			)
		);

		file_put_contents( $page_root . '/index.php', '<h1>Loader Local</h1>' );

		add_filter( 'blockstudio/pages/allow_external_loader_path', '__return_true' );

		try {
			$discovery = new Page_Discovery();
			$pages     = $discovery->discover( $collection_root );
		} finally {
			remove_filter( 'blockstudio/pages/allow_external_loader_path', '__return_true' );
			$this->remove_dir( $temp_dir );
		}

		$this->assertArrayHasKey( 'docs:docs-loader-local', $pages );
		$this->assertSame( 'loader/local', $pages['docs:docs-loader-local']['path'] );
		$this->assertSame( 'publish', $pages['docs:docs-loader-local']['postStatus'] );
	}

	public function test_loader_paths_can_mount_external_markdown_under_logical_prefixes(): void {
		$temp_dir        = sys_get_temp_dir() . '/blockstudio-page-loader-prefix-' . uniqid();
		$collection_root = $temp_dir . '/docs';
		$main_root       = $temp_dir . '/main-docs';
		$cli_root        = $temp_dir . '/cli-docs';

		mkdir( $collection_root, 0755, true );
		mkdir( $main_root, 0755, true );
		mkdir( $cli_root, 0755, true );

		file_put_contents(
			$collection_root . '/pages.json',
			wp_json_encode(
				array(
					'collection' => 'docs',
					'title'      => 'Docs',
					'postType'   => 'page',
					'defaults'   => array(
						'postStatus' => 'publish',
					),
				)
			)
		);

		file_put_contents(
			$collection_root . '/loader.php',
			"<?php\nreturn array(\n\t'paths' => array(\n\t\t" . var_export( $main_root, true ) . ",\n\t\tarray(\n\t\t\t'path' => " . var_export( $cli_root, true ) . ",\n\t\t\t'prefix' => 'cli',\n\t\t\t'meta' => array( 'doc_set' => 'cli' ),\n\t\t),\n\t),\n);\n"
		);

		file_put_contents(
			$main_root . '/index.md',
			"---\ntitle: Main Introduction\npath: .\n---\n\n# Main Introduction"
		);
		file_put_contents(
			$main_root . '/getting-started.md',
			"---\ntitle: Main Getting Started\npath: getting-started\n---\n\n# Main Getting Started"
		);
		file_put_contents(
			$cli_root . '/index.md',
			"---\ntitle: CLI Introduction\npath: .\n---\n\n# CLI Introduction"
		);
		file_put_contents(
			$cli_root . '/getting-started.md',
			"---\ntitle: CLI Getting Started\npath: getting-started\n---\n\n# CLI Getting Started"
		);

		add_filter( 'blockstudio/pages/allow_external_loader_path', '__return_true' );

		try {
			$discovery = new Page_Discovery();
			$pages     = $discovery->discover( $collection_root );
		} finally {
			remove_filter( 'blockstudio/pages/allow_external_loader_path', '__return_true' );
			$this->remove_dir( $temp_dir );
		}

		$this->assertArrayHasKey( 'docs:docs-home', $pages );
		$this->assertArrayHasKey( 'docs:docs-getting-started', $pages );
		$this->assertArrayHasKey( 'docs:docs-cli', $pages );
		$this->assertArrayHasKey( 'docs:docs-cli-getting-started', $pages );
		$this->assertSame( '.', $pages['docs:docs-home']['path'] );
		$this->assertSame( 'getting-started', $pages['docs:docs-getting-started']['path'] );
		$this->assertSame( 'cli', $pages['docs:docs-cli']['path'] );
		$this->assertSame( 'cli/getting-started', $pages['docs:docs-cli-getting-started']['path'] );
		$this->assertSame( 'docs/index.md', $pages['docs:docs-home']['source_path'] );
		$this->assertSame( 'docs/cli/index.md', $pages['docs:docs-cli']['source_path'] );
		$this->assertSame( 'cli', $pages['docs:docs-cli']['meta']['doc_set'] ?? null );
		$this->assertSame( 'CLI Getting Started', $pages['docs:docs-cli-getting-started']['title'] );
	}

	public function test_legacy_nested_pages_link_to_their_parent(): void {
		$temp_dir    = sys_get_temp_dir() . '/blockstudio-legacy-nested-' . uniqid();
		$parent_root = $temp_dir . '/account';
		$child_root  = $parent_root . '/purchases';

		mkdir( $child_root, 0755, true );

		file_put_contents(
			$parent_root . '/page.json',
			wp_json_encode(
				array(
					'name'  => 'account',
					'title' => 'Account',
					'slug'  => 'account',
					'path'  => 'account',
				)
			)
		);
		file_put_contents( $parent_root . '/index.php', '<h1>Account</h1>' );

		file_put_contents(
			$child_root . '/page.json',
			wp_json_encode(
				array(
					'name'  => 'account-purchases',
					'title' => 'Purchases',
					'slug'  => 'purchases',
					'path'  => 'account/purchases',
				)
			)
		);
		file_put_contents( $child_root . '/index.php', '<h1>Purchases</h1>' );

		try {
			$discovery = new Page_Discovery();
			$pages     = $discovery->discover( $temp_dir );
		} finally {
			$this->remove_dir( $temp_dir );
		}

		$this->assertArrayHasKey( 'account', $pages );
		$this->assertArrayHasKey( 'account-purchases', $pages );
		$this->assertSame( 'account/purchases', $pages['account-purchases']['path'] );
		$this->assertSame( 'account', $pages['account-purchases']['parent_key'] ?? null );
		$this->assertSame( 'purchases', $pages['account-purchases']['slug'] );
		$this->assertContains( 'account-purchases', $pages['account']['children'] );
		$this->assertArrayNotHasKey( 'parent_key', $pages['account'] );
	}

	public function test_legacy_nested_pages_inherit_ancestor_layout(): void {
		$temp_dir    = sys_get_temp_dir() . '/blockstudio-legacy-layout-' . uniqid();
		$parent_root = $temp_dir . '/account';
		$child_root  = $parent_root . '/purchases';

		mkdir( $child_root, 0755, true );

		file_put_contents( $parent_root . '/layout.php', '<?php echo "shell";' );

		file_put_contents(
			$parent_root . '/page.json',
			wp_json_encode( array( 'name' => 'account', 'title' => 'Account', 'slug' => 'account', 'path' => 'account' ) )
		);
		file_put_contents( $parent_root . '/index.php', '<h1>Account</h1>' );

		file_put_contents(
			$child_root . '/page.json',
			wp_json_encode( array( 'name' => 'account-purchases', 'title' => 'Purchases', 'slug' => 'purchases', 'path' => 'account/purchases' ) )
		);
		file_put_contents( $child_root . '/index.php', '<h1>Purchases</h1>' );

		try {
			$discovery = new Page_Discovery();
			$pages     = $discovery->discover( $temp_dir );
		} finally {
			$this->remove_dir( $temp_dir );
		}

		$expected = wp_normalize_path( $parent_root . '/layout.php' );
		$this->assertSame( $expected, wp_normalize_path( (string) $pages['account']['layout_path'] ) );
		$this->assertSame( $expected, wp_normalize_path( (string) $pages['account-purchases']['layout_path'] ) );
	}

	public function test_programmatic_layout_exposes_page_context_and_restores_it(): void {
		$temp_dir = sys_get_temp_dir() . '/blockstudio-programmatic-layout-' . uniqid();
		$layout   = $temp_dir . '/layout.php';
		mkdir( $temp_dir, 0755, true );
		file_put_contents(
			$layout,
			'<main data-page="<?php echo esc_attr( Blockstudio\\Pages::current_page()["name"] ); ?>"><?php echo Blockstudio\\Pages::page_content(); ?></main>'
		);

		try {
			$rendered = Pages::render_layout(
				'<p>Selected content</p>',
				array(
					'name'        => 'selected-page',
					'layout_path' => $layout,
				)
			);
		} finally {
			$this->remove_dir( $temp_dir );
		}

		$this->assertSame(
			'<main data-page="selected-page"><p>Selected content</p></main>',
			$rendered
		);
		$this->assertSame( '', Pages::page_content() );
	}

	public function test_programmatic_layout_failure_returns_original_content_and_cleans_buffers(): void {
		$temp_dir = sys_get_temp_dir() . '/blockstudio-programmatic-layout-error-' . uniqid();
		$layout   = $temp_dir . '/layout.php';
		mkdir( $temp_dir, 0755, true );
		file_put_contents( $layout, '<?php echo "partial"; throw new RuntimeException( "Layout failed" );' );
		$level = ob_get_level();

		try {
			$rendered = Pages::render_layout(
				'<p>Fallback</p>',
				array( 'name' => 'failing-page' ),
				$layout
			);
		} finally {
			$this->remove_dir( $temp_dir );
		}

		$this->assertSame( '<p>Fallback</p>', $rendered );
		$this->assertSame( $level, ob_get_level() );
		$this->assertSame( '', Pages::page_content() );
	}

	public function test_synced_collection_pages_store_identity_meta(): void {
		$post_id = Pages::get_post_id( 'docs-install' );

		$this->assertSame( 'docs:docs-install', get_post_meta( $post_id, '_blockstudio_page_key', true ) );
		$this->assertSame( 'docs', get_post_meta( $post_id, '_blockstudio_page_collection', true ) );
		$this->assertSame( 'guide/install', get_post_meta( $post_id, '_blockstudio_page_path', true ) );
		$this->assertSame( 'docs:guide/install', get_post_meta( $post_id, '_blockstudio_page_route', true ) );
		$this->assertSame( 'bs_docs', get_post_type( $post_id ) );
		$this->assertNotEmpty( get_post_meta( $post_id, '_blockstudio_page_fingerprint', true ) );
	}

	public function test_collection_rewrite_signature_is_stable_and_tracks_route_changes(): void {
		$this->clear_collection_manifest_cache();
		Pages::reset();
		Pages::register_collection_post_types();

		$signature = (string) get_option( 'blockstudio_collection_post_types_signature', '' );

		$this->assertNotEmpty( $signature );

		Pages::register_collection_post_types();
		$this->assertSame( $signature, (string) get_option( 'blockstudio_collection_post_types_signature', '' ) );

		$manifest_method = new ReflectionMethod( Pages::class, 'get_collection_manifests' );
		$manifest_method->setAccessible( true );
		$collections = $manifest_method->invoke( null, true );

		$signature_method = new ReflectionMethod( Pages::class, 'collection_rewrite_signature' );
		$signature_method->setAccessible( true );

		$this->assertSame( $signature, $signature_method->invoke( null, $collections ) );

		$post_type_changed                 = $collections;
		$post_type_changed[0]['postType'] = $post_type_changed[0]['postType'] . '_next';

		$this->assertNotSame( $signature, $signature_method->invoke( null, $post_type_changed ) );

		$rewrite_changed                                      = $collections;
		$rewrite_changed[0]['postTypeArgs']['rewrite']['slug'] = ( $rewrite_changed[0]['postTypeArgs']['rewrite']['slug'] ?? $rewrite_changed[0]['slug'] ) . '-next';

		$this->assertNotSame( $signature, $signature_method->invoke( null, $rewrite_changed ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_cli_rewrite_generation_preserves_collection_routes(): void {
		define( 'WP_CLI', true );

		global $wp_rewrite;

		$extra_rules_top = $wp_rewrite->extra_rules_top;

		try {
			$wp_rewrite->extra_rules_top = array();
			Pages::reset();
			Pages::maybe_register_collection_post_types();

			$rules = $wp_rewrite->rewrite_rules();

			$this->assertIsArray( $rules );
			$this->assertArrayHasKey( '^docs/?$', $rules );
			$this->assertSame(
				'index.php?blockstudio_collection=docs&blockstudio_collection_path=.',
				$rules['^docs/?$']
			);
		} finally {
			$wp_rewrite->extra_rules_top = $extra_rules_top;
		}
	}

	public function test_explicit_post_id_migrates_empty_target_post_in_place(): void {
		$target_id = wp_insert_post(
			array(
				'post_title'   => 'Explicit Target',
				'post_name'    => 'explicit-target',
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => '',
			)
		);

		$this->assertIsInt( $target_id );

		$page_data                = Pages::get_page( 'docs-getting-started' );
		$page_data['name']        = 'docs-explicit-postid';
		$page_data['title']       = 'Docs Explicit Post ID';
		$page_data['slug']        = 'explicit-postid';
		$page_data['path']        = 'explicit-postid';
		$page_data['source_path'] = 'docs/explicit-postid.md';
		$page_data['postId']      = $target_id;

		try {
			$result = ( new Page_Sync() )->force_sync( $page_data );

			$this->assertSame( $target_id, $result );
			$this->assertSame( 'bs_docs', get_post_type( $target_id ) );
			$this->assertSame( 'docs:docs-explicit-postid', get_post_meta( $target_id, '_blockstudio_page_key', true ) );
		} finally {
			wp_delete_post( $target_id, true );
		}
	}

	public function test_keyed_custom_block_migrates_legacy_key_and_preserves_editor_fields(): void {
		$page_data                   = Pages::get_page( 'blockstudio-keyed-merge-test' );
		$page_data['name']           = 'blockstudio-keyed-custom-block-unit';
		$page_data['title']          = 'Blockstudio Keyed Custom Block Unit';
		$page_data['slug']           = 'blockstudio-keyed-custom-block-unit';
		$page_data['source_path']    = 'unit/blockstudio-keyed-custom-block-unit';
		$page_data['inline_content'] = '<block name="blockstudio/type-text" key="hero" text="Template v1" /><p>Sentinel v1</p>';

		$sync    = new Page_Sync();
		$post_id = $sync->force_sync( $page_data );

		$this->assertIsInt( $post_id );

		try {
			$blocks = parse_blocks( get_post_field( 'post_content', $post_id ) );

			$this->assertSame( 'hero', $blocks[0]['attrs']['__BLOCKSTUDIO_KEY'] );
			$this->assertSame( 'Template v1', $blocks[0]['attrs']['blockstudio']['attributes']['text'] );
			$this->assertArrayNotHasKey(
				'__BLOCKSTUDIO_KEY',
				$blocks[0]['attrs']['blockstudio']['attributes']
			);

			unset( $blocks[0]['attrs']['__BLOCKSTUDIO_KEY'] );
			$blocks[0]['attrs']['blockstudio']['attributes']['__BLOCKSTUDIO_KEY'] = 'hero';
			$blocks[0]['attrs']['blockstudio']['attributes']['text']              = 'Editor value';

			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => serialize_blocks( $blocks ),
				)
			);

			$page_data['inline_content'] = '<block name="blockstudio/type-text" key="hero" text="Template v2" /><p>Sentinel v2</p>';
			$result                      = $sync->sync( $page_data );

			$this->assertSame( $post_id, $result );

			$synced = parse_blocks( get_post_field( 'post_content', $post_id ) );

			$this->assertSame( 'hero', $synced[0]['attrs']['__BLOCKSTUDIO_KEY'] );
			$this->assertSame( 'Editor value', $synced[0]['attrs']['blockstudio']['attributes']['text'] );
			$this->assertArrayNotHasKey(
				'__BLOCKSTUDIO_KEY',
				$synced[0]['attrs']['blockstudio']['attributes']
			);
			$this->assertStringContainsString( 'Sentinel v2', $synced[1]['innerHTML'] );
			$this->assertStringNotContainsString( 'Sentinel v1', $synced[1]['innerHTML'] );
		} finally {
			wp_delete_post( $post_id, true );
		}
	}

	public function test_collection_post_type_change_migrates_existing_post(): void {
		$page_data = Pages::get_page( 'docs-reference' );
		$post_id   = Pages::get_post_id( 'docs-reference' );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => 'page',
			)
		);

		$this->assertSame( 'page', get_post_type( $post_id ) );

		$result = ( new Page_Sync() )->force_sync( $page_data );

		$this->assertSame( $post_id, $result );
		$this->assertSame( 'bs_docs', get_post_type( $post_id ) );
	}

	public function test_collection_post_type_change_prunes_duplicate_old_posts(): void {
		$page_data    = Pages::get_page( 'docs-getting-started' );
		$keep_post_id = Pages::get_post_id( 'docs-getting-started' );
		$duplicate_id = wp_insert_post(
			array(
				'post_title'   => 'Duplicate Getting Started',
				'post_name'    => 'getting-started',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Duplicate content.',
			)
		);

		$this->assertIsInt( $duplicate_id );

		update_post_meta( $duplicate_id, '_blockstudio_page_key', $page_data['key'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_name', $page_data['name'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_source', $page_data['source_path'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_collection', $page_data['collection'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_path', $page_data['path'] );

		try {
			$result = ( new Page_Sync() )->force_sync( $page_data );

			$this->assertSame( $keep_post_id, $result );
			$this->assertSame( 'trash', get_post_status( $duplicate_id ) );
		} finally {
			wp_delete_post( $duplicate_id, true );
		}
	}

	public function test_duplicate_cleanup_keeps_one_deterministic_healthy_post(): void {
		$page_data    = Pages::get_page( 'docs-getting-started' );
		$keep_post_id = Pages::get_post_id( 'docs-getting-started' );
		$sync         = new Page_Sync();
		$fingerprint  = $sync->fingerprint( $page_data );
		$engine        = Page_Sync::engine_fingerprint();
		$duplicate_id  = wp_insert_post(
			array(
				'post_title'   => $page_data['title'],
				'post_name'    => $page_data['slug'],
				'post_type'    => $page_data['postType'],
				'post_status'  => $page_data['postStatus'],
				'post_content' => get_post_field( 'post_content', $keep_post_id ),
			)
		);

		$this->assertIsInt( $duplicate_id );
		$this->assertGreaterThan( $keep_post_id, $duplicate_id );

		update_post_meta( $duplicate_id, '_blockstudio_page_key', $page_data['key'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_name', $page_data['name'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_source', $page_data['source_path'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_collection', $page_data['collection'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_path', $page_data['path'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_fingerprint', $fingerprint );
		update_post_meta( $duplicate_id, '_blockstudio_page_engine_fingerprint', $engine );

		$method = new ReflectionMethod( Page_Sync::class, 'prune_duplicate_posts' );

		try {
			$canonical_id = $method->invoke( $sync, $page_data, $duplicate_id, $fingerprint, $engine );

			$this->assertSame( $keep_post_id, $canonical_id );
			$this->assertSame( 'publish', get_post_status( $keep_post_id ) );
			$this->assertSame( 'trash', get_post_status( $duplicate_id ) );
		} finally {
			wp_delete_post( $duplicate_id, true );
		}
	}

	public function test_collection_duplicate_auto_draft_posts_are_pruned(): void {
		$page_data    = Pages::get_page( 'docs-getting-started' );
		$keep_post_id = Pages::get_post_id( 'docs-getting-started' );
		$duplicate_id = wp_insert_post(
			array(
				'post_title'   => 'Duplicate Auto Draft',
				'post_name'    => 'getting-started-auto-draft',
				'post_type'    => 'page',
				'post_status'  => 'auto-draft',
				'post_content' => 'Duplicate content.',
			)
		);

		$this->assertIsInt( $duplicate_id );

		update_post_meta( $duplicate_id, '_blockstudio_page_key', $page_data['key'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_name', $page_data['name'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_source', $page_data['source_path'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_collection', $page_data['collection'] );
		update_post_meta( $duplicate_id, '_blockstudio_page_path', $page_data['path'] );

		$delete = static fn (): string => 'delete';
		add_filter( 'blockstudio/pages/orphan_action', $delete );

		try {
			$result = ( new Page_Sync() )->force_sync( $page_data );

			$this->assertSame( $keep_post_id, $result );
			$this->assertNull( get_post( $duplicate_id ) );
		} finally {
			remove_filter( 'blockstudio/pages/orphan_action', $delete );
			wp_delete_post( $duplicate_id, true );
		}
	}

	public function test_markdown_page_stores_content_path(): void {
		Pages::force_sync( 'docs-install' );
		$post_id = Pages::get_post_id( 'docs-install' );
		$path    = (string) get_post_meta( $post_id, '_blockstudio_page_content_path', true );

		$this->assertNotEmpty( $path );
		$this->assertStringEndsWith( '.md', $path );
		$this->assertFileExists( $path );
	}

	public function test_raw_markdown_access_requires_publicly_viewable_or_readable_post(): void {
		$method = new ReflectionMethod( Pages::class, 'can_serve_markdown_post' );
		$method->setAccessible( true );

		$public_id   = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Public Markdown Source',
				'post_content' => '',
			),
			true
		);
		$draft_id    = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'Draft Markdown Source',
				'post_content' => '',
			),
			true
		);
		$password_id = wp_insert_post(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_title'    => 'Password Markdown Source',
				'post_content'  => '',
				'post_password' => 'secret',
			),
			true
		);

		$this->assertIsInt( $public_id );
		$this->assertIsInt( $draft_id );
		$this->assertIsInt( $password_id );

		try {
			wp_set_current_user( 0 );

			$this->assertTrue( $method->invoke( null, get_post( $public_id ) ) );
			$this->assertFalse( $method->invoke( null, get_post( $draft_id ) ) );
			$this->assertFalse( $method->invoke( null, get_post( $password_id ) ) );
		} finally {
			wp_delete_post( $public_id, true );
			wp_delete_post( $draft_id, true );
			wp_delete_post( $password_id, true );
		}
	}

	public function test_parent_resolution_falls_back_to_draft_parent_meta(): void {
		$registry = Page_Registry::instance();
		$registry->reset();

		$parent_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Draft Parent',
				'post_name'   => 'draft-parent',
			),
			true
		);
		$this->assertIsInt( $parent_id );
		update_post_meta( $parent_id, '_blockstudio_page_key', 'draft-parent-key' );
		update_post_meta( $parent_id, '_blockstudio_page_name', 'draft-parent-key' );

		$method = new ReflectionMethod( Page_Sync::class, 'resolve_parent_id' );
		$method->setAccessible( true );

		try {
			$this->assertSame(
				$parent_id,
				$method->invoke(
					new Page_Sync(),
					array(
						'parent_key' => 'draft-parent-key',
						'postType'   => 'page',
					)
				)
			);
		} finally {
			wp_delete_post( $parent_id, true );
		}
	}

	public function test_collection_helpers_include_synced_permalink(): void {
		$page = Pages::get_page( 'docs-install' );

		$this->assertArrayHasKey( 'permalink', $page );
		$this->assertSame( get_permalink( $page['post_id'] ), $page['permalink'] );
	}

	public function test_collection_children_have_wordpress_parent_ids(): void {
		$parent_id = Pages::get_post_id( 'docs-guide' );
		$child_id  = Pages::get_post_id( 'docs-install' );

		$this->assertGreaterThan( 0, $parent_id );
		$this->assertSame( $parent_id, (int) get_post_field( 'post_parent', $child_id ) );
	}

	public function test_layout_file_is_not_discovered_as_page_source(): void {
		$sources = array_column( Pages::in_collection( 'docs' ), 'source_path' );

		foreach ( $sources as $source ) {
			$this->assertStringNotContainsString( 'layout.php', $source );
		}
	}

	public function test_global_page_helpers_proxy_pages_api(): void {
		$this->assertSame( Pages::in_collection( 'docs' ), blockstudio_pages( 'docs' ) );
		$this->assertSame( Pages::collection( 'docs' ), blockstudio_page_collection( 'docs' ) );
		$this->assertSame( Pages::children( 'docs-home', 'docs' ), blockstudio_page_children( 'docs-home', 'docs' ) );
	}

	// get_registered_paths()

	public function test_get_registered_paths_returns_array(): void {
		$paths = Pages::get_registered_paths();
		$this->assertIsArray( $paths );
		$this->assertNotEmpty( $paths );
	}

	public function test_get_registered_paths_contains_theme_pages_dir(): void {
		$paths = Pages::get_registered_paths();
		$this->assertContains( $this->pages_path, $paths );
	}

	// reset()

	public function test_reset_clears_pages(): void {
		$this->assertNotEmpty( Pages::pages() );

		Pages::reset();

		$this->assertNotEmpty( Pages::pages() );
	}

	public function test_init_context_blocks_frontend_without_force(): void {
		$method = new ReflectionMethod( Pages::class, 'can_init_in_current_context' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( null, array(), false, false ) );
	}

	public function test_init_context_allows_frontend_with_force(): void {
		$method = new ReflectionMethod( Pages::class, 'can_init_in_current_context' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, array( 'force' => true ), false, false ) );
	}

	public function test_init_context_allows_admin_but_blocks_cli_without_force(): void {
		$method = new ReflectionMethod( Pages::class, 'can_init_in_current_context' );

		$this->assertTrue( $method->invoke( null, array(), true, false ) );
		$this->assertFalse( $method->invoke( null, array(), false, true ) );
	}

	/**
	 * Request-safe editor hooks remain available when discovery is gated.
	 */
	public function test_editor_assets_register_without_page_reconciliation(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}
		set_current_screen( 'post' );
		do_action( 'enqueue_block_editor_assets' );

		$this->assertTrue( wp_script_is( 'blockstudio-pages', 'registered' ) );
	}

	// Block content slashing

	public function test_sync_preserves_hex_escaped_html_in_block_attributes(): void {
		$page_data                   = Pages::get_page( 'blockstudio-e2e-test' );
		$page_data['name']           = 'blockstudio-slash-test';
		$page_data['title']          = 'Blockstudio Slash Test';
		$page_data['slug']           = 'blockstudio-slash-test';
		$page_data['source_path']    = 'pages/blockstudio-slash-test';
		$page_data['postId']         = null;
		$page_data['inline_content'] = '<bs:blockstudio-type-component heading="H" content="Inline <code>discard_sandbox</code> token" />';

		$post_id = null;

		try {
			$post_id = ( new Page_Sync() )->sync( $page_data );

			$this->assertIsInt( $post_id );
			$this->assertGreaterThan( 0, $post_id );

			$post = get_post( $post_id );
			$this->assertInstanceOf( WP_Post::class, $post );

			$backslash = chr( 92 );
			$escaped   = $backslash . 'u003ccode' . $backslash . 'u003ediscard_sandbox' . $backslash . 'u003c/code' . $backslash . 'u003e';

			$this->assertStringContainsString( $escaped, $post->post_content );
		} finally {
			if ( is_int( $post_id ) && $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}
		}
	}

	// Order persistence

	public function test_page_order_is_persisted_as_menu_order(): void {
		$post_id = Pages::get_post_id( 'docs-reference' );
		$post    = get_post( $post_id );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 12, (int) $post->menu_order );
	}

	// Frontend registry hydration

	public function test_registry_hydrates_from_synced_posts(): void {
		$expected_post_id = Pages::get_post_id( 'docs-install' );
		$this->assertGreaterThan( 0, $expected_post_id );

		$registry = Page_Registry::instance();
		$registry->reset();

		$pages = $registry->get_pages();
		$this->assertNotEmpty( $pages );
		$this->assertArrayHasKey( 'docs:docs-install', $pages );

		$hydrated = $pages['docs:docs-install'];
		$this->assertSame( $expected_post_id, $hydrated['post_id'] );
		$this->assertSame( 'docs', $hydrated['collection'] );
		$this->assertSame( 'guide/install', $hydrated['path'] );
	}

	public function test_registry_hydration_carries_persisted_order(): void {
		$registry = Page_Registry::instance();
		$registry->reset();
		$registry->hydrate_from_posts();

		$pages = $registry->get_pages();
		$this->assertArrayHasKey( 'docs:docs-reference', $pages );
		$this->assertSame( 12, $pages['docs:docs-reference']['order'] );
	}

	public function test_registry_hydration_carries_frontmatter_meta(): void {
		$registry = Page_Registry::instance();
		$registry->reset();
		$registry->hydrate_from_posts();

		$pages = $registry->get_pages();
		$this->assertArrayHasKey( 'docs:docs-reference', $pages );
		$this->assertSame( 'API', $pages['docs:docs-reference']['meta']['section'] ?? null );
	}

	public function test_maybe_hydrate_does_not_override_discovered_pages(): void {
		$registry = Page_Registry::instance();
		$before   = $registry->get_pages();

		$this->assertNotEmpty( $before );

		$registry->maybe_hydrate();

		$this->assertSame( $before, $registry->get_pages() );
	}

	// Orphan pruning

	public function test_orphaned_collection_post_is_pruned_on_sync(): void {
		$post_id = Pages::get_post_id( 'docs-install' );

		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( 'publish', get_post_status( $post_id ) );

		$post_type = get_post_type( $post_id );
		$active    = array();

		foreach ( Pages::in_collection( 'docs' ) as $page ) {
			$pid = $page['post_id'] ?? 0;

			if ( $pid && $pid !== $post_id ) {
				$active[] = (string) get_post_meta( $pid, '_blockstudio_page_source', true );
			}
		}

		try {
			( new Page_Sync() )->mark_stale_missing( $active, 'docs', array( $post_type ) );

			$this->assertSame( 'trash', get_post_status( $post_id ) );
		} finally {
			wp_delete_post( $post_id, true );
		}
	}

	public function test_orphan_action_filter_can_keep_post(): void {
		$post_id = Pages::get_post_id( 'docs-install' );

		$this->assertGreaterThan( 0, $post_id );

		$post_type = get_post_type( $post_id );
		$keep      = static fn (): string => 'keep';

		add_filter( 'blockstudio/pages/orphan_action', $keep );

		try {
			( new Page_Sync() )->mark_stale_missing( array(), 'docs', array( $post_type ) );

			$this->assertSame( 'publish', get_post_status( $post_id ) );
			$this->assertSame( '1', get_post_meta( $post_id, '_blockstudio_page_stale', true ) );
		} finally {
			remove_filter( 'blockstudio/pages/orphan_action', $keep );
		}
	}

	public function test_orphan_action_delete_hard_deletes_loader_generated_page(): void {
		$post_id = Pages::get_post_id( 'docs-loader-api' );

		$this->assertGreaterThan( 0, $post_id );

		$active = array();

		foreach ( Pages::in_collection( 'docs' ) as $page ) {
			if ( 'docs-loader-api' !== ( $page['name'] ?? '' ) ) {
				$active[] = (string) ( $page['source_path'] ?? '' );
			}
		}

		$delete = static fn (): string => 'delete';
		add_filter( 'blockstudio/pages/orphan_action', $delete );

		try {
			( new Page_Sync() )->mark_stale_missing( $active, 'docs', array( 'bs_docs' ) );

			$this->assertNull( get_post( $post_id ) );
		} finally {
			remove_filter( 'blockstudio/pages/orphan_action', $delete );
			wp_delete_post( $post_id, true );
		}
	}

	public function test_mark_stale_missing_prunes_any_post_status(): void {
		register_post_status(
			'blockstudio_tmp',
			array(
				'internal' => false,
				'public'   => false,
			)
		);

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Stale Custom Status',
				'post_name'    => 'stale-custom-status',
				'post_type'    => 'bs_docs',
				'post_status'  => 'blockstudio_tmp',
				'post_content' => 'Stale content.',
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, '_blockstudio_page_key', 'docs:stale-custom-status' );
		update_post_meta( $post_id, '_blockstudio_page_name', 'stale-custom-status' );
		update_post_meta( $post_id, '_blockstudio_page_source', 'docs/stale-custom-status.md' );
		update_post_meta( $post_id, '_blockstudio_page_collection', 'docs' );
		update_post_meta( $post_id, '_blockstudio_page_path', 'stale-custom-status' );

		$delete = static fn (): string => 'delete';
		add_filter( 'blockstudio/pages/orphan_action', $delete );

		try {
			$active = array();

			foreach ( Pages::in_collection( 'docs' ) as $page ) {
				$active[] = (string) ( $page['source_path'] ?? '' );
			}

			( new Page_Sync() )->mark_stale_missing( $active, 'docs', array( 'bs_docs' ) );

			$this->assertNull( get_post( $post_id ) );
		} finally {
			remove_filter( 'blockstudio/pages/orphan_action', $delete );
			wp_delete_post( $post_id, true );
		}
	}

	public function test_resync_is_noop_until_source_or_layout_changes(): void {
		$temp_dir        = sys_get_temp_dir() . '/blockstudio-resync-' . uniqid();
		$collection_root = $temp_dir . '/cache-docs';

		mkdir( $collection_root, 0755, true );

		file_put_contents(
			$collection_root . '/pages.json',
			wp_json_encode(
				array(
					'collection' => 'cache-docs',
					'title'      => 'Cache Docs',
					'postType'   => 'page',
					'defaults'   => array(
						'postStatus' => 'publish',
					),
				)
			)
		);
		file_put_contents( $collection_root . '/layout.php', '<main data-cache-layout><?php echo blockstudio_page_content(); ?></main>' );
		file_put_contents(
			$collection_root . '/index.md',
			"---\nname: cache-docs-home\ntitle: Cache Docs Home\npath: .\n---\n\n# Cache Docs Home\n\nInitial content."
		);

		$sync_page = function () use ( $temp_dir ): int {
			$discovery = new Page_Discovery();
			$pages     = $discovery->discover( $temp_dir );
			$page      = $pages['cache-docs:cache-docs-home'];
			$result    = ( new Page_Sync() )->sync( $page );

			$this->assertIsInt( $result );

			return $result;
		};

		$post_id = null;

		try {
			$post_id = $sync_page();
			$first   = (string) get_post_meta( $post_id, '_blockstudio_page_fingerprint', true );

			$same = $sync_page();
			$this->assertSame( $post_id, $same );
			$this->assertSame( $first, (string) get_post_meta( $post_id, '_blockstudio_page_fingerprint', true ) );

			file_put_contents(
				$collection_root . '/index.md',
				"---\nname: cache-docs-home\ntitle: Cache Docs Home\npath: .\n---\n\n# Cache Docs Home\n\nUpdated content."
			);

			$source_changed = $sync_page();
			$second         = (string) get_post_meta( $post_id, '_blockstudio_page_fingerprint', true );
			$post           = get_post( $source_changed );

			$this->assertSame( $post_id, $source_changed );
			$this->assertNotSame( $first, $second );
			$this->assertInstanceOf( WP_Post::class, $post );
			$this->assertStringContainsString( 'Updated content.', $post->post_content );

			sleep( 1 );
			$modified_before_layout = $post->post_modified_gmt;

			file_put_contents( $collection_root . '/layout.php', '<main data-cache-layout="changed"><?php echo blockstudio_page_content(); ?></main>' );

			$layout_changed = $sync_page();
			$third          = (string) get_post_meta( $post_id, '_blockstudio_page_fingerprint', true );
			$updated_post   = get_post( $layout_changed );

			$this->assertSame( $post_id, $layout_changed );
			$this->assertNotSame( $second, $third );
			$this->assertInstanceOf( WP_Post::class, $updated_post );
			$this->assertGreaterThan( $modified_before_layout, $updated_post->post_modified_gmt );
		} finally {
			if ( is_int( $post_id ) && $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}

			$this->remove_dir( $temp_dir );
		}
	}

	private function clear_collection_manifest_cache(): void {
		global $wpdb;

		delete_option( 'blockstudio_collection_post_types_signature' );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_blockstudio_collection_manifests_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_blockstudio_collection_manifests_' ) . '%'
			)
		);
		wp_cache_flush();
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}
}

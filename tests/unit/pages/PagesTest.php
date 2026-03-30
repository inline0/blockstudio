<?php

use Blockstudio\Page_Discovery;
use Blockstudio\Page_Registry;
use Blockstudio\Page_Sync;
use Blockstudio\Pages;
use PHPUnit\Framework\TestCase;

class PagesTest extends TestCase {

	private string $pages_path;

	protected function setUp(): void {
		$this->pages_path = get_template_directory() . '/pages';

		Pages::reset();

		$discovery = new Page_Discovery();
		$registry  = Page_Registry::instance();
		$sync      = new Page_Sync();

		$registry->add_path( $this->pages_path );

		$pages = $discovery->discover( $this->pages_path );

		foreach ( $pages as $name => $page_data ) {
			$registry->register( $name, $page_data );

			$post_id = $sync->sync( $page_data );

			if ( is_int( $post_id ) && $post_id > 0 ) {
				$registry->set_synced_post( $page_data['source_path'], $post_id );
				$registry->update_page_data( $name, 'post_id', $post_id );
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

		$this->assertEmpty( Pages::pages() );
	}
}

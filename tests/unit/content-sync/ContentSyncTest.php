<?php
/**
 * Tests for Content Sync.
 *
 * @package Blockstudio
 */

use Blockstudio\Content_Sync;
use PHPUnit\Framework\TestCase;

/**
 * Content Sync tests.
 */
class ContentSyncTest extends TestCase {

	/**
	 * Test post type.
	 *
	 * @var string
	 */
	private string $post_type = 'bs_content_test';

	/**
	 * Test taxonomy.
	 *
	 * @var string
	 */
	private string $taxonomy = 'bs_content_topic';

	/**
	 * Test content path.
	 *
	 * @var string
	 */
	private string $content_path = 'content-sync-test';

	/**
	 * Created post IDs.
	 *
	 * @var array<int>
	 */
	private array $post_ids = array();

	/**
	 * Created user IDs.
	 *
	 * @var array<int>
	 */
	private array $user_ids = array();

	/**
	 * Set up test state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! post_type_exists( $this->post_type ) ) {
			register_post_type(
				$this->post_type,
				array(
					'public'       => true,
					'hierarchical' => true,
					'label'        => 'Content Sync Test',
				)
			);
		}

		if ( ! taxonomy_exists( $this->taxonomy ) ) {
			register_taxonomy(
				$this->taxonomy,
				$this->post_type,
				array(
					'public'       => true,
					'hierarchical' => true,
					'label'        => 'Content Sync Topic',
				)
			);
		}

		$this->delete_test_posts();
		$this->delete_test_terms();
		$this->remove_content_dir();
	}

	/**
	 * Clean up test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		$this->post_ids = array();
		$this->user_ids = array();
		$this->delete_test_posts();
		$this->delete_test_terms();
		$this->remove_content_dir();

		parent::tearDown();
	}

	/**
	 * Pull writes post JSON and body files with portable references.
	 *
	 * @return void
	 */
	public function test_pull_writes_post_files_with_declared_references(): void {
		$attachment_id = $this->insert_attachment( 'content-sync-image.jpg' );
		$post_id       = $this->insert_post(
			array(
				'post_title'   => 'Sync Source',
				'post_name'    => 'sync-source',
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		update_post_meta( $post_id, '_my_subtitle', 'Portable subtitle' );
		update_post_meta( $post_id, '_thumbnail_id', $attachment_id );

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->pull();

		$this->assertContains( 'written', wp_list_pluck( $rows, 'action' ) );

		$uid            = get_post_meta( $post_id, Content_Sync::META_UID, true );
		$attachment_uid = get_post_meta( $attachment_id, Content_Sync::META_UID, true );

		$this->assertNotEmpty( $uid );
		$this->assertNotEmpty( $attachment_uid );

		$json_file = $this->find_post_json_file();
		$this->assertFileExists( $json_file );
		$this->assertFileExists( preg_replace( '/\.json$/', '.html', $json_file ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $json_file ), true );
		$this->assertSame( $uid, $data['uid'] );
		$this->assertSame( $this->post_type, $data['type'] );
		$this->assertSame( 'Portable subtitle', $data['meta']['_my_subtitle'] );
		$this->assertSame( $attachment_uid, $data['meta']['_thumbnail_id'] );
		$this->assertSame( 'scalar', $data['metaEncoding']['_thumbnail_id'] );
	}

	/**
	 * Pull dry run does not write UIDs or files.
	 *
	 * @return void
	 */
	public function test_pull_dry_run_does_not_write_database_or_files(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Dry Source',
				'post_name'  => 'dry-source',
			)
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->pull( array( 'dry-run' => true ) );

		$this->assertSame( array( 'would-write' ), array_values( array_unique( wp_list_pluck( $rows, 'action' ) ) ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, Content_Sync::META_UID, true ) );
		$this->assertFileDoesNotExist( $this->content_root() );
	}

	/**
	 * Disabled Content Sync config does not read or write content.
	 *
	 * @return void
	 */
	public function test_disabled_content_sync_is_a_noop(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Disabled Source',
				'post_name'  => 'disabled-source',
			)
		);

		$sync = new Content_Sync( $this->config( array( 'enabled' => false ) ) );

		$this->assertSame( array( 'skipped' ), wp_list_pluck( $sync->pull(), 'action' ) );
		$this->assertSame( array( 'skipped' ), wp_list_pluck( $sync->push(), 'action' ) );
		$this->assertSame( array( 'skipped' ), wp_list_pluck( $sync->status(), 'action' ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, Content_Sync::META_UID, true ) );
		$this->assertFileDoesNotExist( $this->content_root() );
	}

	/**
	 * CLI selectors cannot expand beyond the configured allowlist.
	 *
	 * @return void
	 */
	public function test_cli_selectors_must_be_configured(): void {
		$sync = new Content_Sync( $this->config() );

		$post_rows = $sync->pull( array( 'post-type' => 'post' ) );
		$tax_rows  = $sync->pull( array( 'taxonomy' => 'category' ) );

		$this->assertSame( array( 'error' ), wp_list_pluck( $post_rows, 'action' ) );
		$this->assertSame( array( 'post_type' ), wp_list_pluck( $post_rows, 'entity' ) );
		$this->assertSame( array( 'post' ), wp_list_pluck( $post_rows, 'id' ) );
		$this->assertSame( array( 'error' ), wp_list_pluck( $tax_rows, 'action' ) );
		$this->assertSame( array( 'taxonomy' ), wp_list_pluck( $tax_rows, 'entity' ) );
		$this->assertSame( array( 'category' ), wp_list_pluck( $tax_rows, 'id' ) );
	}

	/**
	 * Pull does not rewrite unchanged files.
	 *
	 * @return void
	 */
	public function test_pull_does_not_rewrite_unchanged_files(): void {
		$this->insert_post(
			array(
				'post_title'   => 'Stable Source',
				'post_name'    => 'stable-source',
				'post_content' => '<p>Stable body</p>',
			)
		);

		$sync = new Content_Sync( $this->config() );
		$sync->pull();

		$json_file = $this->find_post_json_file();
		$body_file = preg_replace( '/\.json$/', '.html', $json_file );
		$this->assertFileExists( $body_file );

		$old_time = time() - 3600;
		touch( $json_file, $old_time );
		touch( $body_file, $old_time );
		clearstatcache( true, $json_file );
		clearstatcache( true, $body_file );

		$rows = $sync->pull();

		clearstatcache( true, $json_file );
		clearstatcache( true, $body_file );

		$this->assertSame( array( 'unchanged' ), wp_list_pluck( $rows, 'action' ) );
		$this->assertSame( $old_time, filemtime( $json_file ) );
		$this->assertSame( $old_time, filemtime( $body_file ) );
	}

	/**
	 * Pull reports stale files whose database source no longer exists.
	 *
	 * @return void
	 */
	public function test_pull_reports_stale_post_and_term_files(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Stale Source',
				'post_name'  => 'stale-source',
			)
		);
		$term    = wp_insert_term( 'Stale Topic', $this->taxonomy, array( 'slug' => 'stale-topic' ) );

		$this->assertIsArray( $term );

		$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
		$sync->pull();

		$post_file = $this->find_post_json_file();
		$term_file = $this->find_term_json_file();

		wp_delete_post( $post_id, true );
		wp_delete_term( (int) $term['term_id'], $this->taxonomy );

		$rows  = $sync->pull( array( 'dry-run' => true ) );
		$stale = array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => 'stale' === $row['action']
			)
		);

		$this->assertCount( 2, $stale );
		$this->assertSame( array( 'post', 'term' ), wp_list_pluck( $stale, 'entity' ) );
		$this->assertFileExists( $post_file );
		$this->assertFileExists( $term_file );
	}

	/**
	 * Push creates posts and rewrites declared post references.
	 *
	 * @return void
	 */
	public function test_push_creates_posts_and_rewrites_declared_references(): void {
		$parent_uid = wp_generate_uuid4();
		$child_uid  = wp_generate_uuid4();

		$this->write_post_file(
			'parent',
			array(
				'uid'          => $parent_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'parent',
				'title'        => 'Parent',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			'<p>Parent body</p>'
		);

		$this->write_post_file(
			'child',
			array(
				'uid'          => $child_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'child',
				'title'        => 'Child',
				'parent'       => $parent_uid,
				'menuOrder'    => 0,
				'meta'         => array(
					'_related_posts' => array( $parent_uid ),
					'_my_payload'    => array( 'id' => 123 ),
				),
				'metaEncoding' => array(
					'_related_posts' => 'json',
					'_my_payload'    => 'json',
				),
			),
			'<p>Child body</p>'
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$parent = $this->get_post_by_uid( $parent_uid );
		$child  = $this->get_post_by_uid( $child_uid );

		$this->assertInstanceOf( WP_Post::class, $parent );
		$this->assertInstanceOf( WP_Post::class, $child );
		$this->assertSame( $parent->ID, (int) $child->post_parent );
		$this->assertSame( array( $parent->ID ), json_decode( (string) get_post_meta( $child->ID, '_related_posts', true ), true ) );
		$this->assertSame( array( 'id' => 123 ), json_decode( (string) get_post_meta( $child->ID, '_my_payload', true ), true ) );
	}

	/**
	 * Push restores database drift when files still match the last sync fingerprint.
	 *
	 * @return void
	 */
	public function test_push_restores_post_database_drift(): void {
		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'drift-source',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'drift-source',
				'title'        => 'File Title',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_my_subtitle' => 'File Subtitle',
				),
				'metaEncoding' => array(
					'_my_subtitle' => 'scalar',
				),
			),
			'<p>File body</p>'
		);

		$sync = new Content_Sync( $this->config() );
		$sync->push();

		$post = $this->get_post_by_uid( $uid );
		$this->assertInstanceOf( WP_Post::class, $post );

		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_title'   => 'Database Drift',
				'post_content' => '<p>Database drift</p>',
			)
		);
		update_post_meta( $post->ID, '_my_subtitle', 'Database Drift' );

		$rows = $sync->push();
		$post = get_post( $post->ID );

		$this->assertContains( 'updated', wp_list_pluck( $rows, 'action' ) );
		$this->assertSame( 'File Title', $post->post_title );
		$this->assertSame( '<p>File body</p>', $post->post_content );
		$this->assertSame( 'File Subtitle', get_post_meta( $post->ID, '_my_subtitle', true ) );
	}

	/**
	 * Push blocks unresolved declared attachment references.
	 *
	 * @return void
	 */
	public function test_push_blocks_unresolved_declared_attachment_reference(): void {
		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'needs-attachment',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'needs-attachment',
				'title'        => 'Needs Attachment',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_thumbnail_id' => wp_generate_uuid4(),
				),
				'metaEncoding' => array(
					'_thumbnail_id' => 'scalar',
				),
			),
			''
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $uid ) );
	}

	/**
	 * media:none drops declared attachment reference meta.
	 *
	 * @return void
	 */
	public function test_media_none_drops_declared_attachment_references(): void {
		$attachment_id = $this->insert_attachment( 'media-none.jpg' );
		$post_id       = $this->insert_post(
			array(
				'post_title' => 'Media None Source',
				'post_name'  => 'media-none-source',
			)
		);

		update_post_meta( $post_id, '_thumbnail_id', $attachment_id );

		$sync = new Content_Sync( $this->config( array( 'media' => 'none' ) ) );
		$sync->pull();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $this->find_post_json_file() ), true );
		$this->assertArrayNotHasKey( '_thumbnail_id', $data['meta'] );
		$this->assertSame( '', (string) get_post_meta( $attachment_id, Content_Sync::META_UID, true ) );

		$incoming_uid = wp_generate_uuid4();
		$this->write_post_file(
			'media-none-push',
			array(
				'uid'          => $incoming_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'media-none-push',
				'title'        => 'Media None Push',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_thumbnail_id' => wp_generate_uuid4(),
				),
				'metaEncoding' => array(
					'_thumbnail_id' => 'scalar',
				),
			),
			''
		);

		$rows = $sync->push();
		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$post = $this->get_post_by_uid( $incoming_uid );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( '', (string) get_post_meta( $post->ID, '_thumbnail_id', true ) );

		$existing_uid = wp_generate_uuid4();
		$existing_id  = $this->insert_post(
			array(
				'post_title' => 'Media None Existing',
				'post_name'  => 'media-none-existing',
			)
		);

		update_post_meta( $existing_id, Content_Sync::META_UID, $existing_uid );
		update_post_meta( $existing_id, Content_Sync::META_SET, 'unit' );
		update_post_meta( $existing_id, '_thumbnail_id', $attachment_id );

		$this->write_post_file(
			'media-none-existing',
			array(
				'uid'          => $existing_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'media-none-existing',
				'title'        => 'Media None Existing Updated',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_thumbnail_id' => wp_generate_uuid4(),
				),
				'metaEncoding' => array(
					'_thumbnail_id' => 'scalar',
				),
			),
			''
		);

		$rows = $sync->push();
		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertSame( (string) $attachment_id, (string) get_post_meta( $existing_id, '_thumbnail_id', true ) );
	}

	/**
	 * Page Sync managed posts are excluded by default.
	 *
	 * @return void
	 */
	public function test_pull_excludes_page_sync_managed_posts_by_default(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Page Sync Owned',
				'post_name'  => 'page-sync-owned',
			)
		);

		update_post_meta( $post_id, '_blockstudio_page_key', 'page-sync-owned' );

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->pull();

		$this->assertSame( array( 'skipped' ), wp_list_pluck( $rows, 'action' ) );
		$this->assertFileDoesNotExist( $this->content_root() );
	}

	/**
	 * Push reports slug conflicts before writing.
	 *
	 * @return void
	 */
	public function test_push_blocks_slug_conflict(): void {
		$this->insert_post(
			array(
				'post_title' => 'Existing',
				'post_name'  => 'same-slug',
			)
		);

		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'same-slug',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'same-slug',
				'title'        => 'Incoming',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $uid ) );
	}

	/**
	 * Push blocks duplicate post slugs in the same file plan.
	 *
	 * @return void
	 */
	public function test_push_blocks_duplicate_file_plan_post_slugs(): void {
		$first_uid  = wp_generate_uuid4();
		$second_uid = wp_generate_uuid4();

		foreach ( array( $first_uid, $second_uid ) as $index => $uid ) {
			$this->write_post_file(
				'duplicate-plan-' . $index,
				array(
					'uid'          => $uid,
					'type'         => $this->post_type,
					'status'       => 'publish',
					'slug'         => 'duplicate-plan',
					'title'        => 'Duplicate Plan ' . $index,
					'parent'       => null,
					'menuOrder'    => 0,
					'meta'         => array(),
					'metaEncoding' => array(),
				),
				''
			);
		}

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $first_uid ) );
		$this->assertNull( $this->get_post_by_uid( $second_uid ) );
	}

	/**
	 * A child under a queued parent can reuse an unrelated top-level slug.
	 *
	 * @return void
	 */
	public function test_push_allows_child_slug_when_parent_is_queued(): void {
		$this->insert_post(
			array(
				'post_title' => 'Top Level Existing',
				'post_name'  => 'shared-child-slug',
			)
		);

		$parent_uid = wp_generate_uuid4();
		$child_uid  = wp_generate_uuid4();

		$this->write_post_file(
			'queued-parent',
			array(
				'uid'          => $parent_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'queued-parent',
				'title'        => 'Queued Parent',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);
		$this->write_post_file(
			'shared-child-slug',
			array(
				'uid'          => $child_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'shared-child-slug',
				'title'        => 'Shared Child Slug',
				'parent'       => $parent_uid,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$parent = $this->get_post_by_uid( $parent_uid );
		$child  = $this->get_post_by_uid( $child_uid );

		$this->assertInstanceOf( WP_Post::class, $parent );
		$this->assertInstanceOf( WP_Post::class, $child );
		$this->assertSame( $parent->ID, (int) $child->post_parent );
		$this->assertSame( 'shared-child-slug', $child->post_name );
	}

	/**
	 * Pull rewrites only declared structured reference paths.
	 *
	 * @return void
	 */
	public function test_pull_rewrites_only_declared_structured_reference_paths(): void {
		$attachment_id = $this->insert_attachment( 'structured-reference.jpg' );
		$post_id       = $this->insert_post(
			array(
				'post_title' => 'Structured Source',
				'post_name'  => 'structured-source',
			)
		);

		update_post_meta(
			$post_id,
			'_hero',
			wp_json_encode(
				array(
					'image' => array( 'id' => $attachment_id ),
					'copy'  => 'Hero',
				)
			)
		);
		update_post_meta(
			$post_id,
			'_my_payload',
			wp_json_encode(
				array(
					'image' => array( 'id' => 123 ),
				)
			)
		);

		$sync = new Content_Sync(
			$this->config(
				array(
					'meta' => array(
						'include'    => array( '_hero', '_my_payload' ),
						'references' => array(
							'_hero' => array(
								'kind' => 'attachment',
								'path' => 'image.id',
							),
						),
					),
				)
			)
		);
		$sync->pull();

		$attachment_uid = get_post_meta( $attachment_id, Content_Sync::META_UID, true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $this->find_post_json_file() ), true );

		$this->assertSame( $attachment_uid, $data['meta']['_hero']['image']['id'] );
		$this->assertSame( 123, $data['meta']['_my_payload']['image']['id'] );
		$this->assertSame( 'json', $data['metaEncoding']['_hero'] );
	}

	/**
	 * Push rewrites declared structured references back to local IDs.
	 *
	 * @return void
	 */
	public function test_push_rewrites_declared_structured_reference_paths(): void {
		$attachment_id  = $this->insert_attachment( 'structured-push.jpg' );
		$attachment_uid = wp_generate_uuid4();
		$post_uid       = wp_generate_uuid4();

		update_post_meta( $attachment_id, Content_Sync::META_UID, $attachment_uid );
		update_post_meta( $attachment_id, Content_Sync::META_SET, 'unit' );

		$this->write_post_file(
			'structured-push',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'structured-push',
				'title'        => 'Structured Push',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_hero' => array(
						'image' => array( 'id' => $attachment_uid ),
						'copy'  => 'Hero',
					),
				),
				'metaEncoding' => array(
					'_hero' => 'json',
				),
			),
			''
		);

		$sync = new Content_Sync(
			$this->config(
				array(
					'meta' => array(
						'include'    => array( '_hero' ),
						'references' => array(
							'_hero' => array(
								'kind' => 'attachment',
								'path' => 'image.id',
							),
						),
					),
				)
			)
		);
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$post = $this->get_post_by_uid( $post_uid );
		$this->assertInstanceOf( WP_Post::class, $post );

		$hero = json_decode( (string) get_post_meta( $post->ID, '_hero', true ), true );
		$this->assertSame( $attachment_id, $hero['image']['id'] );
	}

	/**
	 * Push applies author login mapping when enabled.
	 *
	 * @return void
	 */
	public function test_push_applies_author_login_mapping(): void {
		$login   = 'content_sync_author_' . wp_rand();
		$user_id = $this->insert_user( $login );
		$uid     = wp_generate_uuid4();

		$this->write_post_file(
			'authored-post',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'authored-post',
				'title'        => 'Authored Post',
				'author'       => array(
					'login' => $login,
				),
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config( array( 'authors' => 'login' ) ) );
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$post = $this->get_post_by_uid( $uid );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $user_id, (int) $post->post_author );
	}

	/**
	 * Push blocks missing author login references.
	 *
	 * @return void
	 */
	public function test_push_blocks_missing_author_login(): void {
		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'missing-author',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'missing-author',
				'title'        => 'Missing Author',
				'author'       => array(
					'login' => 'missing_content_sync_author',
				),
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config( array( 'authors' => 'login' ) ) );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $uid ) );
	}

	/**
	 * Pull honors meta include and exclude patterns.
	 *
	 * @return void
	 */
	public function test_pull_honors_meta_include_and_exclude_patterns(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Meta Source',
				'post_name'  => 'meta-source',
			)
		);

		update_post_meta( $post_id, '_my_allowed', 'yes' );
		update_post_meta( $post_id, '_my_secret', 'no' );
		update_post_meta( $post_id, '_outside', 'no' );

		$sync = new Content_Sync(
			$this->config(
				array(
					'meta' => array(
						'include' => array( '_my_*' ),
						'exclude' => array( '_my_secret' ),
					),
				)
			)
		);
		$sync->pull();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $this->find_post_json_file() ), true );

		$this->assertSame( array( '_my_allowed' => 'yes' ), $data['meta'] );
	}

	/**
	 * Push skips locked content-owned posts.
	 *
	 * @return void
	 */
	public function test_push_skips_locked_posts(): void {
		$uid     = wp_generate_uuid4();
		$next_uid = wp_generate_uuid4();
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Locked Original',
				'post_name'  => 'locked-original',
			)
		);

		update_post_meta( $post_id, Content_Sync::META_UID, $uid );
		update_post_meta( $post_id, Content_Sync::META_SET, 'unit' );
		update_post_meta( $post_id, Content_Sync::META_LOCKED, '1' );

		$this->write_post_file(
			'locked-original',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'locked-original',
				'title'        => 'Locked Updated',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			'<p>Updated</p>'
		);
		$this->write_post_file(
			'created-after-lock',
			array(
				'uid'          => $next_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'created-after-lock',
				'title'        => 'Created After Lock',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			'<p>Created</p>'
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'locked', wp_list_pluck( $rows, 'action' ) );
		$this->assertContains( 'created', wp_list_pluck( $rows, 'action' ) );
		$this->assertSame( 'Locked Original', get_post( $post_id )->post_title );
		$this->assertInstanceOf( WP_Post::class, $this->get_post_by_uid( $next_uid ) );
	}

	/**
	 * Prune is scoped to the configured content set.
	 *
	 * @return void
	 */
	public function test_push_prune_deletes_only_current_content_set(): void {
		$owned_uid = wp_generate_uuid4();
		$other_uid = wp_generate_uuid4();
		$attachment_uid = wp_generate_uuid4();

		$owned_id = $this->insert_post(
			array(
				'post_title' => 'Owned Orphan',
				'post_name'  => 'owned-orphan',
			)
		);
		$other_id = $this->insert_post(
			array(
				'post_title' => 'Other Set',
				'post_name'  => 'other-set',
			)
		);
		$attachment_id = $this->insert_attachment( 'owned-media.jpg' );

		update_post_meta( $owned_id, Content_Sync::META_UID, $owned_uid );
		update_post_meta( $owned_id, Content_Sync::META_SET, 'unit' );
		update_post_meta( $other_id, Content_Sync::META_UID, $other_uid );
		update_post_meta( $other_id, Content_Sync::META_SET, 'other' );
		update_post_meta( $attachment_id, Content_Sync::META_UID, $attachment_uid );
		update_post_meta( $attachment_id, Content_Sync::META_SET, 'unit' );

		$filter = static fn() => 'delete';
		add_filter( 'blockstudio/content/orphan_action', $filter );

		try {
			$sync = new Content_Sync( $this->config() );
			$rows = $sync->push( array( 'prune' => true ) );
		} finally {
			remove_filter( 'blockstudio/content/orphan_action', $filter );
		}

		$this->assertContains( 'pruned-delete', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( get_post( $owned_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $other_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $attachment_id ) );
	}

	/**
	 * Prune respects an explicit post type selector.
	 *
	 * @return void
	 */
	public function test_push_prune_is_scoped_to_selected_post_type(): void {
		$other_post_type = 'bs_content_other';

		if ( ! post_type_exists( $other_post_type ) ) {
			register_post_type(
				$other_post_type,
				array(
					'public' => true,
					'label'  => 'Content Sync Other',
				)
			);
		}

		$selected_uid = wp_generate_uuid4();
		$other_uid    = wp_generate_uuid4();
		$selected_id  = $this->insert_post(
			array(
				'post_title' => 'Selected Orphan',
				'post_name'  => 'selected-orphan',
			)
		);
		$other_id     = wp_insert_post(
			array(
				'post_type'   => $other_post_type,
				'post_status' => 'publish',
				'post_title'  => 'Other Type Orphan',
				'post_name'   => 'other-type-orphan',
			),
			true
		);

		$this->assertIsInt( $other_id );
		$this->post_ids[] = $other_id;

		update_post_meta( $selected_id, Content_Sync::META_UID, $selected_uid );
		update_post_meta( $selected_id, Content_Sync::META_SET, 'unit' );
		update_post_meta( $other_id, Content_Sync::META_UID, $other_uid );
		update_post_meta( $other_id, Content_Sync::META_SET, 'unit' );

		$filter = static fn() => 'delete';
		add_filter( 'blockstudio/content/orphan_action', $filter );

		try {
			$sync = new Content_Sync( $this->config( array( 'postTypes' => array( $this->post_type, $other_post_type ) ) ) );
			$rows = $sync->push(
				array(
					'prune'     => true,
					'post-type' => $this->post_type,
				)
			);
		} finally {
			remove_filter( 'blockstudio/content/orphan_action', $filter );
		}

		$this->assertContains( 'pruned-delete', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( get_post( $selected_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $other_id ) );

		$status = $sync->status( array( 'post-type' => $this->post_type ) );
		$this->assertNotContains( $other_uid, wp_list_pluck( $status, 'uid' ) );
	}

	/**
	 * Malformed content JSON blocks prune instead of orphaning the DB entity.
	 *
	 * @return void
	 */
	public function test_malformed_post_json_blocks_prune(): void {
		$orphan_uid = wp_generate_uuid4();
		$orphan_id  = $this->insert_post(
			array(
				'post_title' => 'Malformed Protected',
				'post_name'  => 'malformed-protected',
			)
		);
		$dir        = $this->content_root() . '/posts/' . $this->post_type;

		update_post_meta( $orphan_id, Content_Sync::META_UID, $orphan_uid );
		update_post_meta( $orphan_id, Content_Sync::META_SET, 'unit' );

		wp_mkdir_p( $dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing malformed local content sync fixture.
		file_put_contents( $dir . '/broken.json', '{broken' );

		$filter = static fn() => 'delete';
		add_filter( 'blockstudio/content/orphan_action', $filter );

		try {
			$sync = new Content_Sync( $this->config() );
			$rows = $sync->push( array( 'prune' => true ) );
		} finally {
			remove_filter( 'blockstudio/content/orphan_action', $filter );
		}

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertStringContainsString( 'Syntax error', implode( ' ', wp_list_pluck( $rows, 'message' ) ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $orphan_id ) );
	}

	/**
	 * Status reports unchanged after a successful push.
	 *
	 * @return void
	 */
	public function test_status_reports_unchanged_after_push(): void {
		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'status-source',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'status-source',
				'title'        => 'Status Source',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_my_subtitle' => 'Stable',
				),
				'metaEncoding' => array(
					'_my_subtitle' => 'scalar',
				),
			),
			'<p>Status body</p>'
		);

		$sync = new Content_Sync( $this->config() );
		$sync->push();
		$rows = $sync->status();

		$this->assertSame( array( 'unchanged' ), wp_list_pluck( $rows, 'action' ) );
	}

	/**
	 * Push dry-run reports the plan without writing database changes.
	 *
	 * @return void
	 */
	public function test_push_dry_run_reports_plan_without_writes(): void {
		$term_uid   = wp_generate_uuid4();
		$post_uid   = wp_generate_uuid4();
		$orphan_uid = wp_generate_uuid4();
		$orphan_id  = $this->insert_post(
			array(
				'post_title' => 'Dry Run Orphan',
				'post_name'  => 'dry-run-orphan',
			)
		);

		update_post_meta( $orphan_id, Content_Sync::META_UID, $orphan_uid );
		update_post_meta( $orphan_id, Content_Sync::META_SET, 'unit' );

		$this->write_term_file(
			'dry-run-topic',
			array(
				'uid'          => $term_uid,
				'taxonomy'     => $this->taxonomy,
				'slug'         => 'dry-run-topic',
				'name'         => 'Dry Run Topic',
				'description'  => '',
				'parent'       => null,
				'meta'         => array(),
				'metaEncoding' => array(),
			)
		);
		$this->write_post_file(
			'dry-run-post',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'dry-run-post',
				'title'        => 'Dry Run Post',
				'parent'       => null,
				'menuOrder'    => 0,
				'terms'        => array(
					$this->taxonomy => array( $term_uid ),
				),
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$filter = static fn() => 'delete';
		add_filter( 'blockstudio/content/orphan_action', $filter );

		try {
			$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
			$rows = $sync->push(
				array(
					'dry-run' => true,
					'prune'   => true,
				)
			);
		} finally {
			remove_filter( 'blockstudio/content/orphan_action', $filter );
		}

		$this->assertContains( 'would-create', wp_list_pluck( $rows, 'action' ) );
		$this->assertContains( 'would-prune-delete', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $post_uid ) );
		$this->assertNull( $this->get_term_by_uid( $term_uid ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $orphan_id ) );
	}

	/**
	 * Status reports term unchanged, file update, and database conflict states.
	 *
	 * @return void
	 */
	public function test_status_reports_term_update_and_conflict_states(): void {
		$term_uid = wp_generate_uuid4();

		$file = $this->write_term_file(
			'status-topic',
			array(
				'uid'          => $term_uid,
				'taxonomy'     => $this->taxonomy,
				'slug'         => 'status-topic',
				'name'         => 'Status Topic',
				'description'  => '',
				'parent'       => null,
				'meta'         => array(),
				'metaEncoding' => array(),
			)
		);

		$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
		$sync->push();

		$rows = $sync->status();
		$this->assertSame( array( 'unchanged' ), wp_list_pluck( $rows, 'action' ) );

		$data         = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data['name'] = 'Status Topic From File';
		file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Updating local content sync fixture.

		$rows = $sync->status();
		$this->assertSame( array( 'would-update' ), wp_list_pluck( $rows, 'action' ) );

		$term = $this->get_term_by_uid( $term_uid );
		$this->assertInstanceOf( WP_Term::class, $term );
		wp_update_term( $term->term_id, $this->taxonomy, array( 'name' => 'Status Topic From Database' ) );

		$rows = $sync->status();
		$this->assertSame( array( 'conflict' ), wp_list_pluck( $rows, 'action' ) );
	}

	/**
	 * Push restores term drift when files still match the last sync fingerprint.
	 *
	 * @return void
	 */
	public function test_push_restores_term_database_drift(): void {
		$term_uid = wp_generate_uuid4();

		$this->write_term_file(
			'drift-topic',
			array(
				'uid'          => $term_uid,
				'taxonomy'     => $this->taxonomy,
				'slug'         => 'drift-topic',
				'name'         => 'File Topic',
				'description'  => 'File description',
				'parent'       => null,
				'meta'         => array(
					'_my_color' => 'blue',
				),
				'metaEncoding' => array(
					'_my_color' => 'scalar',
				),
			)
		);

		$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
		$sync->push();

		$term = $this->get_term_by_uid( $term_uid );
		$this->assertInstanceOf( WP_Term::class, $term );

		wp_update_term(
			$term->term_id,
			$this->taxonomy,
			array(
				'name'        => 'Database Drift',
				'description' => 'Database drift',
			)
		);
		update_term_meta( $term->term_id, '_my_color', 'red' );

		$rows = $sync->push();
		$term = get_term( $term->term_id, $this->taxonomy );

		$this->assertContains( 'updated', wp_list_pluck( $rows, 'action' ) );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'File Topic', $term->name );
		$this->assertSame( 'File description', $term->description );
		$this->assertSame( 'blue', get_term_meta( $term->term_id, '_my_color', true ) );
	}

	/**
	 * Status warns when allowlisted meta keys look sensitive.
	 *
	 * @return void
	 */
	public function test_status_warns_about_sensitive_allowlisted_meta_keys(): void {
		$post_uid = wp_generate_uuid4();

		$this->write_post_file(
			'sensitive-meta',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'sensitive-meta',
				'title'        => 'Sensitive Meta',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_my_api_key' => 'should-not-be-committed',
				),
				'metaEncoding' => array(
					'_my_api_key' => 'scalar',
				),
			),
			''
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->status();

		$warnings = array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => 'warning' === $row['action'] && '_my_api_key' === $row['id']
			)
		);

		$this->assertCount( 1, $warnings );
		$this->assertSame( 'meta', $warnings[0]['entity'] );
	}

	/**
	 * Status warns when block markup contains hardcoded numeric IDs.
	 *
	 * @return void
	 */
	public function test_status_warns_about_block_markup_id_references(): void {
		$post_uid = wp_generate_uuid4();

		$this->write_post_file(
			'body-reference',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'body-reference',
				'title'        => 'Body Reference',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			'<!-- wp:image {"id":123,"sizeSlug":"large"} /-->'
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->status();

		$warnings = array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => 'warning' === $row['action'] && 'body' === $row['entity']
			)
		);

		$this->assertCount( 1, $warnings );
		$this->assertSame( $post_uid, $warnings[0]['uid'] );
	}

	/**
	 * Push creates terms parent-first and assigns post term relationships.
	 *
	 * @return void
	 */
	public function test_push_creates_terms_and_assigns_relationships(): void {
		$parent_uid = wp_generate_uuid4();
		$child_uid  = wp_generate_uuid4();
		$post_uid   = wp_generate_uuid4();

		$this->write_term_file(
			'topic-parent',
			array(
				'uid'          => $parent_uid,
				'taxonomy'     => $this->taxonomy,
				'slug'         => 'topic-parent',
				'name'         => 'Topic Parent',
				'description'  => '',
				'parent'       => null,
				'meta'         => array(
					'_my_color' => 'blue',
				),
				'metaEncoding' => array(
					'_my_color' => 'scalar',
				),
			)
		);
		$this->write_term_file(
			'topic-child',
			array(
				'uid'          => $child_uid,
				'taxonomy'     => $this->taxonomy,
				'slug'         => 'topic-child',
				'name'         => 'Topic Child',
				'description'  => '',
				'parent'       => $parent_uid,
				'meta'         => array(),
				'metaEncoding' => array(),
			)
		);
		$this->write_post_file(
			'with-topic',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'with-topic',
				'title'        => 'With Topic',
				'parent'       => null,
				'menuOrder'    => 0,
				'terms'        => array(
					$this->taxonomy => array( $child_uid ),
				),
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$parent = $this->get_term_by_uid( $parent_uid );
		$child  = $this->get_term_by_uid( $child_uid );
		$post   = $this->get_post_by_uid( $post_uid );

		$this->assertInstanceOf( WP_Term::class, $parent );
		$this->assertInstanceOf( WP_Term::class, $child );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $parent->term_id, $child->parent );
		$this->assertSame( 'blue', get_term_meta( $parent->term_id, '_my_color', true ) );
		$this->assertSame( array( $child->term_id ), wp_get_object_terms( $post->ID, $this->taxonomy, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Pull writes empty configured taxonomy relationships.
	 *
	 * @return void
	 */
	public function test_pull_writes_empty_configured_taxonomy_relationships(): void {
		$this->insert_post(
			array(
				'post_title' => 'No Topic',
				'post_name'  => 'no-topic',
			)
		);

		$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
		$sync->pull();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $this->find_post_json_file() ), true );

		$this->assertSame( array( $this->taxonomy => array() ), $data['terms'] );
	}

	/**
	 * Push clears configured taxonomy relationships when the file has an empty list.
	 *
	 * @return void
	 */
	public function test_push_clears_empty_term_relationships(): void {
		$post_uid = wp_generate_uuid4();
		$post_id  = $this->insert_post(
			array(
				'post_title' => 'Clear Topic',
				'post_name'  => 'clear-topic',
			)
		);
		$term     = wp_insert_term( 'Topic To Clear', $this->taxonomy, array( 'slug' => 'topic-to-clear' ) );

		$this->assertIsArray( $term );
		update_post_meta( $post_id, Content_Sync::META_UID, $post_uid );
		update_post_meta( $post_id, Content_Sync::META_SET, 'unit' );
		wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), $this->taxonomy );

		$this->write_post_file(
			'clear-topic',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'clear-topic',
				'title'        => 'Clear Topic',
				'parent'       => null,
				'menuOrder'    => 0,
				'terms'        => array(
					$this->taxonomy => array(),
				),
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertSame( array(), wp_get_object_terms( $post_id, $this->taxonomy, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Push rewrites declared queued term references in post meta.
	 *
	 * @return void
	 */
	public function test_push_rewrites_declared_queued_term_reference(): void {
		$term_uid = wp_generate_uuid4();
		$post_uid = wp_generate_uuid4();

		$this->write_term_file(
			'topic-reference',
			array(
				'uid'          => $term_uid,
				'taxonomy'     => $this->taxonomy,
				'slug'         => 'topic-reference',
				'name'         => 'Topic Reference',
				'description'  => '',
				'parent'       => null,
				'meta'         => array(),
				'metaEncoding' => array(),
			)
		);
		$this->write_post_file(
			'term-reference',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'term-reference',
				'title'        => 'Term Reference',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_my_term_ref' => $term_uid,
				),
				'metaEncoding' => array(
					'_my_term_ref' => 'scalar',
				),
			),
			''
		);

		$sync = new Content_Sync(
			$this->config(
				array(
					'taxonomies' => array( $this->taxonomy ),
					'meta'       => array(
						'include'    => array( '_my_*' ),
						'references' => array(
							'_my_term_ref' => array( 'kind' => 'term' ),
						),
					),
				)
			)
		);
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$term = $this->get_term_by_uid( $term_uid );
		$post = $this->get_post_by_uid( $post_uid );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( (string) $term->term_id, get_post_meta( $post->ID, '_my_term_ref', true ) );
	}

	/**
	 * Push blocks unresolved term relationship references.
	 *
	 * @return void
	 */
	public function test_push_blocks_unresolved_term_relationship_reference(): void {
		$post_uid = wp_generate_uuid4();

		$this->write_post_file(
			'missing-topic',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'missing-topic',
				'title'        => 'Missing Topic',
				'parent'       => null,
				'menuOrder'    => 0,
				'terms'        => array(
					$this->taxonomy => array( wp_generate_uuid4() ),
				),
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config( array( 'taxonomies' => array( $this->taxonomy ) ) ) );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $post_uid ) );
	}

	/**
	 * Push blocks post term relationships outside the configured taxonomy allowlist.
	 *
	 * @return void
	 */
	public function test_push_blocks_unconfigured_taxonomy_relationships(): void {
		$post_uid = wp_generate_uuid4();

		$this->write_post_file(
			'unconfigured-taxonomy',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'unconfigured-taxonomy',
				'title'        => 'Unconfigured Taxonomy',
				'parent'       => null,
				'menuOrder'    => 0,
				'terms'        => array(
					'category' => array(),
				),
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $post_uid ) );
	}

	/**
	 * Get test config.
	 *
	 * @param array $overrides Config overrides.
	 *
	 * @return array
	 */
	private function config( array $overrides = array() ): array {
		$config = array(
			'enabled'                => true,
			'id'                     => 'unit',
			'path'                   => $this->content_path,
			'includePageSyncManaged' => false,
			'authors'                => 'ignore',
			'postTypes'              => array( $this->post_type ),
			'meta'                   => array(
				'include'    => array( '_my_*', '_thumbnail_id', '_related_posts' ),
				'exclude'    => array( '_edit_lock', '_edit_last', '_wp_old_slug' ),
				'references' => array(
					'_thumbnail_id'  => array( 'kind' => 'attachment' ),
					'_related_posts' => array(
						'kind' => 'post',
						'path' => '*',
					),
				),
			),
			'taxonomies'             => array(),
			'media'                  => 'manifest',
		);

		return array_replace_recursive( $config, $overrides );
	}

	/**
	 * Insert a test post.
	 *
	 * @param array $args Post args.
	 *
	 * @return int
	 */
	private function insert_post( array $args ): int {
		$post_id = wp_insert_post(
			array_merge(
				array(
					'post_type'   => $this->post_type,
					'post_status' => 'publish',
				),
				$args
			),
			true
		);

		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Insert a test attachment.
	 *
	 * @param string $file File basename.
	 *
	 * @return int
	 */
	private function insert_attachment( string $file ): int {
		$post_id = wp_insert_attachment(
			array(
				'post_title'     => $file,
				'post_name'      => sanitize_title( $file ),
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'guid'           => 'https://example.test/' . $file,
			)
		);

		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Insert a test user.
	 *
	 * @param string $login User login.
	 *
	 * @return int
	 */
	private function insert_user( string $login ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'user_email' => $login . '@example.test',
			)
		);

		$this->assertIsInt( $user_id );
		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Write a term content file.
	 *
	 * @param string $slug Slug.
	 * @param array  $data Data.
	 *
	 * @return string
	 */
	private function write_term_file( string $slug, array $data ): string {
		$dir  = $this->content_root() . '/terms/' . $this->taxonomy;
		$file = $dir . '/' . $slug . '.' . substr( (string) $data['uid'], 0, 8 ) . '.json';

		wp_mkdir_p( $dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local content sync fixture.
		file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

		return $file;
	}

	/**
	 * Write a post content file pair.
	 *
	 * @param string $slug Slug.
	 * @param array  $data Data.
	 * @param string $body Body.
	 *
	 * @return string
	 */
	private function write_post_file( string $slug, array $data, string $body ): string {
		$dir  = $this->content_root() . '/posts/' . $this->post_type;
		$file = $dir . '/' . $slug . '.' . substr( (string) $data['uid'], 0, 8 ) . '.json';

		wp_mkdir_p( $dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local content sync fixture.
		file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

		if ( '' !== $body ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local content sync fixture.
			file_put_contents( preg_replace( '/\.json$/', '.html', $file ), $body );
		}

		return $file;
	}

	/**
	 * Get content root.
	 *
	 * @return string
	 */
	private function content_root(): string {
		return get_stylesheet_directory() . '/' . $this->content_path;
	}

	/**
	 * Find the generated post JSON file.
	 *
	 * @return string
	 */
	private function find_post_json_file(): string {
		$files = glob( $this->content_root() . '/posts/' . $this->post_type . '/*.json' );
		$this->assertNotEmpty( $files );

		return $files[0];
	}

	/**
	 * Find the generated term JSON file.
	 *
	 * @return string
	 */
	private function find_term_json_file(): string {
		$files = glob( $this->content_root() . '/terms/' . $this->taxonomy . '/*.json' );
		$this->assertNotEmpty( $files );

		return $files[0];
	}

	/**
	 * Find a post by content UID.
	 *
	 * @param string $uid UID.
	 *
	 * @return WP_Post|null
	 */
	private function get_post_by_uid( string $uid ): ?WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => Content_Sync::META_UID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $uid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $posts[0] ?? null;
	}

	/**
	 * Find a term by content UID.
	 *
	 * @param string $uid UID.
	 *
	 * @return WP_Term|null
	 */
	private function get_term_by_uid( string $uid ): ?WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Content_Sync::META_UID,
						'value' => $uid,
					),
				),
			)
		);

		return ! is_wp_error( $terms ) && ! empty( $terms ) && $terms[0] instanceof WP_Term ? $terms[0] : null;
	}

	/**
	 * Remove content directory.
	 *
	 * @return void
	 */
	private function remove_content_dir(): void {
		$root = $this->content_root();
		if ( ! is_dir( $root ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}

		rmdir( $root );
	}

	/**
	 * Delete posts from the test post type.
	 *
	 * @return void
	 */
	private function delete_test_posts(): void {
		$posts = get_posts(
			array(
				'post_type'      => $this->post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}

	/**
	 * Delete test terms.
	 *
	 * @return void
	 */
	private function delete_test_terms(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				wp_delete_term( $term->term_id, $this->taxonomy );
			}
		}
	}
}

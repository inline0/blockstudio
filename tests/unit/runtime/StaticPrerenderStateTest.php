<?php
/**
 * Static prerender state tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Static_Prerender_Batch_Renderer;
use Blockstudio\Static_Prerender_Content_Hasher;
use Blockstudio\Static_Prerender_Identity;
use Blockstudio\Static_Prerender_Miss_Lock;
use Blockstudio\Static_Prerender_Warm_Queue;
use PHPUnit\Framework\TestCase;

/**
 * Tests deterministic hashing, identity, miss locks, queueing, and sharding.
 */
class StaticPrerenderStateTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$this->root = trailingslashit( get_temp_dir() ) . 'blockstudio-prerender-state-' . wp_generate_uuid4();
		wp_mkdir_p( $this->root );
		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Identity::reset();
		Static_Prerender_Miss_Lock::release_all();
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Identity::reset();
		Static_Prerender_Miss_Lock::release_all();
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root . '/cache';
	}

	public function filter_site_key(): string {
		return 'unit-state';
	}

	public function test_file_hashes_are_memoized_then_change_after_reset(): void {
		$path = $this->root . '/page.php';
		file_put_contents( $path, 'first' );

		$first = Static_Prerender_Content_Hasher::file_hash( $path );
		$this->assertSame( $first, Static_Prerender_Content_Hasher::file_hash( $path ) );
		$this->assertSame( 1, Static_Prerender_Content_Hasher::diagnostics()['fileReads'] );

		file_put_contents( $path, 'second' );
		$this->assertSame( $first, Static_Prerender_Content_Hasher::file_hash( $path ) );

		Static_Prerender_Content_Hasher::reset();
		$this->assertNotSame( $first, Static_Prerender_Content_Hasher::file_hash( $path ) );
		unlink( $path );
		Static_Prerender_Content_Hasher::reset();
		$this->assertSame( 'missing', Static_Prerender_Content_Hasher::file_hash( $path ) );
	}

	public function test_snapshot_ids_are_portable_and_order_independent(): void {
		$theme = $this->root . '/theme';
		wp_mkdir_p( $theme . '/blocks' );
		file_put_contents( $theme . '/blocks/card.php', 'card' );
		file_put_contents( $theme . '/blocks/hero.php', 'hero' );

		$first = Static_Prerender_Content_Hasher::snapshot(
			array( $theme . '/blocks/hero.php', $theme . '/blocks/card.php' ),
			$theme
		);
		$same  = Static_Prerender_Content_Hasher::snapshot(
			array( $theme . '/blocks/card.php', $theme . '/blocks/hero.php' ),
			$theme
		);

		$this->assertSame( $first['hash'], $same['hash'] );
		$this->assertSame( array( 'theme/blocks/card.php', 'theme/blocks/hero.php' ), array_keys( $first['hashes'] ) );
		$this->assertNotSame(
			Static_Prerender_Content_Hasher::content_hash( array( 'a' => 'one' ) ),
			Static_Prerender_Content_Hasher::content_hash( array( 'a' => 'two' ) )
		);
	}

	public function test_identity_accepts_only_valid_artifacts_and_rotates(): void {
		$identity = str_repeat( 'a', 32 );

		$this->assertFalse( Static_Prerender_Identity::activate( 'invalid' ) );
		$this->assertTrue( Static_Prerender_Identity::activate( $identity ) );
		$this->assertSame( $identity, Static_Prerender_Identity::current() );
		$this->assertNotSame( $identity, Static_Prerender_Identity::rotate( 'site-change' ) );
	}

	public function test_miss_lock_distinguishes_owner_peer_ready_and_timeout(): void {
		$key = 'cold-page';
		$this->assertSame(
			'owner',
			Static_Prerender_Miss_Lock::acquire( $key, '__return_false', 0 )
		);
		Static_Prerender_Miss_Lock::release( $key );

		$option = 'blockstudio_static_miss_'
			. get_current_blog_id()
			. '_'
			. substr( hash( 'sha256', $key ), 0, 32 );
		delete_option( $option );
		add_option( $option, time(), '', false );

		try {
			$this->assertSame(
				'ready',
				Static_Prerender_Miss_Lock::acquire( $key, '__return_true', 0 )
			);
			$this->assertSame(
				'timeout',
				Static_Prerender_Miss_Lock::acquire( $key, '__return_false', 0 )
			);
		} finally {
			delete_option( $option );
		}
	}

	public function test_queue_coalesces_and_requeues_a_change_arriving_during_work(): void {
		$queue     = new Static_Prerender_Warm_Queue( $this->root . '/queue' );
		$url       = home_url( '/alpha/' );
		$url_hash  = hash( 'sha256', $url );
		$signature = str_repeat( 'b', 32 );

		$this->assertSame(
			1,
			$queue->enqueue_many(
				array(
					array(
						'url'        => $url,
						'urlHash'    => $url_hash,
						'signature'  => $signature,
						'reason'     => 'interval',
						'enqueuedAt' => 10,
					),
				)
			)
		);
		$this->assertSame( 1, $queue->counts()['pending'] );

		$claim = $queue->claim();
		$this->assertIsArray( $claim );
		$this->assertSame( 1, $queue->counts()['processing'] );

		$this->assertSame(
			1,
			$queue->enqueue_many(
				array(
					array(
						'url'        => $url,
						'urlHash'    => $url_hash,
						'signature'  => $signature,
						'reason'     => 'content-change',
						'enqueuedAt' => 20,
					),
				)
			)
		);

		$queue->complete( $claim['id'], $claim['token'] );
		$follow_up = $queue->claim();
		$this->assertIsArray( $follow_up );
		$this->assertSame( 'content-change', $follow_up['data']['reason'] );
		$queue->complete( $follow_up['id'], $follow_up['token'] );
		$this->assertSame( 0, $queue->stats()['unique'] );
	}

	public function test_queue_reset_and_compaction_recover_from_malformed_state(): void {
		$queue = new Static_Prerender_Warm_Queue( $this->root . '/queue-recovery' );
		wp_mkdir_p( $this->root . '/queue-recovery/records' );
		file_put_contents( $this->root . '/queue-recovery/records/broken.json', '{' );

		$compact = $queue->compact();
		$this->assertTrue( $compact['ok'] );
		$this->assertSame( 1, $compact['removed'] );

		$url = home_url( '/reset/' );
		$queue->enqueue_many(
			array(
				array(
					'url'       => $url,
					'urlHash'   => hash( 'sha256', $url ),
					'signature' => str_repeat( 'c', 32 ),
					'reason'    => 'interval',
				),
			)
		);
		$this->assertSame( 1, $queue->reset()['removed'] );
		$this->assertSame( 0, $queue->counts()['pending'] );
	}

	public function test_batch_shards_are_stable_and_cover_every_url_once(): void {
		$urls = array(
			'https://example.com/d',
			'https://example.com/b',
			'https://example.com/a',
			'https://example.com/c',
			'https://example.com/a',
		);

		$first  = Static_Prerender_Batch_Renderer::select_shard( $urls, 1, 2 );
		$second = Static_Prerender_Batch_Renderer::select_shard( $urls, 2, 2 );
		$all    = array_merge( $first, $second );
		sort( $all );

		$this->assertSame(
			array(
				'https://example.com/a',
				'https://example.com/b',
				'https://example.com/c',
				'https://example.com/d',
			),
			$all
		);
		$this->assertSame( array( 2, 4 ), Static_Prerender_Batch_Renderer::parse_shard( '2/4' ) );
		$this->assertSame( array( 1, 1 ), Static_Prerender_Batch_Renderer::parse_shard( '5/4' ) );
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $directory );
	}
}

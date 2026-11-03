<?php
/**
 * Page sync attribute report tests.
 *
 * @package Blockstudio
 */

// phpcs:disable WordPress.WP.AlternativeFunctions

use Blockstudio\Page_Sync;
use Blockstudio\Pages;
use PHPUnit\Framework\TestCase;

/**
 * Page sync stores every attribute an element carries, so it reports the ones
 * the target block will never render.
 */
class PageSyncAttributeReportTest extends TestCase {

	private string $pages_path;

	private int $user = 0;

	protected function setUp(): void {
		// WP-CLI syncs without kses; a user with unfiltered_html does the same.
		$this->user = get_current_user_id();
		wp_set_current_user( 1 );
		$this->pages_path = wp_normalize_path( get_temp_dir() . 'blockstudio-attribute-report-' . wp_generate_uuid4() );
		wp_mkdir_p( $this->pages_path );
		$this->delete_managed_posts();
		delete_option( 'blockstudio_pages_successful_source_identity' );
		Pages::reset();
		add_filter( 'blockstudio/pages/paths', array( $this, 'filter_paths' ), PHP_INT_MAX );
		add_filter( 'blockstudio/parser/element_mapping', array( $this, 'filter_mapping' ) );
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/pages/paths', array( $this, 'filter_paths' ), PHP_INT_MAX );
		remove_filter( 'blockstudio/parser/element_mapping', array( $this, 'filter_mapping' ) );
		$this->delete_managed_posts();
		$this->remove_directory( $this->pages_path );
		delete_option( 'blockstudio_pages_successful_source_identity' );
		Pages::reset();
		wp_set_current_user( $this->user );
	}

	public function filter_paths(): array {
		return array( $this->pages_path );
	}

	public function filter_mapping( array $mapping ): array {
		$mapping['section'] = 'blockstudio/type-block-tags-default';

		return $mapping;
	}

	public function test_sync_reports_dropped_attributes_per_page_and_stores_decoded_values(): void {
		$this->write_page( 'dropping', '<section class="hero" foo="bar" id="kept" title="Tom &amp; Jerry">x</section>' );
		$this->write_page( 'clean', '<section id="kept" data-animate="[&amp;>span]:fade">x</section>' );

		$report = $this->reconcile();

		$this->assertSame( 2, $report['created'] );
		$this->assertSame( 2, $report['droppedAttributes'] );
		$this->assertSame(
			array( 'blockstudio/type-block-tags-default' => array( 'class', 'foo' ) ),
			$report['pages']['dropping']['droppedAttributes']
		);
		$this->assertArrayNotHasKey( 'droppedAttributes', $report['pages']['clean'] );

		$stored = parse_blocks( (string) get_post_field( 'post_content', $report['pages']['clean']['postId'] ) );
		$this->assertSame( '[&>span]:fade', $stored[0]['attrs']['blockstudio']['attributes']['data-animate'] );
		$stored = parse_blocks( (string) get_post_field( 'post_content', $report['pages']['dropping']['postId'] ) );
		$this->assertSame( 'Tom & Jerry', $stored[0]['attrs']['blockstudio']['attributes']['title'] );
	}

	public function test_the_parser_change_moves_the_engine_fingerprint(): void {
		$captured = array();
		$capture  = static function ( array $inputs ) use ( &$captured ): array {
			$captured = $inputs;

			return $inputs;
		};
		add_filter( 'blockstudio/pages/sync_engine_inputs', $capture, PHP_INT_MAX );
		Page_Sync::engine_fingerprint();
		remove_filter( 'blockstudio/pages/sync_engine_inputs', $capture, PHP_INT_MAX );

		$this->assertSame( '2', $captured['htmlParser'] ?? null );
		$this->assertSame( '3', $captured['blockTags'] ?? null );
	}

	private function reconcile(): array {
		return Pages::reconcile(
			array(
				'authoritative' => true,
				'full'          => true,
				'plan_valid'    => true,
			)
		);
	}

	private function write_page( string $name, string $html ): void {
		$directory = $this->pages_path . '/' . $name;
		wp_mkdir_p( $directory );
		file_put_contents(
			$directory . '/page.json',
			wp_json_encode(
				array(
					'name'       => $name,
					'title'      => ucfirst( $name ),
					'slug'       => 'attribute-report-' . $name,
					'postType'   => 'page',
					'postStatus' => 'publish',
				)
			)
		);
		file_put_contents( $directory . '/index.php', $html );
	}

	private function delete_managed_posts(): void {
		foreach ( ( new Page_Sync() )->managed_posts() as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

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

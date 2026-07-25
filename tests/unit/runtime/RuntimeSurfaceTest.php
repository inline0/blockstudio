<?php

use Blockstudio\Media;
use Blockstudio\Media_Metadata;
use Blockstudio\Media_Metadata_Builder;
use Blockstudio\Performance_Measurement;
use Blockstudio\Runtime_Settings;
use Blockstudio\WordPress_Optimizations;
use PHPUnit\Framework\TestCase;

/**
 * Tests for public runtime helpers and generic runtime behavior.
 */
class RuntimeSurfaceTest extends TestCase {

	private string $theme_root;

	protected function setUp(): void {
		$this->theme_root = trailingslashit( get_temp_dir() ) . 'blockstudio-media-' . wp_generate_uuid4();
		wp_mkdir_p( $this->theme_root . '/assets/icons' );
		file_put_contents(
			$this->theme_root . '/assets/icons/card.svg',
			'<svg width="40" height="24" viewBox="0 0 40 24" xmlns="http://www.w3.org/2000/svg"></svg>'
		);
		Media_Metadata::reset();
		Runtime_Settings::reset();
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/performance/media/lazy', '__return_true' );
		remove_filter( 'blockstudio/performance/media/skeleton', '__return_true' );
		remove_filter( 'blockstudio/performance/measurement/enabled', '__return_true' );
		remove_filter( 'blockstudio/performance/measurement/queryMonitor', '__return_true' );
		Media_Metadata::reset();
		Runtime_Settings::reset();
		$this->remove_directory( $this->theme_root );
	}

	public function test_media_builder_writes_deterministic_theme_metadata(): void {
		$builder  = new Media_Metadata_Builder();
		$manifest = $builder->build( $this->theme_root, false );

		$this->assertSame( array( 'assets/icons/card.svg' ), array_keys( $manifest['themeAssets'] ) );
		$this->assertSame( 40, $manifest['themeAssets']['assets/icons/card.svg']['width'] );
		$this->assertSame( 24, $manifest['themeAssets']['assets/icons/card.svg']['height'] );
		$this->assertSame( array(), $manifest['attachments'] );

		$path = $builder->write( $this->theme_root, false );
		$this->assertSame( $this->theme_root . '/assets/media.json', $path );
		$this->assertSame(
			$manifest['themeAssets']['assets/icons/card.svg'],
			Media_Metadata::theme_asset( $this->theme_root, 'assets/icons/card.svg' )
		);
	}

	public function test_media_metadata_invalidates_when_manifest_changes(): void {
		$builder = new Media_Metadata_Builder();
		$builder->write( $this->theme_root, false );
		$this->assertSame( 40, Media_Metadata::theme_asset( $this->theme_root, 'assets/icons/card.svg' )['width'] );

		file_put_contents(
			$this->theme_root . '/assets/media.json',
			wp_json_encode(
				array(
					'version'     => 1,
					'themeAssets' => array(
						'assets/icons/card.svg' => array(
							'width'  => 99,
							'height' => 24,
							'type'   => 'image',
							'mime'   => 'image/svg+xml',
						),
					),
					'attachments' => array(),
				)
			)
		);

		$this->assertSame( 99, Media_Metadata::theme_asset( $this->theme_root, 'assets/icons/card.svg' )['width'] );
	}

	public function test_media_renderer_preserves_dimensions_without_lazy_runtime(): void {
		$html = Media::image(
			array(
				'src'    => 'https://example.com/card.jpg',
				'width'  => 640,
				'height' => 360,
				'alt'    => 'Example',
				'class'  => 'hero',
			)
		);

		$this->assertStringContainsString( 'class="blockstudio-media hero"', $html );
		$this->assertStringContainsString( 'aspect-ratio:640/360', $html );
		$this->assertStringContainsString( 'src="https://example.com/card.jpg"', $html );
		$this->assertStringNotContainsString( 'data-blockstudio-media', $html );
	}

	public function test_media_renderer_uses_product_neutral_lazy_attributes(): void {
		add_filter( 'blockstudio/performance/media/lazy', '__return_true' );
		add_filter( 'blockstudio/performance/media/skeleton', '__return_true' );
		Runtime_Settings::reset();

		$html = bs_media_image(
			array(
				'src'    => 'https://example.com/card.jpg',
				'width'  => 640,
				'height' => 360,
			)
		);

		$this->assertStringContainsString( 'blockstudio-media--skeleton', $html );
		$this->assertStringContainsString( 'data-blockstudio-media', $html );
		$this->assertStringContainsString( 'data-src="https://example.com/card.jpg"', $html );
	}

	public function test_tailwind_helpers_use_the_bundled_engine(): void {
		$this->assertSame( 'text-sm px-4', bs_tw_merge( 'px-2 text-sm', 'px-4' ) );

		$button = bs_tw_variants(
			array(
				'base'            => 'inline-flex',
				'variants'        => array(
					'size' => array(
						'sm' => 'h-8',
						'lg' => 'h-12',
					),
				),
				'defaultVariants' => array(
					'size' => 'sm',
				),
			)
		);
		$this->assertSame( 'inline-flex h-12 rounded', $button( array( 'size' => 'lg', 'class' => 'rounded' ) ) );
	}

	public function test_wordpress_optimization_callbacks_are_independent_and_deterministic(): void {
		$this->assertSame(
			array( 'Content-Type' => 'text/html' ),
			WordPress_Optimizations::filter_wp_headers(
				array(
					'X-Pingback'  => 'https://example.com/xmlrpc.php',
					'Content-Type' => 'text/html',
				)
			)
		);
		$this->assertSame( array( 'lists', 'link' ), WordPress_Optimizations::filter_tinymce_plugins( array( 'lists', 'wpemoji', 'link' ) ) );
		$this->assertSame( 82, WordPress_Optimizations::filter_image_quality( 90, 'image/webp' ) );
		$this->assertSame(
			array( 'thumbnail' => array() ),
			WordPress_Optimizations::filter_intermediate_image_sizes(
				array(
					'thumbnail' => array(),
					'1536x1536' => array(),
					'2048x2048' => array(),
				)
			)
		);
		$this->assertSame(
			array( 'alt' => 'Card', 'decoding' => 'async' ),
			WordPress_Optimizations::filter_attachment_image_attributes( array( 'alt' => 'Card' ) )
		);
	}

	public function test_measurement_snapshot_reports_slow_queries_when_enabled(): void {
		add_filter( 'blockstudio/performance/measurement/enabled', '__return_true' );
		add_filter( 'blockstudio/performance/measurement/queryMonitor', '__return_true' );
		Runtime_Settings::reset();

		$wpdb = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = (object) array(
			'num_queries' => 2,
			'queries'     => array(
				array( 'SELECT 1', 0.01, 'fast' ),
				array( 'SELECT 2', 0.08, 'slow' ),
			),
		);

		try {
			$snapshot = Performance_Measurement::snapshot();
			$this->assertSame( 2, $snapshot['queryCount'] );
			$this->assertCount( 1, $snapshot['slowQueries'] );
			$this->assertSame( 'SELECT 2', $snapshot['slowQueries'][0]['sql'] );
			$this->assertSame( 80.0, $snapshot['slowQueries'][0]['timeMs'] );
		} finally {
			$GLOBALS['wpdb'] = $wpdb;
		}
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

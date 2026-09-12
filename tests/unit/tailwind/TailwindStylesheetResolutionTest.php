<?php

use Blockstudio\Tailwind;
use PHPUnit\Framework\TestCase;

class TailwindStylesheetResolutionTest extends TestCase {

	private array $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing temporary Tailwind cache fixtures.
				unlink( $file );
			}
		}

		$this->files = array();
	}

	private function cache_url( string $path ): string {
		$content_dir = rtrim( wp_normalize_path( (string) WP_CONTENT_DIR ), '/' );

		return rtrim( (string) content_url(), '/' ) . substr( wp_normalize_path( $path ), strlen( $content_dir ) );
	}

	private function document( string $href ): string {
		return '<!doctype html><html><head><link id="blockstudio-tailwind" rel="stylesheet" href="'
			. esc_url( $href ) . '"></head><body>Page</body></html>';
	}

	private function cache_path(): string {
		$path = Tailwind::get_cache_dir() . '/' . str_repeat( 'a', 32 ) . '.css';

		wp_mkdir_p( dirname( $path ) );
		$this->files[] = $path;

		return $path;
	}

	public function test_a_linked_stylesheet_resolves_back_to_its_cache_file(): void {
		$path = $this->cache_path();

		$this->assertSame(
			wp_normalize_path( $path ),
			wp_normalize_path( Tailwind::linked_cache_path( $this->document( $this->cache_url( $path ) ) ) )
		);
	}

	public function test_a_document_with_a_present_cache_file_is_servable(): void {
		$path = $this->cache_path();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a temporary Tailwind cache fixture.
		file_put_contents( $path, '.a{color:red}' );

		$this->assertTrue( Tailwind::document_stylesheet_resolves( $this->document( $this->cache_url( $path ) ) ) );
	}

	// A stored document outlives the content-hashed file it links, and a cached
	// serve never reaches the compiler that would rebuild it, so a lost cache
	// file has to read as unservable rather than as an unstyled page.
	public function test_a_document_whose_cache_file_is_gone_is_not_servable(): void {
		$path = $this->cache_path();

		$this->assertFileDoesNotExist( $path );
		$this->assertFalse( Tailwind::document_stylesheet_resolves( $this->document( $this->cache_url( $path ) ) ) );
	}

	public function test_documents_without_a_resolvable_link_are_always_servable(): void {
		$inline   = '<!doctype html><html><head><style id="blockstudio-tailwind">.a{color:red}</style></head></html>';
		$cdn      = $this->document( 'https://cdn.example.test/tailwind/' . str_repeat( 'a', 32 ) . '.css' );
		$unshaped = $this->document( $this->cache_url( Tailwind::get_cache_dir() . '/theme.css' ) );
		$none     = '<!doctype html><html><head></head><body>Page</body></html>';

		foreach ( array( $inline, $cdn, $unshaped, $none ) as $document ) {
			$this->assertSame( '', Tailwind::linked_cache_path( $document ) );
			$this->assertTrue( Tailwind::document_stylesheet_resolves( $document ) );
		}
	}
}

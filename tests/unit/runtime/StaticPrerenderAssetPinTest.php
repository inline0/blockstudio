<?php

use Blockstudio\Static_Prerender_Early_Serve;
use Blockstudio\Static_Prerender_Runtime;
use Blockstudio\Tailwind;
use PHPUnit\Framework\TestCase;

class StaticPrerenderAssetPinTest extends TestCase {

	private string $path = '';

	protected function setUp(): void {
		$this->path = Tailwind::get_cache_dir() . '/' . str_repeat( 'b', 32 ) . '.css';
		wp_mkdir_p( dirname( $this->path ) );
	}

	protected function tearDown(): void {
		if ( is_file( $this->path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing a temporary Tailwind cache fixture.
			unlink( $this->path );
		}
	}

	private function document(): string {
		$content_dir = rtrim( wp_normalize_path( (string) WP_CONTENT_DIR ), '/' );
		$url         = rtrim( (string) content_url(), '/' ) . substr( wp_normalize_path( $this->path ), strlen( $content_dir ) );

		return '<!doctype html><html><head><link id="blockstudio-tailwind" rel="stylesheet" href="'
			. esc_url( $url ) . '"></head><body>Page</body></html>';
	}

	// The cached document pins a content-hashed stylesheet by URL while that
	// file keeps its own retention. Serving the document after the file is gone
	// renders the page unstyled, and the cached path never reaches the compiler
	// that would republish it, so the page stays broken until something else
	// invalidates the entry.
	public function test_a_document_with_a_missing_stylesheet_is_neither_servable_nor_cacheable(): void {
		$document = $this->document();

		$this->assertFileDoesNotExist( $this->path );
		$this->assertFalse( Static_Prerender_Runtime::document_assets_resolve( $document ) );
		$this->assertFalse( Static_Prerender_Runtime::cacheable_html( $document ) );
		$this->assertFalse( Static_Prerender_Runtime::cacheable_html( $document, 200 ) );
	}

	public function test_the_same_document_is_cacheable_once_its_stylesheet_exists(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a temporary Tailwind cache fixture.
		file_put_contents( $this->path, '.a{color:red}' );

		$document = $this->document();

		$this->assertTrue( Static_Prerender_Runtime::document_assets_resolve( $document ) );
		$this->assertTrue( Static_Prerender_Runtime::cacheable_html( $document, 200 ) );
	}

	public function test_documents_without_a_linked_stylesheet_stay_cacheable(): void {
		$this->assertTrue(
			Static_Prerender_Runtime::cacheable_html( '<!doctype html><html><body>Page</body></html>' )
		);
	}

	public function test_map_entries_carry_the_mapping_the_dropin_verifies_with(): void {
		$scope = Static_Prerender_Early_Serve::content_scope();

		$this->assertSame( rtrim( (string) content_url(), '/' ), $scope['content_url'] );
		$this->assertSame( rtrim( wp_normalize_path( (string) WP_CONTENT_DIR ), '/' ), $scope['content_dir'] );
	}

	public function test_the_dropin_hands_a_request_to_wordpress_when_the_stylesheet_is_gone(): void {
		$source = Static_Prerender_Early_Serve::dropin_source();

		$this->assertStringContainsString( "\$entry['content_url'] ?? ''", $source );
		$this->assertStringContainsString( "\$entry['content_dir'] ?? ''", $source );
		$this->assertStringContainsString( 'id="blockstudio-tailwind"', $source );
		$this->assertStringContainsString(
			'! is_file( $content_dir . substr( $href, strlen( $content_url ) ) )',
			$source
		);
	}
}

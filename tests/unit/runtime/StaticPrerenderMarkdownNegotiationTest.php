<?php
/**
 * Markdown negotiation against the static prerender layer.
 *
 * @package Blockstudio
 */

use Blockstudio\Pages;
use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Static_Prerender_Early_Serve;
use Blockstudio\Static_Prerender_Identity;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\TestCase;

/**
 * A page that answers Accept: text/markdown with its source Markdown must not
 * be answered with stored HTML by the prerender store or the drop-in.
 */
class StaticPrerenderMarkdownNegotiationTest extends TestCase {

	private string $root;

	/**
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		$this->root = trailingslashit( get_temp_dir() ) . 'blockstudio-markdown-negotiation-' . wp_generate_uuid4();
		wp_mkdir_p( $this->root . '/cache' );
		file_put_contents( $this->root . '/wp-config.php', "<?php\n" );

		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		add_filter( 'blockstudio/performance/staticPrerender/enabled', '__return_true' );
		add_filter( 'blockstudio/performance/staticPrerender/earlyServe', '__return_true' );
		Settings::reset();
		Runtime_Settings::reset();
		Static_Prerender_Identity::reset();
		Static_Prerender_Early_Serve::override_content_dir_for_testing( $this->root );
		Static_Prerender_Early_Serve::override_config_path_for_testing( $this->root . '/wp-config.php' );
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		remove_filter( 'blockstudio/performance/staticPrerender/enabled', '__return_true' );
		remove_filter( 'blockstudio/performance/staticPrerender/earlyServe', '__return_true' );
		remove_filter( 'blockstudio/pages/serve_markdown', '__return_false' );
		Settings::reset();
		Runtime_Settings::reset();
		Static_Prerender_Identity::reset();
		Static_Prerender_Early_Serve::override_content_dir_for_testing( null );
		Static_Prerender_Early_Serve::override_config_path_for_testing( null );
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root . '/runtime-cache';
	}

	public function filter_site_key(): string {
		return 'unit-markdown-negotiation';
	}

	public function test_a_markdown_accept_header_bypasses_the_prerender_store(): void {
		$request = array(
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/docs/reference/',
			'HTTP_HOST'      => 'example.com',
		);

		$this->assertFalse( Static_Prerender_Runtime::should_bypass_request( $request, array() ) );
		$this->assertFalse(
			Static_Prerender_Runtime::should_bypass_request( $request + array( 'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*;q=0.8' ), array() )
		);
		$this->assertTrue(
			Static_Prerender_Runtime::should_bypass_request( $request + array( 'HTTP_ACCEPT' => 'text/markdown' ), array() )
		);
		$this->assertTrue(
			Static_Prerender_Runtime::should_bypass_request( $request + array( 'HTTP_ACCEPT' => 'text/html;q=0.5, Text/Markdown' ), array() )
		);
	}

	public function test_the_drop_in_hands_markdown_requests_to_wordpress(): void {
		$site = Static_Prerender_Early_Serve::current_site_identity();
		$this->assertIsArray( $site );
		$key  = str_repeat( 'a', 64 );
		$html = '<!doctype html><html><body>Reference</body></html>';
		file_put_contents( $this->root . '/cache/' . $key . '.html', $html );

		$this->assertTrue(
			Static_Prerender_Early_Serve::install_artifact_entry(
				array(
					'host'      => $site['host'],
					'home_path' => $site['home_path'],
					'build_id'  => str_repeat( 'b', 32 ),
					'ttl'       => 3600,
					'routes'    => array( $site['home_path'] => $key ),
				),
				$this->root . '/cache'
			)
		);

		$this->assertSame( $html, $this->serve_through_dropin( $site['host'], $site['home_path'], 'text/html' ) );
		$this->assertSame( '', $this->serve_through_dropin( $site['host'], $site['home_path'], 'text/markdown' ) );
		$this->assertSame( '', $this->serve_through_dropin( $site['host'], $site['home_path'], 'text/plain, text/markdown;q=0.9' ) );
	}

	public function test_negotiated_markdown_responses_vary_on_accept(): void {
		$method = new ReflectionMethod( Pages::class, 'markdown_response_headers' );
		$method->setAccessible( true );

		$negotiated = $method->invoke( null, true, 'https://example.test/docs/page/', '# Markdown' );
		$this->assertSame(
			array(
				'Content-Type: text/markdown; charset=utf-8'               => true,
				'X-Robots-Tag: noindex'                                    => true,
				'X-Markdown-Tokens: 3'                                    => true,
				'Vary: Accept'                                             => false,
				'Link: <https://example.test/docs/page/>; rel="canonical"' => false,
			),
			$negotiated
		);

		$extension = $method->invoke( null, false, '', '' );
		$this->assertArrayHasKey( 'X-Markdown-Tokens: 0', $extension );
		$this->assertArrayNotHasKey( 'Vary: Accept', $extension );
		$this->assertSame( true, $extension['Content-Type: text/markdown; charset=utf-8'] );
	}

	public function test_markdown_token_estimate_uses_source_unicode_characters(): void {
		$method  = new ReflectionMethod( Pages::class, 'markdown_response_headers' );
		$headers = $method->invoke( null, false, '', str_repeat( "\u{1F600}", 5 ) );
		$this->assertArrayHasKey( 'X-Markdown-Tokens: 2', $headers );
	}

	public function test_served_documents_vary_on_accept_and_old_drop_ins_are_replaced(): void {
		$source = Static_Prerender_Early_Serve::dropin_source();
		$this->assertStringContainsString( "header( 'Vary: Accept', false );", $source );
		$this->assertStringContainsString( 'v' . Static_Prerender_Early_Serve::DROPIN_VERSION . ' ', $source );
		$this->assertGreaterThanOrEqual( 5, Static_Prerender_Early_Serve::DROPIN_VERSION );

		file_put_contents(
			Static_Prerender_Early_Serve::dropin_path(),
			"<?php\n// Blockstudio static prerender early serve v4 - generated by Blockstudio; do not edit.\n"
		);
		$site = Static_Prerender_Early_Serve::current_site_identity();
		$key  = str_repeat( 'c', 64 );
		file_put_contents( $this->root . '/cache/' . $key . '.html', '<html>Upgrade</html>' );

		$this->assertTrue(
			Static_Prerender_Early_Serve::install_artifact_entry(
				array(
					'host'      => $site['host'],
					'home_path' => $site['home_path'],
					'routes'    => array( $site['home_path'] => $key ),
				),
				$this->root . '/cache'
			)
		);
		$this->assertSame( $source, file_get_contents( Static_Prerender_Early_Serve::dropin_path() ) );
	}

	public function test_pages_with_a_markdown_source_negotiate_markdown(): void {
		$markdown = $this->root . '/reference.md';
		file_put_contents( $markdown, "# Reference\n" );

		$published = $this->page( 'publish', $markdown );
		$draft     = $this->page( 'draft', $markdown );
		$missing   = $this->page( 'publish', $this->root . '/missing.md' );
		$plain     = $this->page( 'publish', '' );

		$this->assertTrue( Pages::negotiates_markdown( $published ) );
		$this->assertFalse( Pages::negotiates_markdown( $draft ) );
		$this->assertFalse( Pages::negotiates_markdown( $missing ) );
		$this->assertFalse( Pages::negotiates_markdown( $plain ) );
		$this->assertFalse( Pages::negotiates_markdown( 0 ) );

		add_filter( 'blockstudio/pages/serve_markdown', '__return_false' );
		$this->assertFalse( Pages::negotiates_markdown( $published ) );
	}

	private function page( string $status, string $content_path ): int {
		$user = get_current_user_id();
		wp_set_current_user( 0 );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => $status,
				'post_title'  => 'Markdown negotiation ' . $status,
			)
		);
		$this->assertIsInt( $post_id );
		if ( '' !== $content_path ) {
			update_post_meta( $post_id, '_blockstudio_page_content_path', $content_path );
		}
		$this->posts[] = $post_id;
		wp_set_current_user( $user );

		return $post_id;
	}

	private function serve_through_dropin( string $host, string $request_uri, string $accept ): string {
		$code = sprintf(
			'$_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["HTTP_HOST"] = %s; $_SERVER["HTTP_ACCEPT"] = %s;'
				. ' $_SERVER["REQUEST_URI"] = %s; require %s;'
				. ' blockstudio_early_serve_static_prerender();',
			var_export( $host, true ),
			var_export( $accept, true ),
			var_export( $request_uri, true ),
			var_export( Static_Prerender_Early_Serve::dropin_path(), true )
		);

		return (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1' );
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

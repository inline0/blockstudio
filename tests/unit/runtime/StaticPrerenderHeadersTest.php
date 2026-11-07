<?php
/**
 * Static prerender response header tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Runtime_Cache;
use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Static_Prerender_Batch_Renderer;
use Blockstudio\Static_Prerender_Content_Hasher;
use Blockstudio\Static_Prerender_Early_Serve;
use Blockstudio\Static_Prerender_Headers;
use Blockstudio\Static_Prerender_Identity;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\TestCase;

/**
 * Allowlisted response headers are captured with a prerendered document and
 * replayed by both serve paths.
 */
class StaticPrerenderHeadersTest extends TestCase {

	private const DOCUMENT = '<!doctype html><html><body>Headers</body></html>';

	private const MARKDOWN_LINK = '<https://example.com/docs/page.md>; rel="alternate"; type="text/markdown"';

	private const DESCRIBED_LINK = '<https://example.com/llms.txt>; rel="describedby"';

	private string $root;

	protected function setUp(): void {
		$this->root = trailingslashit( get_temp_dir() ) . 'blockstudio-prerender-headers-' . wp_generate_uuid4();
		wp_mkdir_p( $this->root . '/content' );
		file_put_contents( $this->root . '/content/wp-config.php', "<?php\n" );

		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		add_filter( 'blockstudio/performance/staticPrerender/enabled', '__return_true' );
		add_filter( 'blockstudio/performance/staticPrerender/earlyServe', '__return_true' );
		$this->reset_settings();
		Static_Prerender_Identity::reset();
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Runtime::reset_request_cache();
		Static_Prerender_Early_Serve::override_content_dir_for_testing( $this->root . '/content' );
		Static_Prerender_Early_Serve::override_config_path_for_testing( $this->root . '/content/wp-config.php' );
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		remove_filter( 'blockstudio/performance/staticPrerender/enabled', '__return_true' );
		remove_filter( 'blockstudio/performance/staticPrerender/earlyServe', '__return_true' );
		remove_all_filters( 'blockstudio/settings/performance/staticPrerender/replayHeaders' );
		remove_all_filters( 'pre_http_request' );
		$this->reset_settings();
		Static_Prerender_Identity::reset();
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Runtime::reset_request_cache();
		Static_Prerender_Early_Serve::override_content_dir_for_testing( null );
		Static_Prerender_Early_Serve::override_config_path_for_testing( null );
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root . '/cache';
	}

	public function filter_site_key(): string {
		return 'unit-prerender-headers';
	}

	public function test_link_is_the_default_allowlist_and_unsafe_names_are_never_allowed(): void {
		$this->assertSame( array( 'link' ), Static_Prerender_Headers::allowed() );
		$this->assertSame( array( 'Link' ), Runtime_Settings::current()->value( 'staticPrerender/replayHeaders' ) );

		add_filter(
			'blockstudio/settings/performance/staticPrerender/replayHeaders',
			static fn(): array => array( 'Link', 'X-Robots-Tag', 'Set-Cookie', 'cache-control', 'Content-Length' )
		);
		$this->reset_settings();

		$this->assertSame( array( 'link', 'x-robots-tag' ), Static_Prerender_Headers::allowed() );
		foreach ( Runtime_Settings::current()->errors() as $error ) {
			$this->assertStringNotContainsString( 'replayHeaders', $error );
		}
	}

	public function test_an_empty_allowlist_replaces_the_default(): void {
		add_filter( 'blockstudio/settings/performance/staticPrerender/replayHeaders', '__return_empty_array' );
		$this->reset_settings();

		$this->assertSame( array(), Static_Prerender_Headers::allowed() );
		$this->assertSame(
			array(),
			Static_Prerender_Headers::select( array( 'Link: ' . self::MARKDOWN_LINK ) )
		);
	}

	public function test_invalid_allowlist_entries_are_reported_and_fall_back_to_the_default(): void {
		add_filter(
			'blockstudio/settings/performance/staticPrerender/replayHeaders',
			static fn(): array => array( "Link\r\nSet-Cookie: a=b" )
		);
		$this->reset_settings();

		$this->assertContains(
			'performance.staticPrerender.replayHeaders entries must be header names.',
			Runtime_Settings::current()->errors()
		);
		$this->assertSame( array( 'link' ), Static_Prerender_Headers::allowed() );
	}

	public function test_selection_keeps_allowlisted_headers_from_every_response_shape(): void {
		$lines = array(
			'Content-Type: text/html; charset=UTF-8',
			'Link: ' . self::MARKDOWN_LINK,
			'Set-Cookie: session=secret',
			'Cache-Control: no-store',
			'Content-Length: 42',
			'link: ' . self::DESCRIBED_LINK,
			'Link: ' . self::MARKDOWN_LINK,
			"Link: <https://example.com/>\r\nSet-Cookie: injected=1",
			'Not a header',
		);

		$this->assertSame(
			array(
				array( 'Link', self::MARKDOWN_LINK ),
				array( 'Link', self::DESCRIBED_LINK ),
			),
			Static_Prerender_Headers::select( $lines )
		);

		$map = new \WpOrg\Requests\Utility\CaseInsensitiveDictionary(
			array(
				'link'       => array( self::MARKDOWN_LINK, self::DESCRIBED_LINK ),
				'set-cookie' => 'session=secret',
				'x-robots'   => 'noindex',
			)
		);
		$this->assertSame(
			array(
				array( 'Link', self::MARKDOWN_LINK ),
				array( 'Link', self::DESCRIBED_LINK ),
			),
			Static_Prerender_Headers::select( $map )
		);

		$this->assertSame(
			array(
				array( 'Link', self::MARKDOWN_LINK ),
				array( 'X-Robots-Tag', 'noindex' ),
			),
			Static_Prerender_Headers::select(
				array(
					array( 'Link', self::MARKDOWN_LINK ),
					array( 'x-robots-tag', 'noindex' ),
					array( 'Set-Cookie', 'session=secret' ),
				),
				array( 'link', 'x-robots-tag', 'set-cookie' )
			)
		);
	}

	public function test_built_responses_store_headers_beside_the_document_and_replay_them(): void {
		$url = home_url( '/headers/stored/' );

		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response(
				$url,
				self::DOCUMENT,
				array(),
				array( 'post' => 'stored' ),
				null,
				null,
				array(
					'Link: ' . self::MARKDOWN_LINK,
					'Link: ' . self::DESCRIBED_LINK,
					'Set-Cookie: session=secret',
					'Cache-Control: max-age=60',
				)
			)
		);

		$document = $this->document_for( $url );
		$sidecar  = Static_Prerender_Headers::path( $document );
		$this->assertSame( substr( $document, 0, -5 ) . '.headers.json', $sidecar );
		$this->assertFileExists( $sidecar );

		$stored = json_decode( (string) file_get_contents( $sidecar ), true );
		$this->assertSame(
			array(
				'format'  => 1,
				'headers' => array(
					array( 'Link', self::MARKDOWN_LINK ),
					array( 'Link', self::DESCRIBED_LINK ),
				),
			),
			$stored
		);
		$this->assertStringNotContainsString( 'secret', (string) file_get_contents( $sidecar ) );
		$this->assertStringNotContainsString( 'max-age', (string) file_get_contents( $sidecar ) );

		$this->assertSame(
			array( 'Link: ' . self::MARKDOWN_LINK, 'Link: ' . self::DESCRIBED_LINK ),
			Static_Prerender_Headers::replay( $document )
		);
		$this->assertSame( array(), Static_Prerender_Headers::replay( $document, array( 'x-robots-tag' ) ) );
	}

	public function test_a_rerender_without_headers_removes_the_stored_headers(): void {
		$url = home_url( '/headers/removed/' );

		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response( $url, self::DOCUMENT, array(), array( 'post' => 'removed' ), null, null, array( 'Link: ' . self::MARKDOWN_LINK ) )
		);
		$document = $this->document_for( $url );
		$this->assertFileExists( Static_Prerender_Headers::path( $document ) );

		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response( $url, self::DOCUMENT, array(), array( 'post' => 'removed' ) )
		);
		$this->assertSame( $document, $this->document_for( $url ) );
		$this->assertFileDoesNotExist( Static_Prerender_Headers::path( $document ) );
	}

	public function test_documents_without_stored_headers_serve_exactly_as_before(): void {
		$url = home_url( '/headers/legacy/' );
		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response( $url, self::DOCUMENT, array(), array( 'post' => 'legacy' ) )
		);
		$document = $this->document_for( $url );

		$this->assertFileDoesNotExist( Static_Prerender_Headers::path( $document ) );
		$this->assertSame( array(), Static_Prerender_Headers::replay( $document ) );
		$this->assertSame( array(), Static_Prerender_Headers::send( $document ) );

		ob_start();
		$served = Static_Prerender_Runtime::serve_cached_response( basename( $document, '.html' ) );
		$output = (string) ob_get_clean();
		$this->assertTrue( $served );
		$this->assertSame( self::DOCUMENT, $output );

		file_put_contents( Static_Prerender_Headers::path( $document ), '{not json' );
		$this->assertSame( array(), Static_Prerender_Headers::replay( $document ) );
	}

	public function test_the_http_transport_stores_allowlisted_response_headers(): void {
		$url = home_url( '/headers/http/' );
		add_filter(
			'pre_http_request',
			static fn(): array => array(
				'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary(
					array(
						'content-type'  => 'text/html; charset=UTF-8',
						'link'          => array( self::MARKDOWN_LINK, self::DESCRIBED_LINK ),
						'set-cookie'    => 'session=secret',
						'cache-control' => 'no-store',
					)
				),
				'body'     => self::DOCUMENT,
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			)
		);

		$report = Static_Prerender_Batch_Renderer::render( array( $url ) );
		$this->assertSame( 1, $report['rendered'] );

		$this->assertSame(
			array( 'Link: ' . self::MARKDOWN_LINK, 'Link: ' . self::DESCRIBED_LINK ),
			Static_Prerender_Headers::replay( $this->document_for( $url ) )
		);
	}

	public function test_a_missing_render_report_has_no_headers(): void {
		$report = Static_Prerender_Runtime::take_render_report( Static_Prerender_Runtime::dependency_report_token() );

		$this->assertSame( array(), $report['files'] );
		$this->assertNull( $report['headers'] );
		$this->assertSame( array(), Static_Prerender_Runtime::take_dependency_report( 'invalid' ) );
	}

	public function test_artifact_promotion_carries_headers_and_the_dropin_replays_them(): void {
		$url = home_url( '/headers/early/' );
		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response(
				$url,
				self::DOCUMENT,
				array(),
				array( 'post' => 'early' ),
				null,
				null,
				array( 'Link: ' . self::MARKDOWN_LINK, 'Set-Cookie: session=secret' )
			)
		);
		$document = $this->document_for( $url );
		$key      = basename( $document, '.html' );
		$entry    = Static_Prerender_Runtime::artifact_entry( array( $url ) );

		$this->assertTrue(
			Static_Prerender_Early_Serve::install_artifact_entry( $entry, Static_Prerender_Runtime::cache_root_path() )
		);

		$artifact = rtrim( wp_normalize_path( Runtime_Cache::directory( 'static-prerender-artifact' ) ), '/' ) . '/' . $key . '.html';
		$this->assertFileExists( Static_Prerender_Headers::path( $artifact ) );
		$this->assertSame(
			file_get_contents( Static_Prerender_Headers::path( $document ) ),
			file_get_contents( Static_Prerender_Headers::path( $artifact ) )
		);

		$site    = Static_Prerender_Early_Serve::current_site_identity();
		$entries = Static_Prerender_Early_Serve::installed_map_entries();
		$this->assertSame( array( 'link' ), $entries[ $site['key'] ]['replay_headers'] );

		$this->assertSame( array( 'Link: ' . self::MARKDOWN_LINK ), $this->dropin_headers( $site['key'], $artifact ) );
		$this->assertSame( self::DOCUMENT, $this->serve_through_dropin( $site['host'], (string) wp_parse_url( $url, PHP_URL_PATH ) ) );

		$this->assertTrue(
			Static_Prerender_Runtime::persist_built_response( $url, self::DOCUMENT, array(), array( 'post' => 'early' ) )
		);
		$this->assertTrue(
			Static_Prerender_Early_Serve::install_artifact_entry( $entry, Static_Prerender_Runtime::cache_root_path() )
		);
		$this->assertSame(
			array(
				'promoted' => 0,
				'replaced' => 0,
				'skipped'  => 1,
				'kept'     => 0,
			),
			Static_Prerender_Early_Serve::last_promotion()
		);
		$this->assertFileDoesNotExist( Static_Prerender_Headers::path( $artifact ) );
		$this->assertSame( array(), $this->dropin_headers( $site['key'], $artifact ) );
	}

	public function test_the_dropin_ignores_headers_its_entry_does_not_allow(): void {
		$file = $this->root . '/content/' . str_repeat( 'a', 64 ) . '.html';
		file_put_contents( $file, self::DOCUMENT );
		file_put_contents(
			Static_Prerender_Headers::path( $file ),
			(string) wp_json_encode(
				array(
					'format'  => 1,
					'headers' => array(
						array( 'Link', self::MARKDOWN_LINK ),
						array( 'Set-Cookie', 'session=secret' ),
						array( 'X-Robots-Tag', 'noindex' ),
						array( 'Link', "<https://example.com/>\r\nSet-Cookie: injected=1" ),
					),
				)
			)
		);

		file_put_contents( Static_Prerender_Early_Serve::dropin_path(), Static_Prerender_Early_Serve::dropin_source() );

		$this->assertSame(
			array( 'Link: ' . self::MARKDOWN_LINK ),
			$this->dropin_headers_for_entry( array( 'replay_headers' => array( 'link', 'set-cookie' ) ), $file )
		);
		$this->assertSame( array(), $this->dropin_headers_for_entry( array(), $file ) );
	}

	public function test_cleanup_removes_headers_with_their_documents(): void {
		$alpha = home_url( '/headers/alpha/' );
		$beta  = home_url( '/headers/beta/' );
		foreach ( array( $alpha, $beta ) as $url ) {
			$this->assertTrue(
				Static_Prerender_Runtime::persist_built_response( $url, self::DOCUMENT, array(), array( 'post' => $url ), null, null, array( 'Link: ' . self::MARKDOWN_LINK ) )
			);
		}
		$alpha_document = $this->document_for( $alpha );
		$beta_document  = $this->document_for( $beta );
		$orphan         = Static_Prerender_Runtime::cache_root_path() . '/' . str_repeat( 'e', 64 ) . Static_Prerender_Headers::SUFFIX;
		file_put_contents( $orphan, '{}' );

		Static_Prerender_Runtime::garbage_collect( array( $alpha ) );

		$this->assertFileExists( Static_Prerender_Headers::path( $alpha_document ) );
		$this->assertFileDoesNotExist( $beta_document );
		$this->assertFileDoesNotExist( Static_Prerender_Headers::path( $beta_document ) );
		$this->assertFileDoesNotExist( $orphan );
	}

	public function test_retention_pruning_never_counts_or_strands_header_files(): void {
		$directory = Runtime_Cache::directory( 'static-prerender' );
		wp_mkdir_p( $directory );
		$documents = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$document = $directory . '/' . hash( 'sha256', 'prune-' . $i ) . '.html';
			file_put_contents( $document, self::DOCUMENT );
			file_put_contents( Static_Prerender_Headers::path( $document ), '{}' );
			touch( $document, time() - ( 3 - $i ) * 60 );
			touch( Static_Prerender_Headers::path( $document ), time() );
			$documents[] = $document;
		}

		$cap = static fn(): int => 2;
		add_filter( 'blockstudio/cache/max_files_per_scope', $cap );
		Runtime_Cache::prune( 'static-prerender' );
		remove_filter( 'blockstudio/cache/max_files_per_scope', $cap );

		$this->assertFileDoesNotExist( $documents[0] );
		$this->assertFileDoesNotExist( Static_Prerender_Headers::path( $documents[0] ) );
		foreach ( array( $documents[1], $documents[2] ) as $document ) {
			$this->assertFileExists( $document );
			$this->assertFileExists( Static_Prerender_Headers::path( $document ) );
		}
	}

	private function document_for( string $url ): string {
		$routes = Static_Prerender_Runtime::route_map( array( $url ) );
		$path   = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$this->assertArrayHasKey( $path, $routes );

		return Static_Prerender_Runtime::cache_root_path() . '/' . $routes[ $path ] . '.html';
	}

	/**
	 * @return string[] Header lines the installed drop-in would send.
	 */
	private function dropin_headers( string $entry_key, string $file ): array {
		$code = sprintf(
			'require %s; $map = include %s; echo json_encode( blockstudio_early_serve_static_prerender_headers( $map["entries"][%s], %s ) );',
			var_export( Static_Prerender_Early_Serve::dropin_path(), true ),
			var_export( Static_Prerender_Early_Serve::map_path(), true ),
			var_export( $entry_key, true ),
			var_export( $file, true )
		);

		return $this->run_php( $code );
	}

	/**
	 * @param array<string,mixed> $entry Map entry.
	 *
	 * @return string[] Header lines the drop-in would send.
	 */
	private function dropin_headers_for_entry( array $entry, string $file ): array {
		$code = sprintf(
			'require %s; echo json_encode( blockstudio_early_serve_static_prerender_headers( %s, %s ) );',
			var_export( Static_Prerender_Early_Serve::dropin_path(), true ),
			var_export( $entry, true ),
			var_export( $file, true )
		);

		return $this->run_php( $code );
	}

	/**
	 * @return string[] Decoded output lines.
	 */
	private function run_php( string $code ): array {
		$output  = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1' );
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, $output );

		return $decoded;
	}

	private function serve_through_dropin( string $host, string $request_uri ): string {
		$code = sprintf(
			'$_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["HTTP_HOST"] = %s;'
				. ' $_SERVER["REQUEST_URI"] = %s; require %s;'
				. ' blockstudio_early_serve_static_prerender();',
			var_export( $host, true ),
			var_export( $request_uri, true ),
			var_export( Static_Prerender_Early_Serve::dropin_path(), true )
		);

		return (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1' );
	}

	private function reset_settings(): void {
		Settings::reset();
		Runtime_Settings::reset();
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

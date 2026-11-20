<?php
/**
 * Render dependencies of graph builds rendered over HTTP.
 *
 * @package Blockstudio
 */

use Blockstudio\Assets;
use Blockstudio\Block;
use Blockstudio\Page_Renderers;
use Blockstudio\Runtime_Cache;
use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Static_Prerender_Batch_Renderer;
use Blockstudio\Static_Prerender_Content_Hasher;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\TestCase;

/**
 * The batch renderer fetches each route over HTTP, so the dependencies a page
 * reads are only known inside that warm request. Without them build_plan()
 * could not see a page whose source changed.
 */
class StaticPrerenderDependencyGraphTest extends TestCase {

	private const DEPENDENCY_HEADER = 'X-Blockstudio-Static-Prerender-Dependencies';

	private string $root;

	private int $page_id = 0;

	private int $other_id = 0;

	/**
	 * Headers each simulated warm request received.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $requests = array();

	private bool $report = true;

	/**
	 * Component page the simulated request renders through its post content.
	 *
	 * @var int
	 */
	private int $component_id = 0;

	protected function setUp(): void {
		$this->root = trailingslashit( get_temp_dir() ) . 'blockstudio-prerender-dependencies-' . wp_generate_uuid4();
		wp_mkdir_p( $this->root . '/fixtures/page' );
		wp_mkdir_p( $this->root . '/fixtures/other' );
		file_put_contents( $this->root . '/fixtures/page/index.php', 'page v1' );
		file_put_contents( $this->root . '/fixtures/other/index.php', 'other v1' );
		file_put_contents( $this->root . '/fixtures/layout.php', 'layout v1' );
		file_put_contents( $this->root . '/fixtures/data.json', '{"rows":1}' );
		file_put_contents( $this->root . '/fixtures/page.php', 'template v1' );

		add_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		add_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		add_filter( 'blockstudio/settings/performance/staticPrerender/invalidate', array( $this, 'filter_graph_mode' ) );
		add_filter( 'blockstudio/render/dependencies', array( $this, 'filter_render_dependencies' ), 10, 2 );
		add_filter( 'pre_http_request', array( $this, 'simulate_warm_request' ), 10, 3 );
		Settings::reset();
		Runtime_Settings::reset();
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Runtime::reset_request_cache();

		$this->page_id  = $this->page( 'dependency-graph-page', $this->root . '/fixtures/page/index.php' );
		$this->other_id = $this->page( 'dependency-graph-other', $this->root . '/fixtures/other/index.php' );
		update_post_meta( $this->page_id, '_blockstudio_page_layout', $this->root . '/fixtures/layout.php' );
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/cache/dir', array( $this, 'filter_cache_root' ) );
		remove_filter( 'blockstudio/cache/site_key', array( $this, 'filter_site_key' ) );
		remove_filter( 'blockstudio/settings/performance/staticPrerender/invalidate', array( $this, 'filter_graph_mode' ) );
		remove_filter( 'blockstudio/render/dependencies', array( $this, 'filter_render_dependencies' ), 10 );
		remove_filter( 'pre_http_request', array( $this, 'simulate_warm_request' ), 10 );
		wp_delete_post( $this->page_id, true );
		wp_delete_post( $this->other_id, true );
		Settings::reset();
		Runtime_Settings::reset();
		Static_Prerender_Content_Hasher::reset();
		Static_Prerender_Runtime::reset_request_cache();
		$this->remove_directory( $this->root );
	}

	public function filter_cache_root(): string {
		return $this->root . '/cache';
	}

	public function filter_site_key(): string {
		return 'unit-dependencies';
	}

	public function filter_graph_mode(): string {
		return 'graph';
	}

	/**
	 * A data file loaded by the block's template, reported the way an
	 * integration reports indirectly loaded files.
	 *
	 * @param string[] $paths Dependency paths.
	 * @param string   $name  Block name.
	 *
	 * @return string[]
	 */
	public function filter_render_dependencies( array $paths, string $name ): array {
		if ( 'blockstudio/type-text' === $name && get_queried_object_id() === $this->page_id ) {
			$paths[] = $this->root . '/fixtures/data.json';
		}

		return $paths;
	}

	/**
	 * Answer the batch renderer's loopback request the way a separate PHP
	 * process serving the route would.
	 *
	 * @param mixed               $pre  Short-circuit value.
	 * @param array<string,mixed> $args Request arguments.
	 * @param string              $url  Request URL.
	 *
	 * @return mixed
	 */
	public function simulate_warm_request( $pre, array $args, string $url ) {
		$headers = is_array( $args['headers'] ?? null ) ? $args['headers'] : array();
		if ( ! isset( $headers[ Static_Prerender_Runtime::WARM_HEADER ] ) ) {
			return $pre;
		}
		$this->requests[] = $headers;

		$rendering = new ReflectionProperty( Static_Prerender_Batch_Renderer::class, 'rendering' );
		$rendering->setAccessible( true );
		$server    = $_SERVER;
		$get       = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query     = $GLOBALS['wp_query'];
		$the_query = $GLOBALS['wp_the_query'];
		$user      = get_current_user_id();
		$screen    = $GLOBALS['current_screen'] ?? null;
		$level     = ob_get_level();
		$port      = wp_parse_url( $url, PHP_URL_PORT );

		try {
			$rendering->setValue( null, false );
			wp_set_current_user( 0 );
			// A fresh front-end request: earlier tests can leave an admin screen or
			// an editor render mode behind.
			unset( $GLOBALS['current_screen'] );
			$_GET = array();
			$_SERVER = array(
				'REQUEST_METHOD'                           => 'GET',
				'REQUEST_URI'                              => (string) wp_parse_url( $url, PHP_URL_PATH ),
				'HTTP_HOST'                                => wp_parse_url( $url, PHP_URL_HOST ) . ( $port ? ':' . $port : '' ),
				'HTTP_X_BLOCKSTUDIO_STATIC_PRERENDER_WARM' => '1',
			);
			if ( $this->report && isset( $headers[ self::DEPENDENCY_HEADER ] ) ) {
				$_SERVER['HTTP_X_BLOCKSTUDIO_STATIC_PRERENDER_DEPENDENCIES'] = $headers[ self::DEPENDENCY_HEADER ];
			}
			$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => url_to_postid( $url ) ) );
			$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
			Static_Prerender_Runtime::reset_request_cache();

			ob_start();
			Static_Prerender_Runtime::maybe_serve_or_buffer();
			apply_filters( 'template_include', $this->root . '/fixtures/page.php' );
			if ( $this->component_id > 0 && url_to_postid( $url ) === $this->component_id ) {
				Assets::init()->maybe_buffer_output();
				$GLOBALS['post'] = get_post( $this->component_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				echo '<!doctype html><html><head></head><body>';
				echo do_blocks( (string) get_post_field( 'post_content', $this->component_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</body></html>';
			} else {
				echo '<!doctype html><html><body>';
				echo Block::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'blockstudio' => array(
							'name'       => 'blockstudio/type-text',
							'attributes' => array( 'text' => 'Graph' ),
						),
					),
					'',
					'',
					''
				);
				echo '</body></html>';
			}
			while ( ob_get_level() > $level + 1 ) {
				ob_end_flush();
			}
			$html = (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			$rendering->setValue( null, true );
			$_SERVER                 = $server;
			$_GET                    = $get;
			$GLOBALS['wp_query']     = $query;
			$GLOBALS['wp_the_query'] = $the_query;
			wp_set_current_user( $user );
			if ( null !== $screen ) {
				$GLOBALS['current_screen'] = $screen;
			}
		}

		return array(
			'headers'  => array(),
			'body'     => $html,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function test_an_http_graph_build_records_page_sources_block_templates_and_filtered_files(): void {
		$url    = get_permalink( $this->page_id );
		$other  = get_permalink( $this->other_id );
		$plan   = Static_Prerender_Runtime::build_plan( array( $url, $other ) );
		$report = Static_Prerender_Batch_Renderer::render( $plan['affectedUrls'], array( 'expectedStateRoot' => $plan['stateRoot'] ) );

		$this->assertSame( Runtime_Cache::directory( 'static-prerender-state' ), $plan['stateRoot'] );
		$this->assertSame( array( $plan['stateRoot'] ), $report['stateRoots'] );
		$this->assertSame( 2, $report['rendered'], wp_json_encode( $report ) );
		$this->assertCount( 2, $this->requests );
		$this->assertArrayHasKey( self::DEPENDENCY_HEADER, $this->requests[0] );
		foreach ( $report['results'] as $item ) {
			$this->assertGreaterThan( 0, $item['dependencies'] ?? 0 );
		}

		$paths = $this->dependency_paths( $url );
		$this->assertContains( wp_normalize_path( $this->root . '/fixtures/page/index.php' ), $paths );
		$this->assertContains( wp_normalize_path( $this->root . '/fixtures/layout.php' ), $paths );
		$this->assertContains( wp_normalize_path( $this->root . '/fixtures/page.php' ), $paths );
		$this->assertContains( wp_normalize_path( $this->root . '/fixtures/data.json' ), $paths, 'A dependency added through the filter must be recorded.' );
		$this->assertNotEmpty(
			array_filter( $paths, static fn( string $path ): bool => str_ends_with( $path, '/blockstudio/types/text/index.twig' ) ),
			'The block template must be recorded.'
		);
		$this->assertNotContains( wp_normalize_path( $this->root . '/fixtures/data.json' ), $this->dependency_paths( $other ) );
		$this->assertSame( array(), $this->dependency_reports(), 'Reports are consumed by the batch renderer.' );

		Static_Prerender_Runtime::reset_request_cache();
		$targets   = array_keys( $plan['live'] );
		$remaining = Static_Prerender_Runtime::build_plan( $targets, expected_state_root: $plan['stateRoot'] );
		$this->assertSame( 0, $remaining['affected'] );
		$entry = Static_Prerender_Runtime::artifact_entry( $targets, expected_state_root: $plan['stateRoot'] );
		$this->assertSame( $plan['stateRoot'], $entry['stateRoot'] );
		$this->assertCount( 2, $entry['routes'] );
	}

	public function test_batch_refuses_a_wordpress_version_change_after_planning(): void {
		$url  = get_permalink( $this->page_id );
		$plan = Static_Prerender_Runtime::build_plan( array( $url ) );
		Static_Prerender_Runtime::reset_request_cache();
		$update = static fn( array $identity ): array => array_replace( $identity, array( 'wordpress' => 'updated-core' ) );
		add_filter( 'blockstudio/runtime/identity', $update );
		try {
			$current = Runtime_Cache::directory( 'static-prerender-state' );
			$this->assertNotSame( $plan['stateRoot'], $current );
			$report = Static_Prerender_Batch_Renderer::render( $plan['affectedUrls'], array( 'expectedStateRoot' => $plan['stateRoot'] ) );
			$this->assertSame( 'state_root_mismatch', $report['error'] );
			$this->assertSame( $plan['stateRoot'], $report['expectedStateRoot'] );
			$this->assertSame( array( $current ), $report['stateRoots'] );
			$this->assertSame( 0, $report['processed'] );
			$this->assertSame( 1, $report['failed'] );
			$this->assertStringContainsString( $current, $report['results'][0]['error'] );
			$this->assertStringContainsString( $plan['stateRoot'], $report['results'][0]['error'] );
			$this->assertSame( array(), $this->requests );
			$this->assertDirectoryDoesNotExist( $current );
			$this->assertDirectoryDoesNotExist( Runtime_Cache::directory( 'static-prerender-artifact' ) );
		} finally {
			remove_filter( 'blockstudio/runtime/identity', $update );
		}
	}

	public function test_assembly_refuses_a_wordpress_version_change_after_rendering(): void {
		$url      = get_permalink( $this->page_id );
		$plan     = Static_Prerender_Runtime::build_plan( array( $url ) );
		$expected = $plan['stateRoot'];
		$report   = Static_Prerender_Batch_Renderer::render( $plan['affectedUrls'], array( 'expectedStateRoot' => $expected ) );
		$this->assertSame( 1, $report['rendered'] );
		Static_Prerender_Runtime::reset_request_cache();
		$update = static fn( array $identity ): array => array_replace( $identity, array( 'wordpress' => 'updated-core' ) );
		add_filter( 'blockstudio/runtime/identity', $update );
		try {
			$current = Runtime_Cache::directory( 'static-prerender-state' );
			$this->assertNotSame( $expected, $current );
			foreach ( array(
				Static_Prerender_Runtime::build_plan( array( $url ), expected_state_root: $expected ),
				Static_Prerender_Runtime::garbage_collect( array( $url ), $expected ),
				Static_Prerender_Runtime::artifact_entry( array( $url ), expected_state_root: $expected ),
			) as $result ) {
				$this->assertSame( 'state_root_mismatch', $result['error'] );
				$this->assertSame( $expected, $result['expectedStateRoot'] );
				$this->assertSame( $current, $result['stateRoot'] );
				$this->assertStringContainsString( $expected, $result['message'] );
				$this->assertStringContainsString( $current, $result['message'] );
				$this->assertArrayNotHasKey( 'routes', $result );
				$this->assertArrayNotHasKey( 'reasons', $result );
			}
			$this->assertDirectoryDoesNotExist( $current );
		} finally {
			remove_filter( 'blockstudio/runtime/identity', $update );
		}

		$entry = Static_Prerender_Runtime::artifact_entry( array( $url ), expected_state_root: $expected );
		$this->assertSame( $expected, $entry['stateRoot'] );
		$this->assertCount( 1, $entry['routes'] );
	}

	public function test_unguarded_batches_keep_the_cache_directory_selected_by_existing_hooks(): void {
		$default_state = Runtime_Cache::directory( 'static-prerender-state' );
		$render_state  = '';
		$artifacts     = '';
		$cache_dir     = static fn( string $path ): string => Static_Prerender_Batch_Renderer::is_rendering() ? $path . '-batch' : $path;
		$render        = static function () use ( &$render_state, &$artifacts ): array {
			$render_state = Runtime_Cache::directory( 'static-prerender-state' );
			$artifacts    = Runtime_Cache::directory( 'static-prerender-artifact' );

			return array( 'html' => '<!doctype html><html><body>Ready</body></html>' );
		};
		add_filter( 'blockstudio/cache/dir', $cache_dir, 20 );
		add_filter( 'blockstudio/static_prerender/render_internal', $render );
		try {
			$report = Static_Prerender_Batch_Renderer::render(
				array( get_permalink( $this->page_id ) ),
				array(
					'transport' => 'internal',
					'fallback'  => 'none',
				)
			);
			$this->assertSame( 1, $report['rendered'] );
			$this->assertNotSame( $default_state, $render_state );
			$this->assertCount( 1, glob( $render_state . '/url-index/*.json' ) );
			$this->assertCount( 1, glob( $artifacts . '/*.html' ) );
			$this->assertSame( array(), glob( $default_state . '/url-index/*.json' ) );
			$this->assertArrayNotHasKey( 'stateRoots', $report );
		} finally {
			remove_filter( 'blockstudio/static_prerender/render_internal', $render );
			remove_filter( 'blockstudio/cache/dir', $cache_dir, 20 );
		}
	}

	public function test_a_render_callback_cannot_move_an_active_build_to_another_state_root(): void {
		$url    = get_permalink( $this->page_id );
		$plan   = Static_Prerender_Runtime::build_plan( array( $url ) );
		$update = static fn( array $identity ): array => array_replace( $identity, array( 'wordpress' => 'updated-core' ) );
		$render = static function () use ( $update ): array {
			add_filter( 'blockstudio/runtime/identity', $update );
			Static_Prerender_Runtime::reset_request_cache();

			return array( 'html' => '<!doctype html><html><body>Ready</body></html>' );
		};
		add_filter( 'blockstudio/static_prerender/render_internal', $render );
		try {
			$report = Static_Prerender_Batch_Renderer::render(
				$plan['affectedUrls'],
				array(
					'expectedStateRoot' => $plan['stateRoot'],
					'transport'         => 'internal',
					'fallback'          => 'none',
				)
			);
			$this->assertSame( 1, $report['rendered'] );
			$this->assertSame( array( $plan['stateRoot'] ), $report['stateRoots'] );
			$this->assertNotSame( $plan['stateRoot'], Runtime_Cache::directory( 'static-prerender-state' ) );
			$this->assertDirectoryDoesNotExist( Runtime_Cache::directory( 'static-prerender-state' ) );
			$this->assertDirectoryDoesNotExist( Runtime_Cache::directory( 'static-prerender-artifact' ) );
		} finally {
			remove_filter( 'blockstudio/static_prerender/render_internal', $render );
			remove_filter( 'blockstudio/runtime/identity', $update );
		}

		$entry = Static_Prerender_Runtime::artifact_entry( array( $url ), expected_state_root: $plan['stateRoot'] );
		$this->assertCount( 1, $entry['routes'] );
	}

	public function test_build_plan_marks_exactly_the_route_whose_page_source_changed(): void {
		$url   = get_permalink( $this->page_id );
		$other = get_permalink( $this->other_id );
		Static_Prerender_Batch_Renderer::render( array( $url, $other ) );

		Static_Prerender_Content_Hasher::reset();
		$this->assertSame( 0, Static_Prerender_Runtime::build_plan( array( $url, $other ) )['affected'] );

		file_put_contents( $this->root . '/fixtures/page/index.php', 'page v2' );
		Static_Prerender_Content_Hasher::reset();
		$plan = Static_Prerender_Runtime::build_plan( array( $url, $other ) );

		$this->assertSame( array( $url ), $plan['affectedUrls'] );
		$this->assertSame( array( $other ), $plan['unchangedUrls'] );
		$this->assertContains( 'page', $plan['reasons'][ $url ] );

		file_put_contents( $this->root . '/fixtures/page/index.php', 'page v1' );
		file_put_contents( $this->root . '/fixtures/data.json', '{"rows":2}' );
		Static_Prerender_Content_Hasher::reset();

		$this->assertSame( array( $url ), Static_Prerender_Runtime::build_plan( array( $url, $other ) )['affectedUrls'] );
	}

	public function test_a_warm_request_without_a_signed_token_reports_nothing(): void {
		$url = get_permalink( $this->page_id );
		add_filter( 'http_request_args', array( $this, 'forge_dependency_token' ) );

		try {
			$report = Static_Prerender_Batch_Renderer::render( array( $url ) );
		} finally {
			remove_filter( 'http_request_args', array( $this, 'forge_dependency_token' ) );
		}

		$this->assertSame( 1, $report['rendered'] );
		$this->assertSame( 0, $report['results'][0]['dependencies'] );
		$this->assertSame( array(), $this->dependency_paths( $url ) );
		$this->assertSame( array(), $this->dependency_reports() );
	}

	public function test_a_route_rendered_without_a_report_keeps_the_previous_contract(): void {
		$url          = get_permalink( $this->page_id );
		$this->report = false;

		$report = Static_Prerender_Batch_Renderer::render( array( $url ) );

		$this->assertSame( 1, $report['rendered'] );
		$this->assertSame( 0, $report['results'][0]['dependencies'] );
		$this->assertSame( array(), $this->dependency_paths( $url ) );
	}

	public function test_a_component_page_records_its_entry_data_and_assets_in_the_stored_document(): void {
		$component = $this->root . '/fixtures/component';
		$asset     = WP_CONTENT_DIR . '/blockstudio-prerender-component-' . wp_generate_uuid4();
		wp_mkdir_p( $component );
		wp_mkdir_p( $asset );
		file_put_contents( $component . '/page.tsx', 'export default () => null' );
		file_put_contents( $component . '/data.php', '<?php return array();' );
		file_put_contents( $component . '/props.json', '{}' );
		file_put_contents( $asset . '/page.js', 'console.log("component");' );

		Page_Renderers::reset();
		Page_Renderers::register(
			'unit',
			array(
				'extensions' => array( 'tsx' ),
				'render'     => static fn( array $request ): array => array(
					'html'         => '<main data-component="' . esc_attr( (string) $request['post_id'] ) . '">Component</main>',
					'dependencies' => array( dirname( $request['entry'] ) . '/props.json' ),
					'assets'       => array( array( 'path' => $asset . '/page.js' ) ),
				),
			)
		);

		$this->component_id = (int) wp_insert_post(
			array(
				'post_title'   => 'dependency-graph-component',
				'post_name'    => 'dependency-graph-component',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- wp:blockstudio/page /-->',
			)
		);
		update_post_meta(
			$this->component_id,
			'_blockstudio_page_render',
			array(
				'renderer' => 'unit',
				'entry'    => $component . '/page.tsx',
				'data'     => $component . '/data.php',
			)
		);

		try {
			$url    = get_permalink( $this->component_id );
			$report = Static_Prerender_Batch_Renderer::render( array( $url ) );
			$paths  = $this->dependency_paths( $url );
			$html   = $this->stored_document( $url );
		} finally {
			wp_delete_post( $this->component_id, true );
			$this->component_id = 0;
			Page_Renderers::reset();
			Assets::reset_request_state();
			$this->remove_directory( $asset );
		}

		$this->assertSame( 1, $report['rendered'], wp_json_encode( $report ) );
		$this->assertContains( wp_normalize_path( $component . '/page.tsx' ), $paths );
		$this->assertContains( wp_normalize_path( $component . '/data.php' ), $paths );
		$this->assertContains( wp_normalize_path( $component . '/props.json' ), $paths );
		$this->assertStringContainsString( '<main data-component="', $html );
		$this->assertStringNotContainsString( '<!--blockstudio-assets/', $html );
		$this->assertMatchesRegularExpression( "#<script type='module'[^>]*id='blockstudio-owner-page-[^']*-page-js'[^>]*src='[^']*/page[^']*\.js\?ver=\d+'></script></body>#", $html );
	}

	/**
	 * @param array<string,mixed> $args Request arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function forge_dependency_token( array $args ): array {
		if ( isset( $args['headers'][ self::DEPENDENCY_HEADER ] ) ) {
			$args['headers'][ self::DEPENDENCY_HEADER ] = str_repeat( 'a', 32 ) . '.' . str_repeat( 'b', 32 );
		}

		return $args;
	}

	private function page( string $slug, string $source ): int {
		$post_id = (int) wp_insert_post(
			array(
				'post_title'   => $slug,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>Page</p><!-- /wp:paragraph -->',
			)
		);
		update_post_meta( $post_id, '_blockstudio_page_template_path', $source );

		return $post_id;
	}

	/**
	 * @return string[] Recorded dependency paths of a route.
	 */
	private function dependency_paths( string $url ): array {
		$method = new ReflectionMethod( Static_Prerender_Runtime::class, 'index_record_for_url' );
		$method->setAccessible( true );
		$record = $method->invoke( null, $url );
		$paths  = is_array( $record['dependencyPaths'] ?? null ) ? $record['dependencyPaths'] : array();

		return array_values( array_map( 'wp_normalize_path', $paths ) );
	}

	/**
	 * @return string The stored HTML document of a route.
	 */
	private function stored_document( string $url ): string {
		$record = ( new ReflectionMethod( Static_Prerender_Runtime::class, 'index_record_for_url' ) )->invoke( null, $url );
		$key    = (string) ( $record['cacheKey'] ?? '' );
		$file   = ( new ReflectionMethod( Static_Prerender_Runtime::class, 'artifact_file' ) )->invoke( null, $key );
		if ( ! is_file( $file ) ) {
			$file = ( new ReflectionMethod( Static_Prerender_Runtime::class, 'cache_file' ) )->invoke( null, $key );
		}

		return is_file( $file ) ? (string) file_get_contents( $file ) : '';
	}

	/**
	 * @return string[] Unconsumed dependency reports.
	 */
	private function dependency_reports(): array {
		$reports = glob( dirname( Runtime_Cache::directory( 'static-prerender-state' ), 2 ) . '/.static-prerender-dependencies/*' );

		return is_array( $reports ) ? $reports : array();
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

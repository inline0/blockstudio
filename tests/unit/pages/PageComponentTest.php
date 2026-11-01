<?php
/**
 * Component page block tests.
 *
 * @package Blockstudio
 */

// phpcs:disable WordPress.WP.AlternativeFunctions

use Blockstudio\Page_Component;
use Blockstudio\Page_Discovery;
use Blockstudio\Page_Registry;
use Blockstudio\Page_Renderers;
use Blockstudio\Page_Sync;
use Blockstudio\Pages;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class PageComponentTest extends TestCase {

	private string $pages_path;

	/**
	 * Requests the unit renderer received.
	 *
	 * @var array<int, array>
	 */
	private array $requests = array();

	/**
	 * Builds the unit renderer result.
	 *
	 * @var callable
	 */
	private $result;

	private int $shortcodes = 0;

	protected function setUp(): void {
		$this->pages_path = wp_normalize_path( get_temp_dir() . 'blockstudio-component-' . wp_generate_uuid4() );
		wp_mkdir_p( $this->pages_path );
		$this->requests   = array();
		$this->shortcodes = 0;
		$this->result     = static fn( array $request ): array => array(
			'html'         => '<main class="component">' . $request['slot'] . '</main>',
			'dependencies' => array( dirname( $request['entry'] ) . '/component.css' ),
		);

		Pages::reset();
		Page_Renderers::reset();
		Page_Renderers::register(
			'unit',
			array(
				'extensions' => array( 'tsx' ),
				'layouts'    => array( 'layout.tsx' ),
				'render'     => function ( array $request ): array {
					$this->requests[] = $request;

					return ( $this->result )( $request );
				},
			)
		);
		add_shortcode( 'bs_component_count', array( $this, 'count_shortcode' ) );
	}

	protected function tearDown(): void {
		remove_shortcode( 'bs_component_count' );

		foreach ( ( new Page_Sync() )->managed_posts() as $post ) {
			if ( str_starts_with( (string) get_post_meta( $post->ID, '_blockstudio_page_key', true ), 'component-probe' ) ) {
				wp_delete_post( $post->ID, true );
			}
		}

		$this->remove_directory( $this->pages_path );
		Pages::reset();
		Page_Renderers::reset();
		Static_Prerender_Runtime::reset_request_cache();
		remove_filter( 'template_include', array( Static_Prerender_Runtime::class, 'record_template_dependency' ), PHP_INT_MAX );
		wp_reset_postdata();
	}

	public function count_shortcode(): string {
		++$this->shortcodes;

		return '<span class="expanded">' . $this->shortcodes . '</span>';
	}

	public function test_renders_with_post_context_slot_and_request_context(): void {
		$post_id = $this->sync_component();
		$html    = $this->render_post_content( $post_id, 'do_blocks' );

		$this->assertCount( 1, $this->requests );
		$request = $this->requests[0];
		$this->assertSame( 'page', $request['kind'] );
		$this->assertSame( 'unit', $request['renderer'] );
		$this->assertSame( $this->pages_path . '/component-probe/page.tsx', $request['entry'] );
		$this->assertSame( $this->pages_path . '/component-probe/data.php', $request['data'] );
		$this->assertSame( $post_id, $request['post_id'] );
		$this->assertSame( 'frontend', $request['context'] );
		$this->assertSame( 'component-probe', $request['page']['key'] );
		$this->assertStringContainsString( 'Slot [bs_component_count]', $request['slot'] );
		$this->assertStringStartsWith( '<main class="component">', $html );
		$this->assertSame( 0, $this->shortcodes );
	}

	public function test_records_render_dependencies_under_an_active_report(): void {
		$post_id = $this->sync_component();
		$begin   = new ReflectionMethod( Static_Prerender_Runtime::class, 'begin_dependency_report' );
		$begin->invoke( null, array( 'HTTP_X_BLOCKSTUDIO_STATIC_PRERENDER_DEPENDENCIES' => Static_Prerender_Runtime::dependency_report_token() ) );

		$this->render_post_content( $post_id, 'do_blocks' );

		$dependencies = array_keys( ( new ReflectionProperty( Static_Prerender_Runtime::class, 'dependencies' ) )->getValue() );
		$directory    = $this->pages_path . '/component-probe';
		$this->assertContains( $directory . '/page.tsx', $dependencies );
		$this->assertContains( $directory . '/data.php', $dependencies );
		$this->assertContains( $directory . '/component.css', $dependencies );
	}

	public function test_records_nothing_without_a_report(): void {
		$post_id = $this->sync_component();

		$this->render_post_content( $post_id, 'do_blocks' );

		$this->assertSame( array(), ( new ReflectionProperty( Static_Prerender_Runtime::class, 'dependencies' ) )->getValue() );
	}

	public function test_renderer_failures_throw_on_the_frontend(): void {
		$post_id      = $this->sync_component();
		$this->result = static function (): array {
			throw new RuntimeException( 'Component exploded' );
		};

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Component exploded' );
		$this->render_post_content( $post_id, 'do_blocks' );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_rest_previews_report_failures_as_a_notice(): void {
		$post_id      = $this->sync_component();
		$this->result = static function (): array {
			throw new RuntimeException( 'Preview <failed>' );
		};
		define( 'REST_REQUEST', true );

		$html = $this->render_post_content( $post_id, 'do_blocks' );

		$this->assertSame( 'rest', Page_Component::request_context() );
		$this->assertSame( '<div class="blockstudio-page-error" role="alert"><p>Preview &lt;failed&gt;</p></div>', $html );
	}

	public function test_the_content_skips_wpautop_and_expands_shortcodes_once(): void {
		$post_id      = $this->sync_component();
		$this->result = static fn( array $request ): array => array(
			'html' => "<div class=\"component\">First line\n\nSecond line [bs_component_count]</div>" . $request['slot'],
		);

		$html = $this->render_post_content( $post_id, 'the_content' );

		$this->assertSame( 2, $this->shortcodes );
		$this->assertStringNotContainsString( '[bs_component_count]', $html );
		$this->assertStringContainsString( "First line\n\nSecond line", $html );
		$this->assertStringNotContainsString( '<p>First line', $html );
		$this->assertStringContainsString( '<span class="expanded">1</span>', $html );
		$this->assertStringContainsString( '<span class="expanded">2</span>', $html );
	}

	public function test_nested_page_blocks_are_rejected(): void {
		$post_id = $this->sync_component();
		$this->setup_post( $post_id );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'nested' );
		do_blocks( '<!-- wp:group --><div class="wp-block-group"><!-- wp:blockstudio/page /--></div><!-- /wp:group -->' );
	}

	public function test_a_page_that_renders_itself_is_rejected(): void {
		$post_id      = $this->sync_component();
		$this->result = static fn( array $request ): array => array( 'html' => Page_Component::render_post( $request['post_id'] ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'renders itself' );
		$this->render_post_content( $post_id, 'do_blocks' );
	}

	public function test_a_post_without_a_component_page_is_rejected(): void {
		$post_id = (int) wp_insert_post(
			array(
				'post_title'  => 'Plain',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);

		try {
			$this->expectException( RuntimeException::class );
			Page_Component::render_post( $post_id );
		} finally {
			wp_delete_post( $post_id, true );
		}
	}

	public function test_rendered_action_receives_page_html_and_dependencies(): void {
		$post_id  = $this->sync_component();
		$received = array();
		$listener = static function ( array $page, string $html, array $dependencies ) use ( &$received ): void {
			$received = array( $page['key'] ?? null, $html, $dependencies );
		};
		add_action( 'blockstudio/pages/rendered', $listener, 10, 3 );

		try {
			$html = $this->render_post_content( $post_id, 'do_blocks' );
		} finally {
			remove_action( 'blockstudio/pages/rendered', $listener, 10 );
		}

		$this->assertSame( 'component-probe', $received[0] );
		$this->assertSame( $html, $received[1] );
		$this->assertContains( $this->pages_path . '/component-probe/page.tsx', $received[2] );
	}

	public function test_renderer_assets_become_an_asset_marker(): void {
		$asset        = WP_CONTENT_DIR . '/blockstudio-component-asset-' . uniqid() . '.js';
		$this->result = static fn( array $request ): array => array(
			'html'   => '<main>' . $request['slot'] . '</main>',
			'assets' => array( array( 'path' => $asset ) ),
		);
		file_put_contents( $asset, 'console.log("component");' );

		try {
			$post_id = $this->sync_component();
			$html    = $this->render_post_content( $post_id, 'do_blocks' );
		} finally {
			unlink( $asset );
		}

		$this->assertStringStartsWith( '<!--blockstudio-assets/page-component-probe--><main>', $html );
	}

	public function test_renderer_layout_renders_the_content_as_its_slot(): void {
		$directory = $this->pages_path . '/shell';
		wp_mkdir_p( $directory );
		file_put_contents( $directory . '/layout.tsx', 'export default () => null' );
		file_put_contents( $directory . '/layout.data.php', '<?php return array();' );
		$begin = new ReflectionMethod( Static_Prerender_Runtime::class, 'begin_dependency_report' );
		$begin->invoke( null, array( 'HTTP_X_BLOCKSTUDIO_STATIC_PRERENDER_DEPENDENCIES' => Static_Prerender_Runtime::dependency_report_token() ) );

		$html = Pages::render_layout(
			'<p>Body</p>',
			array(
				'name'        => 'shell-page',
				'key'         => 'shell-page',
				'post_id'     => 7,
				'layout_path' => $directory . '/layout.tsx',
			)
		);

		$dependencies = array_keys( ( new ReflectionProperty( Static_Prerender_Runtime::class, 'dependencies' ) )->getValue() );
		$this->assertSame( '<main class="component"><p>Body</p></main>', $html );
		$this->assertSame( 'layout', $this->requests[0]['kind'] );
		$this->assertSame( $directory . '/layout.tsx', $this->requests[0]['entry'] );
		$this->assertSame( $directory . '/layout.data.php', $this->requests[0]['data'] );
		$this->assertSame( 7, $this->requests[0]['post_id'] );
		$this->assertSame( 'shell-page', $this->requests[0]['page']['key'] );
		$this->assertContains( $directory . '/layout.tsx', $dependencies );
		$this->assertContains( $directory . '/layout.data.php', $dependencies );
		$this->assertSame( '', Pages::page_content() );
	}

	public function test_renderer_layout_failures_keep_the_content_and_fire_the_error_action(): void {
		$directory = $this->pages_path . '/broken';
		wp_mkdir_p( $directory );
		file_put_contents( $directory . '/layout.tsx', 'export default () => null' );
		file_put_contents( $directory . '/layout.vue', '<template />' );
		$errors       = array();
		$listener     = static function ( Throwable $throwable ) use ( &$errors ): void {
			$errors[] = $throwable->getMessage();
		};
		$this->result = static function (): array {
			throw new RuntimeException( 'Layout exploded' );
		};
		add_action( 'blockstudio/pages/layout_error', $listener );

		try {
			$failed    = Pages::render_layout( '<p>Body</p>', array( 'name' => 'broken' ), $directory . '/layout.tsx' );
			$unclaimed = Pages::render_layout( '<p>Body</p>', array( 'name' => 'broken' ), $directory . '/layout.vue' );
		} finally {
			remove_action( 'blockstudio/pages/layout_error', $listener );
		}

		$this->assertSame( '<p>Body</p>', $failed );
		$this->assertSame( '<p>Body</p>', $unclaimed );
		$this->assertSame( 'Layout exploded', $errors[0] );
		$this->assertStringContainsString( 'layout.vue', $errors[1] );
	}

	public function test_synced_pages_store_the_layout_renderer(): void {
		wp_mkdir_p( $this->pages_path . '/component-probe' );
		file_put_contents( $this->pages_path . '/layout.tsx', 'export default () => null' );
		$post_id = $this->sync_component();

		$this->assertSame( 'unit', get_post_meta( $post_id, '_blockstudio_page_layout_renderer', true ) );
		$this->assertSame( $this->pages_path . '/layout.tsx', get_post_meta( $post_id, '_blockstudio_page_layout', true ) );
	}

	/**
	 * Write and sync the component page fixture.
	 *
	 * @return int Post ID.
	 */
	private function sync_component(): int {
		$directory = $this->pages_path . '/component-probe';
		wp_mkdir_p( $directory );
		file_put_contents(
			$directory . '/page.json',
			wp_json_encode(
				array(
					'name'       => 'component-probe',
					'title'      => 'Component Probe',
					'slug'       => 'component-probe-' . substr( md5( $this->pages_path ), 0, 8 ),
					'postStatus' => 'publish',
					'render'     => array(
						'entry' => 'page.tsx',
						'data'  => 'data.php',
					),
				)
			)
		);
		file_put_contents( $directory . '/page.tsx', 'export default () => null' );
		file_put_contents( $directory . '/data.php', '<?php return array();' );
		file_put_contents( $directory . '/index.php', '<p>Slot [bs_component_count]</p>' );

		$discovery = new Page_Discovery();
		$pages     = $discovery->discover( $this->pages_path );
		$this->assertSame( array(), $discovery->get_errors() );

		$page    = $pages['component-probe'];
		$post_id = ( new Page_Sync() )->sync( $page );
		$this->assertIsInt( $post_id );

		$registry = Page_Registry::instance();
		$registry->register( 'component-probe', $page );
		$registry->update_page_data( 'component-probe', 'post_id', $post_id );
		$this->requests = array();

		return $post_id;
	}

	/**
	 * Render a post's content with it as the global post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $mode    do_blocks or the_content.
	 *
	 * @return string Rendered content.
	 */
	private function render_post_content( int $post_id, string $mode ): string {
		$this->setup_post( $post_id );
		$content = (string) get_post_field( 'post_content', $post_id );

		return 'the_content' === $mode ? (string) apply_filters( 'the_content', $content ) : do_blocks( $content );
	}

	/**
	 * Make a post the global post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function setup_post( int $post_id ): void {
		global $post;

		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
	}

	/**
	 * Remove a temporary fixture tree.
	 *
	 * @param string $path Path to remove.
	 *
	 * @return void
	 */
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

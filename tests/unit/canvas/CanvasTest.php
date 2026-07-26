<?php
/**
 * Canvas tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Canvas;
use Blockstudio\Build;
use Blockstudio\Page_Registry;
use Blockstudio\Pattern_Registry;
use Blockstudio\Pages;
use Blockstudio\Site_Templates;
use Blockstudio\Ui;
use PHPUnit\Framework\TestCase;

/**
 * Canvas unit tests.
 */
class CanvasTest extends TestCase {

	private array $filters = array();

	private array $actions = array();

	protected function tearDown(): void {
		foreach ( $this->filters as $filter ) {
			remove_filter( $filter[0], $filter[1], $filter[2] );
		}

		$this->filters = array();

		foreach ( $this->actions as $action ) {
			remove_action( $action[0], $action[1], $action[2] );
		}

		$this->actions = array();
		parent::tearDown();
	}

	private function add_filter_callback( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
		$this->filters[] = array( $hook, $callback, $priority );
	}

	private function add_action_callback( string $hook, callable $callback, int $priority = 10 ): void {
		add_action( $hook, $callback, $priority, 3 );
		$this->actions[] = array( $hook, $callback, $priority );
	}

	public function test_refresh_returns_404_when_canvas_is_disabled(): void {
		$this->add_filter_callback( 'blockstudio/settings/dev/canvas/enabled', static fn() => false );

		$response = ( new Canvas() )->refresh( new WP_REST_Request( 'GET', '/blockstudio/v1/canvas/refresh' ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'Canvas is disabled.', $response->get_data()['message'] );
	}

	public function test_empty_targeted_selection_does_not_load_any_type(): void {
		$before = Site_Templates::discovery_runs();
		$loaded = array();

		$this->add_action_callback(
			'blockstudio/canvas/item_loaded',
			static function ( string $type, string $id ) use ( &$loaded ): void {
				$loaded[] = $type . ':' . $id;
			}
		);

		$result = Canvas::inventory( array( 'blocks' => array() ) );

		$this->assertTrue( $result['selection']['targeted'] );
		$this->assertSame( array(), $result['order'] );
		$this->assertSame( array(), $loaded );
		$this->assertSame( $before, Site_Templates::discovery_runs() );

		foreach ( $result['inventory'] as $items ) {
			$this->assertSame( array(), $items );
		}
	}

	public function test_full_inventory_returns_the_stable_cross_type_contract(): void {
		$result = Canvas::inventory();

		$this->assertSame( Canvas::SCHEMA_VERSION, $result['schemaVersion'] );
		$this->assertFalse( $result['selection']['targeted'] );
		$this->assertSame(
			array( 'pages', 'blocks', 'patterns', 'templates', 'parts', 'ui' ),
			array_keys( $result['inventory'] )
		);
		$this->assertSame( array_keys( $result['inventory'] ), array_keys( $result['sources'] ) );
		$this->assertSame( array_keys( $result['inventory'] ), array_keys( $result['deleted'] ) );
		$this->assertIsArray( $result['order'] );
		$this->assertIsArray( $result['warnings'] );
		$this->assertIsArray( $result['errors'] );
	}

	public function test_targeted_block_inventory_loads_only_the_selected_record(): void {
		$name = $this->first_theme_block_name();

		if ( null === $name ) {
			$this->markTestSkipped( 'No theme block fixture is registered.' );
		}

		$loaded = array();
		$this->add_action_callback(
			'blockstudio/canvas/item_loaded',
			static function ( string $type, string $id ) use ( &$loaded ): void {
				$loaded[] = array( $type, $id );
			}
		);

		$result = Canvas::inventory( array( 'blocks' => array( $name ) ) );

		$this->assertSame( array( array( 'blocks', $name ) ), $loaded );
		$this->assertCount( 1, $result['inventory']['blocks'] );
		$this->assertSame( $name, $result['inventory']['blocks'][0]['id'] );
		$this->assertSame( array(), $result['inventory']['pages'] );
		$this->assertSame( array(), $result['inventory']['patterns'] );
		$this->assertSame( array(), $result['deleted']['blocks'] );
	}

	public function test_mixed_inventory_loads_only_the_selected_block_and_page(): void {
		$block = $this->first_theme_block_name();
		$page  = $this->first_page_id();

		if ( null === $block || null === $page ) {
			$this->markTestSkipped( 'Theme block and page fixtures are required.' );
		}

		$loaded = array();
		$this->add_action_callback(
			'blockstudio/canvas/item_loaded',
			static function ( string $type, string $id ) use ( &$loaded ): void {
				$loaded[] = array( $type, $id );
			}
		);

		$result = Canvas::inventory(
			array(
				'blocks' => array( $block ),
				'pages'  => array( $page ),
			)
		);

		$this->assertSame(
			array(
				array( 'pages', $page ),
				array( 'blocks', $block ),
			),
			$loaded
		);
		$this->assertCount( 1, $result['inventory']['pages'] );
		$this->assertCount( 1, $result['inventory']['blocks'] );
		$this->assertSame( array(), $result['inventory']['patterns'] );
		$this->assertSame( array(), $result['inventory']['templates'] );
	}

	public function test_page_inventory_restores_source_metadata_without_synchronizing_posts(): void {
		$page = $this->first_page_id();

		if ( null === $page ) {
			$this->markTestSkipped( 'A page fixture is required.' );
		}

		$reconciled = 0;
		$this->add_action_callback(
			'blockstudio/pages/reconciled',
			static function () use ( &$reconciled ): void {
				++$reconciled;
			}
		);

		Pages::reset();
		$result = Canvas::inventory( array( 'pages' => array( $page ) ) );
		$record = $result['inventory']['pages'][0] ?? array();
		$source = is_array( $record['page'] ?? null ) ? $record['page'] : array();

		$this->assertCount( 1, $result['inventory']['pages'] );
		$this->assertArrayHasKey( 'template_path', $source );
		$this->assertFileExists( $source['template_path'] );
		$this->assertSame( $source['template_path'], $record['path'] );
		$this->assertSame( 0, $reconciled );
	}

	public function test_explicit_order_groups_page_hierarchies_by_their_root(): void {
		$registry = Page_Registry::instance();
		Pages::reset();
		$registry->add_path( '/virtual/pages' );

		$pages = array(
			'account'       => array( 'title' => 'Account', 'logical_path' => 'account/page.json', 'source_path' => 'account' ),
			'docs-install'  => array( 'title' => 'Install', 'logical_path' => 'docs/install.md', 'source_path' => 'docs/install.md' ),
			'security'      => array( 'title' => 'Security', 'logical_path' => 'account/security/page.json', 'source_path' => 'account/security' ),
			'docs-usage'    => array( 'title' => 'Configure', 'logical_path' => 'docs/usage.md', 'source_path' => 'docs/usage.md' ),
			'docs-home'     => array( 'title' => 'Guides', 'logical_path' => 'docs/index.md', 'source_path' => 'docs/index.md' ),
		);

		try {
			foreach ( $pages as $name => $page ) {
				$registry->register(
					$name,
					array_merge(
						$page,
						array(
							'name'        => $name,
							'key'         => $name,
							'slug'        => $name,
							'path'        => $page['source_path'],
							'contentType' => 'html',
						)
					)
				);
			}

			$result = Canvas::inventory(
				array( 'pages' => true ),
				array( 'order' => array( 'docs', 'account' ) )
			);

			$this->assertSame(
				array( 'docs-home', 'docs-usage', 'docs-install', 'account', 'security' ),
				array_column( $result['order'], 'id' )
			);
		} finally {
			Pages::reset();
		}
	}

	public function test_blocks_only_refresh_does_not_discover_pages(): void {
		$name = $this->first_theme_block_name();

		if ( null === $name ) {
			$this->markTestSkipped( 'No theme block fixture is registered.' );
		}

		$contexts = array();
		$this->add_filter_callback(
			'blockstudio/discovery/sources',
			static function ( array $sources, string $context ) use ( &$contexts ): array {
				$contexts[] = $context;

				return $sources;
			},
			10,
			3
		);
		$this->add_filter_callback( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		$request = new WP_REST_Request( 'GET', '/blockstudio/v1/canvas/refresh' );
		$request->set_query_params( array( 'blocks' => $name ) );

		$response = ( new Canvas() )->refresh( $request );
		$data     = $response->get_data();

		$this->assertNotContains( 'pages', $contexts );
		$this->assertSame( array(), $data['pages'] );
		$this->assertNotEmpty( $data['blocks'] );
		$this->assertSame( array( $name ), array_values( array_unique( wp_list_pluck( $data['blocks'], 'name' ) ) ) );
	}

	public function test_empty_targeted_refresh_does_not_discover_or_render_content(): void {
		$contexts = array();
		$this->add_filter_callback(
			'blockstudio/discovery/sources',
			static function ( array $sources, string $context ) use ( &$contexts ): array {
				$contexts[] = $context;

				return $sources;
			},
			10,
			3
		);
		$this->add_filter_callback( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		$request = new WP_REST_Request( 'GET', '/blockstudio/v1/canvas/refresh' );
		$request->set_query_params(
			array(
				'blocks' => '',
				'pages'  => '',
			)
		);

		$data = ( new Canvas() )->refresh( $request )->get_data();

		$this->assertNotContains( 'blocks', $contexts );
		$this->assertNotContains( 'pages', $contexts );
		$this->assertSame( array(), $data['pages'] );
		$this->assertSame( array(), $data['blocks'] );
		$this->assertSame( array(), $data['blockstudioBlocks'] );
		$this->assertSame( array(), $data['blocksNative'] );
		$this->assertSame( '', $data['tailwindCss'] );
	}

	public function test_targeted_inventory_reports_deleted_identifiers(): void {
		$result = Canvas::inventory(
			array(
				'blocks' => array( 'theme/missing-block' ),
			)
		);

		$this->assertSame( array(), $result['inventory']['blocks'] );
		$this->assertSame( array( 'theme/missing-block' ), $result['deleted']['blocks'] );
	}

	public function test_explicit_order_matches_id_or_path_without_loading_other_types(): void {
		$all = Canvas::inventory( array( 'blocks' => true ) );

		if ( count( $all['inventory']['blocks'] ) < 2 ) {
			$this->markTestSkipped( 'Two theme block fixtures are required.' );
		}

		$first  = $all['inventory']['blocks'][0]['id'];
		$second = $all['inventory']['blocks'][1]['id'];
		$result = Canvas::inventory(
			array(
				'blocks' => array( $first, $second ),
			),
			array(
				'order' => array( $second, $first ),
			)
		);

		$this->assertSame(
			array(
				array(
					'type' => 'blocks',
					'id'   => $second,
				),
				array(
					'type' => 'blocks',
					'id'   => $first,
				),
			),
			$result['order']
		);
	}

	public function test_targeted_documents_render_only_selected_block(): void {
		$name = $this->first_theme_block_name();

		if ( null === $name ) {
			$this->markTestSkipped( 'No theme block fixture is registered.' );
		}

		$this->add_filter_callback( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		$result = Canvas::documents( array( 'blocks' => array( $name ) ) );

		$this->assertCount( 1, $result['documents']['blocks'] );
		$this->assertSame( $name, $result['documents']['blocks'][0]['id'] );
		$this->assertStringContainsString( '<!doctype html>', $result['documents']['blocks'][0]['document']['html'] );
		$this->assertSame( array(), $result['documents']['pages'] );
	}

	public function test_targeted_page_document_uses_a_semantic_main_element(): void {
		$page = $this->first_page_id();

		if ( null === $page ) {
			$this->markTestSkipped( 'A page fixture is required.' );
		}

		$this->add_filter_callback( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		$result   = Canvas::documents( array( 'pages' => array( $page ) ) );
		$document = $result['documents']['pages'][0]['document'] ?? array();

		$this->assertSame( $page, $result['documents']['pages'][0]['id'] ?? null );
		$this->assertStringContainsString( '<main>', $document['html'] ?? '' );
		$this->assertStringContainsString( '</main>', $document['html'] ?? '' );
	}

	public function test_page_documents_use_and_restore_each_selected_frontend_context(): void {
		$registry    = Page_Registry::instance();
		$account_id  = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'account',
				'post_title'  => 'Account',
			)
		);
		$about_id    = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'about',
				'post_title'  => 'About',
			)
		);
		$global_keys = array( 'post', 'wp_query', 'wp_the_query', 'wp_styles', 'wp_scripts' );
		$globals     = array();
		$server      = array();
		$requests    = array();

		$this->assertIsInt( $account_id );
		$this->assertIsInt( $about_id );

		update_post_meta( $account_id, '_blockstudio_page_key', 'account' );
		update_post_meta( $account_id, '_blockstudio_page_name', 'account' );
		update_post_meta( $account_id, '_blockstudio_page_source', '/virtual/pages/account/index.php' );
		update_post_meta( $about_id, '_blockstudio_page_key', 'about' );
		update_post_meta( $about_id, '_blockstudio_page_name', 'about' );
		update_post_meta( $about_id, '_blockstudio_page_source', '/virtual/pages/about/index.php' );

		foreach ( $global_keys as $key ) {
			$globals[ $key ] = array(
				'exists' => array_key_exists( $key, $GLOBALS ),
				'value'  => $GLOBALS[ $key ] ?? null,
			);
		}

		foreach ( array( 'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING' ) as $key ) {
			$server[ $key ] = array(
				'exists' => array_key_exists( $key, $_SERVER ),
				'value'  => $_SERVER[ $key ] ?? null,
			);
		}

		$this->add_filter_callback(
			'body_class',
			static function ( array $classes ): array {
				if ( is_page( 'account' ) ) {
					$classes[] = 'theme-account-page';
				}

				return $classes;
			}
		);
		$this->add_action_callback(
			'wp_enqueue_scripts',
			static function () use ( &$requests ): void {
				$requests[ get_queried_object_id() ] = $_SERVER['REQUEST_URI'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Test records the exact temporary request URI.

				if ( ! is_page( 'account' ) ) {
					return;
				}

				wp_enqueue_style(
					'theme-account',
					'https://example.test/theme-account.css',
					array(),
					'1.0.0'
				);
				wp_enqueue_script(
					'theme-account',
					'https://example.test/theme-account.js',
					array(),
					'1.0.0',
					true
				);
				wp_redirect( 'https://example.test/sign-in/', 307 );
			}
		);
		$this->add_filter_callback( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		try {
			Pages::reset();
			$registry->add_path( '/virtual/pages' );
			$registry->register(
				'account',
				array(
					'name'           => 'account',
					'key'            => 'account',
					'title'          => 'Account',
					'slug'           => 'account',
					'path'           => 'account',
					'source_path'    => '/virtual/pages/account/index.php',
					'template_path'  => '/virtual/pages/account/index.php',
					'contentType'    => 'html',
					'inline_content' => '<section data-page="account">Account</section>',
				)
			);
			$registry->register(
				'about',
				array(
					'name'           => 'about',
					'key'            => 'about',
					'title'          => 'About',
					'slug'           => 'about',
					'path'           => 'about',
					'source_path'    => '/virtual/pages/about/index.php',
					'template_path'  => '/virtual/pages/about/index.php',
					'contentType'    => 'html',
					'inline_content' => '<section data-page="about">About</section>',
				)
			);

			$result    = Canvas::documents(
				array( 'pages' => array( 'account', 'about' ) ),
				array(
					'document' => array(
						'bodyClasses' => array( 'consumer-preview' ),
					),
				)
			);
			$documents = array_column( $result['documents']['pages'], 'document', 'id' );
			$pages     = array_column( $result['inventory']['pages'], 'page', 'id' );
			$account   = $documents['account']['html'] ?? '';
			$about     = $documents['about']['html'] ?? '';

			$this->assertSame( $account_id, $pages['account']['post_id'] ?? null );
			$this->assertSame( $about_id, $pages['about']['post_id'] ?? null );
			$this->assertSame( home_url( '/account/' ), $pages['account']['permalink'] ?? null );
			$this->assertSame( home_url( '/about/' ), $pages['about']['permalink'] ?? null );
			$this->assertStringContainsString( 'consumer-preview', $account );
			$this->assertStringContainsString( 'theme-account-page', $account );
			$this->assertStringContainsString( 'theme-account.css', $account );
			$this->assertStringContainsString( 'theme-account.js', $account );
			$this->assertStringContainsString( 'data-page="account"', $account );
			$this->assertStringContainsString( 'consumer-preview', $about );
			$this->assertStringNotContainsString( 'theme-account-page', $about );
			$this->assertStringNotContainsString( 'theme-account.css', $about );
			$this->assertStringNotContainsString( 'theme-account.js', $about );
			$this->assertStringContainsString( 'data-page="about"', $about );
			$this->assertSame( '/account/', $requests[ $account_id ] ?? null );
			$this->assertSame( '/about/', $requests[ $about_id ] ?? null );
			$this->assertSame(
				'page_redirect_suppressed',
				$documents['account']['warnings'][0]['code'] ?? null
			);
			$this->assertStringContainsString(
				'307 redirect to "https://example.test/sign-in/"',
				$documents['account']['warnings'][0]['message'] ?? ''
			);

			foreach ( $globals as $key => $state ) {
				$this->assertSame( $state['exists'], array_key_exists( $key, $GLOBALS ) );

				if ( $state['exists'] ) {
					$this->assertSame( $state['value'], $GLOBALS[ $key ] );
				}
			}

			foreach ( $server as $key => $state ) {
				$this->assertSame( $state['exists'], array_key_exists( $key, $_SERVER ) );

				if ( $state['exists'] ) {
					$this->assertSame( $state['value'], $_SERVER[ $key ] );
				}
			}
		} finally {
			Pages::reset();
			wp_delete_post( $account_id, true );
			wp_delete_post( $about_id, true );
		}
	}

	public function test_targeted_pattern_compiles_only_the_selected_source(): void {
		$registry        = Pattern_Registry::instance();
		$original        = $registry->get_patterns();
		$selected_path   = tempnam( sys_get_temp_dir(), 'canvas-selected-pattern-' );
		$unselected_path = tempnam( sys_get_temp_dir(), 'canvas-unselected-pattern-' );
		$compiled        = array();

		$this->assertIsString( $selected_path );
		$this->assertIsString( $unselected_path );

		file_put_contents( $selected_path, '<p>Selected pattern</p>' );
		file_put_contents( $unselected_path, '<p>Unselected pattern</p>' );
		Pattern_Registry::reset();

		$registry->register(
			'selected-pattern',
			array(
				'name'          => 'selected-pattern',
				'title'         => 'Selected Pattern',
				'source_path'   => $selected_path,
				'template_path' => $selected_path,
				'directory'     => dirname( $selected_path ),
			)
		);
		$registry->register(
			'unselected-pattern',
			array(
				'name'          => 'unselected-pattern',
				'title'         => 'Unselected Pattern',
				'source_path'   => $unselected_path,
				'template_path' => $unselected_path,
				'directory'     => dirname( $unselected_path ),
			)
		);

		$this->add_action_callback(
			'blockstudio/canvas/source_compiled',
			static function ( string $path ) use ( &$compiled ): void {
				$compiled[] = $path;
			}
		);

		try {
			$result = Canvas::inventory( array( 'patterns' => array( 'selected-pattern' ) ) );

			$this->assertSame( array( $selected_path ), $compiled );
			$this->assertCount( 1, $result['inventory']['patterns'] );
			$this->assertSame( 'selected-pattern', $result['inventory']['patterns'][0]['id'] );
			$this->assertStringContainsString( 'Selected pattern', $result['inventory']['patterns'][0]['content'] );
		} finally {
			Pattern_Registry::reset();

			foreach ( $original as $name => $pattern ) {
				$registry->register( $name, $pattern );
			}

			unlink( $selected_path );
			unlink( $unselected_path );
		}
	}

	private function first_theme_block_name(): ?string {
		foreach ( Build::blocks() as $name => $block ) {
			if ( is_string( $name ) && ! Ui::is_bundled_block( $block ) ) {
				return $name;
			}
		}

		return null;
	}

	private function first_page_id(): ?string {
		foreach ( Pages::pages() as $key => $page ) {
			if ( is_string( $key ) && is_array( $page ) ) {
				return is_string( $page['key'] ?? null )
					? $page['key']
					: $key;
			}
		}

		return null;
	}
}

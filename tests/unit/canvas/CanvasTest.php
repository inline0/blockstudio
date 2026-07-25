<?php
/**
 * Canvas tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Canvas;
use Blockstudio\Build;
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

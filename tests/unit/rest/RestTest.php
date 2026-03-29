<?php

use Blockstudio\Rest;
use PHPUnit\Framework\TestCase;

class RestTest extends TestCase {

	public function test_blockstudio_v1_namespace_is_registered(): void {
		$server     = rest_get_server();
		$namespaces = $server->get_namespaces();

		$this->assertContains( 'blockstudio/v1', $namespaces );
	}

	public function test_data_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/data', $routes );
	}

	public function test_blocks_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/blocks', $routes );
	}

	public function test_blocks_sorted_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/blocks-sorted', $routes );
	}

	public function test_files_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/files', $routes );
	}

	public function test_icons_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/icons', $routes );
	}

	public function test_editor_options_save_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/editor/options/save', $routes );
	}

	public function test_attributes_build_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/attributes/build', $routes );
	}

	public function test_attributes_populate_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/attributes/populate', $routes );
	}

	public function test_gutenberg_block_update_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/gutenberg/block/update', $routes );
	}

	public function test_gutenberg_block_render_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey(
			'/blockstudio/v1/gutenberg/block/render/(?P<name>[a-z0-9-]+/[a-z0-9-]+)',
			$routes
		);
	}

	public function test_gutenberg_block_render_all_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/gutenberg/block/render/all', $routes );
	}

	public function test_scss_compile_route_exists(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );

		$this->assertArrayHasKey( '/blockstudio/v1/scss/compile', $routes );
	}

	public function test_data_route_is_get(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );
		$route  = $routes['/blockstudio/v1/data'][0];

		$this->assertSame( array( 'GET' => true ), $route['methods'] );
	}

	public function test_blocks_route_is_get(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );
		$route  = $routes['/blockstudio/v1/blocks'][0];

		$this->assertSame( array( 'GET' => true ), $route['methods'] );
	}

	public function test_editor_options_save_route_is_post(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );
		$route  = $routes['/blockstudio/v1/editor/options/save'][0];

		$this->assertSame( array( 'POST' => true ), $route['methods'] );
	}

	public function test_attributes_build_route_is_post(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );
		$route  = $routes['/blockstudio/v1/attributes/build'][0];

		$this->assertSame( array( 'POST' => true ), $route['methods'] );
	}

	public function test_gutenberg_block_update_route_is_post(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );
		$route  = $routes['/blockstudio/v1/gutenberg/block/update'][0];

		$this->assertSame( array( 'POST' => true ), $route['methods'] );
	}

	public function test_scss_compile_route_is_post(): void {
		$routes = rest_get_server()->get_routes( 'blockstudio/v1' );
		$route  = $routes['/blockstudio/v1/scss/compile'][0];

		$this->assertSame( array( 'POST' => true ), $route['methods'] );
	}

	// Response helpers

	public function test_response_returns_wp_rest_response(): void {
		$rest     = new Rest();
		$response = $rest->response( 'test_code', 'Test message' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
	}

	public function test_response_has_correct_structure(): void {
		$rest     = new Rest();
		$response = $rest->response( 'test_code', 'Test message', array( 'key' => 'value' ) );
		$data     = $response->get_data();

		$this->assertSame( 'test_code', $data['code'] );
		$this->assertSame( 'Test message', $data['message'] );
		$this->assertSame( 200, $data['data']['status'] );
		$this->assertSame( 'value', $data['data']['key'] );
	}

	public function test_error_returns_wp_error(): void {
		$rest  = new Rest();
		$error = $rest->error( 'test_error', 'Error message' );

		$this->assertInstanceOf( WP_Error::class, $error );
	}

	public function test_error_has_correct_code_and_message(): void {
		$rest  = new Rest();
		$error = $rest->error( 'test_error', 'Error message' );

		$this->assertSame( 'test_error', $error->get_error_code() );
		$this->assertSame( 'Error message', $error->get_error_message() );
	}

	public function test_error_has_status_500_by_default(): void {
		$rest  = new Rest();
		$error = $rest->error( 'test_error', 'Error message' );
		$data  = $error->get_error_data();

		$this->assertSame( 500, $data['status'] );
	}

	public function test_error_merges_additional_data(): void {
		$rest  = new Rest();
		$error = $rest->error( 'test_error', 'Error message', array( 'extra' => 'info' ) );
		$data  = $error->get_error_data();

		$this->assertSame( 'info', $data['extra'] );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_response_or_error_returns_success_when_true(): void {
		$rest   = new Rest();
		$result = $rest->response_or_error(
			true,
			'test',
			array( 'success' => 'It worked', 'error' => 'It failed' )
		);

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 'It worked', $result->get_data()['message'] );
	}

	public function test_response_or_error_returns_error_when_false(): void {
		$rest   = new Rest();
		$result = $rest->response_or_error(
			false,
			'test',
			array( 'success' => 'It worked', 'error' => 'It failed' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'It failed', $result->get_error_message() );
	}

	// Endpoint callbacks return arrays

	public function test_data_endpoint_returns_array(): void {
		$rest = new Rest();
		$data = $rest->data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'dataSorted', $data );
		$this->assertArrayHasKey( 'files', $data );
	}

	public function test_blocks_endpoint_returns_array(): void {
		$rest = new Rest();
		$data = $rest->blocks();

		$this->assertIsArray( $data );
	}

	public function test_blocks_sorted_endpoint_returns_array(): void {
		$rest = new Rest();
		$data = $rest->blocks_sorted();

		$this->assertIsArray( $data );
	}

	public function test_files_endpoint_returns_array(): void {
		$rest = new Rest();
		$data = $rest->files();

		$this->assertIsArray( $data );
	}

	public function test_route_count(): void {
		$routes    = rest_get_server()->get_routes( 'blockstudio/v1' );
		$bs_routes = array_filter(
			array_keys( $routes ),
			fn( $r ) => str_starts_with( $r, '/blockstudio/v1/' )
		);

		$this->assertGreaterThanOrEqual( 13, count( $bs_routes ) );
	}
}

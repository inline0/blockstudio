<?php

use Blockstudio\Rpc;
use PHPUnit\Framework\TestCase;

class RpcTest extends TestCase {

	/**
	 * Reset Rpc static state before each test so load_all() re-discovers functions.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->reset_rpc_state();

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
	}

	private function reset_rpc_state(): void {
		$ref_functions = new ReflectionProperty( Rpc::class, 'functions' );
		$ref_functions->setAccessible( true );
		$ref_functions->setValue( null, array() );

		$ref_loaded = new ReflectionProperty( Rpc::class, 'loaded' );
		$ref_loaded->setAccessible( true );
		$ref_loaded->setValue( null, false );
	}

	// has_any

	public function test_has_any_returns_bool(): void {
		$result = Rpc::has_any();
		$this->assertIsBool( $result );
	}

	public function test_has_any_returns_true_when_functions_exist(): void {
		$this->assertTrue( Rpc::has_any(), 'Test block blockstudio/type-functions should register RPC functions.' );
	}

	// client_script

	public function test_client_script_returns_non_empty_string(): void {
		$script = Rpc::client_script();
		$this->assertIsString( $script );
		$this->assertNotEmpty( $script );
	}

	public function test_client_script_contains_bs_fn(): void {
		$script = Rpc::client_script();
		$this->assertStringContainsString( 'bs.fn', $script );
	}

	public function test_client_script_contains_rest_url(): void {
		$script = Rpc::client_script();
		$this->assertStringContainsString( 'blockstudio/v1/fn/', $script );
	}

	public function test_client_script_contains_csrf_token(): void {
		$script = Rpc::client_script();
		$this->assertStringContainsString( 'bs._token', $script );
	}

	public function test_client_script_contains_nonce(): void {
		$script = Rpc::client_script();
		$this->assertStringContainsString( 'X-WP-Nonce', $script );
	}

	// get_all and function discovery

	public function test_get_all_returns_array(): void {
		$all = Rpc::get_all();
		$this->assertIsArray( $all );
	}

	public function test_get_all_contains_test_block(): void {
		$all = Rpc::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-functions', $all );
	}

	public function test_get_all_contains_php_native_test_block(): void {
		$all = Rpc::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-functions-php', $all );
	}

	public function test_discovered_functions_include_greet(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions'];
		$this->assertArrayHasKey( 'greet', $functions );
	}

	public function test_discovered_functions_include_add(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions'];
		$this->assertArrayHasKey( 'add', $functions );
	}

	public function test_discovered_functions_include_public(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions'];
		$this->assertArrayHasKey( 'public', $functions );
	}

	public function test_discovered_functions_include_open_endpoint(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions'];
		$this->assertArrayHasKey( 'open_endpoint', $functions );
	}

	public function test_discovered_functions_include_admin_only(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions'];
		$this->assertArrayHasKey( 'admin_only', $functions );
	}

	public function test_discovered_functions_include_editor_up(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions'];
		$this->assertArrayHasKey( 'editor_up', $functions );
	}

	public function test_discovered_functions_include_get_status(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions'];
		$this->assertArrayHasKey( 'get_status', $functions );
	}

	public function test_php_native_functions_include_greet(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions-php'];
		$this->assertArrayHasKey( 'greet', $functions );
	}

	public function test_php_native_functions_include_webhook(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions-php'];
		$this->assertArrayHasKey( 'webhook', $functions );
	}

	public function test_php_native_functions_include_admin_panel(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions-php'];
		$this->assertArrayHasKey( 'admin_panel', $functions );
	}

	public function test_php_native_functions_normalize_method_name(): void {
		$all       = Rpc::get_all();
		$functions = $all['blockstudio/type-functions-php'];
		$this->assertArrayHasKey( 'get_status', $functions );
	}

	// Function definition structure

	public function test_simple_closure_gets_default_options(): void {
		$all   = Rpc::get_all();
		$greet = $all['blockstudio/type-functions']['greet'];

		$this->assertIsCallable( $greet['callback'] );
		$this->assertFalse( $greet['public'] );
		$this->assertNull( $greet['capability'] );
		$this->assertSame( array( 'POST' ), $greet['methods'] );
	}

	public function test_public_function_has_public_true(): void {
		$all    = Rpc::get_all();
		$public = $all['blockstudio/type-functions']['public'];

		$this->assertTrue( $public['public'] );
	}

	public function test_open_function_has_public_open(): void {
		$all  = Rpc::get_all();
		$open = $all['blockstudio/type-functions']['open_endpoint'];

		$this->assertSame( 'open', $open['public'] );
	}

	public function test_admin_only_has_capability(): void {
		$all   = Rpc::get_all();
		$admin = $all['blockstudio/type-functions']['admin_only'];

		$this->assertSame( 'manage_options', $admin['capability'] );
	}

	public function test_editor_up_has_multiple_capabilities(): void {
		$all    = Rpc::get_all();
		$editor = $all['blockstudio/type-functions']['editor_up'];

		$this->assertSame( array( 'edit_posts', 'manage_options' ), $editor['capability'] );
	}

	public function test_get_status_has_multiple_methods(): void {
		$all    = Rpc::get_all();
		$status = $all['blockstudio/type-functions']['get_status'];

		$this->assertSame( array( 'GET', 'POST' ), $status['methods'] );
	}

	public function test_php_native_greet_uses_session_access(): void {
		$all   = Rpc::get_all();
		$greet = $all['blockstudio/type-functions-php']['greet'];

		$this->assertTrue( $greet['public'] );
	}

	public function test_php_native_webhook_uses_open_access(): void {
		$all     = Rpc::get_all();
		$webhook = $all['blockstudio/type-functions-php']['webhook'];

		$this->assertSame( 'open', $webhook['public'] );
	}

	public function test_php_native_admin_panel_has_capability(): void {
		$all   = Rpc::get_all();
		$admin = $all['blockstudio/type-functions-php']['admin_panel'];

		$this->assertSame( 'manage_options', $admin['capability'] );
	}

	public function test_php_native_status_has_multiple_methods(): void {
		$all    = Rpc::get_all();
		$status = $all['blockstudio/type-functions-php']['get_status'];

		$this->assertSame( array( 'GET', 'POST' ), $status['methods'] );
	}

	// has_functions

	public function test_has_functions_returns_true_for_test_block(): void {
		$this->assertTrue( Rpc::has_functions( 'blockstudio/type-functions' ) );
	}

	public function test_has_functions_returns_true_for_php_native_block(): void {
		$this->assertTrue( Rpc::has_functions( 'blockstudio/type-functions-php' ) );
	}

	public function test_has_functions_returns_false_for_nonexistent_block(): void {
		$this->assertFalse( Rpc::has_functions( 'blockstudio/does-not-exist' ) );
	}

	// call dispatch

	public function test_call_greet_with_params(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'greet', array( 'name' => 'Alice' ) );
		$this->assertSame( array( 'message' => 'Hello, Alice!' ), $result );
	}

	public function test_call_greet_default_param(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'greet', array() );
		$this->assertSame( array( 'message' => 'Hello, World!' ), $result );
	}

	public function test_call_add_with_params(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'add', array( 'a' => 3, 'b' => 7 ) );
		$this->assertSame( array( 'result' => 10 ), $result );
	}

	public function test_call_add_default_zeroes(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'add', array() );
		$this->assertSame( array( 'result' => 0 ), $result );
	}

	public function test_call_public_function(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'public', array( 'value' => 'test' ) );
		$this->assertSame( array( 'public' => true, 'echo' => 'test' ), $result );
	}

	public function test_call_open_endpoint_function(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'open_endpoint', array( 'value' => 42 ) );
		$this->assertSame( array( 'open' => true, 'echo' => 42 ), $result );
	}

	public function test_call_admin_only_function(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'admin_only', array() );
		$this->assertSame( array( 'admin' => true ), $result );
	}

	public function test_call_get_status_function(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'get_status', array() );
		$this->assertSame( array( 'status' => 'ok' ), $result );
	}

	public function test_call_php_native_greet_with_params(): void {
		$result = Rpc::call( 'blockstudio/type-functions-php', 'greet', array( 'name' => 'Alice' ) );
		$this->assertSame( array( 'message' => 'Hello from PHP, Alice!' ), $result );
	}

	public function test_call_php_native_webhook_function(): void {
		$result = Rpc::call( 'blockstudio/type-functions-php', 'webhook', array( 'value' => 42 ) );
		$this->assertSame( array( 'open' => true, 'echo' => 42 ), $result );
	}

	public function test_call_php_native_admin_panel_function(): void {
		$result = Rpc::call( 'blockstudio/type-functions-php', 'admin_panel', array() );
		$this->assertSame( array( 'admin' => true ), $result );
	}

	public function test_call_php_native_status_function(): void {
		$result = Rpc::call( 'blockstudio/type-functions-php', 'get_status', array() );
		$this->assertSame( array( 'status' => 'ok' ), $result );
	}

	// Error handling: non-existent function

	public function test_call_nonexistent_function_returns_wp_error(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'nonexistent', array() );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_call_nonexistent_function_error_code(): void {
		$result = Rpc::call( 'blockstudio/type-functions', 'nonexistent', array() );
		$this->assertSame( 'blockstudio_fn_not_found', $result->get_error_code() );
	}

	// Error handling: non-existent block

	public function test_call_nonexistent_block_returns_wp_error(): void {
		$result = Rpc::call( 'blockstudio/nonexistent-block', 'greet', array() );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_call_nonexistent_block_error_code(): void {
		$result = Rpc::call( 'blockstudio/nonexistent-block', 'greet', array() );
		$this->assertSame( 'blockstudio_fn_not_found', $result->get_error_code() );
	}

	// handle_request

	public function test_handle_request_returns_result_for_valid_function(): void {
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/greet' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'greet' );
		$request->set_param( 'params', array( 'name' => 'Bob' ) );

		$response = Rpc::handle_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertSame( array( 'message' => 'Hello, Bob!' ), $data );
	}

	public function test_handle_request_block_not_found(): void {
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/fake/block/greet' );
		$request->set_param( 'block', 'fake/block' );
		$request->set_param( 'function', 'greet' );

		$response = Rpc::handle_request( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'blockstudio_fn_block_not_found', $response->get_error_code() );
	}

	public function test_handle_request_function_not_found(): void {
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/nonexistent' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'nonexistent' );

		$response = Rpc::handle_request( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'blockstudio_fn_not_found', $response->get_error_code() );
	}

	public function test_handle_request_default_params(): void {
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/add' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'add' );

		$response = Rpc::handle_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertSame( array( 'result' => 0 ), $data );
	}

	// check_permission

	public function test_check_permission_returns_true_for_unknown_function(): void {
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/fake/block/unknown' );
		$request->set_param( 'block', 'fake/block' );
		$request->set_param( 'function', 'unknown' );

		$result = Rpc::check_permission( $request );
		$this->assertTrue( $result );
	}

	public function test_check_permission_method_not_allowed(): void {
		// The "greet" function only allows POST (default). Sending GET should fail.
		$request = new WP_REST_Request( 'GET', '/blockstudio/v1/fn/blockstudio/type-functions/greet' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'greet' );

		$result = Rpc::check_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'blockstudio_fn_method_not_allowed', $result->get_error_code() );
	}

	public function test_check_permission_get_status_allows_get(): void {
		$request = new WP_REST_Request( 'GET', '/blockstudio/v1/fn/blockstudio/type-functions/get_status' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'get_status' );

		$result = Rpc::check_permission( $request );

		// Should not be a method error (may require auth, but method itself is allowed).
		if ( $result instanceof WP_Error ) {
			$this->assertNotSame( 'blockstudio_fn_method_not_allowed', $result->get_error_code() );
		} else {
			$this->assertTrue( $result );
		}
	}

	public function test_check_permission_open_endpoint_allows_anonymous(): void {
		// Log out to simulate anonymous request.
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/open_endpoint' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'open_endpoint' );

		$result = Rpc::check_permission( $request );

		$this->assertTrue( $result );
	}

	public function test_check_permission_private_function_requires_auth(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/greet' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'greet' );

		$result = Rpc::check_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'blockstudio_fn_unauthorized', $result->get_error_code() );
	}

	public function test_check_permission_admin_function_forbidden_for_subscriber(): void {
		// Create a subscriber user.
		$user_id = wp_insert_user( array(
			'user_login' => 'rpc_test_subscriber_' . wp_rand(),
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/admin_only' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'admin_only' );

		$result = Rpc::check_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'blockstudio_fn_forbidden', $result->get_error_code() );

		wp_delete_user( $user_id );
	}

	public function test_check_permission_admin_function_allowed_for_admin(): void {
		// Get or create an admin user.
		$user_id = wp_insert_user( array(
			'user_login' => 'rpc_test_admin_' . wp_rand(),
			'user_pass'  => 'password',
			'role'       => 'administrator',
		) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/admin_only' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'admin_only' );

		$result = Rpc::check_permission( $request );

		$this->assertTrue( $result );

		wp_delete_user( $user_id );
	}

	public function test_check_permission_editor_up_allowed_for_editor(): void {
		$user_id = wp_insert_user( array(
			'user_login' => 'rpc_test_editor_' . wp_rand(),
			'user_pass'  => 'password',
			'role'       => 'editor',
		) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/editor_up' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'editor_up' );

		$result = Rpc::check_permission( $request );

		$this->assertTrue( $result );

		wp_delete_user( $user_id );
	}

	public function test_check_permission_public_function_logged_in_allowed(): void {
		$user_id = wp_insert_user( array(
			'user_login' => 'rpc_test_public_user_' . wp_rand(),
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/public' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'public' );

		$result = Rpc::check_permission( $request );

		$this->assertTrue( $result );

		wp_delete_user( $user_id );
	}

	public function test_check_permission_public_function_anonymous_without_csrf_fails(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/public' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'public' );

		$result = Rpc::check_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'blockstudio_fn_csrf', $result->get_error_code() );
	}

	public function test_check_permission_public_function_anonymous_with_valid_csrf(): void {
		wp_set_current_user( 0 );

		$token = Blockstudio\Csrf::generate();

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/fn/blockstudio/type-functions/public' );
		$request->set_param( 'block', 'blockstudio/type-functions' );
		$request->set_param( 'function', 'public' );
		$request->set_header( 'X-BS-Token', $token );

		$result = Rpc::check_permission( $request );

		$this->assertTrue( $result );
	}

	// inject_frontend_client

	public function test_inject_frontend_client_adds_script(): void {
		$html   = '<html><head></head><body></body></html>';
		$result = Rpc::inject_frontend_client( $html );

		$this->assertStringContainsString( 'id="blockstudio-fn"', $result );
		$this->assertStringContainsString( 'bs.fn', $result );
	}

	public function test_inject_frontend_client_skips_if_already_present(): void {
		$html   = '<html><head><script id="blockstudio-fn">existing</script></head><body></body></html>';
		$result = Rpc::inject_frontend_client( $html );

		$this->assertSame( $html, $result );
	}

	// Hooks fired by call

	public function test_before_call_action_fires(): void {
		$fired = false;

		$callback = function ( $args ) use ( &$fired ) {
			$fired = true;
			$this->assertSame( 'blockstudio/type-functions', $args['block'] );
			$this->assertSame( 'greet', $args['function'] );
		};

		add_action( 'blockstudio/rpc/before_call', $callback );
		Rpc::call( 'blockstudio/type-functions', 'greet', array( 'name' => 'Hook' ) );
		remove_action( 'blockstudio/rpc/before_call', $callback );

		$this->assertTrue( $fired );
	}

	public function test_after_call_action_fires_with_result(): void {
		$captured_result = null;

		$callback = function ( $args ) use ( &$captured_result ) {
			$captured_result = $args['result'];
		};

		add_action( 'blockstudio/rpc/after_call', $callback );
		Rpc::call( 'blockstudio/type-functions', 'add', array( 'a' => 5, 'b' => 3 ) );
		remove_action( 'blockstudio/rpc/after_call', $callback );

		$this->assertSame( array( 'result' => 8 ), $captured_result );
	}

	// Load caching (load_all only runs once)

	public function test_load_all_caches_results(): void {
		// First call loads functions.
		$first = Rpc::get_all();
		$this->assertNotEmpty( $first );

		// Second call should return same result without re-loading.
		$second = Rpc::get_all();
		$this->assertSame( $first, $second );
	}

	// blockstudio/rpc filter

	public function test_rpc_filter_can_modify_functions(): void {
		$this->reset_rpc_state();

		$callback = function ( $functions ) {
			$functions['blockstudio/type-functions']['custom_filter_fn'] = array(
				'callback'   => function () {
					return array( 'filtered' => true );
				},
				'public'     => false,
				'capability' => null,
				'methods'    => array( 'POST' ),
			);
			return $functions;
		};

		add_filter( 'blockstudio/rpc', $callback );
		$all = Rpc::get_all();
		remove_filter( 'blockstudio/rpc', $callback );

		$this->assertArrayHasKey( 'custom_filter_fn', $all['blockstudio/type-functions'] );
	}
}

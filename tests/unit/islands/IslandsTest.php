<?php

use Blockstudio\Block;
use Blockstudio\Block_Tags;
use Blockstudio\Build;
use Blockstudio\Islands;
use PHPUnit\Framework\TestCase;

class IslandsTest extends TestCase {

	private array $filters = array();
	private array $server_vars = array();
	private array $cookie_vars = array();

	protected function setUp(): void {
		unset( $_GET['blockstudioMode'], $_GET['postId'] );

		if ( class_exists( Build::class ) ) {
			Build::refresh_blocks();
		}
	}

	protected function tearDown(): void {
		foreach ( $this->filters as $filter ) {
			remove_filter( $filter[0], $filter[1], $filter[2] );
		}

		foreach ( $this->server_vars as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}

		foreach ( $this->cookie_vars as $key => $value ) {
			if ( null === $value ) {
				unset( $_COOKIE[ $key ] );
			} else {
				$_COOKIE[ $key ] = $value;
			}
		}

		$this->filters = array();
		$this->server_vars = array();
		$this->cookie_vars = array();
		unset( $_GET['blockstudioMode'], $_GET['postId'] );
		wp_set_current_user( 0 );
	}

	private function require_block( string $name ): void {
		if ( ! isset( Build::blocks()[ $name ] ) ) {
			$this->markTestSkipped( "{$name} fixture block is not registered." );
		}
	}

	private function render_blockstudio_block( string $name, array $attributes = array() ): string {
		$this->require_block( $name );

		return (string) Block::render(
			array(
				'blockstudio' => array(
					'name'       => $name,
					'attributes' => $attributes,
				),
			)
		);
	}

	private function marker_attribute( string $html, string $attribute ): ?string {
		if ( preg_match( '/' . preg_quote( $attribute, '/' ) . '="([^"]*)"/', $html, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES );
		}

		return null;
	}

	private function add_filter_callback( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $hook, $callback, $priority, $args );
		$this->filters[] = array( $hook, $callback, $priority );
	}

	private function set_server_var( string $key, string $value ): void {
		if ( ! array_key_exists( $key, $this->server_vars ) ) {
			$this->server_vars[ $key ] = $_SERVER[ $key ] ?? null;
		}

		$_SERVER[ $key ] = $value;
	}

	private function set_cookie_var( string $key, string $value ): void {
		if ( ! array_key_exists( $key, $this->cookie_vars ) ) {
			$this->cookie_vars[ $key ] = $_COOKIE[ $key ] ?? null;
		}

		$_COOKIE[ $key ] = $value;
	}

	private function is_same_origin_browser_request(): bool {
		$method = new ReflectionMethod( Islands::class, 'is_same_origin_browser_request' );
		$method->setAccessible( true );

		return (bool) $method->invoke( null );
	}

	private function render_endpoint_item( string $name, array $attributes, string $signature = '' ) {
		$props = Islands::filter_attributes( $name, $attributes );
		if ( '' === $signature ) {
			$signature = Islands::signature( Islands::signature_payload( $name, $props ) );
		}

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/island/render' );
		$request->set_param(
			'islands',
			array(
				array(
					'clientId'   => 'i1',
					'name'       => $name,
					'attributes' => $attributes,
					'signature'  => $signature,
				),
			)
		);

		$response = Islands::render_endpoint( $request );

		return is_wp_error( $response ) ? $response : $response->get_data()['i1'];
	}

	public function test_normalizes_island_config_shorthands(): void {
		$this->assertSame( 'hydrate', Islands::normalize_config( true )['mode'] );
		$this->assertSame( 'hydrate', Islands::normalize_config( 'hydrate' )['mode'] );
		$this->assertSame( 'hydrate', Islands::normalize_config( 'hydrated' )['mode'] );
		$this->assertSame( 'dynamic', Islands::normalize_config( 'dynamic' )['mode'] );
		$this->assertFalse( Islands::normalize_config( false ) );
		$this->assertFalse( Islands::normalize_config( 'unknown' ) );
	}

	public function test_normalizes_object_config(): void {
		$config = Islands::normalize_config(
			array(
				'mode'        => 'dynamic',
				'tag'         => 'section',
				'attributes'  => array( 'message', 'secret' ),
				'placeholder' => 'shell.php',
				'cache'       => array(
					'ttl' => 120,
					'per' => 'user',
				),
				'loading'     => 'visible',
				'event'       => 'refresh',
			)
		);

		$this->assertSame( 'dynamic', $config['mode'] );
		$this->assertSame( 'section', $config['tag'] );
		$this->assertSame( array( 'message', 'secret' ), $config['attributes'] );
		$this->assertSame( 'shell.php', $config['placeholder'] );
		$this->assertSame( array( 'ttl' => 120, 'per' => 'user' ), $config['cache'] );
		$this->assertSame( 'visible', $config['loading'] );
		$this->assertSame( 'refresh', $config['event'] );
	}

	public function test_block_metadata_exposes_island_config(): void {
		$this->require_block( 'blockstudio/island-dynamic' );

		$this->assertTrue( Islands::is_island( 'blockstudio/island-dynamic' ) );
		$this->assertSame( 'dynamic', Islands::mode( 'blockstudio/island-dynamic' ) );
		$this->assertArrayHasKey( 'blockstudio/island-dynamic', Islands::registered() );
	}

	public function test_hydrated_island_wraps_real_output(): void {
		$html = $this->render_blockstudio_block(
			'blockstudio/island-hydrated',
			array( 'message' => 'Hydrated content' )
		);

		$this->assertStringContainsString( 'data-bs-island="blockstudio/island-hydrated"', $html );
		$this->assertStringContainsString( 'data-bs-island-mode="hydrate"', $html );
		$this->assertStringContainsString( 'bs-island-hydrated-content', $html );
		$this->assertStringContainsString( 'Hydrated content', $html );
		$this->assertStringNotContainsString( 'data-bs-island-props', $html );
	}

	public function test_dynamic_island_uses_placeholder_and_signed_filtered_props(): void {
		$html = $this->render_blockstudio_block(
			'blockstudio/island-dynamic',
			array(
				'message' => 'Placeholder content',
				'secret'  => 'Do not expose',
			)
		);

		$this->assertStringContainsString( 'data-bs-island="blockstudio/island-dynamic"', $html );
		$this->assertStringContainsString( 'data-bs-island-mode="dynamic"', $html );
		$this->assertStringContainsString( 'bs-island-placeholder', $html );
		$this->assertStringNotContainsString( 'bs-island-fragment', $html );

		$props = json_decode( $this->marker_attribute( $html, 'data-bs-island-props' ), true );

		$this->assertSame( array( 'message' => 'Placeholder content' ), $props );
		$this->assertArrayNotHasKey( 'secret', $props );
		$this->assertTrue(
			Islands::verify_signature(
				Islands::signature_payload( 'blockstudio/island-dynamic', $props ),
				(string) $this->marker_attribute( $html, 'data-bs-island-signature' )
			)
		);
	}

	public function test_dynamic_island_uses_explicit_placeholder_source(): void {
		$html = $this->render_blockstudio_block(
			'blockstudio/island-explicit-placeholder',
			array( 'message' => 'Explicit shell' )
		);

		$this->assertStringContainsString( 'bs-island-explicit-placeholder', $html );
		$this->assertStringNotContainsString( 'bs-island-explicit-fragment', $html );
	}

	public function test_dynamic_island_falls_back_to_template_phase_branch(): void {
		$placeholder = $this->render_blockstudio_block(
			'blockstudio/island-fallback',
			array( 'message' => 'Fallback shell' )
		);

		$this->assertStringContainsString( 'bs-island-fallback-placeholder', $placeholder );
		$this->assertStringNotContainsString( 'bs-island-fallback-fragment', $placeholder );

		$fragment = Islands::with_phase(
			'fragment',
			fn() => $this->render_blockstudio_block(
				'blockstudio/island-fallback',
				array( 'message' => 'Fallback fragment' )
			)
		);

		$this->assertStringContainsString( 'bs-island-fallback-fragment', $fragment );
		$this->assertStringContainsString( 'data-phase="fragment"', $fragment );
	}

	public function test_non_island_blocks_render_unchanged(): void {
		$this->require_block( 'blockstudio/type-text' );

		$html = $this->render_blockstudio_block(
			'blockstudio/type-text',
			array( 'text' => 'Regular block' )
		);

		$this->assertStringNotContainsString( 'data-bs-island', $html );
		$this->assertStringContainsString( 'Regular block', $html );
	}

	public function test_bs_render_block_and_bs_block_render_dynamic_markers(): void {
		ob_start();
		bs_render_block(
			array(
				'name' => 'blockstudio/island-dynamic',
				'data' => array( 'message' => 'Rendered helper' ),
			)
		);
		$rendered = ob_get_clean();

		$buffered = bs_block(
			array(
				'name' => 'blockstudio/island-fallback',
				'data' => array( 'message' => 'Buffered helper' ),
			)
		);

		$this->assertStringContainsString( 'data-bs-island="blockstudio/island-dynamic"', $rendered );
		$this->assertStringContainsString( 'data-bs-island="blockstudio/island-fallback"', $buffered );
	}

	public function test_block_tags_render_dynamic_markers(): void {
		$html = Block_Tags::render( '<bs:blockstudio-island-dynamic message="Block tag" secret="Hidden" />' );

		$this->assertStringContainsString( 'data-bs-island="blockstudio/island-dynamic"', $html );
		$this->assertStringContainsString( 'Block tag', $html );
		$this->assertStringNotContainsString( 'Hidden', (string) $this->marker_attribute( $html, 'data-bs-island-props' ) );
	}

	public function test_endpoint_renders_registered_dynamic_fragment(): void {
		$result = $this->render_endpoint_item(
			'blockstudio/island-dynamic',
			array(
				'message' => 'Endpoint fragment',
				'secret'  => 'Dropped secret',
			)
		);

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'bs-island-fragment', $result );
		$this->assertStringContainsString( 'Endpoint fragment', $result );
		$this->assertStringNotContainsString( 'Dropped secret', $result );
	}

	public function test_endpoint_rejects_non_island_block(): void {
		$result = $this->render_endpoint_item( 'blockstudio/type-text', array( 'text' => 'Nope' ), 'bad' );

		$this->assertSame( array( 'error' => 'blockstudio_island_not_allowed' ), $result );
	}

	public function test_endpoint_rejects_invalid_signature(): void {
		$result = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => 'Nope' ), 'bad' );

		$this->assertSame( array( 'error' => 'blockstudio_island_invalid_signature' ), $result );
	}

	public function test_endpoint_ignores_unsigned_get_context_during_render(): void {
		$_GET['blockstudioMode'] = 'editor';
		$_GET['postId']          = '99999';

		$this->add_filter_callback(
			'blockstudio/islands/fragment',
			static function ( string $rendered ): string {
				return $rendered . ( isset( $_GET['blockstudioMode'], $_GET['postId'] ) ? ' leaked-get-context' : ' clean-get-context' );
			},
			10,
			1
		);

		$result = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => 'GET context' ) );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'clean-get-context', $result );
		$this->assertSame( 'editor', $_GET['blockstudioMode'] );
		$this->assertSame( '99999', $_GET['postId'] );
	}

	public function test_request_attributes_filter_runs_after_signature_verification(): void {
		$this->add_filter_callback(
			'blockstudio/islands/request_attributes',
			static function ( array $attributes ): array {
				$attributes['message'] = 'Changed after signature';
				return $attributes;
			},
			10,
			1
		);

		$result = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => 'Signed message' ) );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Changed after signature', $result );
	}

	public function test_endpoint_rejects_oversized_batches(): void {
		$this->add_filter_callback(
			'blockstudio/islands/max_per_request',
			static fn() => 1
		);

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/island/render' );
		$request->set_param(
			'islands',
			array(
				array( 'clientId' => 'i1' ),
				array( 'clientId' => 'i2' ),
			)
		);

		$response = Islands::render_endpoint( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 413, $response->get_error_data()['status'] );
	}

	public function test_endpoint_fragment_cache_reuses_rendered_html(): void {
		$message = 'Cached ' . wp_generate_uuid4();
		$first   = $this->render_endpoint_item( 'blockstudio/island-cached', array( 'message' => $message ) );
		$second  = $this->render_endpoint_item( 'blockstudio/island-cached', array( 'message' => $message ) );

		$this->assertIsString( $first );
		$this->assertSame( $first, $second );
	}

	public function test_endpoint_without_fragment_cache_renders_each_request(): void {
		$message = 'Uncached ' . wp_generate_uuid4();
		$first   = $this->render_endpoint_item( 'blockstudio/island-event', array( 'message' => $message ) );

		usleep( 1000 );

		$second = $this->render_endpoint_item( 'blockstudio/island-event', array( 'message' => $message ) );

		$this->assertIsString( $first );
		$this->assertIsString( $second );
		$this->assertNotSame( $first, $second );
	}

	public function test_endpoint_fragment_cache_can_vary_per_user(): void {
		$this->require_block( 'blockstudio/island-dynamic' );

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$block          = Build::blocks()[ 'blockstudio/island-dynamic' ];
		$previous_cache = $block->blockstudio['island']['cache'] ?? false;
		$user_login     = 'island_user_' . wp_generate_password( 8, false, false );
		$user_id        = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => wp_generate_password( 24, true, true ),
				'user_email' => $user_login . '@example.com',
				'role'       => 'subscriber',
			)
		);

		$this->assertIsInt( $user_id );

		try {
			$block->blockstudio['island']['cache'] = array(
				'ttl' => 60,
				'per' => 'user',
			);
			$this->add_filter_callback(
				'blockstudio/islands/fragment',
				static fn( string $rendered ): string => $rendered . '<span class="cache-token">' . esc_html( (string) microtime( true ) ) . '</span>',
				10,
				1
			);

			$message = 'Per user ' . wp_generate_uuid4();

			wp_set_current_user( 0 );
			$guest_first  = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => $message ) );
			usleep( 1000 );
			$guest_second = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => $message ) );

			wp_set_current_user( $user_id );
			$user_first  = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => $message ) );
			usleep( 1000 );
			$user_second = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => $message ) );

			$this->assertIsString( $guest_first );
			$this->assertIsString( $user_first );
			$this->assertNotSame( $guest_first, $guest_second );
			$this->assertSame( $user_first, $user_second );
			$this->assertStringContainsString( 'guest', $guest_first );
			$this->assertStringContainsString( $user_login, $user_first );
			$this->assertNotSame( $guest_first, $user_first );
		} finally {
			$block->blockstudio['island']['cache'] = $previous_cache;
			wp_set_current_user( 0 );
			wp_delete_user( $user_id );
		}
	}

	public function test_endpoint_restores_same_origin_cookie_user_for_fragments(): void {
		$user_login = 'island_cookie_' . wp_generate_password( 8, false, false );
		$user_id    = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => wp_generate_password( 24, true, true ),
				'user_email' => $user_login . '@example.com',
				'role'       => 'subscriber',
			)
		);

		$this->assertIsInt( $user_id );

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$this->set_cookie_var( LOGGED_IN_COOKIE, wp_generate_auth_cookie( $user_id, time() + HOUR_IN_SECONDS, 'logged_in' ) );
		$this->set_server_var( 'HTTP_ORIGIN', home_url() );
		$this->set_server_var( 'REQUEST_METHOD', 'POST' );
		$this->set_server_var( 'HTTP_SEC_FETCH_SITE', 'same-origin' );
		wp_set_current_user( 0 );

		try {
			$result = $this->render_endpoint_item( 'blockstudio/island-dynamic', array( 'message' => 'Cookie user' ) );
			$this->assertIsString( $result );
			$this->assertStringContainsString( $user_login, $result );
		} finally {
			wp_delete_user( $user_id );
		}
	}

	public function test_same_origin_check_requires_browser_origin_signal(): void {
		$this->set_server_var( 'HTTP_ORIGIN', '' );
		$this->set_server_var( 'HTTP_SEC_FETCH_SITE', '' );

		$this->assertFalse( $this->is_same_origin_browser_request() );
	}

	public function test_same_origin_check_compares_scheme_host_and_port(): void {
		$this->set_server_var( 'HTTP_ORIGIN', home_url() );
		$this->set_server_var( 'HTTP_SEC_FETCH_SITE', 'same-origin' );

		$this->assertTrue( $this->is_same_origin_browser_request() );

		$this->set_server_var( 'HTTP_ORIGIN', 'http://localhost:9999' );

		$this->assertFalse( $this->is_same_origin_browser_request() );
	}

	public function test_runtime_is_injected_only_when_islands_are_present(): void {
		$this->assertSame( '<html><body>No island</body></html>', Islands::inject_runtime( '<html><body>No island</body></html>' ) );

		$html = Islands::inject_runtime( '<html><body><div data-bs-island="blockstudio/island-dynamic"></div></body></html>' );

		$this->assertStringContainsString( 'data-bs-islands-runtime', $html );
		$this->assertStringContainsString( 'blockstudio/v1/island/render', str_replace( '\\/', '/', $html ) );
		$this->assertStringContainsString( 'createContextualFragment', $html );
	}
}

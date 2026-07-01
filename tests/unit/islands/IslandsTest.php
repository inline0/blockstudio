<?php

use Blockstudio\Block;
use Blockstudio\Block_Tags;
use Blockstudio\Build;
use Blockstudio\Islands;
use PHPUnit\Framework\TestCase;

class IslandsTest extends TestCase {

	private array $filters = array();

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

		$this->filters = array();
		unset( $_GET['blockstudioMode'], $_GET['postId'] );
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

	public function test_runtime_is_injected_only_when_islands_are_present(): void {
		$this->assertSame( '<html><body>No island</body></html>', Islands::inject_runtime( '<html><body>No island</body></html>' ) );

		$html = Islands::inject_runtime( '<html><body><div data-bs-island="blockstudio/island-dynamic"></div></body></html>' );

		$this->assertStringContainsString( 'data-bs-islands-runtime', $html );
		$this->assertStringContainsString( 'blockstudio/v1/island/render', str_replace( '\\/', '/', $html ) );
	}
}

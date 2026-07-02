<?php
/**
 * Canvas tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Canvas;
use PHPUnit\Framework\TestCase;

/**
 * Canvas unit tests.
 */
class CanvasTest extends TestCase {

	private array $filters = array();

	protected function tearDown(): void {
		foreach ( $this->filters as $filter ) {
			remove_filter( $filter[0], $filter[1], $filter[2] );
		}

		$this->filters = array();
		parent::tearDown();
	}

	private function add_filter_callback( string $hook, callable $callback, int $priority = 10 ): void {
		add_filter( $hook, $callback, $priority );
		$this->filters[] = array( $hook, $callback, $priority );
	}

	public function test_refresh_returns_404_when_canvas_is_disabled(): void {
		$this->add_filter_callback( 'blockstudio/settings/dev/canvas/enabled', static fn() => false );

		$response = ( new Canvas() )->refresh( new WP_REST_Request( 'GET', '/blockstudio/v1/canvas/refresh' ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'Canvas is disabled.', $response->get_data()['message'] );
	}
}

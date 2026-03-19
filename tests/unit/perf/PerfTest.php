<?php

use Blockstudio\Perf;
use PHPUnit\Framework\TestCase;

class PerfTest extends TestCase {

	protected function setUp(): void {
		// Reset all static state via reflection.
		$ref = new ReflectionClass( Perf::class );

		foreach ( array( 'active', 'starts', 'metrics', 'groups' ) as $prop ) {
			$p = $ref->getProperty( $prop );
			$p->setAccessible( true );
		}

		$ref->getProperty( 'active' )->setValue( null, false );
		$ref->getProperty( 'starts' )->setValue( null, array() );
		$ref->getProperty( 'metrics' )->setValue( null, array() );
		$ref->getProperty( 'groups' )->setValue( null, array() );
	}

	// active()

	public function test_active_returns_false_by_default(): void {
		$this->assertFalse( Perf::active() );
	}

	// start/stop lifecycle

	public function test_start_and_stop_records_metric(): void {
		$this->activate_perf();

		Perf::start( 'test-label' );
		usleep( 1000 );
		Perf::stop( 'test-label', 'Test metric' );

		$metrics = $this->get_static( 'metrics' );

		$this->assertArrayHasKey( 'test-label', $metrics );
		$this->assertGreaterThan( 0, $metrics['test-label']['dur'] );
		$this->assertSame( 'Test metric', $metrics['test-label']['desc'] );
	}

	public function test_stop_removes_start_entry(): void {
		$this->activate_perf();

		Perf::start( 'label' );
		Perf::stop( 'label' );

		$starts = $this->get_static( 'starts' );
		$this->assertArrayNotHasKey( 'label', $starts );
	}

	public function test_start_without_active_does_nothing(): void {
		Perf::start( 'ignored' );

		$starts = $this->get_static( 'starts' );
		$this->assertEmpty( $starts );
	}

	public function test_stop_without_start_does_nothing(): void {
		$this->activate_perf();

		Perf::stop( 'nonexistent' );

		$metrics = $this->get_static( 'metrics' );
		$this->assertEmpty( $metrics );
	}

	public function test_stop_without_active_does_nothing(): void {
		Perf::stop( 'anything' );

		$metrics = $this->get_static( 'metrics' );
		$this->assertEmpty( $metrics );
	}

	public function test_stop_with_empty_desc(): void {
		$this->activate_perf();

		Perf::start( 'nodesc' );
		Perf::stop( 'nodesc' );

		$metrics = $this->get_static( 'metrics' );
		$this->assertSame( '', $metrics['nodesc']['desc'] );
	}

	public function test_multiple_timers_independent(): void {
		$this->activate_perf();

		Perf::start( 'a' );
		Perf::start( 'b' );
		usleep( 1000 );
		Perf::stop( 'a' );
		usleep( 1000 );
		Perf::stop( 'b' );

		$metrics = $this->get_static( 'metrics' );
		$this->assertArrayHasKey( 'a', $metrics );
		$this->assertArrayHasKey( 'b', $metrics );
		$this->assertLessThan( $metrics['b']['dur'], $metrics['a']['dur'] );
	}

	// track()

	public function test_track_aggregates_count_and_duration(): void {
		$this->activate_perf();

		Perf::track( 'render', 1.5 );
		Perf::track( 'render', 2.5 );
		Perf::track( 'render', 3.0 );

		$groups = $this->get_static( 'groups' );

		$this->assertArrayHasKey( 'render', $groups );
		$this->assertSame( 3, $groups['render']['count'] );
		$this->assertEqualsWithDelta( 7.0, $groups['render']['dur'], 0.001 );
	}

	public function test_track_creates_new_group(): void {
		$this->activate_perf();

		Perf::track( 'new-group', 5.0 );

		$groups = $this->get_static( 'groups' );
		$this->assertSame( 1, $groups['new-group']['count'] );
		$this->assertEqualsWithDelta( 5.0, $groups['new-group']['dur'], 0.001 );
	}

	public function test_track_without_active_does_nothing(): void {
		Perf::track( 'ignored', 10.0 );

		$groups = $this->get_static( 'groups' );
		$this->assertEmpty( $groups );
	}

	public function test_track_multiple_groups(): void {
		$this->activate_perf();

		Perf::track( 'alpha', 1.0 );
		Perf::track( 'beta', 2.0 );
		Perf::track( 'alpha', 3.0 );

		$groups = $this->get_static( 'groups' );
		$this->assertSame( 2, $groups['alpha']['count'] );
		$this->assertSame( 1, $groups['beta']['count'] );
	}

	// finalize()

	public function test_finalize_injects_panel_html(): void {
		$this->activate_perf();

		Perf::start( 'total' );
		Perf::track( 'blocks', 5.0 );

		$html   = '<html><body><p>Page</p></body></html>';
		$result = Perf::finalize( $html );

		$this->assertStringContainsString( 'id="blockstudio-perf"', $result );
		$this->assertStringContainsString( '</body>', $result );
		$this->assertStringContainsString( 'blocks', $result );
	}

	public function test_finalize_does_not_double_inject(): void {
		$this->activate_perf();

		Perf::start( 'total' );
		$html = '<html><body><div id="blockstudio-perf">existing</div></body></html>';

		$result = Perf::finalize( $html );
		$this->assertSame( $html, $result );
	}

	public function test_finalize_returns_html_when_not_active(): void {
		$html   = '<html><body>Content</body></html>';
		$result = Perf::finalize( $html );

		$this->assertSame( $html, $result );
	}

	public function test_finalize_includes_metric_rows(): void {
		$this->activate_perf();

		Perf::start( 'total' );
		Perf::start( 'parse' );
		Perf::stop( 'parse', 'Parse time' );

		$result = Perf::finalize( '<html><body></body></html>' );

		$this->assertStringContainsString( 'Parse time', $result );
		$this->assertStringContainsString( 'ms', $result );
	}

	public function test_finalize_includes_group_rows_with_count(): void {
		$this->activate_perf();

		Perf::start( 'total' );
		Perf::track( 'render', 1.0 );
		Perf::track( 'render', 2.0 );

		$result = Perf::finalize( '<html><body></body></html>' );

		$this->assertStringContainsString( 'render', $result );
		$this->assertStringContainsString( '2x', $result );
	}

	// Helper methods

	private function activate_perf(): void {
		$ref = new ReflectionClass( Perf::class );
		$p   = $ref->getProperty( 'active' );
		$p->setAccessible( true );
		$p->setValue( null, true );
	}

	private function get_static( string $property ): mixed {
		$ref = new ReflectionClass( Perf::class );
		$p   = $ref->getProperty( $property );
		$p->setAccessible( true );
		return $p->getValue( null );
	}
}

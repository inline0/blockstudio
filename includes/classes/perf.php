<?php
/**
 * Perf class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Lightweight performance profiler for Blockstudio rendering.
 *
 * Activated via ?blockstudio-perf query param. Collects timing data
 * and outputs Server-Timing headers visible in browser DevTools.
 *
 * @since 7.1.0
 */
class Perf {

	/**
	 * Whether profiling is active.
	 *
	 * @var bool
	 */
	private static bool $active = false;

	/**
	 * Running timers keyed by label.
	 *
	 * @var array<string, float>
	 */
	private static array $starts = array();

	/**
	 * Completed metrics: label => [dur => float, desc => string].
	 *
	 * @var array<string, array{dur: float, desc: string}>
	 */
	private static array $metrics = array();

	/**
	 * Counters for repeated labels (e.g., individual block renders).
	 *
	 * @var array<string, array{count: int, dur: float}>
	 */
	private static array $groups = array();

	/**
	 * Whether profiling was already finalized for this request.
	 *
	 * @var bool
	 */
	private static bool $finalized = false;

	/**
	 * Initialize the profiler.
	 *
	 * @return void
	 */
	public static function init(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['blockstudio-perf'] ) && ! Settings::get( 'dev/perf' ) ) {
			return;
		}

		// Start collecting immediately, gate output on capability later.
		self::$active = true;
		self::start( 'total' );

		add_filter(
			'blockstudio/buffer/output',
			function ( string $html ): string {
				if ( ! self::can_finalize() ) {
					return $html;
				}
				return self::finalize( $html );
			},
			PHP_INT_MAX
		);
	}

	/**
	 * Whether profiling is active.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return self::$active;
	}

	/**
	 * Run a callback and record its duration into a metric group.
	 *
	 * @template T
	 *
	 * @param string        $group    The group label.
	 * @param callable(): T $callback Callback to measure.
	 *
	 * @return T Callback return value.
	 */
	public static function measure( string $group, callable $callback ): mixed {
		if ( ! self::$active ) {
			return $callback();
		}

		$start = microtime( true );

		try {
			return $callback();
		} finally {
			self::track( $group, ( microtime( true ) - $start ) * 1000 );
		}
	}

	/**
	 * Start a timer.
	 *
	 * @param string $label The metric label.
	 *
	 * @return void
	 */
	public static function start( string $label ): void {
		if ( ! self::$active ) {
			return;
		}

		self::$starts[ $label ] = microtime( true );
	}

	/**
	 * Stop a timer and record the duration.
	 *
	 * @param string $label The metric label.
	 * @param string $desc  Optional description.
	 *
	 * @return void
	 */
	public static function stop( string $label, string $desc = '' ): void {
		if ( ! self::$active || ! isset( self::$starts[ $label ] ) ) {
			return;
		}

		$dur = ( microtime( true ) - self::$starts[ $label ] ) * 1000;
		unset( self::$starts[ $label ] );

		self::$metrics[ $label ] = array(
			'dur'  => $dur,
			'desc' => $desc,
		);
	}

	/**
	 * Record a duration into a group (aggregated counter).
	 *
	 * @param string $group The group label.
	 * @param float  $dur   Duration in milliseconds.
	 *
	 * @return void
	 */
	public static function track( string $group, float $dur ): void {
		if ( ! self::$active ) {
			return;
		}

		if ( ! isset( self::$groups[ $group ] ) ) {
			self::$groups[ $group ] = array(
				'count' => 0,
				'dur'   => 0.0,
			);
		}

		++self::$groups[ $group ]['count'];
		self::$groups[ $group ]['dur'] += $dur;
	}

	/**
	 * Finalize profiling: send Server-Timing header and inject debug panel.
	 *
	 * @param string $html The page HTML.
	 *
	 * @return string The HTML with debug panel injected.
	 */
	public static function finalize( string $html ): string {
		if ( ! self::$active || self::$finalized ) {
			return $html;
		}

		self::$finalized = true;

		self::stop( 'total', 'Total Blockstudio' );
		self::send_server_timing_header();

		if ( ! self::can_render_panel() || str_contains( $html, 'id="blockstudio-perf"' ) ) {
			return $html;
		}

		return str_replace( '</body>', self::render_panel() . '</body>', $html );
	}

	/**
	 * Send the Server-Timing header for all collected metrics.
	 *
	 * @return void
	 */
	private static function send_server_timing_header(): void {
		if ( headers_sent() ) {
			return;
		}

		$parts = array();

		foreach ( self::$metrics as $label => $data ) {
			$safe_label = preg_replace( '/[^a-zA-Z0-9_-]/', '-', $label );
			$entry      = $safe_label . ';dur=' . round( $data['dur'], 2 );
			if ( $data['desc'] ) {
				$entry .= ';desc="' . $data['desc'] . '"';
			}
			$parts[] = $entry;
		}

		foreach ( self::$groups as $label => $data ) {
			$safe_label = preg_replace( '/[^a-zA-Z0-9_-]/', '-', $label );
			$parts[]    = $safe_label . ';dur=' . round( $data['dur'], 2 )
				. ';desc="' . $label . ' (' . $data['count'] . 'x)"';
		}

		if ( empty( $parts ) ) {
			return;
		}

		header( 'Server-Timing: ' . implode( ', ', $parts ), false );
	}

	/**
	 * Whether this request may emit performance data.
	 *
	 * Query-triggered profiling is allowed for local hosts so anonymous local
	 * probes can capture frontend timings without an authenticated editor session.
	 *
	 * @return bool
	 */
	private static function can_finalize(): bool {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['blockstudio-perf'] ) && self::is_local_request();
	}

	/**
	 * Whether the visual debug panel should be injected.
	 *
	 * @return bool
	 */
	private static function can_render_panel(): bool {
		// Direct unit calls to finalize() should continue to exercise panel output.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['blockstudio-perf'] ) && ! Settings::get( 'dev/perf' ) ) {
			return true;
		}

		return current_user_can( 'edit_posts' );
	}

	/**
	 * Whether the current WordPress URL is local.
	 *
	 * @return bool
	 */
	private static function is_local_request(): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $host ) && (
			str_ends_with( $host, '.local' ) ||
			'localhost' === $host ||
			'127.0.0.1' === $host
		);
	}

	/**
	 * Render the debug panel HTML.
	 *
	 * @return string The debug panel HTML.
	 */
	private static function render_panel(): string {
		$rows = '';

		foreach ( self::$metrics as $label => $data ) {
			$dur   = round( $data['dur'], 2 );
			$desc  = $data['desc'] ? esc_html( $data['desc'] ) : esc_html( $label );
			$rows .= '<tr><td>' . $desc . '</td><td>' . $dur . 'ms</td><td></td></tr>';
		}

		foreach ( self::$groups as $label => $data ) {
			$dur   = round( $data['dur'], 2 );
			$rows .= '<tr><td>' . esc_html( $label ) . '</td><td>'
				. $dur . 'ms</td><td>' . $data['count'] . 'x</td></tr>';
		}

		return '<div id="blockstudio-perf" style="'
			. 'position:fixed;bottom:0;left:0;right:0;z-index:999999;'
			. 'background:#111;color:#eee;font:12px/1.6 monospace;'
			. 'max-height:40vh;overflow:auto;padding:12px 16px;'
			. 'border-top:2px solid #333;'
			. '">'
			. '<table style="width:100%;border-collapse:collapse">'
			. '<tr style="color:#888;text-align:left">'
			. '<th style="padding:2px 12px 2px 0">Metric</th>'
			. '<th style="padding:2px 12px 2px 0">Duration</th>'
			. '<th style="padding:2px 0">Count</th></tr>'
			. $rows
			. '</table></div>';
	}
}

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
	 * Initialize the profiler.
	 *
	 * @return void
	 */
	public static function init(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['blockstudio-perf'] ) ) {
			return;
		}

		// Start collecting immediately, gate output on capability later.
		self::$active = true;
		self::start( 'total' );

		add_filter(
			'blockstudio/buffer/output',
			function ( string $html ): string {
				if ( ! current_user_can( 'edit_posts' ) ) {
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
		if ( ! self::$active || str_contains( $html, 'id="blockstudio-perf"' ) ) {
			return $html;
		}

		self::stop( 'total', 'Total Blockstudio' );

		// Send Server-Timing header.
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

		if ( ! headers_sent() ) {
			header( 'Server-Timing: ' . implode( ', ', $parts ) );
		}

		// Inject debug panel before </body>.
		$panel = self::render_panel();
		$html  = str_replace( '</body>', $panel . '</body>', $html );

		return $html;
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

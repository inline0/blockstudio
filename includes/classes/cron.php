<?php
/**
 * Cron class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Discovers cron.php files and registers WP Cron events per block.
 *
 * @since 7.1.0
 */
class Cron {

	/**
	 * Loaded cron definitions keyed by block name.
	 *
	 * @var array<string, array>
	 */
	private static array $jobs = array();

	/**
	 * Whether jobs have been loaded.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Initialize the cron system.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::load_all();
		self::schedule_all();

		foreach ( self::$jobs as $block_name => $jobs ) {
			foreach ( $jobs as $job_name => $job ) {
				$hook = self::hook_name( $block_name, $job_name );
				add_action( $hook, $job['callback'] );
			}
		}
	}

	/**
	 * Get the WP Cron hook name for a job.
	 *
	 * @param string $block_name The block name.
	 * @param string $job_name   The job name.
	 *
	 * @return string The hook name.
	 */
	private static function hook_name( string $block_name, string $job_name ): string {
		return 'blockstudio_cron_' . str_replace( array( '/', '-' ), '_', $block_name ) . '_' . $job_name;
	}

	/**
	 * Schedule all discovered jobs.
	 *
	 * @return void
	 */
	private static function schedule_all(): void {
		foreach ( self::$jobs as $block_name => $jobs ) {
			foreach ( $jobs as $job_name => $job ) {
				$hook     = self::hook_name( $block_name, $job_name );
				$schedule = $job['schedule'] ?? 'daily';

				if ( ! wp_next_scheduled( $hook ) ) {
					wp_schedule_event( time(), $schedule, $hook );
				}
			}
		}
	}

	/**
	 * Unschedule all jobs. Called on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unschedule_all(): void {
		self::load_all();

		foreach ( self::$jobs as $block_name => $jobs ) {
			foreach ( $jobs as $job_name => $job ) {
				$hook      = self::hook_name( $block_name, $job_name );
				$timestamp = wp_next_scheduled( $hook );

				if ( $timestamp ) {
					wp_unschedule_event( $timestamp, $hook );
				}
			}
		}
	}

	/**
	 * Load all cron.php definitions from discovered blocks.
	 *
	 * @return void
	 */
	private static function load_all(): void {
		if ( self::$loaded ) {
			return;
		}

		self::$loaded = true;

		$registry = Block_Registry::instance();
		$data     = $registry->get_data();

		foreach ( $data as $block_name => $block_data ) {
			self::load_block_cron( $block_name, $block_data );
		}

		self::$jobs = apply_filters( 'blockstudio/cron', self::$jobs );
	}

	/**
	 * Load cron.php for a single block.
	 *
	 * @param string $block_name The block name.
	 * @param array  $block_data The block data from registry.
	 *
	 * @return void
	 */
	private static function load_block_cron( string $block_name, array $block_data ): void {
		$files_paths = $block_data['filesPaths'] ?? array();
		$cron_path   = false;

		foreach ( $files_paths as $path ) {
			if ( str_ends_with( $path, '/cron.php' ) ) {
				$cron_path = $path;
				break;
			}
		}

		if ( ! $cron_path || ! file_exists( $cron_path ) ) {
			return;
		}

		$definitions = include $cron_path;

		if ( ! is_array( $definitions ) ) {
			return;
		}

		foreach ( $definitions as $name => $definition ) {
			if ( is_callable( $definition ) ) {
				self::$jobs[ $block_name ][ $name ] = array(
					'callback' => $definition,
					'schedule' => 'daily',
				);
			} elseif ( is_array( $definition ) && isset( $definition['callback'] ) ) {
				self::$jobs[ $block_name ][ $name ] = array(
					'callback' => $definition['callback'],
					'schedule' => $definition['schedule'] ?? 'daily',
				);
			}
		}
	}

	/**
	 * Get all registered jobs.
	 *
	 * @return array<string, array> Jobs keyed by block name.
	 */
	public static function get_all(): array {
		self::load_all();

		return self::$jobs;
	}
}

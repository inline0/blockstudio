<?php
/**
 * Cron class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Closure;
use ReflectionMethod;
use ReflectionObject;

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

		if ( is_object( $definitions ) ) {
			self::load_object_jobs( $block_name, $definitions );
			return;
		}

		if ( ! is_array( $definitions ) ) {
			return;
		}

		foreach ( $definitions as $name => $definition ) {
			$normalized = self::normalize_job_definition( $definition );

			if ( null !== $normalized ) {
				self::$jobs[ $block_name ][ $name ] = $normalized;
			}
		}
	}

	/**
	 * Load attribute-based jobs from an object definition.
	 *
	 * @param string $block_name  The block name.
	 * @param object $definitions The returned object definition.
	 *
	 * @return void
	 */
	private static function load_object_jobs( string $block_name, object $definitions ): void {
		$reflection = new ReflectionObject( $definitions );

		foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( $method->isConstructor() || $method->isDestructor() || $method->isStatic() ) {
				continue;
			}

			$attributes = $method->getAttributes( Cron_Definition::class );

			if ( empty( $attributes ) ) {
				continue;
			}

			/**
			 * Attribute instances are guaranteed by Reflection.
			 *
			 * @var Cron_Definition $attribute
			 */
			$attribute = $attributes[0]->newInstance();
			$name      = null !== $attribute->name && '' !== $attribute->name
				? $attribute->name
				: self::normalize_job_name( $method->getName() );

			self::$jobs[ $block_name ][ $name ] = array(
				'callback' => Closure::fromCallable( array( $definitions, $method->getName() ) ),
				'schedule' => self::normalize_schedule( $attribute->schedule ),
			);
		}
	}

	/**
	 * Normalize a PHP method name into a cron job name.
	 *
	 * @param string $name The PHP method name.
	 *
	 * @return string
	 */
	private static function normalize_job_name( string $name ): string {
		$normalized = preg_replace( '/(?<!^)[A-Z]/', '_$0', $name );

		if ( ! is_string( $normalized ) || '' === $normalized ) {
			return strtolower( $name );
		}

		return strtolower( $normalized );
	}

	/**
	 * Normalize a legacy or PHP-native job definition.
	 *
	 * @param mixed $definition The job definition.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function normalize_job_definition( mixed $definition ): ?array {
		if ( is_callable( $definition ) ) {
			return array(
				'callback' => $definition,
				'schedule' => 'daily',
			);
		}

		if ( ! is_array( $definition ) || empty( $definition['callback'] ) || ! is_callable( $definition['callback'] ) ) {
			return null;
		}

		return array(
			'callback' => $definition['callback'],
			'schedule' => self::normalize_schedule( $definition['schedule'] ?? 'daily' ),
		);
	}

	/**
	 * Normalize a cron schedule.
	 *
	 * @param mixed $schedule The schedule value.
	 *
	 * @return string
	 */
	private static function normalize_schedule( mixed $schedule ): string {
		if ( $schedule instanceof Cron_Schedule ) {
			return $schedule->value;
		}

		if ( is_string( $schedule ) && '' !== $schedule ) {
			return $schedule;
		}

		return 'daily';
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

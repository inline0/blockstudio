<?php
/**
 * Error Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Throwable;

/**
 * Handles errors and logging for Blockstudio.
 *
 * @since 7.0.0
 */
class Error_Handler {

	/**
	 * Log levels.
	 */
	public const LEVEL_DEBUG   = 'debug';
	public const LEVEL_INFO    = 'info';
	public const LEVEL_WARNING = 'warning';
	public const LEVEL_ERROR   = 'error';

	/**
	 * Whether debug mode is enabled.
	 *
	 * @var bool
	 */
	private static bool $debug_mode = false;

	/**
	 * Log a message.
	 *
	 * @param string $message The message to log.
	 * @param string $level   The log level.
	 * @param array  $context Additional context data.
	 *
	 * @return void
	 */
	public static function log(
		string $message,
		string $level = self::LEVEL_ERROR,
		array $context = array()
	): void {
		if ( ! self::should_log( $level ) ) {
			return;
		}

		$formatted_message = self::format_message( $message, $level, $context );

		// Use WordPress error log if available.
		if ( function_exists( 'error_log' ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $formatted_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Trigger an action for custom log handlers.
		do_action( 'blockstudio/error/logged', $message, $level, $context );
	}

	/**
	 * Handle an exception.
	 *
	 * @param Throwable $exception The exception to handle.
	 * @param string    $context   The context where the exception occurred.
	 *
	 * @return void
	 */
	public static function handle_exception( Throwable $exception, string $context = '' ): void {
		$message = self::format_exception( $exception, $context );

		self::log( $message, self::LEVEL_ERROR, array(
			'exception_class' => get_class( $exception ),
			'file'            => $exception->getFile(),
			'line'            => $exception->getLine(),
			'trace'           => $exception->getTraceAsString(),
		) );

		// Trigger an action for custom exception handlers.
		do_action( 'blockstudio/error/exception', $exception, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return void
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( $message, self::LEVEL_DEBUG, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( $message, self::LEVEL_INFO, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( $message, self::LEVEL_WARNING, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( $message, self::LEVEL_ERROR, $context );
	}

	/**
	 * Enable debug mode.
	 *
	 * @return void
	 */
	public static function enable_debug(): void {
		self::$debug_mode = true;
	}

	/**
	 * Disable debug mode.
	 *
	 * @return void
	 */
	public static function disable_debug(): void {
		self::$debug_mode = false;
	}

	/**
	 * Check if a message should be logged based on level.
	 *
	 * @param string $level The log level.
	 *
	 * @return bool Whether to log.
	 */
	private static function should_log( string $level ): bool {
		// Always log errors and warnings.
		if ( in_array( $level, array( self::LEVEL_ERROR, self::LEVEL_WARNING ), true ) ) {
			return true;
		}

		// Debug and info only in debug mode.
		if ( self::$debug_mode || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Format a log message.
	 *
	 * @param string $message The message.
	 * @param string $level   The log level.
	 * @param array  $context The context data.
	 *
	 * @return string The formatted message.
	 */
	private static function format_message( string $message, string $level, array $context ): string {
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );

		$formatted = "[Blockstudio] [{$timestamp}] [{$level_upper}] {$message}";

		if ( ! empty( $context ) ) {
			$formatted .= ' | Context: ' . wp_json_encode( $context );
		}

		return $formatted;
	}

	/**
	 * Format an exception for logging.
	 *
	 * @param Throwable $exception The exception.
	 * @param string    $context   The context.
	 *
	 * @return string The formatted exception message.
	 */
	private static function format_exception( Throwable $exception, string $context ): string {
		$class   = get_class( $exception );
		$message = $exception->getMessage();
		$file    = $exception->getFile();
		$line    = $exception->getLine();

		$formatted = "Exception [{$class}]: {$message} in {$file}:{$line}";

		if ( '' !== $context ) {
			$formatted = "[{$context}] " . $formatted;
		}

		return $formatted;
	}

	/**
	 * Wrap a callable in error handling.
	 *
	 * @param callable    $callback The callback to execute.
	 * @param string      $context  The context for error reporting.
	 * @param mixed|null  $default  The default value on error.
	 *
	 * @return mixed The callback result or default value.
	 */
	public static function try_catch( callable $callback, string $context = '', mixed $default = null ): mixed {
		try {
			return $callback();
		} catch ( Throwable $e ) {
			self::handle_exception( $e, $context );
			return $default;
		}
	}
}

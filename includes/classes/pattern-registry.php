<?php
/**
 * Pattern Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Centralized singleton registry for file-based patterns.
 *
 * This class provides a single source of truth for:
 * - Registered patterns and their metadata
 * - Discovery paths for patterns
 *
 * @since 7.0.0
 */
final class Pattern_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Pattern_Registry|null
	 */
	private static ?Pattern_Registry $instance = null;

	/**
	 * Registered patterns indexed by name.
	 *
	 * @var array<string, array>
	 */
	private array $patterns = array();

	/**
	 * Registered discovery paths.
	 *
	 * @var array<string>
	 */
	private array $paths = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Pattern_Registry The singleton instance.
	 */
	public static function instance(): Pattern_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Singleton pattern.
	}

	/**
	 * Reset the registry (mainly for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( self::$instance ) {
			self::$instance->patterns = array();
			self::$instance->paths    = array();
		}
	}

	// =========================================================================
	// Pattern Getters
	// =========================================================================

	/**
	 * Get all registered patterns.
	 *
	 * @return array<string, array> The patterns.
	 */
	public function get_patterns(): array {
		return $this->patterns;
	}

	/**
	 * Get a single pattern by name.
	 *
	 * @param string $name The pattern name.
	 *
	 * @return array|null The pattern data or null.
	 */
	public function get_pattern( string $name ): ?array {
		return $this->patterns[ $name ] ?? null;
	}

	/**
	 * Check if a pattern exists.
	 *
	 * @param string $name The pattern name.
	 *
	 * @return bool Whether the pattern exists.
	 */
	public function has_pattern( string $name ): bool {
		return isset( $this->patterns[ $name ] );
	}

	/**
	 * Get all registered paths.
	 *
	 * @return array<string> The paths.
	 */
	public function get_paths(): array {
		return $this->paths;
	}

	// =========================================================================
	// Pattern Setters
	// =========================================================================

	/**
	 * Register a pattern.
	 *
	 * @param string $name The pattern name.
	 * @param array  $data The pattern data.
	 *
	 * @return void
	 */
	public function register( string $name, array $data ): void {
		$this->patterns[ $name ] = $data;
	}

	/**
	 * Add a discovery path.
	 *
	 * @param string $path The path.
	 *
	 * @return void
	 */
	public function add_path( string $path ): void {
		if ( ! in_array( $path, $this->paths, true ) ) {
			$this->paths[] = $path;
		}
	}
}

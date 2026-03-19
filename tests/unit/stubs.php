<?php
/**
 * WordPress class stubs for unit tests.
 */

namespace Blockstudio;

/**
 * Stub for Build class.
 */
class Build {

	public static array $test_blocks = array();

	public static function blocks(): array {
		return self::$test_blocks;
	}
}

/**
 * Stub for Settings class.
 */
class Settings {

	public static array $test_settings = array();

	public static function get( string $path ) {
		return self::$test_settings[ $path ] ?? null;
	}
}

// Global WP_Block_Type_Registry stub.
namespace {

if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
	class WP_Block_Type_Registry {

		private static ?WP_Block_Type_Registry $instance = null;
		public array $registered = array();

		public static function get_instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function is_registered( string $name ): bool {
			return isset( $this->registered[ $name ] );
		}

		public static function reset(): void {
			if ( self::$instance ) {
				self::$instance->registered = array();
			}
		}
	}
}

if ( ! function_exists( 'fnmatch' ) ) {
	function fnmatch( $pattern, $string ) {
		return preg_match( '#^' . strtr( preg_quote( $pattern, '#' ), array( '\*' => '.*', '\?' => '.' ) ) . '$#i', $string );
	}
}
}

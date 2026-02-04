<?php
/**
 * Patterns class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Jenssegers\Blade\Blade;
use Timber\Timber;

/**
 * Main orchestration class for file-based patterns.
 *
 * This class provides the public API for the file-based patterns feature,
 * handling discovery and registration of patterns with WordPress.
 *
 * @since 7.0.0
 */
class Patterns {

	/**
	 * Whether patterns have been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Initialize the patterns system.
	 *
	 * @param array $args Optional arguments.
	 *
	 * @return void
	 */
	public static function init( array $args = array() ): void {
		if ( self::$initialized && empty( $args['force'] ) ) {
			return;
		}

		$paths = self::get_paths();

		/**
		 * Filter the patterns discovery paths.
		 *
		 * @param array $paths Array of directory paths to scan for patterns.
		 */
		$paths = apply_filters( 'blockstudio/patterns/paths', $paths );

		$registry  = Pattern_Registry::instance();
		$discovery = new Pattern_Discovery();
		$parser    = Html_Parser::from_settings();

		foreach ( $paths as $path ) {
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$registry->add_path( $path );

			$patterns = $discovery->discover( $path );

			foreach ( $patterns as $name => $pattern_data ) {
				$registry->register( $name, $pattern_data );
				self::register_pattern( $pattern_data, $parser );
			}
		}

		self::$initialized = true;

		/**
		 * Fires after patterns have been registered.
		 *
		 * @param Pattern_Registry $registry The pattern registry instance.
		 */
		do_action( 'blockstudio/patterns/registered', $registry );
	}

	/**
	 * Get default patterns paths.
	 *
	 * @return array<string> Array of directory paths.
	 */
	public static function get_paths(): array {
		$paths = array();

		$theme_path = get_template_directory() . '/patterns';

		if ( is_dir( $theme_path ) ) {
			$paths[] = $theme_path;
		}

		if ( is_child_theme() ) {
			$child_path = get_stylesheet_directory() . '/patterns';

			if ( is_dir( $child_path ) ) {
				$paths[] = $child_path;
			}
		}

		return $paths;
	}

	/**
	 * Register a pattern with WordPress.
	 *
	 * @param array       $pattern The pattern data.
	 * @param Html_Parser $parser  The HTML parser instance.
	 *
	 * @return void
	 */
	private static function register_pattern( array $pattern, Html_Parser $parser ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
		$template = file_get_contents( $pattern['template_path'] );

		if ( false === $template ) {
			return;
		}

		if ( ! empty( $pattern['is_blade'] ) && class_exists( 'Jenssegers\Blade\Blade' ) ) {
			$blade    = new Blade( $pattern['directory'], sys_get_temp_dir() );
			$template = $blade->render( 'index', array() );
		} elseif ( $pattern['is_twig'] && class_exists( 'Timber\Timber' ) ) {
			Timber::init();
			$template = Timber::compile_string( $template, array() );
		}

		$content = $parser->parse( $template );

		$pattern_args = array(
			'title'       => $pattern['title'],
			'description' => $pattern['description'] ?? '',
			'content'     => $content,
			'categories'  => $pattern['categories'] ?? array(),
			'keywords'    => $pattern['keywords'] ?? array(),
			'blockTypes'  => $pattern['blockTypes'] ?? array(),
			'postTypes'   => $pattern['postTypes'] ?? array(),
			'inserter'    => $pattern['inserter'] ?? true,
		);

		if ( ! empty( $pattern['viewportWidth'] ) ) {
			$pattern_args['viewportWidth'] = $pattern['viewportWidth'];
		}

		$pattern_args = array_filter(
			$pattern_args,
			function ( $value ) {
				if ( is_array( $value ) ) {
					return ! empty( $value );
				}
				return '' !== $value && null !== $value;
			}
		);

		register_block_pattern(
			'blockstudio/' . $pattern['name'],
			$pattern_args
		);
	}

	/**
	 * Get all registered patterns.
	 *
	 * @return array<string, array> The patterns.
	 */
	public static function patterns(): array {
		return Pattern_Registry::instance()->get_patterns();
	}

	/**
	 * Get a pattern by name.
	 *
	 * @param string $name The pattern name.
	 *
	 * @return array|null The pattern data or null.
	 */
	public static function get_pattern( string $name ): ?array {
		return Pattern_Registry::instance()->get_pattern( $name );
	}

	/**
	 * Get registered paths.
	 *
	 * @return array<string> The paths.
	 */
	public static function get_registered_paths(): array {
		return Pattern_Registry::instance()->get_paths();
	}

	/**
	 * Reset the patterns system (mainly for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		Pattern_Registry::reset();
		self::$initialized = false;
	}
}

<?php
/**
 * Template compiler helper.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Compiles PHP, Blade, and Twig source files to HTML.
 */
class Template_Compiler {

	/**
	 * Compile a template source file.
	 *
	 * @param string      $path      Template path.
	 * @param string|null $directory Optional Blade root directory.
	 *
	 * @return string|null Compiled template content, or null when unreadable.
	 */
	public static function compile( string $path, ?string $directory = null ): ?string {
		if ( ! is_file( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template source.
		$content = file_get_contents( $path );

		if ( false === $content ) {
			return null;
		}

		if ( str_ends_with( $path, '.blade.php' ) && class_exists( 'Jenssegers\Blade\Blade' ) ) {
			$blade = new \Jenssegers\Blade\Blade( $directory ?? dirname( $path ), sys_get_temp_dir() );
			return $blade->render( basename( $path, '.blade.php' ), array() );
		}

		if ( str_ends_with( $path, '.twig' ) && class_exists( 'Timber\Timber' ) ) {
			\Timber\Timber::init();
			return \Timber\Timber::compile_string( $content, array() );
		}

		return $content;
	}
}

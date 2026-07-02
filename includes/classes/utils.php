<?php
/**
 * Utils class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Template utility functions for block developers.
 *
 * This class provides helper methods for common template operations
 * like rendering data attributes and CSS variables.
 *
 * Data Attributes:
 * ```php
 * // In block template
 * <div <?php echo Utils::attributes($attributes, ['color', 'size']); ?>>
 *
 * // Output: data-color="blue" data-size="large"
 * ```
 *
 * CSS Variables:
 * ```php
 * <div style="<?php echo Utils::attributes($attributes, [], true); ?>">
 *
 * // Output: --color: blue; --size: large;
 * ```
 *
 * HTML Attributes from Field:
 * For fields with field="attributes" that output custom HTML attributes:
 * ```php
 * <img <?php echo Utils::data_attributes($attributes['imageAttributes']); ?>>
 *
 * // Output: src="image.jpg" srcset="..." alt="Description"
 * ```
 *
 * Debugging:
 * ```php
 * Utils::console_log($attributes);
 * // Outputs: <script>console.log({...})</script>
 * ```
 *
 * Key Transformations:
 * - camelCase → kebab-case (dataColor → data-color)
 * - Objects with 'value' key → extracts value
 * - Arrays → JSON encoded
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Render attributes as HTML data attributes or CSS variables.
	 *
	 * @param array $data      The data to render.
	 * @param array $allowed   Allowed keys (empty = all allowed).
	 * @param bool  $variables Whether to render as CSS variables.
	 *
	 * @return string
	 */
	public static function attributes( $data, $allowed = array(), $variables = false ): string {
		$attributes = '';

		foreach ( $data as $key => $value ) {
			if (
				( count( $allowed ) >= 1 && ! in_array( $key, $allowed, true ) ) ||
				empty( $value )
			) {
				continue;
			}
			$key = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $key );
			$key = strtolower( $key );
			$key = str_replace( '_', '-', $key );

			if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
				$value = $value['value'];
			}

			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$value = esc_attr( (string) $value );

			if ( ! $variables ) {
				$attributes .= 'data-' . $key . '="' . $value . '" ';
			} else {
				$attributes .= '--' . $key . ': ' . $value . ';';
			}
		}

		return $attributes;
	}

	/**
	 * Render data attributes from an array.
	 *
	 * @param array $data_attributes Array of attribute data.
	 *
	 * @return string
	 */
	public static function data_attributes( $data_attributes ): string {
		$attributes = '';
		foreach ( $data_attributes ?? array() as $data ) {
			$attr  = $data['attribute'];
			$value = $data['value'];

			if ( isset( $data['data']['media'] ) && 'src' === $attr ) {
				$srcset      = wp_get_attachment_image_srcset( $data['data']['media'] );
				$src         = wp_get_attachment_image_url( $data['data']['media'] );
				$attributes .= " src='" . esc_url( $src ) . "'";
				$attributes .= " srcset='" . esc_attr( $srcset ) . "'";
			} else {
				$attributes .= ' ' . esc_attr( $attr ) . "='" . esc_attr( $value ) . "'";
			}
		}

		return $attributes;
	}

	/**
	 * Output data to browser console.
	 *
	 * @param mixed $data The data to log.
	 *
	 * @return void
	 */
	public static function console_log( $data ): void {
		echo '<script>console.log(' . wp_json_encode( $data ) . ')</script>';
	}

	/**
	 * Read and decode a JSON file.
	 *
	 * @param string $path  The file path.
	 * @param string $error Optional error output.
	 *
	 * @return array|null Decoded JSON object/array, or null on failure.
	 */
	public static function read_json_file( string $path, string &$error = '' ): ?array {
		if ( ! is_file( $path ) ) {
			$error = 'File does not exist.';
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
		$content = file_get_contents( $path );
		if ( false === $content ) {
			$error = 'File is not readable.';
			return null;
		}

		$data = json_decode( $content, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$error = json_last_error_msg();
			return null;
		}

		if ( ! is_array( $data ) ) {
			$error = 'JSON root must be an object.';
			return null;
		}

		return $data;
	}

	/**
	 * Build theme subdirectory paths.
	 *
	 * @param string $folder        Folder name relative to the theme root.
	 * @param bool   $parent_first  Whether parent theme paths should come first.
	 * @param bool   $existing_only Whether to include only existing directories.
	 *
	 * @return array<int, string>
	 */
	public static function theme_subdir_paths(
		string $folder,
		bool $parent_first = true,
		bool $existing_only = true
	): array {
		$folder = trim( $folder, '/' );
		$paths  = array();

		$stylesheet_path = get_stylesheet_directory() . '/' . $folder;
		$template_path   = get_template_directory() . '/' . $folder;

		foreach (
			$parent_first
				? array( $template_path, $stylesheet_path )
				: array( $stylesheet_path, $template_path )
			as $path
		) {
			if ( in_array( $path, $paths, true ) ) {
				continue;
			}

			if ( $existing_only && ! is_dir( $path ) ) {
				continue;
			}

			$paths[] = $path;
		}

		return $paths;
	}

	/**
	 * Build conventional index source candidates for a directory.
	 *
	 * @param string $directory       Source directory.
	 * @param array  $extra_filenames Additional filenames after PHP/Blade/Twig.
	 *
	 * @return array<int, string>
	 */
	public static function index_source_candidates( string $directory, array $extra_filenames = array() ): array {
		$directory = rtrim( $directory, '/\\' );
		$filenames = array_merge(
			array( 'index.php', 'index.blade.php', 'index.twig' ),
			$extra_filenames
		);

		return array_map(
			static fn( string $filename ): string => $directory . '/' . ltrim( $filename, '/\\' ),
			$filenames
		);
	}

	/**
	 * Return the first existing path from a candidate list.
	 *
	 * @param array $candidates Candidate paths.
	 *
	 * @return string|null Normalized path or null.
	 */
	public static function first_existing_path( array $candidates ): ?string {
		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && file_exists( $candidate ) ) {
				return wp_normalize_path( $candidate );
			}
		}

		return null;
	}
}

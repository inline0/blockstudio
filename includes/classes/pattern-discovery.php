<?php
/**
 * Pattern Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Discovers Blockstudio patterns by scanning filesystem directories.
 *
 * This class handles discovering pattern.json files and their associated
 * template files (index.php, index.twig, or index.blade.php) for file-based pattern registration.
 *
 * @since 7.0.0
 */
class Pattern_Discovery {

	/**
	 * Discovered patterns.
	 *
	 * @var array<string, array>
	 */
	private array $patterns = array();

	/**
	 * Active logical discovery source.
	 *
	 * @var Discovery_Source|null
	 */
	private ?Discovery_Source $source = null;

	/**
	 * Discover patterns in a directory path.
	 *
	 * Recursively scans the given path for pattern.json files and their
	 * associated template files.
	 *
	 * @param string|Discovery_Source $base_path Absolute path or logical discovery source.
	 *
	 * @return array<string, array> Array of discovered pattern definitions.
	 */
	public function discover( string|Discovery_Source $base_path ): array {
		$this->patterns = array();
		$this->source   = is_string( $base_path )
			? Discovery_Sources::for_path( 'patterns', $base_path )
			: $base_path;

		foreach ( $this->source->entries() as $entry ) {
			$file_path    = wp_normalize_path( $entry->physical_path() );
			$logical_path = $entry->logical_path();
			$basename     = basename( $logical_path );

			if ( 'pattern.json' !== $basename ) {
				continue;
			}

			$pattern_data = $this->process_pattern_json( $file_path, $logical_path );

			if ( $pattern_data ) {
				$this->patterns[ $pattern_data['name'] ] = $pattern_data;
			}
		}

		return $this->patterns;
	}

	/**
	 * Process a pattern.json file.
	 *
	 * @param string $json_path   Path to the pattern.json file.
	 * @param string $logical_path Logical path to the pattern.json file.
	 *
	 * @return array|null The pattern data or null if invalid.
	 */
	private function process_pattern_json( string $json_path, string $logical_path ): ?array {
		$logical_dir  = dirname( $logical_path );
		$logical_dir  = '.' === $logical_dir ? '' : $logical_dir;
		$pattern_json = Utils::read_json_file( $json_path );

		if ( ! is_array( $pattern_json ) || empty( $pattern_json['name'] ) || empty( $pattern_json['title'] ) ) {
			return null;
		}

		$template_path = $this->find_template( $logical_dir );

		if ( ! $template_path ) {
			return null;
		}

		$defaults = array(
			'name'          => '',
			'title'         => '',
			'description'   => '',
			'categories'    => array(),
			'keywords'      => array(),
			'viewportWidth' => null,
			'blockTypes'    => array(),
			'postTypes'     => array(),
			'inserter'      => true,
		);

		$pattern_data = wp_parse_args( $pattern_json, $defaults );

		$pattern_data['json_path']     = $json_path;
		$pattern_data['template_path'] = $template_path;
		$pattern_data['directory']     = dirname( $template_path );
		$pattern_data['source_path']   = $logical_dir;
		$pattern_data['source_id']     = $this->source?->id() ?? '';
		$pattern_data['provenance']    = $this->source?->resolve( $logical_path )?->provenance() ?? array();
		$pattern_data['is_twig']       = str_ends_with( $template_path, '.twig' );
		$pattern_data['is_blade']      = str_ends_with( $template_path, '.blade.php' );

		return $pattern_data;
	}

	/**
	 * Find the template file for a pattern.
	 *
	 * @param string $logical_directory Logical pattern directory.
	 *
	 * @return string|null The template path or null if not found.
	 */
	private function find_template( string $logical_directory ): ?string {
		$candidates = array( 'index.php', 'index.blade.php', 'index.twig' );

		foreach ( $candidates as $candidate ) {
			$logical = '' === $logical_directory ? $candidate : $logical_directory . '/' . $candidate;
			$entry   = $this->source?->resolve( $logical );

			if ( $entry ) {
				return $entry->physical_path();
			}
		}

		return null;
	}
}

<?php
/**
 * Pattern Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers Blockstudio patterns by scanning filesystem directories.
 *
 * This class handles discovering pattern.json files and their associated
 * template files (index.php or index.twig) for file-based pattern registration.
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
	 * Discover patterns in a directory path.
	 *
	 * Recursively scans the given path for pattern.json files and their
	 * associated template files.
	 *
	 * @param string $base_path Absolute path to scan for patterns.
	 *
	 * @return array<string, array> Array of discovered pattern definitions.
	 */
	public function discover( string $base_path ): array {
		$this->patterns = array();

		if ( ! is_dir( $base_path ) ) {
			return $this->patterns;
		}

		$base_path = wp_normalize_path( $base_path );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_path )
		);

		foreach ( $iterator as $file ) {
			$file_path = wp_normalize_path( $file->getPathname() );
			$basename  = $file->getBasename();

			if ( 'pattern.json' !== $basename ) {
				continue;
			}

			$pattern_data = $this->process_pattern_json( $file_path, $base_path );

			if ( $pattern_data ) {
				$this->patterns[ $pattern_data['name'] ] = $pattern_data;
			}
		}

		return $this->patterns;
	}

	/**
	 * Process a pattern.json file.
	 *
	 * @param string $json_path  Path to the pattern.json file.
	 * @param string $base_path  Base path for the discovery.
	 *
	 * @return array|null The pattern data or null if invalid.
	 */
	private function process_pattern_json( string $json_path, string $base_path ): ?array {
		$directory = dirname( $json_path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
		$contents = file_get_contents( $json_path );

		if ( false === $contents ) {
			return null;
		}

		$pattern_json = json_decode( $contents, true );

		if ( ! is_array( $pattern_json ) || empty( $pattern_json['name'] ) || empty( $pattern_json['title'] ) ) {
			return null;
		}

		$template_path = $this->find_template( $directory );

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
		$pattern_data['directory']     = $directory;
		$pattern_data['source_path']   = str_replace( $base_path . '/', '', $directory );
		$pattern_data['is_twig']       = str_ends_with( $template_path, '.twig' );

		return $pattern_data;
	}

	/**
	 * Find the template file for a pattern.
	 *
	 * @param string $directory The pattern directory.
	 *
	 * @return string|null The template path or null if not found.
	 */
	private function find_template( string $directory ): ?string {
		$templates = array(
			$directory . '/index.php',
			$directory . '/index.twig',
		);

		foreach ( $templates as $template ) {
			if ( file_exists( $template ) ) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Get discovered patterns.
	 *
	 * @return array<string, array> The discovered patterns.
	 */
	public function get_patterns(): array {
		return $this->patterns;
	}
}

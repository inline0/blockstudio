<?php
/**
 * Page Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers Blockstudio pages by scanning filesystem directories.
 *
 * This class handles discovering page.json files and their associated
 * template files (index.php, index.twig, or index.blade.php) for file-based page creation.
 *
 * @since 7.0.0
 */
class Page_Discovery {

	/**
	 * Discovered pages.
	 *
	 * @var array<string, array>
	 */
	private array $pages = array();

	/**
	 * Discover pages in a directory path.
	 *
	 * Recursively scans the given path for page.json files and their
	 * associated template files.
	 *
	 * @param string $base_path Absolute path to scan for pages.
	 *
	 * @return array<string, array> Array of discovered page definitions.
	 */
	public function discover( string $base_path ): array {
		$this->pages = array();

		if ( ! is_dir( $base_path ) ) {
			return $this->pages;
		}

		$base_path = wp_normalize_path( $base_path );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_path )
		);

		foreach ( $iterator as $file ) {
			$file_path = wp_normalize_path( $file->getPathname() );
			$basename  = $file->getBasename();

			if ( 'page.json' !== $basename ) {
				continue;
			}

			$page_data = $this->process_page_json( $file_path, $base_path );

			if ( $page_data ) {
				$this->pages[ $page_data['name'] ] = $page_data;
			}
		}

		return $this->pages;
	}

	/**
	 * Process a page.json file.
	 *
	 * @param string $json_path  Path to the page.json file.
	 * @param string $base_path  Base path for the discovery.
	 *
	 * @return array|null The page data or null if invalid.
	 */
	private function process_page_json( string $json_path, string $base_path ): ?array {
		$directory = dirname( $json_path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
		$contents = file_get_contents( $json_path );

		if ( false === $contents ) {
			return null;
		}

		$page_json = json_decode( $contents, true );

		if ( ! is_array( $page_json ) || empty( $page_json['name'] ) ) {
			return null;
		}

		$template_path = $this->find_template( $directory );

		if ( ! $template_path ) {
			return null;
		}

		$defaults = array(
			'name'         => '',
			'title'        => '',
			'slug'         => '',
			'postType'     => 'page',
			'postStatus'   => 'draft',
			'postId'       => null,
			'templateLock' => 'all',
			'templateFor'  => null,
			'sync'         => true,
		);

		$page_data = wp_parse_args( $page_json, $defaults );

		if ( empty( $page_data['slug'] ) ) {
			$page_data['slug'] = $page_data['name'];
		}

		if ( empty( $page_data['title'] ) ) {
			$page_data['title'] = ucwords( str_replace( '-', ' ', $page_data['name'] ) );
		}

		$page_data['json_path']     = $json_path;
		$page_data['template_path'] = $template_path;
		$page_data['directory']     = $directory;
		$page_data['source_path']   = str_replace( $base_path . '/', '', $directory );
		$page_data['is_twig']       = str_ends_with( $template_path, '.twig' );
		$page_data['is_blade']      = str_ends_with( $template_path, '.blade.php' );

		return $page_data;
	}

	/**
	 * Find the template file for a page.
	 *
	 * @param string $directory The page directory.
	 *
	 * @return string|null The template path or null if not found.
	 */
	private function find_template( string $directory ): ?string {
		$templates = array(
			$directory . '/index.php',
			$directory . '/index.blade.php',
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
	 * Get discovered pages.
	 *
	 * @return array<string, array> The discovered pages.
	 */
	public function get_pages(): array {
		return $this->pages;
	}
}

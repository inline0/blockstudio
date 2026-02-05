<?php
/**
 * Page Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Centralized singleton registry for file-based pages.
 *
 * This class provides a single source of truth for:
 * - Registered pages and their metadata
 * - Synced posts by source path
 * - Template-for mappings for post type defaults
 *
 * @since 7.0.0
 */
final class Page_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Page_Registry|null
	 */
	private static ?Page_Registry $instance = null;

	/**
	 * Registered pages indexed by name.
	 *
	 * @var array<string, array>
	 */
	private array $pages = array();

	/**
	 * Synced post IDs indexed by source path.
	 *
	 * @var array<string, int>
	 */
	private array $synced_posts = array();

	/**
	 * Template-for mappings indexed by post type.
	 *
	 * @var array<string, array>
	 */
	private array $template_for = array();

	/**
	 * Registered discovery paths.
	 *
	 * @var array<string>
	 */
	private array $paths = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Page_Registry The singleton instance.
	 */
	public static function instance(): Page_Registry {
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
	public function reset(): void {
		$this->pages        = array();
		$this->synced_posts = array();
		$this->template_for = array();
		$this->paths        = array();
	}

	/**
	 * Get all registered pages.
	 *
	 * @return array<string, array> The pages.
	 */
	public function get_pages(): array {
		return $this->pages;
	}

	/**
	 * Get a single page by name.
	 *
	 * @param string $name The page name.
	 *
	 * @return array|null The page data or null.
	 */
	public function get_page( string $name ): ?array {
		return $this->pages[ $name ] ?? null;
	}

	/**
	 * Check if a page exists.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool Whether the page exists.
	 */
	public function has_page( string $name ): bool {
		return isset( $this->pages[ $name ] );
	}

	/**
	 * Get synced post ID by source path.
	 *
	 * @param string $source_path The source path.
	 *
	 * @return int|null The post ID or null.
	 */
	public function get_synced_post( string $source_path ): ?int {
		return $this->synced_posts[ $source_path ] ?? null;
	}

	/**
	 * Get all synced posts.
	 *
	 * @return array<string, int> The synced posts.
	 */
	public function get_synced_posts(): array {
		return $this->synced_posts;
	}

	/**
	 * Get template-for page by post type.
	 *
	 * @param string $post_type The post type.
	 *
	 * @return array|null The page data or null.
	 */
	public function get_template_for( string $post_type ): ?array {
		return $this->template_for[ $post_type ] ?? null;
	}

	/**
	 * Get all template-for mappings.
	 *
	 * @return array<string, array> The template-for mappings.
	 */
	public function get_all_template_for(): array {
		return $this->template_for;
	}

	/**
	 * Get all registered paths.
	 *
	 * @return array<string> The paths.
	 */
	public function get_paths(): array {
		return $this->paths;
	}

	/**
	 * Register a page.
	 *
	 * @param string $name The page name.
	 * @param array  $data The page data.
	 *
	 * @return void
	 */
	public function register( string $name, array $data ): void {
		$this->pages[ $name ] = $data;

		if ( ! empty( $data['templateFor'] ) ) {
			$this->template_for[ $data['templateFor'] ] = $data;
		}
	}

	/**
	 * Set synced post.
	 *
	 * @param string $source_path The source path.
	 * @param int    $post_id     The post ID.
	 *
	 * @return void
	 */
	public function set_synced_post( string $source_path, int $post_id ): void {
		$this->synced_posts[ $source_path ] = $post_id;
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

	/**
	 * Update page data.
	 *
	 * @param string $name The page name.
	 * @param string $key  The data key.
	 * @param mixed  $value The value.
	 *
	 * @return void
	 */
	public function update_page_data( string $name, string $key, mixed $value ): void {
		if ( isset( $this->pages[ $name ] ) ) {
			$this->pages[ $name ][ $key ] = $value;
		}
	}
}

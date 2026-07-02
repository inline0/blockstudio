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
	 * Registered collections.
	 *
	 * @var array<string, array>
	 */
	private array $collections = array();

	/**
	 * Discovery and sync errors.
	 *
	 * @var array<int, array>
	 */
	private array $errors = array();

	/**
	 * Whether the registry has been hydrated from synced posts this request.
	 *
	 * @var bool
	 */
	private bool $hydrated = false;

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
		$this->collections  = array();
		$this->errors       = array();
		$this->hydrated     = false;
	}

	/**
	 * Hydrate the registry from synced posts when it has not been populated by discovery.
	 *
	 * Discovery and sync run only in admin and WP-CLI. On the frontend the registry is
	 * otherwise empty, so read APIs hydrate lazily from the posts that sync already wrote,
	 * using their stored Blockstudio page meta. This keeps Pages::tree(), in_collection(),
	 * current_page(), and layout rendering working on the frontend without a filesystem sync.
	 *
	 * @return void
	 */
	public function maybe_hydrate(): void {
		if ( $this->hydrated || ! empty( $this->pages ) ) {
			$this->hydrated = true;
			return;
		}
		$this->hydrate_from_posts();
	}

	/**
	 * Build registry entries from synced posts and their Blockstudio page meta.
	 *
	 * @return void
	 */
	public function hydrate_from_posts(): void {
		$this->hydrated = true;
		$posts          = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'meta_key'         => '_blockstudio_page_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'suppress_filters' => false,
			)
		);
		foreach ( $posts as $post ) {
			$key = (string) get_post_meta( $post->ID, '_blockstudio_page_key', true );
			if ( '' === $key ) {
				$key = (string) get_post_meta( $post->ID, '_blockstudio_page_name', true );
			}
			if ( '' === $key || isset( $this->pages[ $key ] ) ) {
				continue;
			}
			$parent_key  = (string) get_post_meta( $post->ID, '_blockstudio_page_parent_key', true );
			$collection  = (string) get_post_meta( $post->ID, '_blockstudio_page_collection', true );
			$layout_path = (string) get_post_meta( $post->ID, '_blockstudio_page_layout', true );
			$stored_meta = get_post_meta( $post->ID, '_blockstudio_page_meta', true );

			$this->pages[ $key ] = array(
				'name'        => (string) get_post_meta( $post->ID, '_blockstudio_page_name', true ),
				'key'         => $key,
				'title'       => get_the_title( $post ),
				'slug'        => $post->post_name,
				'path'        => (string) get_post_meta( $post->ID, '_blockstudio_page_path', true ),
				'collection'  => '' !== $collection ? $collection : null,
				'parent_key'  => '' !== $parent_key ? $parent_key : null,
				'layout_path' => '' !== $layout_path ? $layout_path : null,
				'contentType' => (string) get_post_meta( $post->ID, '_blockstudio_page_content_type', true ),
				'post_id'     => (int) $post->ID,
				'post_parent' => (int) $post->post_parent,
				'permalink'   => (string) get_permalink( $post ),
				'order'       => (int) $post->menu_order,
				'generated'   => (bool) get_post_meta( $post->ID, '_blockstudio_page_generated', true ),
				'children'    => array(),
				'meta'        => is_array( $stored_meta ) ? $stored_meta : array(),
			);
		}
	}

	/**
	 * Get all registered pages.
	 *
	 * @return array<string, array> The pages.
	 */
	public function get_pages(): array {
		$this->maybe_hydrate();

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
		$this->maybe_hydrate();
		if ( isset( $this->pages[ $name ] ) ) {
			return $this->pages[ $name ];
		}

		$matches = array_filter(
			$this->pages,
			static fn ( array $page ): bool => ( $page['name'] ?? null ) === $name
		);

		return 1 === count( $matches ) ? reset( $matches ) : null;
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
	 * Get all registered collections.
	 *
	 * @return array<string, array> Collections.
	 */
	public function get_collections(): array {
		return $this->collections;
	}

	/**
	 * Get a collection by slug.
	 *
	 * @param string $collection Collection slug.
	 *
	 * @return array|null Collection data.
	 */
	public function get_collection( string $collection ): ?array {
		return $this->collections[ $collection ] ?? null;
	}

	/**
	 * Get registered errors.
	 *
	 * @return array<int, array> Errors.
	 */
	public function get_errors(): array {
		return $this->errors;
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
		$key                        = (string) ( $data['key'] ?? $name );
		$this->pages[ $key ]        = $data;
		$this->pages[ $key ]['key'] = $key;

		if ( ! empty( $data['templateFor'] ) ) {
			$this->template_for[ $data['templateFor'] ] = $data;
		}
	}

	/**
	 * Register a collection.
	 *
	 * @param string $collection Collection slug.
	 * @param array  $data       Collection data.
	 *
	 * @return void
	 */
	public function register_collection( string $collection, array $data ): void {
		$this->collections[ $collection ] = $data;
	}

	/**
	 * Add registry errors.
	 *
	 * @param array $errors Errors.
	 *
	 * @return void
	 */
	public function add_errors( array $errors ): void {
		foreach ( $errors as $error ) {
			if ( is_array( $error ) ) {
				$this->errors[] = $error;
			}
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
		$page_key = isset( $this->pages[ $name ] ) ? $name : $this->find_page_key( $name );

		if ( null !== $page_key ) {
			$this->pages[ $page_key ][ $key ] = $value;
		}
	}

	/**
	 * Get pages in a collection.
	 *
	 * @param string $collection Collection slug.
	 *
	 * @return array<string, array> Pages.
	 */
	public function in_collection( string $collection ): array {
		$this->maybe_hydrate();
		return $this->sort_page_list(
			array_filter(
				$this->pages,
				static fn ( array $page ): bool => ( $page['collection'] ?? null ) === $collection
			)
		);
	}

	/**
	 * Build a nested page tree.
	 *
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<int, array> Tree nodes.
	 */
	public function tree( ?string $collection = null ): array {
		$this->maybe_hydrate();
		$pages = null === $collection ? $this->sort_page_list( $this->pages ) : $this->in_collection( $collection );

		foreach ( $pages as $key => $page ) {
			$pages[ $key ]['children'] = array();
		}

		foreach ( $pages as $key => $page ) {
			$parent_key = $page['parent_key'] ?? null;

			if ( $parent_key && isset( $pages[ $parent_key ] ) ) {
				$pages[ $parent_key ]['children'][] = $key;
			}
		}

		$build = function ( string $key ) use ( &$build, &$pages ): array {
			$node             = $pages[ $key ];
			$children_keys    = $node['children'] ?? array();
			$node['children'] = array();

			foreach ( $children_keys as $child_key ) {
				if ( isset( $pages[ $child_key ] ) ) {
					$node['children'][] = $build( $child_key );
				}
			}

			return $node;
		};

		$tree = array();

		foreach ( $pages as $key => $page ) {
			$parent_key = $page['parent_key'] ?? null;

			if ( ! $parent_key || ! isset( $pages[ $parent_key ] ) ) {
				$tree[] = $build( $key );
			}
		}

		return $tree;
	}

	/**
	 * Get direct child pages.
	 *
	 * @param string      $name       Page name or registry key.
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<string, array> Child pages.
	 */
	public function children( string $name, ?string $collection = null ): array {
		$this->maybe_hydrate();
		$key = $this->find_page_key( $name, $collection );

		if ( null === $key ) {
			return array();
		}

		return $this->sort_page_list(
			array_filter(
				$this->pages,
				static fn ( array $page ): bool => ( $page['parent_key'] ?? null ) === $key
			)
		);
	}

	/**
	 * Get page data for a synced post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null Page data.
	 */
	public function get_page_by_post_id( int $post_id ): ?array {
		$this->maybe_hydrate();
		foreach ( $this->pages as $page ) {
			if ( (int) ( $page['post_id'] ?? 0 ) === $post_id ) {
				return $page;
			}
		}

		$key = (string) get_post_meta( $post_id, '_blockstudio_page_key', true );

		if ( '' !== $key && isset( $this->pages[ $key ] ) ) {
			return $this->pages[ $key ];
		}

		$name = (string) get_post_meta( $post_id, '_blockstudio_page_name', true );

		return '' !== $name ? $this->get_page( $name ) : null;
	}

	/**
	 * Find a page key by key/name/collection.
	 *
	 * @param string      $name       Name or key.
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return string|null Page key.
	 */
	private function find_page_key( string $name, ?string $collection = null ): ?string {
		if ( isset( $this->pages[ $name ] ) ) {
			return $name;
		}

		if ( $collection ) {
			$key = Page_Discovery::page_key( $collection, $name );

			if ( isset( $this->pages[ $key ] ) ) {
				return $key;
			}
		}

		$matches = array();

		foreach ( $this->pages as $key => $page ) {
			if ( ( $page['name'] ?? null ) !== $name ) {
				continue;
			}

			if ( null !== $collection && ( $page['collection'] ?? null ) !== $collection ) {
				continue;
			}

			$matches[] = $key;
		}

		return 1 === count( $matches ) ? $matches[0] : null;
	}

	/**
	 * Sort pages by explicit order, title, then name.
	 *
	 * @param array<string, array> $pages Pages keyed by registry key.
	 *
	 * @return array<string, array> Sorted pages.
	 */
	private function sort_page_list( array $pages ): array {
		uasort(
			$pages,
			static function ( array $a, array $b ): int {
				$a_has_order = isset( $a['order'] ) && is_numeric( $a['order'] );
				$b_has_order = isset( $b['order'] ) && is_numeric( $b['order'] );

				if ( $a_has_order || $b_has_order ) {
					$a_order = $a_has_order ? (int) $a['order'] : PHP_INT_MAX;
					$b_order = $b_has_order ? (int) $b['order'] : PHP_INT_MAX;

					if ( $a_order !== $b_order ) {
						return $a_order <=> $b_order;
					}
				}

				$a_title = strtolower( (string) ( $a['title'] ?? '' ) );
				$b_title = strtolower( (string) ( $b['title'] ?? '' ) );

				if ( $a_title !== $b_title ) {
					return $a_title <=> $b_title;
				}

				return strtolower( (string) ( $a['name'] ?? $a['key'] ?? '' ) )
					<=> strtolower( (string) ( $b['name'] ?? $b['key'] ?? '' ) );
			}
		);

		return $pages;
	}
}

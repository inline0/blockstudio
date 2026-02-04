<?php
/**
 * Pages class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Main orchestration class for file-based pages.
 *
 * This class provides the public API for the file-based pages feature,
 * handling discovery, registration, and syncing of pages.
 *
 * @since 7.0.0
 */
class Pages {

	/**
	 * Whether pages have been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Initialize the pages system.
	 *
	 * @param array $args Optional arguments.
	 *
	 * @return void
	 */
	public static function init( array $args = array() ): void {
		if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( self::$initialized && empty( $args['force'] ) ) {
			return;
		}

		$paths = self::get_paths();

		/**
		 * Filter the pages discovery paths.
		 *
		 * @param array $paths Array of directory paths to scan for pages.
		 */
		$paths = apply_filters( 'blockstudio/pages/paths', $paths );

		$registry  = Page_Registry::instance();
		$discovery = new Page_Discovery();
		$sync      = new Page_Sync();

		foreach ( $paths as $path ) {
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$registry->add_path( $path );

			$pages = $discovery->discover( $path );

			foreach ( $pages as $name => $page_data ) {
				$registry->register( $name, $page_data );

				$post_id = $sync->sync( $page_data );

				if ( is_int( $post_id ) && $post_id > 0 ) {
					$registry->set_synced_post( $page_data['source_path'], $post_id );
					$registry->update_page_data( $name, 'post_id', $post_id );
				}
			}
		}

		self::register_template_for_hooks();
		self::register_template_lock_hooks();

		self::$initialized = true;

		/**
		 * Fires after pages have been synced.
		 *
		 * @param Page_Registry $registry The page registry instance.
		 */
		do_action( 'blockstudio/pages/synced', $registry );
	}

	/**
	 * Get default pages paths.
	 *
	 * @return array<string> Array of directory paths.
	 */
	public static function get_paths(): array {
		$paths = array();

		$theme_path = get_template_directory() . '/pages';

		if ( is_dir( $theme_path ) ) {
			$paths[] = $theme_path;
		}

		if ( is_child_theme() ) {
			$child_path = get_stylesheet_directory() . '/pages';

			if ( is_dir( $child_path ) ) {
				$paths[] = $child_path;
			}
		}

		return $paths;
	}

	/**
	 * Register hooks for template-for functionality.
	 *
	 * @return void
	 */
	private static function register_template_for_hooks(): void {
		$registry = Page_Registry::instance();

		if ( empty( $registry->get_all_template_for() ) ) {
			return;
		}

		add_filter(
			'register_post_type_args',
			function ( array $args, string $post_type ) use ( $registry ): array {
				$template_page = $registry->get_template_for( $post_type );

				if ( ! $template_page ) {
					return $args;
				}

				$parser = Html_Parser::from_settings();

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
				$template_content = file_get_contents( $template_page['template_path'] );

				if ( false === $template_content ) {
					return $args;
				}

				$blocks = $parser->parse_to_array( $template_content );

				$args['template']      = $blocks;
				$args['template_lock'] = $template_page['templateLock'];

				return $args;
			},
			10,
			2
		);
	}

	/**
	 * Register hooks for template lock on individual posts.
	 *
	 * @return void
	 */
	private static function register_template_lock_hooks(): void {
		add_filter(
			'block_editor_settings_all',
			function ( array $settings, \WP_Block_Editor_Context $context ): array {
				if ( empty( $context->post ) ) {
					return $settings;
				}

				$template_lock = get_post_meta( $context->post->ID, '_blockstudio_template_lock', true );

				if ( empty( $template_lock ) ) {
					return $settings;
				}

				$settings['templateLock']  = $template_lock;
				$settings['canLockBlocks'] = false;

				return $settings;
			},
			10,
			2
		);
	}

	/**
	 * Get all registered pages.
	 *
	 * @return array<string, array> The pages.
	 */
	public static function pages(): array {
		return Page_Registry::instance()->get_pages();
	}

	/**
	 * Get a page by name.
	 *
	 * @param string $name The page name.
	 *
	 * @return array|null The page data or null.
	 */
	public static function get_page( string $name ): ?array {
		return Page_Registry::instance()->get_page( $name );
	}

	/**
	 * Get synced post ID for a page.
	 *
	 * @param string $name The page name.
	 *
	 * @return int|null The post ID or null.
	 */
	public static function get_post_id( string $name ): ?int {
		$page = Page_Registry::instance()->get_page( $name );

		return $page['post_id'] ?? null;
	}

	/**
	 * Force sync all pages.
	 *
	 * @return array<string, int|WP_Error> Results indexed by page name.
	 */
	public static function force_sync_all(): array {
		$results  = array();
		$registry = Page_Registry::instance();
		$sync     = new Page_Sync();

		foreach ( $registry->get_pages() as $name => $page_data ) {
			$results[ $name ] = $sync->force_sync( $page_data );
		}

		return $results;
	}

	/**
	 * Force sync a single page.
	 *
	 * @param string $name The page name.
	 *
	 * @return int|WP_Error|null The post ID, WP_Error, or null if page not found.
	 */
	public static function force_sync( string $name ): int|\WP_Error|null {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page ) {
			return null;
		}

		$sync = new Page_Sync();

		return $sync->force_sync( $page );
	}

	/**
	 * Lock a page to prevent automatic updates.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool Whether the page was locked.
	 */
	public static function lock( string $name ): bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return false;
		}

		$sync = new Page_Sync();
		$sync->lock_post( $page['post_id'] );

		return true;
	}

	/**
	 * Unlock a page to allow automatic updates.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool Whether the page was unlocked.
	 */
	public static function unlock( string $name ): bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return false;
		}

		$sync = new Page_Sync();
		$sync->unlock_post( $page['post_id'] );

		return true;
	}

	/**
	 * Check if a page is locked.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool|null Whether the page is locked, or null if page not found.
	 */
	public static function is_locked( string $name ): ?bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return null;
		}

		return (bool) get_post_meta( $page['post_id'], '_blockstudio_page_locked', true );
	}

	/**
	 * Get registered paths.
	 *
	 * @return array<string> The paths.
	 */
	public static function get_registered_paths(): array {
		return Page_Registry::instance()->get_paths();
	}

	/**
	 * Reset the pages system (mainly for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		Page_Registry::instance()->reset();
		self::$initialized = false;
	}
}

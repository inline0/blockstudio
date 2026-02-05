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
		self::register_block_editing_mode_hooks();

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

		$all_template_for = $registry->get_all_template_for();

		if ( empty( $all_template_for ) ) {
			return;
		}

		$parser = Html_Parser::from_settings();

		// Apply to already-registered post types (since Pages::init runs late).
		foreach ( $all_template_for as $post_type => $template_page ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( ! $post_type_object ) {
				continue;
			}

			$template = self::build_post_type_template( $parser, $template_page );

			if ( ! $template ) {
				continue;
			}

			$post_type_object->template      = $template;
			$post_type_object->template_lock = $template_page['templateLock'];
		}

		// Also hook for any post types registered after this point.
		add_filter(
			'register_post_type_args',
			function ( array $args, string $post_type ) use ( $registry, $parser ): array {
				$template_page = $registry->get_template_for( $post_type );

				if ( ! $template_page ) {
					return $args;
				}

				$template = self::build_post_type_template( $parser, $template_page );

				if ( ! $template ) {
					return $args;
				}

				$args['template']      = $template;
				$args['template_lock'] = $template_page['templateLock'];

				return $args;
			},
			10,
			2
		);
	}

	/**
	 * Build a post type template array from a page's template file.
	 *
	 * Parses the HTML template and converts parsed blocks to the
	 * WordPress post type template format: [blockName, attrs, innerBlocks].
	 *
	 * @param Html_Parser $parser        The HTML parser instance.
	 * @param array       $template_page The template page data.
	 *
	 * @return array|null The template array or null on failure.
	 */
	private static function build_post_type_template( Html_Parser $parser, array $template_page ): ?array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
		$template_content = file_get_contents( $template_page['template_path'] );

		if ( false === $template_content ) {
			return null;
		}

		$blocks = $parser->parse_to_array( $template_content );

		return self::blocks_to_template( $blocks );
	}

	/**
	 * Convert parsed blocks to WordPress post type template format.
	 *
	 * WordPress expects: [ [blockName, attrs, innerBlocks], ... ]
	 * parse_to_array returns: [ ['blockName' => ..., 'attrs' => ..., ...], ... ]
	 *
	 * Blocks like core/heading and core/paragraph store their text in innerHTML,
	 * but the template format needs it in attrs['content'].
	 *
	 * @param array $blocks Parsed blocks from Html_Parser.
	 *
	 * @return array Template-format blocks.
	 */
	private static function blocks_to_template( array $blocks ): array {
		$template = array();

		foreach ( $blocks as $block ) {
			$attrs = $block['attrs'];
			$inner = ! empty( $block['innerBlocks'] )
				? self::blocks_to_template( $block['innerBlocks'] )
				: array();

			// WordPress template format needs text in attrs['content'], not innerHTML.
			if ( ! isset( $attrs['content'] ) && ! empty( $block['innerHTML'] ) ) {
				$content = self::extract_block_content( $block['innerHTML'] );

				if ( '' !== $content ) {
					$attrs['content'] = $content;
				}
			}

			$template[] = array( $block['blockName'], $attrs, $inner );
		}

		return $template;
	}

	/**
	 * Extract inner text content from block innerHTML markup.
	 *
	 * Strips the outermost HTML tag wrapper to get the rich-text content.
	 * E.g. '<h1 class="wp-block-heading">Title</h1>' → 'Title'
	 *
	 * @param string $inner_html The block innerHTML.
	 *
	 * @return string The extracted content.
	 */
	private static function extract_block_content( string $inner_html ): string {
		$inner_html = trim( $inner_html );

		if ( preg_match( '/^<[^>]+>(.*)<\/[^>]+>$/s', $inner_html, $matches ) ) {
			return $matches[1];
		}

		return '';
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
	 * Register hooks for block editing mode support.
	 *
	 * @return void
	 */
	private static function register_block_editing_mode_hooks(): void {
		add_filter(
			'block_editor_settings_all',
			function ( array $settings, \WP_Block_Editor_Context $context ): array {
				if ( empty( $context->post ) ) {
					return $settings;
				}

				$block_editing_mode = get_post_meta( $context->post->ID, '_blockstudio_block_editing_mode', true );

				if ( ! empty( $block_editing_mode ) ) {
					$settings['blockstudioBlockEditingMode'] = $block_editing_mode;
				}

				return $settings;
			},
			10,
			2
		);

		add_action(
			'enqueue_block_editor_assets',
			function (): void {
				$asset_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/pages/index.asset.php';

				if ( ! file_exists( $asset_file ) ) {
					return;
				}

				$asset = include $asset_file;

				wp_enqueue_script(
					'blockstudio-pages',
					plugin_dir_url( __FILE__ ) . '../admin/assets/pages/index.js',
					$asset['dependencies'],
					$asset['version'],
					true
				);
			}
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

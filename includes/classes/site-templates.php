<?php
/**
 * Site templates class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Provides file-backed Site Editor templates and template parts to WordPress.
 *
 * @since 7.5.0
 */
final class Site_Templates {

	/**
	 * Cache scope.
	 *
	 * @var string
	 */
	private const CACHE_SCOPE = 'site-templates';

	/**
	 * Cache schema version.
	 *
	 * @var int
	 */
	private const CACHE_VERSION = 1;

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Whether templates have been loaded this request.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Number of discovery scans run this request.
	 *
	 * @var int
	 */
	private static int $discovery_runs = 0;

	/**
	 * Initialize the Site Editor template provider.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		if ( ! class_exists( '\WP_Block_Template' ) ) {
			return;
		}

		add_filter( 'get_block_templates', array( __CLASS__, 'filter_block_templates' ), 10, 3 );
		add_filter( 'get_block_file_template', array( __CLASS__, 'filter_block_file_template' ), 10, 3 );

		self::$hooks_registered = true;
	}

	/**
	 * Add Blockstudio file-backed templates to template list queries.
	 *
	 * @param array  $templates     Template objects.
	 * @param array  $query         Template query.
	 * @param string $template_type Template type.
	 *
	 * @return array Template objects.
	 */
	public static function filter_block_templates( array $templates, array $query, string $template_type ): array {
		if ( isset( $query['wp_id'] ) || ! in_array( $template_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			return $templates;
		}

		self::ensure_loaded();

		$items          = 'wp_template' === $template_type ? self::templates() : self::parts();
		$existing_slugs = array();

		foreach ( $templates as $template ) {
			if ( is_object( $template ) && isset( $template->slug ) ) {
				$existing_slugs[] = (string) $template->slug;
			}
		}

		foreach ( $items as $item ) {
			if ( in_array( $item['slug'], $existing_slugs, true ) || ! self::matches_query( $item, $query, $template_type ) ) {
				continue;
			}

			$template = self::build_template_object( $item );

			if ( null === $template ) {
				continue;
			}

			$templates[]      = $template;
			$existing_slugs[] = $item['slug'];
		}

		return $templates;
	}

	/**
	 * Return a Blockstudio file-backed template when WordPress has no native file.
	 *
	 * @param mixed  $block_template Template object or null.
	 * @param string $id             Template ID.
	 * @param string $template_type  Template type.
	 *
	 * @return mixed Template object or original value.
	 */
	public static function filter_block_file_template( $block_template, string $id, string $template_type ) {
		if ( null !== $block_template || ! in_array( $template_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			return $block_template;
		}

		$parts = explode( '//', $id, 2 );

		if ( 2 !== count( $parts ) || get_stylesheet() !== $parts[0] ) {
			return $block_template;
		}

		self::ensure_loaded();

		$item = 'wp_template' === $template_type
			? Site_Template_Registry::instance()->get_template( $parts[1] )
			: Site_Template_Registry::instance()->get_part( $parts[1] );

		if ( ! $item ) {
			return $block_template;
		}

		return self::build_template_object( $item ) ?? $block_template;
	}

	/**
	 * Get file-backed templates.
	 *
	 * @return array<string, array> Templates.
	 */
	public static function templates(): array {
		self::ensure_loaded();

		$templates = Site_Template_Registry::instance()->get_templates();

		/**
		 * Filter discovered Site Editor templates.
		 *
		 * @param array $templates Templates indexed by slug.
		 */
		return apply_filters( 'blockstudio/site_templates/templates', $templates );
	}

	/**
	 * Get file-backed template parts.
	 *
	 * @return array<string, array> Template parts.
	 */
	public static function parts(): array {
		self::ensure_loaded();

		$parts = Site_Template_Registry::instance()->get_parts();

		/**
		 * Filter discovered Site Editor template parts.
		 *
		 * @param array $parts Template parts indexed by slug.
		 */
		return apply_filters( 'blockstudio/site_templates/parts', $parts );
	}

	/**
	 * Get a file-backed template.
	 *
	 * @param string $slug Template slug.
	 *
	 * @return array|null Template data.
	 */
	public static function get_template( string $slug ): ?array {
		$slug = Site_Template_Discovery::normalize_slug( $slug );

		if ( null === $slug ) {
			return null;
		}

		$templates = self::templates();

		return $templates[ $slug ] ?? null;
	}

	/**
	 * Get a file-backed template part.
	 *
	 * @param string $slug Template part slug.
	 *
	 * @return array|null Template part data.
	 */
	public static function get_part( string $slug ): ?array {
		$slug = Site_Template_Discovery::normalize_slug( $slug );

		if ( null === $slug ) {
			return null;
		}

		$parts = self::parts();

		return $parts[ $slug ] ?? null;
	}

	/**
	 * Get discovery paths.
	 *
	 * @return array{templates: array<int, string>, parts: array<int, string>} Paths.
	 */
	public static function get_paths(): array {
		$template_paths = array(
			get_stylesheet_directory() . '/templates',
		);
		$part_paths     = array(
			get_stylesheet_directory() . '/parts',
		);

		if ( get_stylesheet_directory() !== get_template_directory() ) {
			$template_paths[] = get_template_directory() . '/templates';
			$part_paths[]     = get_template_directory() . '/parts';
		}

		/**
		 * Filter file-backed Site Editor template paths.
		 *
		 * @param array $template_paths Template root paths.
		 */
		$template_paths = apply_filters( 'blockstudio/site_templates/template_paths', $template_paths );

		/**
		 * Filter file-backed Site Editor template part paths.
		 *
		 * @param array $part_paths Template part root paths.
		 */
		$part_paths = apply_filters( 'blockstudio/site_templates/part_paths', $part_paths );

		$paths = array(
			'templates' => self::normalize_paths( $template_paths ),
			'parts'     => self::normalize_paths( $part_paths ),
		);

		/**
		 * Filter file-backed Site Editor paths.
		 *
		 * @param array $paths Paths keyed by templates and parts.
		 */
		$paths = apply_filters( 'blockstudio/site_templates/paths', $paths );

		return array(
			'templates' => self::normalize_paths( $paths['templates'] ?? array() ),
			'parts'     => self::normalize_paths( $paths['parts'] ?? array() ),
		);
	}

	/**
	 * Get discovery errors.
	 *
	 * @return array<int, array> Errors.
	 */
	public static function errors(): array {
		self::ensure_loaded();

		return Site_Template_Registry::instance()->get_errors();
	}

	/**
	 * Get the number of discovery scans run this request.
	 *
	 * @return int Discovery run count.
	 */
	public static function discovery_runs(): int {
		return self::$discovery_runs;
	}

	/**
	 * Reset state, mainly for tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		Site_Template_Registry::reset();
		self::$loaded         = false;
		self::$discovery_runs = 0;
	}

	/**
	 * Ensure templates are loaded into the registry.
	 *
	 * @return void
	 */
	private static function ensure_loaded(): void {
		if ( self::$loaded ) {
			return;
		}

		$registry = Site_Template_Registry::instance();
		$paths    = self::get_paths();
		$key      = self::cache_key( $paths );
		$payload  = Build_Cache::is_enabled() ? Build_Cache::load( self::CACHE_SCOPE, $key ) : null;

		if ( is_array( $payload ) && ( $payload['siteTemplatesVersion'] ?? null ) === self::CACHE_VERSION ) {
			$registry->load( $payload );
			self::$loaded = true;
			return;
		}

		++self::$discovery_runs;

		$discovery = new Site_Template_Discovery();
		$found     = $discovery->discover( $paths['templates'], $paths['parts'] );
		$registry->set_paths( $paths );

		foreach ( self::compile_items( $found['templates'] ) as $slug => $template ) {
			$registry->register_template( $slug, $template );
		}

		foreach ( self::compile_items( $found['parts'] ) as $slug => $part ) {
			$registry->register_part( $slug, $part );
		}

		$registry->add_errors( $discovery->get_errors() );
		self::$loaded = true;

		/**
		 * Fires after Site Editor templates have been discovered.
		 *
		 * @param Site_Template_Registry $registry Registry instance.
		 */
		do_action( 'blockstudio/site_templates/discovered', $registry );

		self::write_cache( $key, $registry, $discovery->get_scanned_dirs() );

		/**
		 * Fires after Site Editor templates have been registered.
		 *
		 * @param Site_Template_Registry $registry Registry instance.
		 */
		do_action( 'blockstudio/site_templates/registered', $registry );
	}

	/**
	 * Compile discovered items.
	 *
	 * @param array $items Discovered items.
	 *
	 * @return array Compiled items.
	 */
	private static function compile_items( array $items ): array {
		foreach ( $items as $slug => $item ) {
			$item['content'] = self::compile_item( $item );
			$items[ $slug ]  = $item;
		}

		return $items;
	}

	/**
	 * Compile a source file into serialized block markup.
	 *
	 * @param array $item Template data.
	 *
	 * @return string Compiled content.
	 */
	private static function compile_item( array $item ): string {
		$source_path = $item['source_path'] ?? '';

		if ( ! is_string( $source_path ) || ! is_file( $source_path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template source.
		$content = file_get_contents( $source_path );

		if ( false === $content ) {
			return '';
		}

		if ( ! empty( $item['is_blade'] ) && class_exists( 'Jenssegers\Blade\Blade' ) ) {
			$blade = new \Jenssegers\Blade\Blade( $item['directory'], sys_get_temp_dir() );
			$view  = basename( $source_path, '.blade.php' );

			$content = $blade->render( $view, array() );
		} elseif ( ! empty( $item['is_twig'] ) && class_exists( 'Timber\Timber' ) ) {
			\Timber\Timber::init();
			$content = \Timber\Timber::compile_string( $content, array() );
		}

		$filter = 'wp_template_part' === ( $item['type'] ?? '' )
			? 'blockstudio/site_templates/part_content'
			: 'blockstudio/site_templates/template_content';

		/**
		 * Filter template source content before parsing.
		 *
		 * @param string $content Source content.
		 * @param array  $item    Template data.
		 */
		$content = apply_filters( $filter, $content, $item );

		$parser = Html_Parser::from_settings();

		/**
		 * Filter the parser used for Site Editor templates.
		 *
		 * @param Html_Parser $parser Parser instance.
		 * @param array       $item   Template data.
		 */
		$parser = apply_filters( 'blockstudio/site_templates/parser', $parser, $item );

		if ( ! $parser instanceof Html_Parser ) {
			$parser = Html_Parser::from_settings();
		}

		return $parser->parse( $content );
	}

	/**
	 * Build a WordPress template object.
	 *
	 * @param array $item Template data.
	 *
	 * @return \WP_Block_Template|null Template object.
	 */
	private static function build_template_object( array $item ): ?\WP_Block_Template {
		if ( ! class_exists( '\WP_Block_Template' ) ) {
			return null;
		}

		$template                 = new \WP_Block_Template();
		$template->id             = get_stylesheet() . '//' . $item['slug'];
		$template->theme          = get_stylesheet();
		$template->slug           = $item['slug'];
		$template->type           = $item['type'];
		$template->title          = $item['title'] ?? $item['slug'];
		$template->description    = $item['description'] ?? '';
		$template->content        = $item['content'] ?? '';
		$template->source         = 'theme';
		$template->origin         = 'theme';
		$template->status         = $item['status'] ?? 'publish';
		$template->has_theme_file = true;
		$template->author         = null;
		$template->plugin         = null;
		$template->wp_id          = null;
		$template->modified       = $item['modified'] ?? null;
		$template->is_custom      = true;

		if ( 'wp_template' === $item['type'] ) {
			$default_types       = function_exists( 'get_default_block_template_types' ) ? get_default_block_template_types() : array();
			$template->is_custom = ! isset( $default_types[ $item['slug'] ] );

			if ( ! empty( $item['postTypes'] ) ) {
				$template->post_types = $item['postTypes'];
			}
		}

		if ( 'wp_template_part' === $item['type'] ) {
			$template->area = $item['area'] ?? ( defined( 'WP_TEMPLATE_PART_AREA_UNCATEGORIZED' ) ? WP_TEMPLATE_PART_AREA_UNCATEGORIZED : 'uncategorized' );
		}

		$template->content = self::apply_block_hooks( $template );

		return $template;
	}

	/**
	 * Apply WordPress block hooks to a template object.
	 *
	 * @param \WP_Block_Template $template Template object.
	 *
	 * @return string Content.
	 */
	private static function apply_block_hooks( \WP_Block_Template $template ): string {
		if ( ! function_exists( 'apply_block_hooks_to_content' ) ) {
			return $template->content;
		}

		if (
			'wp_template_part' === $template->type &&
			function_exists( 'get_comment_delimited_block_content' ) &&
			function_exists( 'remove_serialized_parent_block' )
		) {
			$content = get_comment_delimited_block_content(
				'core/template-part',
				array(),
				$template->content
			);
			$content = apply_block_hooks_to_content(
				$content,
				$template,
				'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata'
			);

			return remove_serialized_parent_block( $content );
		}

		return apply_block_hooks_to_content(
			$template->content,
			$template,
			'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata'
		);
	}

	/**
	 * Check whether an item matches a WordPress template query.
	 *
	 * @param array  $item          Template data.
	 * @param array  $query         Query.
	 * @param string $template_type Template type.
	 *
	 * @return bool Whether the item matches.
	 */
	private static function matches_query( array $item, array $query, string $template_type ): bool {
		if ( ! empty( $query['slug__in'] ) && ! in_array( $item['slug'], (array) $query['slug__in'], true ) ) {
			return false;
		}

		if ( ! empty( $query['slug__not_in'] ) && in_array( $item['slug'], (array) $query['slug__not_in'], true ) ) {
			return false;
		}

		if ( 'wp_template_part' === $template_type && isset( $query['area'] ) && ( $item['area'] ?? '' ) !== $query['area'] ) {
			return false;
		}

		if ( 'wp_template' === $template_type && ! empty( $query['post_type'] ) ) {
			$post_type     = (string) $query['post_type'];
			$post_types    = $item['postTypes'] ?? array();
			$default_types = function_exists( 'get_default_block_template_types' ) ? get_default_block_template_types() : array();
			$is_custom     = ! isset( $default_types[ $item['slug'] ] );

			if ( ! empty( $post_types ) ) {
				return in_array( $post_type, $post_types, true );
			}

			return $is_custom;
		}

		return true;
	}

	/**
	 * Normalize path values.
	 *
	 * @param mixed $paths Paths.
	 *
	 * @return array<int, string> Normalized paths.
	 */
	private static function normalize_paths( mixed $paths ): array {
		if ( is_string( $paths ) ) {
			$paths = array( $paths );
		}

		if ( ! is_array( $paths ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || '' === $path ) {
				continue;
			}

			$normalized[] = Site_Template_Discovery::normalize_path( $path );
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Build a cache key.
	 *
	 * @param array $paths Discovery paths.
	 *
	 * @return string Cache key.
	 */
	private static function cache_key( array $paths ): string {
		return md5(
			wp_json_encode(
				array(
					'version'             => defined( 'BLOCKSTUDIO_VERSION' ) ? BLOCKSTUDIO_VERSION : '',
					'siteTemplateVersion' => self::CACHE_VERSION,
					'stylesheet'          => get_stylesheet(),
					'template'            => get_template(),
					'paths'               => $paths,
					'blockTags'           => Settings::get( 'blockTags' ),
					'filters'             => array(
						'candidates'       => has_filter( 'blockstudio/site_templates/template_candidates' ),
						'template_content' => has_filter( 'blockstudio/site_templates/template_content' ),
						'part_content'     => has_filter( 'blockstudio/site_templates/part_content' ),
						'parser'           => has_filter( 'blockstudio/site_templates/parser' ),
					),
				)
			)
		);
	}

	/**
	 * Write registry data to cache.
	 *
	 * @param string                 $key          Cache key.
	 * @param Site_Template_Registry $registry     Registry.
	 * @param array                  $scanned_dirs Directories scanned.
	 *
	 * @return void
	 */
	private static function write_cache( string $key, Site_Template_Registry $registry, array $scanned_dirs ): void {
		if ( ! Build_Cache::is_enabled() ) {
			return;
		}

		$payload = $registry->to_array();
		$files   = array();

		foreach ( array_merge( $payload['templates'], $payload['parts'] ) as $item ) {
			foreach ( array( 'manifest_path', 'source_path' ) as $path_key ) {
				if ( is_string( $item[ $path_key ] ?? null ) && '' !== $item[ $path_key ] ) {
					$files[] = $item[ $path_key ];
				}
			}
		}

		$payload['siteTemplatesVersion'] = self::CACHE_VERSION;
		$payload['watch']                = Build_Cache::create_watch_snapshot( $files, $scanned_dirs );

		Build_Cache::write( self::CACHE_SCOPE, $key, $payload );
	}
}

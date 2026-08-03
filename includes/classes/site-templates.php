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
	 * Errors from the most recent exact selection, keyed by item family.
	 *
	 * @var array{templates: array<int, array>, parts: array<int, array>}
	 */
	private static array $selection_errors = array(
		'templates' => array(),
		'parts'     => array(),
	);

	/**
	 * Native path-based aliases owned by manifest HTML sources.
	 *
	 * @var array<string, array<string, true>>
	 */
	private static array $native_html_aliases = array();

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
		if ( ! in_array( $template_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			return $templates;
		}

		self::ensure_loaded();

		$registry         = Site_Template_Registry::instance();
		$items            = 'wp_template' === $template_type ? self::templates() : self::parts();
		$registered_items = 'wp_template' === $template_type ? $registry->get_templates() : $registry->get_parts();
		$native_aliases   = self::native_html_aliases( $registered_items, $template_type );
		$existing_slugs   = array();
		$append_templates = ! isset( $query['wp_id'] );

		foreach ( $templates as $index => $template ) {
			if ( is_object( $template ) && isset( $template->slug ) ) {
				if ( isset( $native_aliases[ self::template_object_id( $template ) ] ) ) {
					unset( $templates[ $index ] );
					continue;
				}

				$slug             = self::template_object_slug( $template );
				$existing_slugs[] = $slug;

				if ( isset( $items[ $slug ] ) ) {
					self::mark_as_file_backed( $template, $items[ $slug ] );
				}
			}
		}

		$templates = array_values( $templates );

		if ( ! $append_templates ) {
			return $templates;
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
	 * Mark a customized database template as having a matching file source.
	 *
	 * @param object $template Template object.
	 * @param array  $item     File-backed template data.
	 *
	 * @return void
	 */
	private static function mark_as_file_backed( object $template, array $item ): void {
		$template->origin         = 'theme';
		$template->has_theme_file = true;

		if ( 'wp_template' === $item['type'] && ! empty( $item['postTypes'] ) ) {
			$template->post_types = $item['postTypes'];
		}

		if ( 'wp_template_part' === $item['type'] && ! empty( $item['area'] ) ) {
			$template->area = $item['area'];
		}
	}

	/**
	 * Extract a comparable slug from a WordPress template object.
	 *
	 * @param object $template Template object.
	 *
	 * @return string Template slug.
	 */
	private static function template_object_slug( object $template ): string {
		$slug = (string) $template->slug;

		if ( str_contains( $slug, '//' ) ) {
			$parts = explode( '//', $slug, 2 );
			return $parts[1];
		}

		return $slug;
	}

	/**
	 * Build a comparable ID for a WordPress template object.
	 *
	 * @param object $template Template object.
	 *
	 * @return string Template ID.
	 */
	private static function template_object_id( object $template ): string {
		if ( is_scalar( $template->id ?? null ) && str_contains( (string) $template->id, '//' ) ) {
			return (string) $template->id;
		}

		$theme = is_scalar( $template->theme ?? null ) ? (string) $template->theme : get_stylesheet();

		return $theme . '//' . self::template_object_slug( $template );
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
		if ( ! in_array( $template_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			return $block_template;
		}

		self::ensure_loaded();

		$registry       = Site_Template_Registry::instance();
		$items          = 'wp_template' === $template_type ? $registry->get_templates() : $registry->get_parts();
		$native_aliases = self::native_html_aliases( $items, $template_type );
		$template_id    = is_object( $block_template ) && isset( $block_template->slug )
			? self::template_object_id( $block_template )
			: $id;

		if ( isset( $native_aliases[ $template_id ] ) ) {
			return null;
		}

		if ( null !== $block_template ) {
			return $block_template;
		}

		$parts = explode( '//', $id, 2 );

		if ( 2 !== count( $parts ) || get_stylesheet() !== $parts[0] ) {
			return $block_template;
		}

		$item = 'wp_template' === $template_type
			? Site_Template_Registry::instance()->get_template( $parts[1] )
			: Site_Template_Registry::instance()->get_part( $parts[1] );

		if ( ! $item ) {
			return $block_template;
		}

		return self::build_template_object( $item ) ?? $block_template;
	}

	/**
	 * Get native path-based IDs created from manifest-owned HTML sources.
	 *
	 * @param array  $items         Discovered templates or parts.
	 * @param string $template_type Template type.
	 *
	 * @return array<string, true> Native IDs indexed for lookup.
	 */
	private static function native_html_aliases( array $items, string $template_type ): array {
		if ( isset( self::$native_html_aliases[ $template_type ] ) ) {
			return self::$native_html_aliases[ $template_type ];
		}

		$roots   = self::native_template_roots( $template_type );
		$aliases = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || 'html' !== ( $item['source_type'] ?? '' ) || ! is_scalar( $item['source_path'] ?? null ) ) {
				continue;
			}

			$source_path = self::comparable_path( (string) $item['source_path'] );

			foreach ( $roots as $root ) {
				$relative_path = self::path_relative_to( $source_path, $root );

				if ( null === $relative_path || ! str_ends_with( $relative_path, '.html' ) ) {
					continue;
				}

				$native_slug = substr( $relative_path, 0, -5 );
				$item_slug   = is_scalar( $item['slug'] ?? null ) ? (string) $item['slug'] : '';

				if ( '' === $native_slug || $native_slug === $item_slug ) {
					break;
				}

				foreach ( $roots as $candidate_root ) {
					$candidate = $candidate_root . '/' . $native_slug . '.html';

					if ( ! is_file( $candidate ) ) {
						continue;
					}

					if ( self::comparable_path( $candidate ) === $source_path ) {
						$aliases[ get_stylesheet() . '//' . $native_slug ] = true;
					}

					break;
				}

				break;
			}
		}

		self::$native_html_aliases[ $template_type ] = $aliases;

		return $aliases;
	}

	/**
	 * Get active theme roots WordPress scans for one template type.
	 *
	 * @param string $template_type Template type.
	 *
	 * @return array<int, string> Native template roots in Core precedence order.
	 */
	private static function native_template_roots( string $template_type ): array {
		$themes = array(
			get_stylesheet() => get_stylesheet_directory(),
			get_template()   => get_template_directory(),
		);
		$roots  = array();

		foreach ( $themes as $theme => $directory ) {
			$folders = function_exists( 'get_block_theme_folders' )
				? get_block_theme_folders( $theme )
				: array();
			$folders = is_array( $folders ) ? $folders : array();
			$folder  = is_scalar( $folders[ $template_type ] ?? null )
				? (string) $folders[ $template_type ]
				: ( 'wp_template' === $template_type ? 'templates' : 'parts' );
			$root    = self::comparable_path( $directory . '/' . trim( $folder, '/\\' ) );

			if ( ! in_array( $root, $roots, true ) ) {
				$roots[] = $root;
			}
		}

		return $roots;
	}

	/**
	 * Return a path relative to a containing root.
	 *
	 * @param string $path Absolute path.
	 * @param string $root Absolute root path.
	 *
	 * @return string|null Relative path or null when outside the root.
	 */
	private static function path_relative_to( string $path, string $root ): ?string {
		$prefix = trailingslashit( $root );

		return str_starts_with( $path, $prefix ) ? substr( $path, strlen( $prefix ) ) : null;
	}

	/**
	 * Normalize a path and resolve symlinks when possible.
	 *
	 * @param string $path Filesystem path.
	 *
	 * @return string Comparable path.
	 */
	private static function comparable_path( string $path ): string {
		$resolved = realpath( $path );

		return Site_Template_Discovery::normalize_path( false === $resolved ? $path : $resolved );
	}

	/**
	 * Get file-backed templates.
	 *
	 * Passing an explicit selection keeps discovery and compilation scoped to
	 * those identifiers. `null` preserves the complete registry behavior.
	 *
	 * @param array<int, string>|null $only Exact slugs, paths, or source paths.
	 *
	 * @return array<string, array> Templates.
	 */
	public static function templates( ?array $only = null ): array {
		if ( null !== $only ) {
			$templates = self::selected_items( false, $only );

			/**
			 * Filter selected Site Editor templates.
			 *
			 * @param array $templates Templates indexed by slug.
			 */
			$templates = apply_filters( 'blockstudio/site_templates/templates', $templates );

			return self::filter_selected_items( is_array( $templates ) ? $templates : array(), $only );
		}

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
	 * Passing an explicit selection keeps discovery and compilation scoped to
	 * those identifiers. `null` preserves the complete registry behavior.
	 *
	 * @param array<int, string>|null $only Exact slugs, paths, or source paths.
	 *
	 * @return array<string, array> Template parts.
	 */
	public static function parts( ?array $only = null ): array {
		if ( null !== $only ) {
			$parts = self::selected_items( true, $only );

			/**
			 * Filter selected Site Editor template parts.
			 *
			 * @param array $parts Template parts indexed by slug.
			 */
			$parts = apply_filters( 'blockstudio/site_templates/parts', $parts );

			return self::filter_selected_items( is_array( $parts ) ? $parts : array(), $only );
		}

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
		$template_paths = Utils::theme_subdir_paths( 'templates', false, false );
		$part_paths     = Utils::theme_subdir_paths( 'parts', false, false );

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
	 * Get errors from the most recent exact selection without loading all items.
	 *
	 * @param string|null $type Optional `templates` or `parts` family.
	 *
	 * @return array<int, array> Errors.
	 */
	public static function selection_errors( ?string $type = null ): array {
		if ( in_array( $type, array( 'templates', 'parts' ), true ) ) {
			return self::$selection_errors[ $type ];
		}

		return array_merge(
			self::$selection_errors['templates'],
			self::$selection_errors['parts']
		);
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
		self::$loaded              = false;
		self::$discovery_runs      = 0;
		self::$native_html_aliases = array();
		self::$selection_errors    = array(
			'templates' => array(),
			'parts'     => array(),
		);
	}

	/**
	 * Discover and compile only explicitly requested templates or parts.
	 *
	 * Exact conventional slugs and physical paths first narrow each discovery
	 * source to the matching directories. A custom manifest slug falls back to
	 * metadata discovery, but compilation and returned records remain exact.
	 *
	 * @param bool               $parts Whether to select template parts.
	 * @param array<int, string> $only  Exact identifiers.
	 *
	 * @return array<string, array> Selected items.
	 */
	private static function selected_items( bool $parts, array $only ): array {
		$type = $parts ? 'parts' : 'templates';
		$only = self::normalize_identifiers( $only );

		self::$selection_errors[ $type ] = array();

		if ( array() === $only ) {
			return array();
		}

		$registry = Site_Template_Registry::instance();

		if ( self::$loaded ) {
			$items = $parts ? $registry->get_parts() : $registry->get_templates();
			return self::filter_selected_items( $items, $only );
		}

		$paths   = self::get_paths();
		$key     = $parts ? 'parts' : 'templates';
		$context = $parts ? 'site-template-parts' : 'site-templates';
		$sources = Discovery_Sources::for_paths( $context, $paths[ $key ] );
		$sources = self::scope_sources( $sources, $only );

		++self::$discovery_runs;

		$discovery = new Site_Template_Discovery();
		$found     = $discovery->discover(
			$parts ? array() : $sources,
			$parts ? $sources : array()
		);
		$items     = $parts ? $found['parts'] : $found['templates'];
		$items     = self::filter_selected_items( $items, $only );

		self::$selection_errors[ $type ] = self::filter_selection_errors(
			$discovery->get_errors(),
			$only
		);

		return self::compile_items( $items );
	}

	/**
	 * Normalize exact selection identifiers.
	 *
	 * @param array $identifiers Identifiers.
	 *
	 * @return array<int, string> Identifiers.
	 */
	private static function normalize_identifiers( array $identifiers ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $identifier ): string => is_scalar( $identifier )
							? trim( wp_normalize_path( (string) $identifier ) )
							: '',
						$identifiers
					)
				)
			)
		);
	}

	/**
	 * Keep only records matching exact public identifiers.
	 *
	 * @param array              $items       Items indexed by slug.
	 * @param array<int, string> $identifiers Identifiers.
	 *
	 * @return array<string, array> Selected items.
	 */
	private static function filter_selected_items( array $items, array $identifiers ): array {
		$identifiers = self::normalize_identifiers( $identifiers );
		$selected    = array();

		foreach ( $items as $key => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$candidates = array( is_scalar( $key ) ? (string) $key : '' );

			foreach ( array( 'slug', 'name', 'source_path', 'manifest_path', 'logical_path', 'directory' ) as $field ) {
				if ( is_scalar( $item[ $field ] ?? null ) ) {
					$candidates[] = (string) $item[ $field ];
				}
			}

			if ( self::identifiers_match( $identifiers, $candidates ) ) {
				$selected[ (string) $key ] = $item;
			}
		}

		return $selected;
	}

	/**
	 * Narrow conventional slug/path selections before manifest discovery.
	 *
	 * If any identifier cannot be mapped safely, the original sources are
	 * returned so custom manifest slugs remain supported. Record filtering
	 * still happens before source compilation.
	 *
	 * @param array<int, Discovery_Source> $sources     Discovery sources.
	 * @param array<int, string>           $identifiers Identifiers.
	 *
	 * @return array<int, Discovery_Source> Sources.
	 */
	private static function scope_sources( array $sources, array $identifiers ): array {
		$matched = array_fill_keys( $identifiers, false );
		$scoped  = array();

		foreach ( $sources as $source ) {
			$directories = array();
			$entries     = $source->entries();

			foreach ( $entries as $entry ) {
				$logical    = Discovery_Sources::normalize_logical_path( $entry->logical_path() );
				$directory  = dirname( $logical );
				$directory  = '.' === $directory ? '' : $directory;
				$physical   = wp_normalize_path( $entry->physical_path() );
				$candidates = array(
					$logical,
					$physical,
					$directory,
					wp_normalize_path( dirname( $physical ) ),
					basename( $directory ),
				);

				foreach ( $identifiers as $identifier ) {
					if ( self::identifiers_match( array( $identifier ), $candidates ) ) {
						$matched[ $identifier ]    = true;
						$directories[ $directory ] = true;
					}
				}
			}

			if ( array() === $directories ) {
				continue;
			}

			$selected_entries = array();

			foreach ( $entries as $entry ) {
				$logical = Discovery_Sources::normalize_logical_path( $entry->logical_path() );

				foreach ( array_keys( $directories ) as $directory ) {
					if ( '' === $directory
						|| $logical === $directory
						|| str_starts_with( $logical, $directory . '/' )
					) {
						$selected_entries[ $logical ] = $entry;
						break;
					}
				}
			}

			$scoped[] = new Inventory_Discovery_Source(
				$source->id() . '#selection:' . hash( 'sha256', implode( "\n", array_keys( $selected_entries ) ) ),
				$source->root(),
				$selected_entries,
				null,
				$source->watch_paths(),
				array_keys( $directories )
			);
		}

		return in_array( false, $matched, true ) ? $sources : $scoped;
	}

	/**
	 * Check exact paths and normalized slugs for a selection match.
	 *
	 * @param array<int, string> $identifiers Identifiers.
	 * @param array<int, string> $candidates  Candidate values.
	 *
	 * @return bool Whether any value matches.
	 */
	private static function identifiers_match( array $identifiers, array $candidates ): bool {
		foreach ( $identifiers as $identifier ) {
			$identifier = trim( wp_normalize_path( $identifier ) );
			$slug       = Site_Template_Discovery::normalize_slug( $identifier );

			foreach ( $candidates as $candidate ) {
				$candidate = trim( wp_normalize_path( $candidate ) );

				if ( '' !== $candidate && $identifier === $candidate ) {
					return true;
				}

				if ( null !== $slug && Site_Template_Discovery::normalize_slug( $candidate ) === $slug ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Keep discovery errors that belong to selected paths or slugs.
	 *
	 * @param array              $errors      Discovery errors.
	 * @param array<int, string> $identifiers Identifiers.
	 *
	 * @return array<int, array> Errors.
	 */
	private static function filter_selection_errors( array $errors, array $identifiers ): array {
		return array_values(
			array_filter(
				$errors,
				static function ( mixed $error ) use ( $identifiers ): bool {
					if ( ! is_array( $error ) ) {
						return false;
					}

					$context    = is_array( $error['context'] ?? null ) ? $error['context'] : array();
					$candidates = array();

					foreach ( array( 'slug', 'path', 'source_path', 'manifest_path' ) as $field ) {
						if ( is_scalar( $context[ $field ] ?? null ) ) {
							$candidates[] = (string) $context[ $field ];
						}
					}

					return self::identifiers_match( $identifiers, $candidates );
				}
			)
		);
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

		$registry         = Site_Template_Registry::instance();
		$paths            = self::get_paths();
		$template_sources = Discovery_Sources::for_paths( 'site-templates', $paths['templates'] );
		$part_sources     = Discovery_Sources::for_paths( 'site-template-parts', $paths['parts'] );
		$key              = self::cache_key( $paths );
		$payload          = Build_Cache::is_enabled() ? Build_Cache::load( self::CACHE_SCOPE, $key ) : null;

		if ( is_array( $payload ) && ( $payload['siteTemplatesVersion'] ?? null ) === self::CACHE_VERSION ) {
			$registry->load( $payload );
			self::$loaded = true;
			return;
		}

		++self::$discovery_runs;

		$discovery = new Site_Template_Discovery();
		$found     = $discovery->discover( $template_sources, $part_sources );
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

		/**
		 * Fires immediately before one selected Site Editor source is compiled.
		 *
		 * @since 7.6.0
		 *
		 * @param string $source_path Physical source path.
		 * @param array  $item        Template or part record.
		 */
		do_action( 'blockstudio/site_templates/source_compiled', $source_path, $item );

		$content = Template_Compiler::compile(
			$source_path,
			is_string( $item['directory'] ?? null ) ? $item['directory'] : null
		);

		if ( null === $content ) {
			return '';
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
					'context'             => Runtime_Context::hash( self::CACHE_SCOPE, array( 'site-templates', 'site-template-parts' ) ),
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

		foreach ( array( 'templates', 'parts' ) as $group ) {
			foreach ( $payload[ $group ] as $item ) {
				foreach ( array( 'manifest_path', 'source_path' ) as $path_key ) {
					if ( is_string( $item[ $path_key ] ?? null ) && '' !== $item[ $path_key ] ) {
						$files[] = $item[ $path_key ];
					}
				}
			}
		}

		$payload['siteTemplatesVersion'] = self::CACHE_VERSION;
		$payload['watch']                = Build_Cache::create_watch_snapshot( $files, $scanned_dirs );

		Build_Cache::write( self::CACHE_SCOPE, $key, $payload );
	}
}

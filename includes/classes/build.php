<?php
/**
 * Build class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassException;
use Exception;
use WP_Block_Type;

/**
 * Main entry point for discovering, processing, and registering Blockstudio blocks.
 *
 * This class orchestrates the entire block build process through its init() method,
 * which is typically called during WordPress initialization. The build process has
 * four phases:
 *
 * Phase 1 - Discovery (Block_Discovery):
 *   Recursively scans directories for block.json files and classifies each file
 *   as a block, override, extension, init file, or blade template.
 *
 * Phase 2 - Asset Processing (process_block_assets):
 *   Processes CSS/SCSS/JS files found in each block directory. Handles SCSS
 *   compilation, CSS scoping, minification, and categorizes assets by type
 *   (admin, block-editor, global, inline).
 *
 * Phase 3 - Block Registration (register_block_type):
 *   Creates WP_Block_Type instances for each discovered block and registers
 *   them with WordPress. Builds block attributes from field definitions.
 *
 * Phase 4 - Override Application (apply_overrides):
 *   Merges override configurations into their target blocks, allowing blocks
 *   to be extended or modified by other block definitions.
 *
 * Public API Methods:
 *   Build::init()              - Run the build process for a directory
 *   Build::blocks()            - Get all registered block types
 *   Build::data()              - Get block metadata indexed by name
 *   Build::extensions()        - Get extension block types
 *   Build::files()             - Get discovered files (editor mode)
 *   Build::assets()            - Get registered assets
 *   Build::assets_admin()      - Get admin-only assets
 *   Build::assets_block_editor() - Get block editor assets
 *   Build::assets_global()     - Get globally loaded assets
 *   Build::paths()             - Get registered block paths
 *   Build::overrides()         - Get override configurations
 *   Build::blade()             - Get Blade template configurations
 *
 * Usage:
 *   // Register blocks from theme directory
 *   Build::init( get_stylesheet_directory() . '/blockstudio' );
 *
 *   // Register blocks with options
 *   Build::init([
 *       'dir'    => '/path/to/blocks',
 *       'editor' => false, // Normal mode (not editor)
 *   ]);
 *
 * @since 1.0.0
 */
class Build {

	/**
	 * Wait budget in milliseconds for requests that lose the builder election.
	 *
	 * @var int
	 */
	private const BUILD_WAIT_BUDGET_MS = 5000;

	/**
	 * Whether interactivity API has been rendered.
	 *
	 * @var bool
	 */
	private static bool $interactivity_api_rendered = false;

	/**
	 * Reset request-local render state for in-process batch rendering.
	 *
	 * @return void
	 */
	public static function reset_request_state(): void {
		self::$interactivity_api_rendered = false;
	}

	/**
	 * Check if a block's blockstudio data has interactivity enabled.
	 *
	 * @param array $blockstudio_data The blockstudio data array.
	 *
	 * @return bool Whether interactivity is enabled.
	 */
	public static function has_interactivity( array $blockstudio_data ): bool {
		$val = $blockstudio_data['interactivity'] ?? false;

		if ( true === $val ) {
			return true;
		}

		if ( is_array( $val ) && ! empty( $val['enqueue'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Normalize plugin dependencies from blockstudio.pluginDependencies.
	 *
	 * @param mixed $plugin_dependencies The dependency list from block.json.
	 *
	 * @return array<string, array{version?: string}> Dependencies keyed by plugin slug.
	 */
	public static function normalize_plugin_dependencies( $plugin_dependencies ): array {
		if ( ! is_array( $plugin_dependencies ) ) {
			return array();
		}

		$normalized = array();

		if ( array_is_list( $plugin_dependencies ) ) {
			foreach ( $plugin_dependencies as $dependency ) {
				$slug = self::sanitize_plugin_dependency_slug( $dependency );

				if ( false === $slug ) {
					continue;
				}

				$normalized[ $slug ] = array();
			}

			return $normalized;
		}

		foreach ( $plugin_dependencies as $slug => $dependency ) {
			$slug = self::sanitize_plugin_dependency_slug( $slug );

			if ( false === $slug ) {
				continue;
			}

			$normalized[ $slug ] = array();

			if ( is_array( $dependency ) && isset( $dependency['version'] ) && is_string( $dependency['version'] ) ) {
				$version = trim( $dependency['version'] );

				if ( '' !== $version ) {
					$normalized[ $slug ]['version'] = $version;
				}
			}
		}

		return $normalized;
	}

	/**
	 * Check whether all plugin dependencies are active.
	 *
	 * @param mixed $plugin_dependencies The dependency list from block.json.
	 *
	 * @return bool Whether all dependencies are active.
	 */
	public static function has_active_plugin_dependencies( $plugin_dependencies ): bool {
		$plugin_dependencies = self::normalize_plugin_dependencies( $plugin_dependencies );

		if ( empty( $plugin_dependencies ) ) {
			return true;
		}

		$active_plugins = self::get_active_plugin_dependency_data();

		foreach ( $plugin_dependencies as $slug => $dependency ) {
			if ( ! isset( $active_plugins[ $slug ] ) ) {
				return false;
			}

			if (
				! empty( $dependency['version'] ) &&
				! self::is_plugin_dependency_version_compatible(
					$active_plugins[ $slug ]['version'] ?? '',
					$dependency['version']
				)
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether an installed plugin version satisfies a dependency constraint.
	 *
	 * @param string $installed_version The installed plugin version.
	 * @param string $constraint        The version constraint.
	 *
	 * @return bool Whether the installed version satisfies the constraint.
	 */
	public static function is_plugin_dependency_version_compatible(
		string $installed_version,
		string $constraint
	): bool {
		$installed_version = trim( $installed_version );
		$constraint        = trim( $constraint );

		if ( '' === $constraint ) {
			return true;
		}

		if ( '' === $installed_version ) {
			return false;
		}

		$operator = '>=';
		$version  = $constraint;

		if ( preg_match( '/^(>=|<=|==|!=|<>|>|<|=|gt|ge|lt|le|eq|ne)\s*(.+)$/i', $constraint, $matches ) ) {
			$operator = strtolower( $matches[1] );
			$version  = trim( $matches[2] );
		}

		$operators = array(
			'gt' => '>',
			'ge' => '>=',
			'lt' => '<',
			'le' => '<=',
			'eq' => '==',
			'ne' => '!=',
			'='  => '==',
		);

		$operator = $operators[ $operator ] ?? $operator;

		if ( '' === $version ) {
			return false;
		}

		return version_compare( $installed_version, $version, $operator );
	}

	/**
	 * Get active plugin data keyed by WordPress plugin slug.
	 *
	 * @return array<string, array{file: string, version: string}> Active plugin data.
	 */
	private static function get_active_plugin_dependency_data(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

			if ( is_array( $network_plugins ) ) {
				$active_plugins = array_merge( $active_plugins, array_keys( $network_plugins ) );
			}
		}

		$installed_plugins = get_plugins();
		$plugins           = array();

		foreach ( $active_plugins as $plugin_file ) {
			if ( ! is_string( $plugin_file ) ) {
				continue;
			}

			$plugin_file = trim( str_replace( '\\', '/', $plugin_file ), " \t\n\r\0\x0B/" );

			if ( '' === $plugin_file ) {
				continue;
			}

			$slug = self::plugin_file_to_slug( $plugin_file );

			$plugins[ $slug ] = array(
				'file'    => $plugin_file,
				'version' => $installed_plugins[ $plugin_file ]['Version'] ?? '',
			);
		}

		return $plugins;
	}

	/**
	 * Sanitize a plugin dependency slug the same way WordPress core does.
	 *
	 * @param mixed $slug The plugin slug.
	 *
	 * @return string|false The sanitized slug, or false when invalid.
	 */
	private static function sanitize_plugin_dependency_slug( $slug ): string|false {
		if ( ! is_string( $slug ) ) {
			return false;
		}

		$slug = trim( $slug );

		if ( '' === $slug ) {
			return false;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Mirrors WordPress core plugin dependency slug normalization.
		$slug = apply_filters( 'wp_plugin_dependencies_slug', $slug );

		if ( ! is_string( $slug ) ) {
			return false;
		}

		$slug = trim( $slug );

		if ( preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/mu', $slug ) ) {
			return $slug;
		}

		return false;
	}

	/**
	 * Convert an active plugin filepath to its WordPress dependency slug.
	 *
	 * @param string $plugin_file The plugin file relative to the plugins directory.
	 *
	 * @return string The plugin slug.
	 */
	private static function plugin_file_to_slug( string $plugin_file ): string {
		if ( 'hello.php' === $plugin_file ) {
			return 'hello-dolly';
		}

		return str_contains( $plugin_file, '/' )
			? dirname( $plugin_file )
			: str_replace( '.php', '', $plugin_file );
	}

	/**
	 * Check whether a block.json entry may register for the active plugins.
	 *
	 * @param array $block_json The block.json data.
	 *
	 * @return bool Whether plugin dependencies are satisfied.
	 */
	private static function has_satisfied_plugin_dependencies( array $block_json ): bool {
		$blockstudio = $block_json['blockstudio'] ?? array();

		if ( ! is_array( $blockstudio ) || ! array_key_exists( 'pluginDependencies', $blockstudio ) ) {
			return true;
		}

		return self::has_active_plugin_dependencies( $blockstudio['pluginDependencies'] );
	}

	/**
	 * Remove discovered blocks whose plugin dependencies are not active.
	 *
	 * @param array $store           Discovered block data.
	 * @param array $registerable    Discovered registration data.
	 * @param array $overrides       Discovered overrides.
	 * @param array $block_json_data Discovered block.json data.
	 *
	 * @return void
	 */
	private static function filter_missing_plugin_dependencies(
		array &$store,
		array &$registerable,
		array &$overrides,
		array $block_json_data
	): void {
		$skipped = array();

		foreach ( $block_json_data as $name => $block_json ) {
			if ( ! is_array( $block_json ) || self::has_satisfied_plugin_dependencies( $block_json ) ) {
				continue;
			}

			$skipped[ $name ] = true;
		}

		if ( empty( $skipped ) ) {
			return;
		}

		foreach ( array_keys( $skipped ) as $name ) {
			unset( $store[ $name ], $registerable[ $name ] );
		}

		foreach ( $overrides as $override_key => $override_info ) {
			$name = is_array( $override_info )
				? ( $override_info['name'] ?? $override_key )
				: $override_key;

			if ( isset( $skipped[ $name ] ) ) {
				unset( $overrides[ $override_key ] );
			}
		}
	}

	/**
	 * Filter deep array everything but a given string.
	 *
	 * @since 3.1.1
	 *
	 * @param array  $array The array to filter (passed by reference).
	 * @param string $key   The key to filter by.
	 * @param mixed  $val   The value to filter by.
	 *
	 * @return void
	 */
	public static function filter_not_key( &$array, $key, $val ) {
		foreach ( $array as $k => $v ) {
			if ( isset( $v[ $key ] ) && $v[ $key ] === $val ) {
				unset( $array[ $k ] );
			} elseif ( is_array( $v ) ) {
				self::filter_not_key( $array[ $k ], $key, $val );
			}
			if ( empty( $array[ $k ] ) ) {
				unset( $array[ $k ] );
			}
		}
		foreach ( $array as $k => $v ) {
			if ( 'attributes' === $k ) {
				$array[ $k ] = array_values( $v );
			} elseif ( is_array( $v ) ) {
				self::filter_not_key( $array[ $k ], $key, $val );
			}
		}
	}

	/**
	 * Flatten groups without IDs while preserving ID-bearing groups.
	 *
	 * @since 7.2.3
	 *
	 * @param array $attributes The attributes to flatten.
	 *
	 * @return array The flattened attributes.
	 */
	private static function flatten_idless_groups( array $attributes ): array {
		$flattened = array();

		foreach ( $attributes as $attribute ) {
			if (
				'group' === ( $attribute['type'] ?? '' ) &&
				empty( $attribute['id'] ) &&
				isset( $attribute['attributes'] )
			) {
				$flattened = array_merge(
					$flattened,
					self::flatten_idless_groups( $attribute['attributes'] )
				);
				continue;
			}

			$flattened[] = $attribute;
		}

		return $flattened;
	}

	/**
	 * Filter attributes.
	 *
	 * @since 4.0.3
	 *
	 * @param array $block      The block data.
	 * @param array $attrs      The attributes to filter.
	 * @param array $attributes The filtered attributes (passed by reference).
	 *
	 * @return void
	 */
	public static function filter_attributes( $block, $attrs, &$attributes ) {
		foreach ( $attrs as $k => $v ) {
			$attributes[ $k ] = apply_filters(
				'blockstudio/blocks/attributes',
				$v,
				$block
			);

			$type = $attributes[ $k ]['type'] ?? false;

			if ( 'group' === $type || 'repeater' === $type ) {
				self::filter_attributes(
					$block,
					$attributes[ $k ]['attributes'],
					$attributes[ $k ]['attributes']
				);
			}
		}
	}

	/**
	 * Merge attributes.
	 *
	 * @since 5.3.0
	 *
	 * @param array $original_attributes The original attributes (passed by reference).
	 * @param array $override_attributes The override attributes.
	 *
	 * @return void
	 */
	public static function merge_attributes(
		&$original_attributes,
		$override_attributes
	) {
		$merge_attribute_by_key_or_id = function (
			$key_or_id,
			&$attributes,
			$override
		) use ( &$merge_attribute_by_key_or_id ) {
			foreach ( $attributes as &$attribute ) {
				if (
					( isset( $attribute['key'] ) &&
						$attribute['key'] === $key_or_id ) ||
					( isset( $attribute['id'] ) && $attribute['id'] === $key_or_id )
				) {
					foreach ( $override as $key => $value ) {
						if ( 'attributes' !== $key && 'tabs' !== $key ) {
							$attribute[ $key ] = $value;
						} elseif ( isset( $attribute[ $key ] ) && is_array( $value ) ) {
							self::merge_attributes( $attribute[ $key ], $value );
						}
					}

					return true;
				}

				foreach ( array( 'attributes', 'tabs' ) as $nested_key ) {
					if (
						isset( $attribute[ $nested_key ] ) &&
						is_array( $attribute[ $nested_key ] )
					) {
						if (
							$merge_attribute_by_key_or_id(
								$key_or_id,
								$attribute[ $nested_key ],
								$override
							)
						) {
							return true;
						}
					}
				}
			}

			return false;
		};

		foreach ( $override_attributes as $override_attribute ) {
			$key_or_id =
				$override_attribute['key'] ?? ( $override_attribute['id'] ?? null );
			if ( null !== $key_or_id ) {
				if (
					! $merge_attribute_by_key_or_id(
						$key_or_id,
						$original_attributes,
						$override_attribute
					)
				) {
					$original_attributes[] = $override_attribute;
				}
			} else {
				$original_attributes[] = $override_attribute;
			}
		}
	}

	/**
	 * Get WordPress root folder name.
	 *
	 * @since 5.0.0
	 *
	 * @param string $path The path.
	 *
	 * @return string The instance name.
	 */
	public static function get_instance_name( $path ): string {
		$root = wp_normalize_path( Files::get_root_folder() );
		$path = wp_normalize_path( (string) $path );

		if ( '' !== $root && str_contains( $path, $root ) ) {
			$parts = explode( $root, $path, 2 );
			return trim( $parts[1] ?? '', '/\\' );
		}

		return trim( $path, '/\\' );
	}

	/**
	 * Check whether an asset basename has a reserved prefix with a boundary.
	 *
	 * @param string $basename Asset basename.
	 * @param string $prefix   Reserved prefix.
	 *
	 * @return bool
	 */
	private static function asset_basename_matches_prefix( string $basename, string $prefix ): bool {
		return 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '(?:[.-]|$)/', $basename );
	}

	/**
	 * Get WordPress root folder name.
	 *
	 * @since 2.3.3
	 *
	 * @param string $path   The path.
	 * @param string $filter The filter.
	 *
	 * @return string The build directory.
	 */
	public static function get_build_dir(
		string $path = '/blockstudio',
		string $filter = 'path'
	): string {
		$theme = is_child_theme()
			? get_stylesheet_directory()
			: get_template_directory();

		return has_filter( 'blockstudio/' . $filter )
			? apply_filters( 'blockstudio/' . $filter, '' )
			: $theme . $path;
	}

	/**
	 * Initialize the build.
	 *
	 * On a cold runtime cache one request per instance is elected builder via
	 * a Single_Flight lock. The elected builder rechecks the cache under the
	 * lock before building. Concurrent requests wait briefly for the published
	 * runtime payload and hydrate from it; a request whose wait budget expires
	 * builds unguarded so block registration always completes.
	 *
	 * @since 1.0.0
	 *
	 * @param bool|string|array $args The arguments.
	 *
	 * @return void
	 * @throws SassException When SCSS compilation fails.
	 */
	public static function init( $args = false ) {
		$editor = $args['editor'] ?? false;
		if ( is_array( $args ) ) {
			$p    = $args;
			$args = $p['dir'] ?? false;
		}
		$path               = false === $args ? self::get_build_dir() : $args;
		$empty_dist_folders = array();

		$registry = Block_Registry::instance();

		if ( ! $editor && is_string( $path ) && '' !== $path ) {
			$cached_path     = untrailingslashit( wp_normalize_path( $path ) );
			$cached_instance = self::get_instance_name( $cached_path );
			$cached_runtime  = Build_Cache::load_runtime( $cached_path, $cached_instance );

			if ( is_array( $cached_runtime ) ) {
				$registry->add_instance( $cached_path );
				$registry->add_path( $cached_instance, $cached_path );

				do_action( 'blockstudio/init/before' );
				do_action( "blockstudio/init/before/$cached_instance" );

				$registry->set_blade_instance( $cached_instance, $cached_path );
				self::hydrate_cached_runtime_build(
					$cached_runtime,
					$cached_instance,
					$registry
				);
				return;
			}
		}

		$source = Discovery_Sources::for_path( 'blocks', $path );

		if ( empty( $source->entries() ) ) {
			return;
		}

		$path = $source->root();
		$registry->add_instance( $path );

		$path     = wp_normalize_path( $path );
		$instance = self::get_instance_name( $path );

		$registry->add_path( $instance, $path );

		do_action( 'blockstudio/init/before' );
		do_action( "blockstudio/init/before/$instance" );

		$registry->set_blade_instance( $instance, $path );

		$build_lock = null;

		if ( ! $editor ) {
			$cached_runtime = Build_Cache::load_runtime( $path, $instance );

			if ( is_array( $cached_runtime ) ) {
				self::hydrate_cached_runtime_build(
					$cached_runtime,
					$instance,
					$registry
				);
				return;
			}

			if ( Build_Cache::is_enabled() ) {
				$build_lock = Single_Flight::acquire(
					Build_Cache::get_cache_dir( 'runtime' ) . '/' . Build_Cache::get_runtime_key( $path, $instance ) . '.build.lock'
				);

				if ( is_resource( $build_lock ) ) {
					$cached_runtime = Build_Cache::load_runtime( $path, $instance );

					if ( is_array( $cached_runtime ) ) {
						Single_Flight::release( $build_lock );
						self::hydrate_cached_runtime_build(
							$cached_runtime,
							$instance,
							$registry
						);
						return;
					}
				}

				if ( false === $build_lock ) {
					$cached_runtime = self::wait_for_published_runtime( $path, $instance );

					if ( is_array( $cached_runtime ) ) {
						self::hydrate_cached_runtime_build(
							$cached_runtime,
							$instance,
							$registry
						);
						return;
					}

					$build_lock = null;
				}
			}
		}

		// Phase 1: Discover blocks using Block_Discovery.
		$results = Perf::measure(
			'build:discovery',
			static function () use ( $source, $instance, $editor ): array {
				$discovery = new Block_Discovery();
				return $discovery->discover( $source, $instance, $editor );
			}
		);

		$store        = $results['store'];
		$registerable = $results['registerable'];
		$overrides    = $results['overrides'];
		$registered   = array();

		self::filter_missing_plugin_dependencies(
			$store,
			$registerable,
			$overrides,
			$results['block_json_data'] ?? array()
		);

		self::register_blade_templates( $results['blade_templates'], $registry );

		// Handle overrides in editor mode.
		if ( $editor ) {
			foreach ( $overrides as $override_key => $override_info ) {
				$registry->set_data_override( $override_key, $override_info['data'] );
			}
		}

		// Phase 2: Process assets for each discovered item.
		Perf::measure(
			'build:assets',
			static function () use ( &$store, $instance, $editor, $registry, &$empty_dist_folders ): void {
				foreach ( $store as $name => &$data ) {
					if ( Settings::get( 'assets/enqueue' ) || $editor ) {
						$processed_assets = self::process_block_assets(
							$data,
							$name,
							$instance,
							$editor,
							$registry
						);

						$source_paths = array_filter(
							$data['filesPaths'] ?? array(),
							static fn( mixed $source_path ): bool => is_string( $source_path ) && ( Assets::is_css( $source_path ) || str_ends_with( $source_path, '.js' ) )
						);
						$dist_folders = array_unique(
							array_map(
								static fn( string $source_path ): string => Assets::get_dist_folder( $source_path ),
								$source_paths
							)
						);

						foreach ( $dist_folders as $dist_folder ) {
							$all_processed_assets = Files::get_files_recursively_and_delete_empty_folders( $dist_folder );

							if ( ! $editor ) {
								foreach ( $all_processed_assets as $file_path ) {
									if ( ! in_array( $file_path, $processed_assets, true ) && file_exists( $file_path ) ) {
										unlink( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
									}
								}

								if ( Files::is_directory_empty( $dist_folder ) ) {
									$empty_dist_folders[] = $dist_folder;
								}
							}
						}
					}
				}

				unset( $data );
			}
		);

		// Phase 2.5: Discover custom fields.
		$local_fields = self::discover_local_custom_fields( $path, $source );
		self::register_custom_field_definitions( $local_fields['fields'] );
		self::register_filtered_custom_fields();

		// Phase 3: Register blocks.
		if ( ! $editor ) {
			$registered = Perf::measure(
				'build:registration',
				static function () use ( $registerable, $registry ): array {
					return self::register_discovered_blocks( $registerable, $registry );
				}
			);
		}

		// Final processing.
		if ( $editor ) {
			$registry->merge_files( $store );
			foreach ( $registry->get_data_overrides() as $override ) {
				foreach ( $override['filesPaths'] as $override_path ) {
					$file_data = $registry->get_file_data( $override_path );
					if ( $file_data ) {
						$file_data['assets'] = array_merge(
							$registry->get_block_data( $override['name'] )['assets'] ?? array(),
							$override['assets'] ?? array()
						);
						$registry->merge_files( array( $override_path => $file_data ) );
					}
				}
			}

			return;
		}

		Build_Cache::write_runtime(
			$path,
			$instance,
			array(
				'store'                => $store,
				'registerable'         => self::filter_native_registerable( $registerable ),
				'registeredBlockTypes' => $registered,
				'bladeTemplates'       => $results['blade_templates'],
				'fields'               => $local_fields['fields'],
				'fieldPaths'           => $local_fields['paths'],
				'fieldDirs'            => $local_fields['dirs'],
			)
		);

		if ( is_resource( $build_lock ) ) {
			Single_Flight::release( $build_lock );
		}

		$registry->merge_data( $store );
		self::include_init_files( $registry );

		// Apply overrides.
		Perf::measure(
			'build:overrides',
			static function () use ( $registry ): void {
				self::apply_overrides( $registry );
			}
		);

		self::maybe_enqueue_interactivity_api( $registry );

		foreach ( $empty_dist_folders as $folder ) {
			if ( is_dir( $folder ) ) {
				rmdir( $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}

		do_action( 'blockstudio/init' );
		do_action( "blockstudio/init/$instance" );
	}

	/**
	 * Wait for a concurrent builder to publish the runtime cache payload.
	 *
	 * The probe stats the cache file and only includes and validates the
	 * payload when its mtime or size changed since the previous poll, so
	 * waiters do not deserialize a stale payload every 25ms while the
	 * elected builder runs.
	 *
	 * @param string $path     Build path.
	 * @param string $instance Build instance.
	 *
	 * @return array|null Runtime payload or null when the wait budget expired.
	 */
	private static function wait_for_published_runtime( string $path, string $instance ): ?array {
		$key      = Build_Cache::get_runtime_key( $path, $instance );
		$file     = Build_Cache::get_cache_file( 'runtime', $key );
		$snapshot = null;

		return Single_Flight::wait(
			static function () use ( $key, $file, &$snapshot ): ?array {
				clearstatcache( true, $file );

				if ( ! is_file( $file ) ) {
					return null;
				}

				$current = array( (int) filemtime( $file ), (int) filesize( $file ) );

				if ( $current === $snapshot ) {
					return null;
				}

				$snapshot = $current;

				return Build_Cache::load( 'runtime', $key );
			},
			self::BUILD_WAIT_BUDGET_MS
		);
	}

	/**
	 * Hydrate a cached runtime build payload.
	 *
	 * @param array          $payload  Cached runtime payload.
	 * @param string         $instance Build instance.
	 * @param Block_Registry $registry Block registry.
	 *
	 * @return void
	 */
	private static function hydrate_cached_runtime_build(
		array $payload,
		string $instance,
		Block_Registry $registry
	): void {
		$store        = $payload['store'] ?? array();
		$registerable = $payload['registerable'] ?? array();
		$overrides    = $payload['overrides'] ?? array();
		$registered   = $payload['registeredBlockTypes'] ?? array();

		self::register_blade_templates(
			$payload['bladeTemplates'] ?? array(),
			$registry
		);
		self::register_cached_assets( $store, $registry );
		self::register_custom_field_definitions( $payload['fields'] ?? array() );
		self::register_filtered_custom_fields();

		Perf::measure(
			'build:registration:cached',
			static function () use ( $registerable, $registered, $registry ): void {
				if ( is_array( $registered ) && ! empty( $registered ) ) {
					self::register_native_discovered_blocks( $registerable );
					self::hydrate_cached_registered_block_types( $registered, $registry );
					return;
				}

				self::register_discovered_blocks( $registerable, $registry );
			}
		);

		$registry->merge_data( $store );
		self::include_init_files( $registry );

		Perf::measure(
			'build:overrides:cached',
			static function () use ( $registry ): void {
				self::apply_overrides( $registry );
			}
		);

		self::maybe_enqueue_interactivity_api( $registry );

		do_action( 'blockstudio/init' );
		do_action( "blockstudio/init/$instance" );
	}

	/**
	 * Include valid init files from the runtime registry.
	 *
	 * A source can disappear after discovery or cache hydration. Treat stale
	 * entries as absent instead of emitting a warning during the request.
	 *
	 * @param Block_Registry $registry Block registry.
	 *
	 * @return void
	 */
	private static function include_init_files( Block_Registry $registry ): void {
		foreach ( $registry->get_data() as $file ) {
			$path = $file['path'] ?? null;

			if ( ! ( $file['init'] ?? false ) || ! is_string( $path ) || ! is_file( $path ) ) {
				continue;
			}

			include_once $path;
		}
	}

	/**
	 * Register Blade templates from discovery results.
	 *
	 * @param array          $blade_templates Blade templates.
	 * @param Block_Registry $registry        Block registry.
	 *
	 * @return void
	 */
	private static function register_blade_templates( array $blade_templates, Block_Registry $registry ): void {
		foreach ( $blade_templates as $blade_instance => $templates ) {
			foreach ( $templates as $template_name => $template_path ) {
				$registry->add_blade_template( $blade_instance, $template_name, $template_path );
			}
		}
	}

	/**
	 * Rebuild asset registry state from cached block asset metadata.
	 *
	 * @param array          $store    Cached block data.
	 * @param Block_Registry $registry Block registry.
	 *
	 * @return void
	 */
	private static function register_cached_assets( array $store, Block_Registry $registry ): void {
		if ( ! Settings::get( 'assets/enqueue' ) ) {
			return;
		}

		foreach ( $store as $name => $data ) {
			foreach ( $data['assets'] ?? array() as $asset_id => $asset ) {
				$path = $asset['path'] ?? '';
				$file = $asset['file'] ?? array();

				if ( '' === $path || empty( $file['basename'] ) ) {
					continue;
				}

				$mtime = $asset['key'] ?? $asset['mtime'] ?? filemtime( $path );

				if (
					false === apply_filters(
						'blockstudio/assets/enable',
						true,
						array(
							'file' => $file,
							'path' => $path,
							'url'  => $asset['url'] ?? '',
							'type' => Assets::is_css( $path ) ? 'css' : 'js',
						)
					)
				) {
					continue;
				}

				if ( self::asset_basename_matches_prefix( $file['basename'], 'admin' ) ) {
					$registry->add_admin_asset(
						sanitize_title( $path ),
						array(
							'path' => $path,
							'key'  => $mtime,
						)
					);
				}

				if ( self::asset_basename_matches_prefix( $file['basename'], 'block-editor' ) ) {
					$registry->add_block_editor_asset(
						sanitize_title( $path ),
						array(
							'path' => $path,
							'key'  => $mtime,
						)
					);
				}

				if ( self::asset_basename_matches_prefix( $file['basename'], 'global' ) ) {
					$registry->add_global_asset(
						sanitize_title( $path ),
						$asset['url'] ?? ''
					);
				}

				$handle = Assets::get_id( $asset_id, $data );
				$type   = Assets::is_css( $path ) ? 'style' : 'script';

				$registry->add_asset(
					$type,
					$handle,
					array(
						'path'  => $asset['url'] ?? '',
						'mtime' => $mtime,
					)
				);
			}
		}
	}

	/**
	 * Discover fields in the local build directory.
	 *
	 * @param string           $path Build path.
	 * @param Discovery_Source $source Active block source.
	 *
	 * @return array Fields, watched field paths, and watched field dirs.
	 */
	private static function discover_local_custom_fields( string $path, Discovery_Source $source ): array {
		$fields_path  = $path . '/fields';
		$field_source = Discovery_Sources::scope( $source, 'fields', $fields_path );
		$result       = array(
			'fields' => array(),
			'paths'  => array(),
			'dirs'   => array(),
		);

		if ( empty( $field_source->entries() ) ) {
			return $result;
		}

		$field_discovery  = new Field_Discovery();
		$result['fields'] = $field_discovery->discover( $field_source );
		$result['dirs']   = $field_source->watch_paths();

		foreach ( $field_source->entries() as $entry ) {
			$result['paths'][] = wp_normalize_path( $entry->physical_path() );
		}

		return $result;
	}

	/**
	 * Register custom field definitions.
	 *
	 * @param array $fields Field definitions.
	 *
	 * @return void
	 */
	private static function register_custom_field_definitions( array $fields ): void {
		if ( empty( $fields ) ) {
			return;
		}

		$field_registry = Field_Registry::instance();

		foreach ( $fields as $field_name => $definition ) {
			$field_registry->register( $field_name, $definition );
		}
	}

	/**
	 * Register filtered custom fields.
	 *
	 * Dynamic field filters are intentionally replayed on every request even
	 * when the filesystem discovery payload came from cache.
	 *
	 * @return void
	 */
	private static function register_filtered_custom_fields(): void {
		/**
		 * Filter additional field discovery paths.
		 *
		 * @param array $paths Array of directory paths to scan for custom fields.
		 */
		$extra_field_paths = apply_filters( 'blockstudio/fields/paths', array() );

		if ( ! empty( $extra_field_paths ) ) {
			$field_discovery = new Field_Discovery();

			foreach ( Discovery_Sources::for_paths( 'fields', $extra_field_paths ) as $source ) {
				self::register_custom_field_definitions(
					$field_discovery->discover( $source )
				);
			}
		}

		/**
		 * Filter for programmatic custom field registration.
		 *
		 * @param array $fields Array of custom field definitions keyed by name.
		 */
		$filter_fields = apply_filters( 'blockstudio/fields', array() );

		if ( ! empty( $filter_fields ) ) {
			self::register_custom_field_definitions( $filter_fields );
		}
	}

	/**
	 * Register discovered block types.
	 *
	 * @param array          $registerable Registerable block payload.
	 * @param Block_Registry $registry     Block registry.
	 *
	 * @return array Cached registration payloads.
	 */
	private static function register_discovered_blocks( array $registerable, Block_Registry $registry ): array {
		$registered   = array();
		$block_lookup = array();
		foreach ( $registerable as $reg_name => $reg_item ) {
			$block_lookup[ $reg_name ] = $reg_item['block_json'];
		}

		foreach ( $registerable as $name => $item ) {
			if ( $item['classification']['is_native'] ?? false ) {
				self::register_native_discovered_block( $name, $item );
				continue;
			}

			$registered[ $name ] = self::register_block_type(
				$item['data'],
				$item['block_json'],
				$item['classification'],
				$item['contents'],
				$name,
				$registry,
				$block_lookup
			);
		}

		return array_filter( $registered );
	}

	/**
	 * Register native WordPress blocks from discovery results.
	 *
	 * @param array $registerable Registerable block payload.
	 *
	 * @return void
	 */
	private static function register_native_discovered_blocks( array $registerable ): void {
		foreach ( $registerable as $name => $item ) {
			if ( $item['classification']['is_native'] ?? false ) {
				self::register_native_discovered_block( $name, $item );
			}
		}
	}

	/**
	 * Keep only native block entries needed on runtime cache hits.
	 *
	 * @param array $registerable Registerable block payload.
	 *
	 * @return array Native registerable payload.
	 */
	private static function filter_native_registerable( array $registerable ): array {
		return array_filter(
			$registerable,
			static fn( $item ) => $item['classification']['is_native'] ?? false
		);
	}

	/**
	 * Register a native WordPress block from a discovery item.
	 *
	 * @param string $name Block name.
	 * @param array  $item Discovery item.
	 *
	 * @return void
	 */
	private static function register_native_discovered_block( string $name, array $item ): void {
		$native_dir = dirname( $item['data']['path'] );

		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			\register_block_type( $native_dir );
		}
	}

	/**
	 * Hydrate cached Blockstudio block type registrations.
	 *
	 * @param array          $registered Cached registration payload.
	 * @param Block_Registry $registry   Block registry.
	 *
	 * @return void
	 */
	private static function hydrate_cached_registered_block_types( array $registered, Block_Registry $registry ): void {
		foreach ( $registered as $item ) {
			if ( ! is_array( $item ) || empty( $item['block'] ) ) {
				continue;
			}

			$block = self::hydrate_cached_block_type( $item['block'] );

			if ( ! empty( $item['storageAttributes'] ) ) {
				Field_Type_Registry::instance()->mark_used_fields( $item['storageAttributes'] );

				foreach ( (array) $block->name as $storage_block_name ) {
					if ( is_string( $storage_block_name ) ) {
						Storage_Registry::instance()->process_block_fields(
							$storage_block_name,
							$item['storageAttributes']
						);
					}
				}
			}

			if ( 'override' === ( $item['kind'] ?? '' ) ) {
				$registry->register_override(
					$item['name'] ?? $block->name,
					$block,
					$item['overrideConfig'] ?? array()
				);
				continue;
			}

			if ( 'extension' === ( $item['kind'] ?? '' ) ) {
				$registry->register_extension( $block );
				continue;
			}

			$registry->register_block( $block->name, $block );
		}
	}

	/**
	 * Serialize a Blockstudio block type for the runtime cache.
	 *
	 * @param \WP_Block_Type $block WP block type.
	 *
	 * @return array Serialized block type.
	 */
	private static function serialize_block_type( \WP_Block_Type $block ): array {
		$properties = array();

		foreach ( get_object_vars( $block ) as $property => $value ) {
			if ( 'render_callback' === $property ) {
				continue;
			}

			$properties[ $property ] = self::normalize_cache_value( $value );
		}

		foreach ( array( 'uses_context', 'variations' ) as $property ) {
			$properties[ $property ] = self::normalize_cache_value( $block->{$property} );
		}

		return array(
			'name'       => $block->name,
			'properties' => $properties,
		);
	}

	/**
	 * Hydrate a cached Blockstudio block type.
	 *
	 * @param array $payload Serialized block type.
	 *
	 * @return \WP_Block_Type Hydrated block type.
	 */
	private static function hydrate_cached_block_type( array $payload ): \WP_Block_Type {
		$block = new \WP_Block_Type( $payload['name'], array() );

		foreach ( $payload['properties'] ?? array() as $property => $value ) {
			$block->{$property} = $value;
		}

		$block->render_callback = array( 'Blockstudio\Block', 'render' );

		return $block;
	}

	/**
	 * Normalize values so cache payloads can be exported as PHP arrays.
	 *
	 * @param mixed $value Value to normalize.
	 *
	 * @return mixed Normalized value.
	 */
	private static function normalize_cache_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::normalize_cache_value( $item );
			}

			return $value;
		}

		if ( $value instanceof \WP_Post ) {
			return $value->to_array();
		}

		if ( $value instanceof \WP_User ) {
			return $value->to_array();
		}

		if ( $value instanceof \WP_Term ) {
			return get_object_vars( $value );
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'to_array' ) ) {
				return self::normalize_cache_value( $value->to_array() );
			}

			return self::normalize_cache_value( get_object_vars( $value ) );
		}

		return $value;
	}

	/**
	 * Enqueue the Interactivity API if any registered block needs it.
	 *
	 * @param Block_Registry $registry Block registry.
	 *
	 * @return void
	 */
	private static function maybe_enqueue_interactivity_api( Block_Registry $registry ): void {
		if ( self::$interactivity_api_rendered ) {
			return;
		}

		foreach ( $registry->get_blocks() as $block ) {
			if ( self::has_interactivity( $block->blockstudio ?? array() ) ) {
				self::$interactivity_api_rendered = true;
				add_action(
					'wp_enqueue_scripts',
					static function () {
						wp_enqueue_script_module( '@wordpress/interactivity' );
					}
				);
				break;
			}
		}
	}

	/**
	 * Re-discover blocks for all registered instances.
	 *
	 * Finds new blocks added to the filesystem and removes blocks whose
	 * directories have been deleted. Existing blocks are left untouched.
	 *
	 * @since 7.0.0
	 *
	 * @param bool          $allow_cached Whether a valid runtime cache may skip discovery.
	 * @param array<string> $only_blocks Explicit existing block names to rediscover.
	 *
	 * @return void
	 */
	public static function refresh_blocks( bool $allow_cached = false, array $only_blocks = array() ): void {
		$registry = Block_Registry::instance();

		// If the default build dir was created after init (e.g. during a live
		// canvas session), register it now so we can discover its blocks.
		$default_path       = wp_normalize_path( self::get_build_dir() );
		$default_registered = false;

		foreach ( $registry->get_instances() as $inst ) {
			if ( wp_normalize_path( $inst['path'] ) === $default_path ) {
				$default_registered = true;
				break;
			}
		}

		if ( ! $default_registered && is_dir( $default_path ) ) {
			$instance = self::get_instance_name( $default_path );
			$registry->add_instance( $default_path );
			$registry->add_path( $instance, $default_path );
		}

		if ( $allow_cached && self::refresh_runtime_cache_is_current( $registry ) ) {
			return;
		}

		$only_blocks = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $name ): string => is_string( $name ) ? strtolower( trim( $name ) ) : '',
						$only_blocks
					),
					static fn( string $name ): bool => (bool) preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name )
				)
			)
		);

		if ( array() !== $only_blocks ) {
			self::refresh_selected_blocks( $registry, $only_blocks );
			return;
		}

		$existing_block_names = array_keys( $registry->get_blocks() );
		$discovered_names     = array();

		foreach ( $registry->get_instances() as $instance_data ) {
			$path     = wp_normalize_path( $instance_data['path'] );
			$instance = self::get_instance_name( $path );
			$source   = Discovery_Sources::for_path( 'blocks', $path );

			if ( empty( $source->entries() ) ) {
				continue;
			}

			$discovery        = new Block_Discovery();
			$results          = $discovery->discover( $source, $instance, false );
			$discovered_names = array_merge(
				$discovered_names,
				self::register_new_discovery_results(
					$registry,
					$results,
					$instance,
					$existing_block_names
				)
			);
		}

		foreach ( $existing_block_names as $name ) {
			if ( ! in_array( $name, $discovered_names, true ) ) {
				$registry->remove_block( $name );

				if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
					\WP_Block_Type_Registry::get_instance()->unregister( $name );
				}
			}
		}
	}

	/**
	 * Discover and register brand-new blocks only around changed source files.
	 *
	 * Existing blocks use refresh_blocks() with canonical names. This method is
	 * the topology counterpart for live consumers: it scopes Block_Discovery to
	 * the exact changed directories and never scans unrelated block directories.
	 *
	 * @since 7.6.0
	 *
	 * @param array<string> $paths Changed physical source files.
	 *
	 * @return array<int, string> Newly registered canonical block names.
	 */
	public static function refresh_block_paths( array $paths ): array {
		$paths = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $path ): string => is_string( $path )
							? wp_normalize_path( $path )
							: '',
						$paths
					),
					static fn( string $path ): bool => '' !== $path && is_file( $path )
				)
			)
		);

		if ( array() === $paths ) {
			return array();
		}

		$registry = Block_Registry::instance();
		$before   = array_fill_keys( array_keys( $registry->get_blocks() ), true );
		$found    = array();

		foreach ( $registry->get_instances() as $instance_data ) {
			$instance_path = is_string( $instance_data['path'] ?? null )
				? wp_normalize_path( $instance_data['path'] )
				: '';

			if ( '' === $instance_path ) {
				continue;
			}

			$source      = Discovery_Sources::for_path( 'blocks', $instance_path );
			$directories = array();
			$all_entries = $source->entries();

			foreach ( $all_entries as $entry ) {
				if ( ! in_array( wp_normalize_path( $entry->physical_path() ), $paths, true ) ) {
					continue;
				}

				$directory                 = dirname( Discovery_Sources::normalize_logical_path( $entry->logical_path() ) );
				$directories[ $directory ] = true;
			}

			if ( array() === $directories ) {
				continue;
			}

			$entries = array();

			foreach ( $all_entries as $entry ) {
				$logical = Discovery_Sources::normalize_logical_path( $entry->logical_path() );

				foreach ( array_keys( $directories ) as $directory ) {
					if ( '.' === $directory
						|| $logical === $directory
						|| str_starts_with( $logical, $directory . '/' )
					) {
						$entries[ $logical ] = $entry;
						break;
					}
				}
			}

			$selected_source = new Inventory_Discovery_Source(
				$source->id() . '#changed-topology:' . hash( 'sha256', implode( "\n", array_keys( $entries ) ) ),
				$source->root(),
				$entries,
				null,
				$paths
			);
			$instance        = self::get_instance_name( $instance_path );
			$discovery       = new Block_Discovery();
			$results         = $discovery->discover( $selected_source, $instance, false );

			$found = array_merge(
				$found,
				self::register_new_discovery_results(
					$registry,
					$results,
					$instance,
					array_keys( $registry->get_blocks() )
				)
			);
		}

		$names = array_values(
			array_unique(
				array_filter(
					$found,
					static fn( string $name ): bool => ! isset( $before[ $name ] )
						&& \WP_Block_Type_Registry::get_instance()->is_registered( $name )
				)
			)
		);

		/**
		 * Fires after scoped changed-path topology discovery.
		 *
		 * @since 7.6.0
		 *
		 * @param array<int, string> $names Newly registered block names.
		 * @param array<int, string> $paths Changed physical paths.
		 */
		do_action( 'blockstudio/blocks/topology_refreshed', $names, $paths );

		return $names;
	}

	/**
	 * Register only new block results from one already-scoped discovery pass.
	 *
	 * @param Block_Registry $registry             Block registry.
	 * @param array          $results              Block_Discovery result.
	 * @param string         $instance             Instance name.
	 * @param array<string>  $existing_block_names Names that predate the pass.
	 *
	 * @return array<int, string> Discoverable names in this source.
	 */
	private static function register_new_discovery_results(
		Block_Registry $registry,
		array $results,
		string $instance,
		array $existing_block_names
	): array {
		self::filter_missing_plugin_dependencies(
			$results['store'],
			$results['registerable'],
			$results['overrides'],
			$results['block_json_data'] ?? array()
		);

		$refresh_lookup = array();

		foreach ( $results['registerable'] as $name => $item ) {
			$refresh_lookup[ $name ] = $item['block_json'];
		}

		foreach ( $results['registerable'] as $name => $item ) {
			if ( in_array( $name, $existing_block_names, true ) ) {
				continue;
			}

			if ( $item['classification']['is_native'] ?? false ) {
				$native_dir = dirname( $item['data']['path'] );

				if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
					\register_block_type( $native_dir );
				}
				continue;
			}

			if ( Settings::get( 'assets/enqueue' ) ) {
				self::process_block_assets(
					$item['data'],
					$name,
					$instance,
					false,
					$registry
				);
			}

			self::register_block_type(
				$item['data'],
				$item['block_json'],
				$item['classification'],
				$item['contents'],
				$name,
				$registry,
				$refresh_lookup
			);

			$block = $registry->get_block( $name );
			if ( $block && empty( $block->blockstudio['component'] ) ) {
				\register_block_type(
					apply_filters( 'blockstudio/blocks/meta', $block )
				);
			}
		}

		$new_store = array_diff_key( $results['store'], array_flip( $existing_block_names ) );
		if ( ! empty( $new_store ) ) {
			$registry->merge_data( $new_store );
		}

		return array_keys( $results['registerable'] );
	}

	/**
	 * Re-discover exact existing blocks without scanning unrelated directories.
	 *
	 * Unknown names intentionally remain untouched: locating a newly added
	 * block requires a topology refresh, while a changed-only request must not
	 * inspect unrelated source directories.
	 *
	 * @param Block_Registry $registry     Block registry.
	 * @param array<string>  $only_blocks Existing canonical names.
	 *
	 * @return void
	 */
	private static function refresh_selected_blocks( Block_Registry $registry, array $only_blocks ): void {
		$existing_data = $registry->get_data();

		foreach ( $only_blocks as $requested_name ) {
			$current = $existing_data[ $requested_name ] ?? null;

			if ( ! is_array( $current ) ) {
				continue;
			}

			$instance_path = isset( $current['instancePath'] ) && is_string( $current['instancePath'] )
				? wp_normalize_path( $current['instancePath'] )
				: '';
			$logical_path  = isset( $current['logicalPath'] ) && is_string( $current['logicalPath'] )
				? Discovery_Sources::normalize_logical_path( $current['logicalPath'] )
				: '';

			if ( '' === $instance_path || '' === $logical_path ) {
				continue;
			}

			$logical_directory = dirname( $logical_path );
			$logical_directory = '.' === $logical_directory ? '' : $logical_directory;
			$source            = Discovery_Sources::for_path( 'blocks', $instance_path );
			$entries           = array();

			foreach ( $source->entries( $logical_directory, true ) as $entry ) {
				$entry_path = Discovery_Sources::normalize_logical_path( $entry->logical_path() );

				if ( '' === $logical_directory
					|| $entry_path === $logical_directory
					|| str_starts_with( $entry_path, $logical_directory . '/' )
				) {
					$entries[ $entry_path ] = $entry;
				}
			}

			$selected_source = new Inventory_Discovery_Source(
				$source->id() . '#block:' . $requested_name,
				$source->root(),
				$entries,
				null,
				$source->watch_paths()
			);
			$instance        = self::get_instance_name( $instance_path );
			$discovery       = new Block_Discovery();
			$results         = $discovery->discover( $selected_source, $instance, false );

			$results['store']        = array_intersect_key( $results['store'], array( $requested_name => true ) );
			$results['registerable'] = array_intersect_key( $results['registerable'], array( $requested_name => true ) );
			$results['overrides']    = array_intersect_key( $results['overrides'], array( $requested_name => true ) );

			self::filter_missing_plugin_dependencies(
				$results['store'],
				$results['registerable'],
				$results['overrides'],
				$results['block_json_data'] ?? array()
			);

			$registry->remove_block( $requested_name );

			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $requested_name ) ) {
				\WP_Block_Type_Registry::get_instance()->unregister( $requested_name );
			}

			$item = $results['registerable'][ $requested_name ] ?? null;

			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( $item['classification']['is_native'] ?? false ) {
				$native_dir = dirname( $item['data']['path'] );
				\register_block_type( $native_dir );
				continue;
			}

			if ( Settings::get( 'assets/enqueue' ) ) {
				self::process_block_assets(
					$item['data'],
					$requested_name,
					$instance,
					false,
					$registry
				);
			}

			$refresh_lookup = array(
				$requested_name => $item['block_json'],
			);

			self::register_block_type(
				$item['data'],
				$item['block_json'],
				$item['classification'],
				$item['contents'],
				$requested_name,
				$registry,
				$refresh_lookup
			);

			$block = $registry->get_block( $requested_name );
			if ( $block && empty( $block->blockstudio['component'] ) ) {
				\register_block_type(
					apply_filters( 'blockstudio/blocks/meta', $block )
				);
			}

			if ( isset( $results['store'][ $requested_name ] ) ) {
				$registry->set_block_data( $requested_name, $results['store'][ $requested_name ] );
			}
		}
	}

	/**
	 * Check whether the current registry still matches valid runtime caches.
	 *
	 * Callers may use this only after independently observing an unchanged
	 * filesystem fingerprint. Runtime cache directory mtimes alone are too
	 * coarse to replace an explicit refresh after files change.
	 *
	 * @param Block_Registry $registry Block registry.
	 *
	 * @return bool Whether refresh can be skipped.
	 */
	private static function refresh_runtime_cache_is_current( Block_Registry $registry ): bool {
		$instances = $registry->get_instances();

		if ( empty( $instances ) ) {
			return false;
		}

		$cached_block_names = array();

		foreach ( $instances as $instance_data ) {
			$path = isset( $instance_data['path'] ) && is_string( $instance_data['path'] )
				? wp_normalize_path( $instance_data['path'] )
				: '';

			if ( '' === $path || ! is_dir( $path ) ) {
				return false;
			}

			$instance = self::get_instance_name( $path );
			$cached   = Build_Cache::load_runtime( $path, $instance );

			if ( ! is_array( $cached ) ) {
				return false;
			}

			$registered = $cached['registeredBlockTypes'] ?? array();

			if ( ! is_array( $registered ) ) {
				return false;
			}

			foreach ( array_keys( $registered ) as $name ) {
				if ( is_string( $name ) && '' !== $name ) {
					$cached_block_names[ $name ] = true;
				}
			}
		}

		$current_block_names = array_fill_keys( array_keys( $registry->get_blocks() ), true );
		ksort( $cached_block_names );
		ksort( $current_block_names );

		return array_keys( $cached_block_names ) === array_keys( $current_block_names );
	}

	/**
	 * Process assets for a block.
	 *
	 * @since 7.0.0
	 *
	 * @param array          $data     The block data (passed by reference).
	 * @param string         $name     The block name.
	 * @param string         $instance The instance name.
	 * @param bool           $editor   Whether in editor mode.
	 * @param Block_Registry $registry The block registry.
	 *
	 * @return array The processed assets.
	 */
	private static function process_block_assets(
		array &$data,
		string $name,
		string $instance,
		bool $editor,
		Block_Registry $registry
	): array {
		$block_arr_files  = $data['files'];
		$processed_assets = array();

		$assets = array_filter(
			$block_arr_files,
			fn( $e ) => Assets::is_css( $e ) || str_ends_with( $e, '.js' )
		);

		$file_classifier = new File_Classifier();

		foreach ( $assets as $asset ) {
			$is_css = Assets::is_css( $asset );

			$asset_path  = $data['filesMap'][ $asset ] ?? dirname( $data['path'] ) . '/' . $asset;
			$asset_file  = pathinfo( $asset_path );
			$asset_url   = Files::get_relative_url( $asset_path );
			$asset_mtime = filemtime( $asset_path );
			$asset_key   = Assets::get_asset_version(
				$asset_path,
				$data['scopedClass'] ?? '',
				$asset_mtime
			);

			if (
				false === apply_filters(
					'blockstudio/assets/enable',
					true,
					array(
						'file' => $asset_file,
						'path' => $asset_path,
						'url'  => $asset_url,
						'type' => $is_css ? 'css' : 'js',
					)
				)
			) {
				continue;
			}

			if ( ! $editor ) {
				$processed_asset = Assets::process(
					$asset_path,
					$data['scopedClass']
				);

				if ( is_array( $processed_asset ) ) {
					$processed_assets = array_merge(
						$processed_assets,
						$processed_asset
					);
				} else {
					$processed_assets[] = $processed_asset;
				}
			}

			$id = strtolower(
				preg_replace( '/(?<!^)[A-Z]/', '-$0', $asset )
			);

			if ( self::asset_basename_matches_prefix( $asset_file['basename'], 'admin' ) && ! $editor ) {
				$registry->add_admin_asset(
					sanitize_title( $asset_path ),
					array(
						'path' => $asset_path,
						'key'  => $asset_key,
					)
				);
			}

			if ( self::asset_basename_matches_prefix( $asset_file['basename'], 'block-editor' ) && ! $editor ) {
				$registry->add_block_editor_asset(
					sanitize_title( $asset_path ),
					array(
						'path' => $asset_path,
						'key'  => $asset_key,
					)
				);
			}

			if ( self::asset_basename_matches_prefix( $asset_file['basename'], 'global' ) && ! $editor ) {
				$registry->add_global_asset(
					sanitize_title( $asset_path ),
					$asset_url
				);
			}

			$handle          = Assets::get_id( $id, $data );
			$is_editor_asset = $file_classifier->is_editor_asset( $asset );

			$data['assets'][ $id ] = array(
				'type'     =>
					str_ends_with( $asset, '.inline.css' ) ||
					str_ends_with( $asset, '.inline.scss' ) ||
					str_ends_with( $asset, '.inline.js' ) ||
					str_ends_with( $asset, '.scoped.css' ) ||
					str_ends_with( $asset, '.scoped.scss' ) ||
					str_ends_with( $asset, '-inline.css' ) ||
					str_ends_with( $asset, '-inline.scss' ) ||
					str_ends_with( $asset, '-inline.js' ) ||
					str_ends_with( $asset, '-scoped.css' ) ||
					str_ends_with( $asset, '-scoped.scss' )
						? 'inline'
						: 'external',
				'path'     => $asset_path,
				'url'      => $asset_url,
				'editor'   => $is_editor_asset,
				'instance' => $instance,
				'key'      => $asset_key,
				'mtime'    => $asset_mtime,
				'file'     => $asset_file,
			);

			$asset_dependencies = Assets::get_asset_dependency_paths( $asset_path );

			if ( array() !== $asset_dependencies ) {
				$data['assets'][ $id ]['dependencies'] = $asset_dependencies;
			}

			if ( ! $editor ) {
				if ( $is_css ) {
					$registry->add_asset(
						'style',
						$handle,
						array(
							'path'  => $asset_url,
							'mtime' => $asset_key,
						)
					);
				} else {
					$registry->add_asset(
						'script',
						$handle,
						array(
							'path'  => $asset_url,
							'mtime' => $asset_key,
						)
					);
				}
			}
		}

		return $processed_assets;
	}

	/**
	 * Expand custom field references in an attributes array.
	 *
	 * Custom field references use the format "custom/{name}" as their type.
	 * This method splices the referenced field definitions inline, applying
	 * idStructure and overrides. Recurses into groups, tabs, and repeaters.
	 *
	 * @since 7.0.0
	 *
	 * @param array $attributes   The attributes array (passed by reference).
	 * @param array $block_lookup Block JSON data indexed by block name for resolving block field references.
	 *
	 * @return void
	 */
	public static function expand_custom_fields( array &$attributes, array $block_lookup = array() ): void {
		$registry = Field_Registry::instance();
		$expanded = array();

		foreach ( $attributes as $attr ) {
			$type = $attr['type'] ?? '';

			if ( 'group' === $type && isset( $attr['attributes'] ) ) {
				self::expand_custom_fields( $attr['attributes'], $block_lookup );
				$expanded[] = $attr;
				continue;
			}

			if ( 'tabs' === $type && isset( $attr['tabs'] ) ) {
				foreach ( $attr['tabs'] as &$tab ) {
					if ( isset( $tab['attributes'] ) ) {
						self::expand_custom_fields( $tab['attributes'], $block_lookup );
					}
				}
				unset( $tab );
				$expanded[] = $attr;
				continue;
			}

			if ( 'repeater' === $type && isset( $attr['attributes'] ) ) {
				self::expand_custom_fields( $attr['attributes'], $block_lookup );
				$expanded[] = $attr;
				continue;
			}

			$is_custom  = str_starts_with( $type, 'custom/' );
			$is_block   = 'block' === $type && isset( $attr['block'] );
			$block_name = '';

			if ( ! $is_custom && ! $is_block ) {
				$expanded[] = $attr;
				continue;
			}

			if ( $is_custom ) {
				$field_name      = substr( $type, 7 );
				$definition_atts = $registry->get( $field_name )['attributes'] ?? null;
			} else {
				$block_name      = $attr['block'];
				$definition_atts = $block_lookup[ $block_name ]['blockstudio']['attributes'] ?? null;
			}

			if ( ! $definition_atts ) {
				$expanded[] = $attr;
				continue;
			}

			$definition_atts = array_values( $definition_atts );
			self::expand_custom_fields( $definition_atts, $block_lookup );

			$id_structure   = $attr['idStructure'] ?? '{id}';
			$overrides      = $attr['overrides'] ?? array();
			$field_id       = $attr['id'] ?? '';
			$ref_conditions = $attr['conditions'] ?? null;

			if ( $is_block && $field_id ) {
				$expanded[] = array(
					'id'           => $field_id,
					'type'         => 'text',
					'hidden'       => true,
					'_blockField'  => true,
					'_blockName'   => $block_name,
					'_blockIds'    => array(),
					'_idStructure' => $id_structure,
					'returnFormat' => $attr['returnFormat'] ?? 'rendered',
				);
				$expanded[] = array(
					'id'      => $field_id . '_block',
					'type'    => 'text',
					'hidden'  => true,
					'default' => $block_name,
				);
			}

			$block_field_ids = array();

			foreach ( $definition_atts as $field_attr ) {
				$original_attr   = $field_attr;
				$original_id     = $field_attr['id'] ?? '';
				$field_override  = $overrides[ $original_id ] ?? array();
				$merged          = array_merge( $field_attr, $field_override );
				$skip_current_id = isset( $field_override['id'] );

				if ( '{id}' !== $id_structure ) {
					self::rewrite_attribute_ids(
						$merged,
						$id_structure,
						false,
						false,
						$skip_current_id
					);
				}

				if ( $ref_conditions ) {
					$merged['conditions'] = isset( $merged['conditions'] )
						? array_merge( $merged['conditions'], $ref_conditions )
						: $ref_conditions;
				}
				$expanded[] = $merged;

				if ( $is_block ) {
					self::collect_block_field_ids(
						$merged,
						$original_attr,
						$block_field_ids
					);
				}
			}

			if ( $is_block && $field_id ) {
				foreach ( $expanded as $ei => &$eattr ) {
					if ( ( $eattr['id'] ?? '' ) === $field_id && ! empty( $eattr['_blockField'] ) ) {
						$eattr['_blockIds'] = $block_field_ids;
						break;
					}
				}
				unset( $eattr );
			}
		}

		$attributes = $expanded;
	}

	/**
	 * Rewrite condition IDs using an idStructure pattern.
	 *
	 * @since 7.1.0
	 *
	 * @param array  $conditions   The conditions array (passed by reference).
	 * @param string $id_structure The idStructure pattern.
	 *
	 * @return void
	 */
	private static function rewrite_condition_ids( array &$conditions, string $id_structure ): void {
		foreach ( $conditions as &$group ) {
			foreach ( $group as &$condition ) {
				if ( isset( $condition['id'] ) ) {
					$condition['id'] = str_replace( '{id}', $condition['id'], $id_structure );
				}
			}
		}
		unset( $group, $condition );
	}

	/**
	 * Rewrite an expanded custom field attribute using an idStructure pattern.
	 *
	 * @since 7.2.3
	 *
	 * @param array  $attribute       The attribute to rewrite (passed by reference).
	 * @param string $id_structure    The idStructure pattern.
	 * @param bool   $inside_repeater Whether the attribute belongs to a repeater row.
	 * @param bool   $inside_id_group Whether an ID-bearing group will prefix the attribute.
	 * @param bool   $skip_current_id Whether the current attribute ID was explicitly overridden.
	 *
	 * @return void
	 */
	private static function rewrite_attribute_ids(
		array &$attribute,
		string $id_structure,
		bool $inside_repeater = false,
		bool $inside_id_group = false,
		bool $skip_current_id = false
	): void {
		$type   = $attribute['type'] ?? '';
		$has_id = isset( $attribute['id'] ) && '' !== $attribute['id'];

		if ( ! $inside_repeater && ! $inside_id_group && $has_id && ! $skip_current_id ) {
			$attribute['id'] = str_replace( '{id}', $attribute['id'], $id_structure );
		}

		if ( ! $inside_repeater && isset( $attribute['conditions'] ) ) {
			self::rewrite_condition_ids( $attribute['conditions'], $id_structure );
		}

		if ( 'tabs' === $type && isset( $attribute['tabs'] ) ) {
			foreach ( $attribute['tabs'] as &$tab ) {
				if ( isset( $tab['attributes'] ) ) {
					foreach ( $tab['attributes'] as &$tab_attribute ) {
						self::rewrite_attribute_ids(
							$tab_attribute,
							$id_structure,
							$inside_repeater,
							$inside_id_group
						);
					}
					unset( $tab_attribute );
				}
			}
			unset( $tab );
		}

		if ( 'group' === $type && isset( $attribute['attributes'] ) ) {
			$children_inside_id_group = $inside_id_group || ( ! $inside_repeater && $has_id );

			foreach ( $attribute['attributes'] as &$group_attribute ) {
				self::rewrite_attribute_ids(
					$group_attribute,
					$id_structure,
					$inside_repeater,
					$children_inside_id_group
				);
			}
			unset( $group_attribute );
		}
	}

	/**
	 * Collect mapped field IDs for block fields after idStructure expansion.
	 *
	 * @since 7.2.3
	 *
	 * @param array  $rewritten          The rewritten attribute.
	 * @param array  $original           The original attribute.
	 * @param array  $mapped             The collected map (passed by reference).
	 * @param string $rewritten_prefix   The rewritten group prefix.
	 * @param string $original_prefix    The original group prefix.
	 * @param bool   $inside_repeater    Whether the attribute belongs to a repeater row.
	 *
	 * @return void
	 */
	private static function collect_block_field_ids(
		array $rewritten,
		array $original,
		array &$mapped,
		string $rewritten_prefix = '',
		string $original_prefix = '',
		bool $inside_repeater = false
	): void {
		$type = $rewritten['type'] ?? '';

		if ( 'tabs' === $type && isset( $rewritten['tabs'], $original['tabs'] ) ) {
			foreach ( $rewritten['tabs'] as $index => $tab ) {
				if ( ! isset( $tab['attributes'], $original['tabs'][ $index ]['attributes'] ) ) {
					continue;
				}

				foreach ( $tab['attributes'] as $attribute_index => $tab_attribute ) {
					if ( ! isset( $original['tabs'][ $index ]['attributes'][ $attribute_index ] ) ) {
						continue;
					}

					self::collect_block_field_ids(
						$tab_attribute,
						$original['tabs'][ $index ]['attributes'][ $attribute_index ],
						$mapped,
						$rewritten_prefix,
						$original_prefix,
						$inside_repeater
					);
				}
			}
			return;
		}

		if ( 'group' === $type && isset( $rewritten['attributes'], $original['attributes'] ) ) {
			$next_rewritten_prefix = $rewritten_prefix;
			$next_original_prefix  = $original_prefix;

			if ( ! empty( $rewritten['id'] ) ) {
				$next_rewritten_prefix .= $rewritten['id'] . '_';
			}

			if ( ! empty( $original['id'] ) ) {
				$next_original_prefix .= $original['id'] . '_';
			}

			foreach ( $rewritten['attributes'] as $index => $group_attribute ) {
				if ( ! isset( $original['attributes'][ $index ] ) ) {
					continue;
				}

				self::collect_block_field_ids(
					$group_attribute,
					$original['attributes'][ $index ],
					$mapped,
					$next_rewritten_prefix,
					$next_original_prefix,
					$inside_repeater
				);
			}
			return;
		}

		if ( empty( $rewritten['id'] ) || empty( $original['id'] ) ) {
			return;
		}

		$mapped[ $rewritten_prefix . $rewritten['id'] ] = $original_prefix . $original['id'];
	}

	/**
	 * Register a block type with WordPress.
	 *
	 * @since 7.0.0
	 *
	 * @param array          $data           The block data.
	 * @param array          $block_json     The block.json data.
	 * @param array          $classification The classification.
	 * @param string         $contents       The file contents.
	 * @param string         $name           The block name.
	 * @param Block_Registry $registry       The block registry.
	 * @param array          $block_lookup   Block JSON data indexed by block name.
	 *
	 * @return array Cached registration payload.
	 */
	private static function register_block_type(
		array $data,
		array $block_json,
		array $classification,
		string $contents,
		string $name,
		Block_Registry $registry,
		array $block_lookup = array()
	): array {
		$is_block    = $classification['is_block'];
		$is_override = $classification['is_override'];
		$is_extend   = $classification['is_extend'];

		$native_path = $is_override && ! $is_block
			? $data['path']
			: ( $data['renderTemplate'] ?? Files::get_render_template( $data['path'] ) );

		$attributes          = array();
		$filtered_attributes = array();

		if ( isset( $block_json['blockstudio']['attributes'] ) ) {
			self::expand_custom_fields( $block_json['blockstudio']['attributes'], $block_lookup );
			Field_Type_Registry::instance()->mark_used_fields( $block_json['blockstudio']['attributes'] );

			if ( ! $is_override ) {
				self::filter_attributes(
					$block_json,
					$block_json['blockstudio']['attributes'],
					$filtered_attributes
				);
			}

			$attributes = ( new Attribute_Builder() )->build(
				$is_override ? $block_json['blockstudio']['attributes'] : $filtered_attributes,
				false,
				$is_extend
			);

			// Register storage handlers for fields.
			Storage_Registry::instance()->process_block_fields(
				$name,
				$block_json['blockstudio']['attributes']
			);
		}

		$attributes['blockstudio'] = array(
			'type'    => 'object',
			'default' => array(
				'name' => $block_json['name'],
			),
		);

		$attributes['anchor'] = $is_extend
			? array(
				'type'      => 'string',
				'source'    => 'attribute',
				'attribute' => 'id',
				'selector'  => '*',
			)
			: array(
				'type' => 'string',
			);

		$attributes['className'] = array(
			'type' => 'string',
		);

		if ( ! $is_extend ) {
			$attributes = self::remove_expanded_populate_options( $attributes );
		}
		$filtered_attributes      = self::remove_expanded_populate_options( $filtered_attributes );
		$block_json['attributes'] = self::remove_expanded_populate_options(
			$block_json['attributes'] ?? array()
		);

		$block                   = new WP_Block_Type( $block_json['name'], $block_json );
		$block->api_version      = 3;
		$block->render_callback  = array( 'Blockstudio\Block', 'render' );
		$block->attributes       = array_merge(
			$block_json['attributes'] ?? array(),
			$attributes
		);
		$block->uses_context     = array_merge(
			array( 'postId', 'postType' ),
			$block_json['usesContext'] ?? array()
		);
		$block->provides_context = array_merge(
			array( $name => 'blockstudio' ),
			$block_json['providesContext'] ?? array()
		);
		$block->path             = $native_path;

		if ( isset( $block_json['variations'] ) ) {
			$variations = array();
			foreach ( $block_json['variations'] as $variation ) {
				$variations[] = array(
					'attributes' => array(
						'blockstudio' => array(
							'attributes' => $variation['attributes'],
						),
					),
				) + $variation;
			}
			$block->variations = $variations;
		}

		$disable_loading      = $block_json['blockstudio']['blockEditor']['disableLoading']
			?? ( Settings::get( 'blockEditor/disableLoading' ) ?? false );
		$blockstudio_settings = is_array( $block_json['blockstudio'] ?? null )
			? $block_json['blockstudio']
			: array();

		$block->blockstudio = array(
			'attributes'         => $filtered_attributes,
			'blockEditor'        => array(
				'disableLoading' => $disable_loading,
			),
			'component'          => $classification['is_component'] ?? false,
			'conditions'         => $block->blockstudio['conditions'] ?? true,
			'pluginDependencies' => self::normalize_plugin_dependencies(
				$blockstudio_settings['pluginDependencies'] ?? array()
			),
			'editor'             => $block->blockstudio['editor'] ?? false,
			'extend'             => $block->blockstudio['extend'] ?? false,
			'group'              => $block->blockstudio['group'] ?? false,
			'icon'               => $block->blockstudio['icon'] ?? null,
			'interactivity'      => $block->blockstudio['interactivity'] ?? false,
			'island'             => Islands::normalize_config(
				$blockstudio_settings['island'] ?? false
			),
			'refreshOn'          => $block->blockstudio['refreshOn'] ?? false,
			'transforms'         => $block->blockstudio['transforms'] ?? false,
			'variations'         => $block->variations ?? false,
		);

		if ( $is_override ) {
			$registry->register_override(
				$block_json['name'],
				$block,
				json_decode( $contents, true )
			);
		} elseif ( ! $is_extend ) {
			$registry->register_block( $block_json['name'], $block );
		} else {
			$registry->register_extension( $block );
		}

		$override_config = $is_override ? json_decode( $contents, true ) : array();
		$override_config = is_array( $override_config ) ? $override_config : array();

		return array(
			'kind'              => $is_override ? 'override' : ( $is_extend ? 'extension' : 'block' ),
			'name'              => $block_json['name'],
			'block'             => self::serialize_block_type( $block ),
			'overrideConfig'    => $override_config,
			'storageAttributes' => $block_json['blockstudio']['attributes'] ?? array(),
		);
	}

	/**
	 * Apply overrides to registered blocks.
	 *
	 * @since 7.0.0
	 *
	 * @param Block_Registry $registry The block registry.
	 *
	 * @return void
	 */
	private static function apply_overrides( Block_Registry $registry ): void {
		foreach ( $registry->get_blocks() as $block ) {
			$override_config = $registry->get_override_config( $block->name );
			if ( ! $override_config ) {
				continue;
			}

			foreach ( $override_config as $key => $value ) {
				if ( 'blockstudio' === $key ) {
					$override_attributes = $value['attributes'] ?? array();
					self::merge_attributes(
						$block->blockstudio['attributes'],
						$override_attributes
					);

					$override_built_attributes = ( new Attribute_Builder() )->build(
						$override_attributes,
						true
					);

					$override_block = $registry->get_override( $block->name );
					if ( $override_block ) {
						$override_block->attributes = $override_built_attributes;
					}

					self::merge_attributes(
						$block->attributes,
						$override_built_attributes
					);

					$mapped_attributes = array();
					foreach ( $block->attributes as $attr_name => $attribute ) {
						if ( isset( $attribute['id'] ) ) {
							$mapped_attributes[ $attribute['id'] ] = $attribute;
						} else {
							$mapped_attributes[ $attr_name ] = $attribute;
						}
					}
					$block->attributes = $mapped_attributes;

					continue;
				}

				$block->{$key} = $value;
			}

			$block_data = $registry->get_block_data( $block->name );
			if ( $block_data ) {
				$block_data['assets'] = array_merge(
					$block_data['assets'] ?? array(),
					$registry->get_block_data( $block->name . '-override' )['assets'] ?? array()
				);
				$registry->set_block_data( $block->name, $block_data );
			}
		}
	}

	/**
	 * Convert a path to array.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $array     The array (passed by reference).
	 * @param string $path      The path.
	 * @param mixed  $value     The value.
	 * @param string $delimiter The delimiter.
	 *
	 * @return mixed The backup value.
	 */
	public static function path_to_array(
		&$array,
		$path,
		$value,
		string $delimiter = '/'
	) {
		$path_parts = explode( $delimiter, $path );

		$current = &$array;
		foreach ( $path_parts as $key ) {
			$current = &$current[ $key ];
		}

		$backup  = $current;
		$current = $value;

		return $backup;
	}

	/**
	 * Recursive sort files.
	 *
	 * @since 5.0.0
	 *
	 * @param array $arr The array (passed by reference).
	 *
	 * @return void
	 */
	public static function recursive_sort( &$arr ) {
		foreach ( $arr as &$value ) {
			if ( is_array( $value ) && array_key_exists( '.', $value ) ) {
				self::recursive_sort( $value );
			}
		}

		uksort(
			$arr,
			function ( $a, $b ) use ( &$arr ) {
				$a_is_dir =
					isset( $arr[ $a ] ) &&
					is_array( $arr[ $a ] ) &&
					array_key_exists( '.', $arr[ $a ] );
				$b_is_dir =
					isset( $arr[ $b ] ) &&
					is_array( $arr[ $b ] ) &&
					array_key_exists( '.', $arr[ $b ] );

				if ( $a_is_dir && ! $b_is_dir ) {
					return -1;
				} elseif ( ! $a_is_dir && $b_is_dir ) {
					return 1;
				} else {
					return $a <=> $b;
				}
			}
		);
	}

	/**
	 * Get sorted blocks data for the editor.
	 *
	 * @since 2.3.0
	 *
	 * @return array The sorted data.
	 * @throws SassException When SCSS compilation fails.
	 */
	public static function data_sorted(): array {
		$files  = self::files();
		$sorted = array();

		foreach ( $files as $d ) {
			$sorted[ $d['instance'] ]['instance'] = $d['instance'];
			$sorted[ $d['instance'] ]['path']     = $d['instancePath'];

			self::path_to_array(
				$sorted[ $d['instance'] ]['children'],
				$d['structure'],
				$d
			);

			self::recursive_sort( $sorted[ $d['instance'] ]['children'] );
		}

		ksort( $sorted );

		return $sorted;
	}

	/**
	 * Get native blocks data.
	 *
	 * @since 3.0.0
	 *
	 * @return array The blocks.
	 */
	public static function blocks(): array {
		return Block_Registry::instance()->get_blocks();
	}

	/**
	 * Prepare native block types for client-side JSON payloads.
	 *
	 * @param array $blocks Block types.
	 *
	 * @return array Client-safe block types.
	 */
	public static function prepare_blocks_for_client( array $blocks ): array {
		foreach ( $blocks as $key => $block ) {
			if ( $block instanceof \WP_Block_Type ) {
				$block = clone $block;

				foreach ( get_object_vars( $block ) as $property => $value ) {
					if ( is_array( $value ) ) {
						$block->{$property} = self::remove_expanded_populate_options(
							$value
						);
					}
				}

				$blocks[ $key ] = $block;
				continue;
			}

			if ( is_array( $block ) ) {
				$blocks[ $key ] = self::remove_expanded_populate_options( $block );
			}
		}

		return $blocks;
	}

	/**
	 * Remove expanded populate objects that are not needed after option normalization.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return mixed Sanitized value.
	 */
	public static function remove_expanded_populate_options( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			if ( 'optionsPopulateFull' === $key ) {
				unset( $value[ $key ] );
				continue;
			}

			$value[ $key ] = self::remove_expanded_populate_options( $item );
		}

		return $value;
	}

	/**
	 * Get blocks data.
	 *
	 * @since 2.3.0
	 *
	 * @return array The data.
	 */
	public static function data(): array {
		return Block_Registry::instance()->get_data();
	}

	/**
	 * Get extends data.
	 *
	 * @since 5.3.3
	 *
	 * @return array The extensions.
	 */
	public static function extensions(): array {
		return Block_Registry::instance()->get_extensions();
	}

	/**
	 * Get all block files.
	 *
	 * @since 2.3.0
	 *
	 * @return array The files.
	 * @throws SassException When SCSS compilation fails.
	 */
	public static function files(): array {
		$registry = Block_Registry::instance();
		foreach ( $registry->get_instances() as $instance ) {
			if ( $registry->has_editor_files_for_instance( $instance['path'] ) ) {
				continue;
			}

			self::init(
				array(
					'dir'    => $instance['path'],
					'editor' => true,
				)
			);
			$registry->mark_editor_files_for_instance( $instance['path'] );
		}

		return $registry->get_files();
	}

	/**
	 * Get admin assets.
	 *
	 * @since 5.5.0
	 *
	 * @return array The admin assets.
	 */
	public static function assets_admin(): array {
		return Block_Registry::instance()->get_assets_admin();
	}

	/**
	 * Get block editor assets.
	 *
	 * @since 5.5.0
	 *
	 * @return array The block editor assets.
	 */
	public static function assets_block_editor(): array {
		return Block_Registry::instance()->get_assets_block_editor();
	}

	/**
	 * Get global assets.
	 *
	 * @since 5.0.0
	 *
	 * @return array The global assets.
	 */
	public static function assets_global(): array {
		return Block_Registry::instance()->get_assets_global();
	}

	/**
	 * Get instance paths.
	 *
	 * @since 2.5.0
	 *
	 * @return array The paths.
	 */
	public static function paths(): array {
		return Block_Registry::instance()->get_paths();
	}

	/**
	 * Get overrides.
	 *
	 * @since 5.3.0
	 *
	 * @return array The overrides.
	 */
	public static function overrides(): array {
		return Block_Registry::instance()->get_overrides();
	}

	/**
	 * Get assets data.
	 *
	 * @since 5.5.7
	 *
	 * @return array The assets.
	 */
	public static function assets(): array {
		return Block_Registry::instance()->get_assets();
	}

	/**
	 * Blade templates.
	 *
	 * @since 5.6.0
	 *
	 * @return array The blade templates.
	 */
	public static function blade(): array {
		return Block_Registry::instance()->get_blade();
	}

	/**
	 * Check if Tailwind is active.
	 *
	 * @since 5.6.0
	 *
	 * @return bool Whether Tailwind is active.
	 */
	public static function is_tailwind_active(): bool {
		return Block_Registry::instance()->is_tailwind_active();
	}
}

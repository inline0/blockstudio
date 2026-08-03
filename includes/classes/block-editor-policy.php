<?php
/**
 * Block Editor Policy class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Applies global block editor policies from blockstudio.json.
 *
 * @since 7.3.0
 */
class Block_Editor_Policy {

	/**
	 * Whether configured hooks have registered.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		if (
			false === self::setting( 'blockEditor/patterns/core' ) ||
			false === self::setting( 'blockEditor/patterns/theme' )
		) {
			self::apply_early_init_policy();
		}

		if (
			self::has_category_policy( 'blockEditor/patterns/categories' ) ||
			! empty( self::setting( 'blockEditor/blocks/styles/deny' ) )
		) {
			add_action( 'init', array( self::class, 'apply_late_init_policy' ), PHP_INT_MAX );
		}

		if ( false === self::setting( 'blockEditor/blocks/directory' ) ) {
			add_action( 'enqueue_block_editor_assets', array( self::class, 'maybe_disable_block_directory' ), 0 );
		}

		if (
			array() !== self::string_list( self::setting( 'blockEditor/blocks/allow' ) ) ||
			array() !== self::string_list( self::setting( 'blockEditor/blocks/deny' ) )
		) {
			add_filter( 'allowed_block_types_all', array( self::class, 'filter_allowed_block_types' ), 10, 2 );
		}

		if ( self::has_category_policy( 'blockEditor/blocks/categories' ) ) {
			add_filter( 'block_categories_all', array( self::class, 'filter_block_categories' ), 10, 2 );
		}

		if ( false === self::setting( 'blockEditor/patterns/remote' ) ) {
			add_filter( 'should_load_remote_block_patterns', array( self::class, 'filter_remote_patterns' ) );
		}

		if ( false === self::setting( 'blockEditor/media/openverse' ) ) {
			add_filter( 'block_editor_settings_all', array( self::class, 'filter_editor_settings' ), 10, 2 );
		}

		if (
			array() !== self::string_list( self::setting( 'blockEditor/media/imageSizes/allow' ) ) ||
			array() !== self::string_list( self::setting( 'blockEditor/media/imageSizes/deny' ) )
		) {
			add_filter( 'image_size_names_choose', array( self::class, 'filter_image_sizes' ) );
		}

		if ( array() !== self::string_list( self::setting( 'blockEditor/blocks/legacyWidgets/hide' ) ) ) {
			add_filter(
				'widget_types_to_hide_from_legacy_widget_block',
				array( self::class, 'filter_hidden_legacy_widgets' )
			);
		}
	}

	/**
	 * Apply policies that must run before WordPress registers defaults.
	 *
	 * @return void
	 */
	public static function apply_early_init_policy(): void {
		if ( false === self::setting( 'blockEditor/patterns/core' ) ) {
			remove_theme_support( 'core-block-patterns' );
		}

		if ( false === self::setting( 'blockEditor/patterns/theme' ) ) {
			remove_action( 'init', '_register_theme_block_patterns' );
		}
	}

	/**
	 * Apply policies that depend on registered WordPress objects.
	 *
	 * @return void
	 */
	public static function apply_late_init_policy(): void {
		self::apply_pattern_categories();
		self::apply_block_style_denies();
	}

	/**
	 * Remove the block directory inserter tab when disabled.
	 *
	 * @return void
	 */
	public static function maybe_disable_block_directory(): void {
		if ( false !== self::setting( 'blockEditor/blocks/directory' ) ) {
			return;
		}

		remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
	}

	/**
	 * Filter the set of allowed editor blocks.
	 *
	 * @param bool|array $allowed_block_types Existing allowed block types.
	 * @param object     $block_editor_context Block editor context.
	 *
	 * @return bool|array Filtered allowed block types.
	 */
	public static function filter_allowed_block_types( $allowed_block_types, $block_editor_context ) {
		$allow = self::string_list( self::setting( 'blockEditor/blocks/allow' ) );
		$deny  = self::string_list( self::setting( 'blockEditor/blocks/deny' ) );

		if ( empty( $allow ) && empty( $deny ) ) {
			return $allowed_block_types;
		}

		if ( ! empty( $allow ) ) {
			$block_names = self::matching_block_names( $allow );

			if ( is_array( $allowed_block_types ) ) {
				$block_names = array_values( array_intersect( $block_names, $allowed_block_types ) );
			}
		} elseif ( true === $allowed_block_types ) {
			$block_names = self::registered_block_names();
		} elseif ( is_array( $allowed_block_types ) ) {
			$block_names = $allowed_block_types;
		} else {
			return $allowed_block_types;
		}

		if ( ! empty( $deny ) ) {
			$block_names = array_values(
				array_filter(
					$block_names,
					function ( string $block_name ) use ( $deny ): bool {
						return ! self::matches_any_pattern( $block_name, $deny );
					}
				)
			);
		}

		return array_values( array_unique( $block_names ) );
	}

	/**
	 * Filter inserter block categories.
	 *
	 * @param array  $categories Block categories.
	 * @param object $block_editor_context Block editor context.
	 *
	 * @return array Filtered categories.
	 */
	public static function filter_block_categories( array $categories, $block_editor_context ): array {
		return self::filter_categories(
			$categories,
			self::category_policy( 'blockEditor/blocks/categories' ),
			'slug',
			'title'
		);
	}

	/**
	 * Filter remote pattern loading.
	 *
	 * @param bool $should_load_remote Whether remote patterns should load.
	 *
	 * @return bool Filtered value.
	 */
	public static function filter_remote_patterns( bool $should_load_remote ): bool {
		if ( false === self::setting( 'blockEditor/patterns/remote' ) ) {
			return false;
		}

		return $should_load_remote;
	}

	/**
	 * Filter block editor settings.
	 *
	 * @param array  $settings Block editor settings.
	 * @param object $block_editor_context Block editor context.
	 *
	 * @return array Filtered settings.
	 */
	public static function filter_editor_settings( array $settings, $block_editor_context ): array {
		if ( false === self::setting( 'blockEditor/media/openverse' ) ) {
			$settings['enableOpenverseMediaCategory'] = false;
		}

		return $settings;
	}

	/**
	 * Filter image sizes shown in media controls.
	 *
	 * @param array $sizes Image sizes.
	 *
	 * @return array Filtered image sizes.
	 */
	public static function filter_image_sizes( array $sizes ): array {
		$allow = self::string_list( self::setting( 'blockEditor/media/imageSizes/allow' ) );
		$deny  = self::string_list( self::setting( 'blockEditor/media/imageSizes/deny' ) );

		if ( ! empty( $allow ) ) {
			$sizes = array_intersect_key( $sizes, array_flip( $allow ) );
		}

		if ( ! empty( $deny ) ) {
			$sizes = array_diff_key( $sizes, array_flip( $deny ) );
		}

		return $sizes;
	}

	/**
	 * Add configured widgets to the legacy widget block hidden list.
	 *
	 * @param array $hidden_widgets Hidden legacy widget IDs.
	 *
	 * @return array Hidden legacy widget IDs.
	 */
	public static function filter_hidden_legacy_widgets( array $hidden_widgets ): array {
		$hide = self::string_list( self::setting( 'blockEditor/blocks/legacyWidgets/hide' ) );

		if ( empty( $hide ) ) {
			return $hidden_widgets;
		}

		return array_values( array_unique( array_merge( $hidden_widgets, $hide ) ) );
	}

	/**
	 * Apply pattern category policy to the WordPress pattern category registry.
	 *
	 * @return void
	 */
	public static function apply_pattern_categories(): void {
		if ( ! class_exists( '\WP_Block_Pattern_Categories_Registry' ) ) {
			return;
		}

		$registry   = \WP_Block_Pattern_Categories_Registry::get_instance();
		$categories = $registry->get_all_registered();

		if ( empty( $categories ) ) {
			return;
		}

		$filtered = self::filter_categories(
			$categories,
			self::category_policy( 'blockEditor/patterns/categories' ),
			'name',
			'label'
		);

		if ( $filtered === $categories ) {
			return;
		}

		foreach ( $categories as $category ) {
			if ( isset( $category['name'] ) && $registry->is_registered( $category['name'] ) ) {
				unregister_block_pattern_category( $category['name'] );
			}
		}

		foreach ( $filtered as $category ) {
			if ( empty( $category['name'] ) || empty( $category['label'] ) ) {
				continue;
			}

			register_block_pattern_category(
				$category['name'],
				array(
					'label' => $category['label'],
				)
			);
		}
	}

	/**
	 * Unregister configured PHP-registered block styles.
	 *
	 * @return void
	 */
	public static function apply_block_style_denies(): void {
		$denies = self::setting( 'blockEditor/blocks/styles/deny' );

		if ( ! is_array( $denies ) || empty( $denies ) || ! class_exists( '\WP_Block_Styles_Registry' ) ) {
			return;
		}

		$registry = \WP_Block_Styles_Registry::get_instance();

		foreach ( $denies as $block_pattern => $style_names ) {
			if ( ! is_string( $block_pattern ) ) {
				continue;
			}

			$style_names = self::string_list( $style_names );
			if ( empty( $style_names ) ) {
				continue;
			}

			$block_names = array_values(
				array_unique(
					array_merge(
						self::matching_block_names( array( $block_pattern ) ),
						array_filter(
							array_keys( $registry->get_all_registered() ),
							function ( string $block_name ) use ( $block_pattern ): bool {
								return self::matches_any_pattern( $block_name, array( $block_pattern ) );
							}
						)
					)
				)
			);

			foreach ( $block_names as $block_name ) {
				$styles_to_remove = $style_names;

				if ( in_array( '*', $style_names, true ) ) {
					$registered_styles = $registry->get_registered_styles_for_block( $block_name );
					$styles_to_remove  = array_keys( $registered_styles );
				}

				foreach ( $styles_to_remove as $style_name ) {
					if ( $registry->is_registered( $block_name, $style_name ) ) {
						unregister_block_style( $block_name, $style_name );
					}
				}
			}
		}
	}

	/**
	 * Filter categories by allow, deny, rename, and order policy.
	 *
	 * @param array  $categories Categories.
	 * @param array  $policy Category policy.
	 * @param string $id_key Category identifier key.
	 * @param string $label_key Category label key.
	 *
	 * @return array Filtered categories.
	 */
	private static function filter_categories( array $categories, array $policy, string $id_key, string $label_key ): array {
		$allow  = self::string_list( $policy['allow'] ?? array() );
		$deny   = self::string_list( $policy['deny'] ?? array() );
		$rename = is_array( $policy['rename'] ?? null ) ? $policy['rename'] : array();
		$order  = self::string_list( $policy['order'] ?? array() );

		if ( ! empty( $allow ) ) {
			$categories = array_values(
				array_filter(
					$categories,
					function ( array $category ) use ( $allow, $id_key ): bool {
						return isset( $category[ $id_key ] ) && in_array( $category[ $id_key ], $allow, true );
					}
				)
			);
		}

		if ( ! empty( $deny ) ) {
			$categories = array_values(
				array_filter(
					$categories,
					function ( array $category ) use ( $deny, $id_key ): bool {
						return ! isset( $category[ $id_key ] ) || ! in_array( $category[ $id_key ], $deny, true );
					}
				)
			);
		}

		if ( ! empty( $rename ) ) {
			$categories = array_map(
				function ( array $category ) use ( $rename, $id_key, $label_key ): array {
					$category_id = $category[ $id_key ] ?? null;

					if ( is_string( $category_id ) && isset( $rename[ $category_id ] ) && is_string( $rename[ $category_id ] ) ) {
						$category[ $label_key ] = $rename[ $category_id ];
					}

					return $category;
				},
				$categories
			);
		}

		if ( empty( $order ) ) {
			return array_values( $categories );
		}

		usort(
			$categories,
			function ( array $left, array $right ) use ( $order, $id_key ): int {
				$left_id  = $left[ $id_key ] ?? '';
				$right_id = $right[ $id_key ] ?? '';

				$left_position  = array_search( $left_id, $order, true );
				$right_position = array_search( $right_id, $order, true );

				$left_position  = false === $left_position ? PHP_INT_MAX : $left_position;
				$right_position = false === $right_position ? PHP_INT_MAX : $right_position;

				if ( $left_position === $right_position ) {
					return 0;
				}

				return $left_position <=> $right_position;
			}
		);

		return array_values( $categories );
	}

	/**
	 * Get a category policy.
	 *
	 * @param string $path Setting path.
	 *
	 * @return array Category policy.
	 */
	private static function category_policy( string $path ): array {
		return array(
			'allow'  => self::setting( $path . '/allow' ),
			'deny'   => self::setting( $path . '/deny' ),
			'rename' => self::setting( $path . '/rename' ),
			'order'  => self::setting( $path . '/order' ),
		);
	}

	/**
	 * Whether a category policy contains any configured operation.
	 *
	 * @param string $path Setting path.
	 *
	 * @return bool Whether configured.
	 */
	private static function has_category_policy( string $path ): bool {
		foreach ( self::category_policy( $path ) as $value ) {
			if ( is_array( $value ) && array() !== $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read a setting after ensuring settings are initialized.
	 *
	 * @param string $path Setting path.
	 *
	 * @return mixed Setting value.
	 */
	private static function setting( string $path ) {
		Settings::get_instance();

		return Settings::get( $path );
	}

	/**
	 * Normalize a string list setting.
	 *
	 * @param mixed $value Value to normalize.
	 *
	 * @return array<string> String list.
	 */
	private static function string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$value,
				function ( $item ): bool {
					return is_string( $item ) && '' !== $item;
				}
			)
		);
	}

	/**
	 * Get registered block names matching any configured pattern.
	 *
	 * @param array $patterns Block name patterns.
	 *
	 * @return array<string> Block names.
	 */
	private static function matching_block_names( array $patterns ): array {
		return array_values(
			array_filter(
				self::registered_block_names(),
				function ( string $block_name ) use ( $patterns ): bool {
					return self::matches_any_pattern( $block_name, $patterns );
				}
			)
		);
	}

	/**
	 * Get registered block names.
	 *
	 * @return array<string> Block names.
	 */
	private static function registered_block_names(): array {
		if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
			return array();
		}

		return array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
	}

	/**
	 * Determine whether a value matches any literal or wildcard pattern.
	 *
	 * @param string $value Value to match.
	 * @param array  $patterns Patterns to match against.
	 *
	 * @return bool Whether any pattern matches.
	 */
	private static function matches_any_pattern( string $value, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( ! is_string( $pattern ) ) {
				continue;
			}

			if ( $pattern === $value ) {
				return true;
			}

			if ( false !== strpos( $pattern, '*' ) && self::wildcard_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match a wildcard pattern without relying on platform-specific PHP support.
	 *
	 * @param string $pattern Wildcard pattern.
	 * @param string $value Value to match.
	 *
	 * @return bool Whether the value matches.
	 */
	private static function wildcard_match( string $pattern, string $value ): bool {
		if ( function_exists( 'fnmatch' ) ) {
			return fnmatch( $pattern, $value );
		}

		$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/';

		return 1 === preg_match( $regex, $value );
	}
}

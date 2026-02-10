<?php
/**
 * Settings class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles plugin settings with a layered configuration system.
 *
 * This class implements a cascading settings architecture where values
 * can come from multiple sources, each overriding the previous:
 *
 * Configuration Priority (lowest to highest):
 * 1. $defaults - Hardcoded default values in this class
 * 2. $settings_options - Values from wp_options table ('blockstudio_settings')
 * 3. $settings_json - Values from theme's blockstudio.json file
 * 4. $settings_filters - Values from WordPress filters (blockstudio/settings/*)
 *
 * Settings Structure:
 * - assets/enqueue: Enable/disable asset loading on frontend
 * - assets/minify/css: Enable CSS minification
 * - assets/minify/js: Enable JavaScript minification
 * - assets/process/scss: Enable inline SCSS compilation
 * - assets/process/scssFiles: Enable .scss file compilation
 * - editor/formatOnSave: Auto-format code in editor
 * - editor/library: Enable block library panel
 * - tailwind/enabled: Enable Tailwind CSS integration
 * - dev/grab/enabled: Enable frontend element grabber
 * - dev/canvas/enabled: Enable the canvas
 * - users/ids: Array of user IDs allowed to use editor
 * - users/roles: Array of user roles allowed to use editor
 *
 * Access Pattern:
 * Use Settings::get('path/to/setting') with forward-slash notation:
 * - Settings::get('assets/enqueue') → true/false
 * - Settings::get('users/ids') → [1, 2, 3]
 *
 * JSON Configuration:
 * Place blockstudio.json in theme root to configure via file:
 * ```json
 * {
 *   "assets": { "enqueue": true, "minify": { "css": true } },
 *   "tailwind": { "enabled": true }
 * }
 * ```
 *
 * Filter Configuration:
 * Use filters for dynamic/conditional settings:
 * ```php
 * add_filter('blockstudio/settings/assets/enqueue', fn() => true);
 * add_filter('blockstudio/settings/tailwind/enabled', fn() => WP_DEBUG);
 * ```
 *
 * Initialization:
 * - Settings singleton is created on 'blockstudio/init/before' or 'init'
 * - Loads options first, then JSON, then applies filters
 * - Includes migration logic for settings from v5.x
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	protected static array $defaults = array(
		'users'       => array(
			'ids'   => array(),
			'roles' => array(),
		),
		'assets'      => array(
			'enqueue' => true,
			'reset'   => false,
			'minify'  => array(
				'css' => false,
				'js'  => false,
			),
			'process' => array(
				'scss'      => false,
				'scssFiles' => true,
			),
		),
		'editor'      => array(
			'formatOnSave' => false,
			'library'      => false,
			'assets'       => array(),
			'markup'       => false,
		),
		'tailwind'    => array(
			'enabled' => false,
			'config'  => '',
		),
		'blockEditor' => array(
			'disableLoading' => false,
			'cssClasses'     => array(),
			'cssVariables'   => array(),
		),
		'library'     => false,
		'ai'          => array(
			'enableContextGeneration' => false,
		),
		'dev'         => array(
			'grab'   => array(
				'enabled' => false,
			),
			'canvas' => array(
				'enabled'  => false,
				'adminBar' => true,
			),
		),
	);

	/**
	 * Singleton instance.
	 *
	 * @var Settings|null
	 */
	private static ?Settings $instance = null;

	/**
	 * Current settings.
	 *
	 * @var array
	 */
	protected static array $settings = array();

	/**
	 * Settings from options table.
	 *
	 * @var array
	 */
	protected static array $settings_options = array();

	/**
	 * Settings from JSON file.
	 *
	 * @var array|null
	 */
	protected static $settings_json = null;

	/**
	 * Settings from filters.
	 *
	 * @var array
	 */
	protected static array $settings_filters = array();

	/**
	 * Filter values.
	 *
	 * @var array
	 */
	protected static array $settings_filters_values = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Settings|null The singleton instance.
	 */
	public static function get_instance(): ?Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		static::$settings         = static::$defaults;
		static::$settings_filters = static::$defaults;

		static::$settings_filters['assets']['enqueue']              = false;
		static::$settings_filters['assets']['process']['scssFiles'] = false;

		if ( ! self::json() ) {
			static::$settings_options = static::$defaults;
			$this->load_settings_from_options();
		}

		$this->migrate_settings_from_old_version( static::$settings );
		$this->migrate_settings_from_old_version( static::$settings_filters );

		if ( self::json() ) {
			static::$settings_json = static::$defaults;
			$this->load_settings_from_json();
		}

		$this->load_settings_from_filters();
	}

	/**
	 * Migrate from 5.1.1.
	 *
	 * @param array $settings The settings array.
	 *
	 * @todo Remove legacy v5 migration.
	 */
	protected function migrate_settings_from_old_version( &$settings ): void {
		if ( has_filter( 'blockstudio/library' ) ) {
			$library             = apply_filters( 'blockstudio/library', false );
			$settings['library'] = $library;
		}

		if ( has_filter( 'blockstudio/assets' ) ) {
			$assets                        = apply_filters( 'blockstudio/assets', true );
			$settings['assets']['enqueue'] = $assets;
		}

		if ( has_filter( 'blockstudio/editor/library' ) ) {
			$editor_library                = apply_filters( 'blockstudio/editor/library', false );
			$settings['editor']['library'] = $editor_library;
		}

		if ( has_filter( 'blockstudio/editor/assets' ) ) {
			$editor_assets                = apply_filters( 'blockstudio/editor/assets', false );
			$settings['editor']['assets'] = $editor_assets;
		}

		if ( has_filter( 'blockstudio/editor/markup' ) ) {
			$editor_markup                = apply_filters( 'blockstudio/editor/markup', false );
			$settings['editor']['markup'] = $editor_markup;
		}

		if ( has_filter( 'blockstudio/editor/users' ) ) {
			$editor_user_ids          = apply_filters( 'blockstudio/editor/users', false );
			$settings['users']['ids'] = $editor_user_ids;
		}

		if ( has_filter( 'blockstudio/editor/users/roles' ) ) {
			$editor_user_roles          = apply_filters( 'blockstudio/editor/users/roles', false );
			$settings['users']['roles'] = $editor_user_roles;
		}

		if ( has_filter( 'blockstudio/editor/options' ) ) {
			$editor_options    = apply_filters( 'blockstudio/editor/options', false );
			$format_on_save    = $editor_options['formatOnSave'] ?? false;
			$processor_scss    = $editor_options['processorScss'] ?? false;
			$processor_esbuild = $editor_options['processorEsbuild'] ?? false;

			if ( $format_on_save ) {
				$settings['editor']['formatOnSave'] = $format_on_save;
			}
			if ( $processor_scss ) {
				$settings['assets']['minify']['css']   = $processor_scss;
				$settings['assets']['process']['scss'] = $processor_scss;
			}
			if ( $processor_esbuild ) {
				$settings['assets']['minify']['js'] = $processor_esbuild;
			}
		}
	}

	/**
	 * Convert object to array.
	 *
	 * @param mixed $data The data to convert.
	 *
	 * @return array|mixed The converted data.
	 */
	protected function object_to_array( $data ) {
		if ( is_object( $data ) ) {
			$data = get_object_vars( $data );
		}
		if ( is_array( $data ) ) {
			return array_map( array( $this, 'object_to_array' ), $data );
		}

		return $data;
	}

	/**
	 * Load settings from the options table.
	 *
	 * @return void
	 */
	protected function load_settings_from_options(): void {
		$options = $this->object_to_array( get_option( 'blockstudio_settings', array() ) );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		static::$settings         = $this->array_deep_merge( static::$settings, $options );
		static::$settings_options = $this->array_deep_merge( static::$settings, $options );
	}

	/**
	 * Get JSON path.
	 *
	 * @return string The JSON file path.
	 */
	public static function json_path(): string {
		return apply_filters(
			'blockstudio/settings/path',
			get_stylesheet_directory() . '/blockstudio.json'
		);
	}

	/**
	 * Get JSON file if it exists.
	 *
	 * @return string|false The JSON file path or false.
	 */
	public static function json() {
		if ( file_exists( self::json_path() ) ) {
			return self::json_path();
		}

		return false;
	}

	/**
	 * Load settings from the blockstudio.json file.
	 *
	 * @return void
	 */
	protected function load_settings_from_json(): void {
		$this->merge_json_settings_from_path( self::$settings );
		$this->merge_json_settings_from_path( self::$settings_json );
	}

	/**
	 * Merge JSON settings from path.
	 *
	 * @param array $settings The settings array to merge into.
	 *
	 * @return void
	 */
	private function merge_json_settings_from_path( &$settings ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
		$json_data     = file_get_contents( self::json() );
		$json_settings = json_decode( $json_data, true );

		if ( $json_settings && is_array( $json_settings ) ) {
			$settings = $this->array_deep_merge( $settings, $json_settings );
		}
	}

	/**
	 * Recursively merge arrays.
	 *
	 * @param array $base  The base array.
	 * @param array $merge The array to merge with base.
	 *
	 * @return array The merged array.
	 */
	protected function array_deep_merge( array $base, array $merge ): array {
		foreach ( $merge as $key => $value ) {
			if (
				isset( $base[ $key ] ) &&
				is_array( $base[ $key ] ) &&
				is_array( $value )
			) {
				$base[ $key ] = $this->array_deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * Load settings by applying filters.
	 *
	 * @return void
	 */
	protected function load_settings_from_filters(): void {
		$this->apply_settings_filters( static::$settings );
		$this->apply_settings_filters( static::$settings_filters );
	}

	/**
	 * Apply settings filters.
	 *
	 * @param array $settings The settings array.
	 * @param array $path     The current path.
	 *
	 * @return void
	 */
	protected function apply_settings_filters( array &$settings, array $path = array() ): void {
		foreach ( $settings as $key => &$value ) {
			$current_path = array_merge( $path, array( $key ) );

			if ( is_array( $value ) && 0 !== count( $value ) ) {
				$this->apply_settings_filters( $value, $current_path );
			} else {
				$filter_name = 'blockstudio/settings/' . implode( '/', $current_path );
				$value       = apply_filters( $filter_name, $value );

				if ( has_filter( $filter_name ) ) {
					static::$settings_filters_values[ $filter_name ] = $value;
				}
			}
		}
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default The default value.
	 *
	 * @return mixed The setting value.
	 */
	public static function get( string $key, $default = null ) {
		$value = static::fetch_value_from_key( $key, static::$settings );

		if ( null === $value ) {
			$value = static::fetch_value_from_key( $key, static::$defaults );
		}

		if ( null === $value ) {
			$value = $default;
		}

		// Convert key to snake_case for new filter name.
		$snake_key = self::to_snake_case( $key );
		$value     = apply_filters( 'blockstudio/settings/' . $snake_key, $value );

		// Backwards compatibility: also apply old camelCase filter if different.
		if ( $snake_key !== $key && has_filter( 'blockstudio/settings/' . $key ) ) {
			$value = apply_filters( 'blockstudio/settings/' . $key, $value );
		}

		return $value;
	}

	/**
	 * Convert a path string to snake_case.
	 *
	 * Converts path segments from camelCase to snake_case while preserving slashes.
	 * Example: 'blockEditor/cssClasses' -> 'block_editor/css_classes'
	 *
	 * @param string $path The path string with forward slashes.
	 *
	 * @return string The snake_case path.
	 */
	private static function to_snake_case( string $path ): string {
		$segments = explode( '/', $path );
		$segments = array_map(
			function ( $segment ) {
				// Insert underscore before uppercase letters and convert to lowercase.
				return strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', $segment ) );
			},
			$segments
		);
		return implode( '/', $segments );
	}

	/**
	 * Fetch value from key.
	 *
	 * @param string $key   The key path.
	 * @param array  $array The array to search.
	 *
	 * @return mixed|null The value or null.
	 */
	private static function fetch_value_from_key( string $key, array $array ) {
		$keys = explode( '/', $key );
		$temp = $array;

		foreach ( $keys as $segment ) {
			if ( ! isset( $temp[ $segment ] ) ) {
				return null;
			}
			$temp = $temp[ $segment ];
		}

		return $temp;
	}

	/**
	 * Get all setting values.
	 *
	 * @return array All settings.
	 */
	public static function get_all(): array {
		return static::$settings;
	}

	/**
	 * Get options setting values.
	 *
	 * @return array Options settings.
	 */
	public static function get_options(): array {
		return static::$settings_options;
	}

	/**
	 * Get JSON setting values.
	 *
	 * @return array|null JSON settings.
	 */
	public static function get_json(): ?array {
		return static::$settings_json;
	}

	/**
	 * Get filters setting values.
	 *
	 * @return array Filters settings.
	 */
	public static function get_filters(): array {
		return static::$settings_filters;
	}

	/**
	 * Get filter values.
	 *
	 * @return array Filter values.
	 */
	public static function get_filters_values(): array {
		return static::$settings_filters_values;
	}

	/**
	 * Get schema.
	 *
	 * @return array The schema.
	 */
	public static function get_schema(): array {
		return json_decode(
			file_get_contents( BLOCKSTUDIO_DIR . '/includes/schemas/blockstudio.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			true
		);
	}
}

foreach ( array( 'blockstudio/init/before', 'init' ) as $blockstudio_hook ) {
	add_action(
		$blockstudio_hook,
		function () {
			Settings::get_instance();
		}
	);
}

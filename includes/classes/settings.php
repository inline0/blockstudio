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
 * - tailwind/enabled: Enable Tailwind CSS integration
 * - ui/enabled: Enable bundled UI components
 * - githooks/commit: Enable the Blockstudio-owned PHPStan commit hook
 * - phpstan/*: Configure the canonical Blockstudio PHPStan command
 * - themeDefaults/*: Configure generic theme setup and development page sync
 * - performance/*: Configure generic runtime profiles, media, and measurements
 * - blockEditor/enhance: Add Blockstudio editor hover and selection affordances
 * - blockEditor/blocks: Configure global block inserter policy
 * - blockEditor/patterns: Configure global pattern inserter policy
 * - blockEditor/media: Configure global block editor media policy
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
		'users'         => array(
			'ids'   => array(),
			'roles' => array(),
		),
		'assets'        => array(
			'enqueue' => true,
			'reset'   => array(
				'enabled'   => false,
				'fullWidth' => array(),
			),
			'minify'  => array(
				'css' => false,
				'js'  => false,
			),
			'process' => array(
				'scss'      => false,
				'scssFiles' => true,
			),
		),
		'cache'         => array(
			'enabled' => true,
			'path'    => 'blockstudio/cache',
		),
		'githooks'      => array(
			'commit' => false,
		),
		'phpstan'       => array(
			'preset'        => 'base',
			'configuration' => '',
			'roots'         => array( '.' ),
			'excludePaths'  => array(),
			'maxFiles'      => 10000,
		),
		'themeDefaults' => array(
			'titleTag'                 => false,
			'suppressDirectoryUpdates' => false,
			'syncPagesInDevelopment'   => false,
		),
		'performance'   => array(
			'profile'         => 'compat',
			'wordpress'       => array(
				'headNoise'      => false,
				'embeds'         => false,
				'xmlrpc'         => false,
				'editor'         => false,
				'frontendAssets' => false,
				'media'          => false,
				'heartbeat'      => false,
			),
			'preload'         => array(
				'links' => 'off',
			),
			'media'           => array(
				'lazy'       => false,
				'skeleton'   => false,
				'metadata'   => false,
				'rootMargin' => '300px',
			),
			'measurement'     => array(
				'enabled'      => false,
				'queryMonitor' => false,
				'headers'      => false,
				'timings'      => false,
			),
			'staticPrerender' => array(
				'enabled'       => false,
				'ttl'           => 86400,
				'invalidate'    => 'signature',
				'earlyServe'    => false,
				'serveLoggedIn' => false,
				'dynamicPaths'  => array(),
				'warm'          => array(
					'enabled'     => false,
					'interval'    => 3600,
					'concurrency' => 2,
					'transport'   => 'http',
				),
			),
		),
		'content'       => array(
			'enabled'                => false,
			'id'                     => 'default',
			'path'                   => 'content',
			'includePageSyncManaged' => false,
			'authors'                => 'ignore',
			'postTypes'              => array(),
			'meta'                   => array(
				'include'    => array(),
				'exclude'    => array( '_edit_lock', '_edit_last', '_wp_old_slug' ),
				'references' => array(),
			),
			'taxonomies'             => array(),
			'media'                  => 'manifest',
		),
		'editor'        => array(
			'formatOnSave' => false,
			'assets'       => array(),
			'markup'       => false,
		),
		'tailwind'      => array(
			'enabled' => false,
			'config'  => '',
		),
		'ui'            => array(
			'enabled' => false,
		),
		'blockEditor'   => array(
			'disableLoading' => false,
			'enhance'        => false,
			'cssClasses'     => array(),
			'cssVariables'   => array(),
			'blocks'         => array(
				'allow'         => array(),
				'deny'          => array(),
				'directory'     => true,
				'categories'    => array(
					'allow'  => array(),
					'deny'   => array(),
					'rename' => array(),
					'order'  => array(),
				),
				'styles'        => array(
					'deny' => array(),
				),
				'legacyWidgets' => array(
					'hide' => array(),
				),
			),
			'patterns'       => array(
				'core'        => true,
				'remote'      => true,
				'theme'       => true,
				'blockstudio' => true,
				'categories'  => array(
					'allow'  => array(),
					'deny'   => array(),
					'rename' => array(),
					'order'  => array(),
				),
			),
			'media'          => array(
				'openverse'  => true,
				'imageSizes' => array(
					'allow' => array(),
					'deny'  => array(),
				),
			),
		),
		'ai'            => array(
			'enableContextGeneration' => false,
		),
		'blockTags'     => array(
			'enabled'  => false,
			'allow'    => array(),
			'deny'     => array(),
			'prefixes' => array(),
		),
		'dev'           => array(
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
	 * Unmerged settings from the active source.
	 *
	 * @var array
	 */
	protected static array $settings_raw = array();

	/**
	 * Configuration errors from the active source.
	 *
	 * @var string[]
	 */
	protected static array $errors = array();

	/**
	 * Fingerprint of the source loaded by the singleton.
	 *
	 * @var string
	 */
	private static string $loaded_source_fingerprint = '';

	/**
	 * Get singleton instance.
	 *
	 * @return Settings|null The singleton instance.
	 */
	public static function get_instance(): ?Settings {
		$source_fingerprint = self::source_fingerprint();

		if (
			null === self::$instance ||
			self::$loaded_source_fingerprint !== $source_fingerprint
		) {
			self::$instance = null;
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		static::$settings                = static::$defaults;
		static::$settings_filters        = static::$defaults;
		static::$settings_options        = array();
		static::$settings_json           = null;
		static::$settings_filters_values = array();
		static::$settings_raw            = array();
		static::$errors                  = array();

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
		self::$loaded_source_fingerprint = self::source_fingerprint();
	}

	/**
	 * Migrate from 5.1.1.
	 *
	 * @param array $settings The settings array.
	 *
	 * @todo Remove legacy v5 migration.
	 */
	protected function migrate_settings_from_old_version( &$settings ): void {
		if ( has_filter( 'blockstudio/assets' ) ) {
			$assets                        = apply_filters( 'blockstudio/assets', true );
			$settings['assets']['enqueue'] = $assets;
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

		static::$settings_raw     = $options;
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
		$path = self::json();
		if ( ! is_string( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
		$json_data     = file_get_contents( $path );
		$json_root     = json_decode( (string) $json_data );
		$json_settings = json_decode( $json_data, true );

		if ( ! is_object( $json_root ) || ! is_array( $json_settings ) ) {
			static::$errors[] = sprintf(
				'%s must contain a valid JSON object: %s',
				$path,
				json_last_error_msg()
			);

			return;
		}

		static::$settings_raw  = $json_settings;
		static::$settings      = $this->array_deep_merge( static::$settings, $json_settings );
		static::$settings_json = $this->array_deep_merge( static::$settings_json, $json_settings );
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
		self::get_instance();

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
	 * Get a boolean setting with a strict fallback.
	 *
	 * @param string $key     The setting key.
	 * @param bool   $default The fallback value.
	 *
	 * @return bool The resolved boolean value.
	 */
	public static function get_bool( string $key, bool $default = false ): bool {
		$value = self::get( $key, $default );

		return is_bool( $value ) ? $value : $default;
	}

	/**
	 * Get an integer setting with a strict fallback.
	 *
	 * @param string $key     The setting key.
	 * @param int    $default The fallback value.
	 *
	 * @return int The resolved integer value.
	 */
	public static function get_int( string $key, int $default = 0 ): int {
		$value = self::get( $key, $default );

		return is_int( $value ) ? $value : $default;
	}

	/**
	 * Get a string setting with a strict fallback.
	 *
	 * @param string $key     The setting key.
	 * @param string $default The fallback value.
	 *
	 * @return string The resolved string value.
	 */
	public static function get_string( string $key, string $default = '' ): string {
		$value = self::get( $key, $default );

		return is_string( $value ) ? $value : $default;
	}

	/**
	 * Get an array setting with a strict fallback.
	 *
	 * @param string $key     The setting key.
	 * @param array  $default The fallback value.
	 *
	 * @return array The resolved array value.
	 */
	public static function get_array( string $key, array $default = array() ): array {
		$value = self::get( $key, $default );

		return is_array( $value ) ? $value : $default;
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
		self::get_instance();

		return static::$settings;
	}

	/**
	 * Get options setting values.
	 *
	 * @return array Options settings.
	 */
	public static function get_options(): array {
		self::get_instance();

		return static::$settings_options;
	}

	/**
	 * Get JSON setting values.
	 *
	 * @return array|null JSON settings.
	 */
	public static function get_json(): ?array {
		self::get_instance();

		return static::$settings_json;
	}

	/**
	 * Get the unmerged active source settings.
	 *
	 * @return array The raw blockstudio.json or options payload.
	 */
	public static function get_raw(): array {
		self::get_instance();

		return static::$settings_raw;
	}

	/**
	 * Get filters setting values.
	 *
	 * @return array Filters settings.
	 */
	public static function get_filters(): array {
		self::get_instance();

		return static::$settings_filters;
	}

	/**
	 * Get filter values.
	 *
	 * @return array Filter values.
	 */
	public static function get_filters_values(): array {
		self::get_instance();

		return static::$settings_filters_values;
	}

	/**
	 * Get configuration errors from the active source.
	 *
	 * @return string[] Configuration errors.
	 */
	public static function errors(): array {
		self::get_instance();

		return static::$errors;
	}

	/**
	 * Get a deterministic fingerprint of resolved settings.
	 *
	 * @return string Settings fingerprint.
	 */
	public static function fingerprint(): string {
		self::get_instance();
		$encoded = wp_json_encode( static::$settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * Clear all cached settings so the next access reloads the active source.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance                  = null;
		self::$settings                  = array();
		self::$settings_options          = array();
		self::$settings_json             = null;
		self::$settings_filters          = array();
		self::$settings_filters_values   = array();
		self::$settings_raw              = array();
		self::$errors                    = array();
		self::$loaded_source_fingerprint = '';
	}

	/**
	 * Reload settings immediately.
	 *
	 * @return Settings|null The reloaded singleton.
	 */
	public static function reload(): ?Settings {
		self::reset();

		return self::get_instance();
	}

	/**
	 * Fingerprint the active JSON file or options payload.
	 *
	 * @return string Source fingerprint.
	 */
	private static function source_fingerprint(): string {
		$path = self::json_path();
		clearstatcache( true, $path );

		if ( is_file( $path ) && is_readable( $path ) ) {
			$hash = hash_file( 'sha256', $path );

			return 'json:' . $path . ':' . ( is_string( $hash ) ? $hash : 'unreadable' );
		}

		$options = function_exists( 'get_option' )
			? get_option( 'blockstudio_settings', array() )
			: array();
		$encoded = wp_json_encode( $options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return 'options:' . hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
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

<?php
/**
 * Migrate class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Database and settings migration between plugin versions.
 *
 * This class handles data migrations when users upgrade from older
 * versions of Blockstudio. It ensures settings and data structures
 * are updated to match the current version's expectations.
 *
 * Migration Flow:
 * 1. On plugin activation: check for first install vs upgrade
 * 2. On plugins_loaded: compare stored version to current
 * 3. Run any pending migrations for versions between stored and current
 * 4. Update stored version in database
 *
 * Registered Migrations:
 * - 5.2.0: Migrate from blockstudio_options to blockstudio_settings
 *   - formatOnSave → editor/formatOnSave
 *   - processorScss → assets/minify/css + assets/process/scss
 *   - processorEsbuild → assets/minify/js
 *
 * Adding New Migrations:
 * ```php
 * // In constructor $migrations array:
 * '7.1.0' => array( $this, 'migrate_to_710' ),
 *
 * // Then define the method:
 * private function migrate_to_710(): void {
 *     // Migration logic here
 * }
 * ```
 *
 * Version Storage:
 * - Option name: blockstudio_version
 * - Contains the last-run plugin version
 * - Compared against BLOCKSTUDIO_VERSION constant
 *
 * @since 5.2.0
 */
class Migrate {

	/**
	 * The current stored version.
	 *
	 * @var string|false
	 */
	private $current_version;

	/**
	 * The new plugin version.
	 *
	 * @var string
	 */
	private string $new_version;

	/**
	 * Migration callbacks keyed by version.
	 *
	 * @var array
	 */
	private array $migrations;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->current_version = get_option( 'blockstudio_version' );
		$this->new_version     = BLOCKSTUDIO_VERSION;
		$this->migrations      = array(
			'5.2.0'  => array( $this, 'migrate_to_520' ),
			'7.6.6'  => array( $this, 'migrate_to_766' ),
			'7.7.14' => array( $this, 'migrate_to_7714' ),
		);
	}

	/**
	 * Register version checks after all plugin classes have loaded.
	 *
	 * Theme-bundled installs can boot after plugins_loaded. Wait for their
	 * settings filters before migrations inspect optional runtime features.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! did_action( 'plugins_loaded' ) ) {
			add_action( 'plugins_loaded', array( self::class, 'maybe_update' ) );
		} elseif ( ! did_action( 'after_setup_theme' ) ) {
			add_action( 'after_setup_theme', array( self::class, 'maybe_update' ), PHP_INT_MAX );
		} else {
			self::maybe_update();
		}
	}

	/**
	 * Run pending migrations once for the installed version.
	 *
	 * @return void
	 */
	public static function maybe_update(): void {
		if ( BLOCKSTUDIO_VERSION !== get_option( 'blockstudio_version' ) ) {
			$migrator = new self();
			$migrator->check_and_update();
		}
	}

	/**
	 * Check if migration should run and execute if needed.
	 *
	 * @return void
	 */
	public function check_and_update(): void {
		if ( ! $this->current_version ) {
			$this->handle_first_activation();
		} else {
			$this->handle_updates();
		}
	}

	/**
	 * Handle the very first activation.
	 *
	 * @return void
	 */
	public function handle_first_activation(): void {
		$legacy_option = get_option( 'blockstudio_options' );

		if ( $legacy_option ) {
			$this->run_migrations();
		}

		$this->store_missing_request_options();
		add_option( 'blockstudio_version', $this->new_version );
	}

	/**
	 * Handle plugin updates.
	 *
	 * @return void
	 */
	private function handle_updates(): void {
		if ( version_compare( $this->current_version, $this->new_version, '<' ) ) {
			$this->run_migrations();
			update_option( 'blockstudio_version', $this->new_version );
		}
	}

	/**
	 * Run pending migrations.
	 *
	 * @return void
	 */
	private function run_migrations(): void {
		foreach ( $this->migrations as $version => $callable ) {
			if ( version_compare( $this->current_version, $version, '<' ) ) {
				call_user_func( $callable );
			}
		}
	}

	/**
	 * Migrate to version 5.2.0.
	 *
	 * @return void
	 */
	private function migrate_to_520(): void {
		$old_options = get_option( 'blockstudio_options' );

		if ( $old_options ) {
			$new_settings = array();

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Reading legacy option names.
			if ( isset( $old_options->formatOnSave ) && $old_options->formatOnSave ) {
				$new_settings['editor']['formatOnSave'] = true;
			}

			if (
				isset( $old_options->processorScss ) &&
				$old_options->processorScss
			) {
				$new_settings['assets']['minify']['css']   = true;
				$new_settings['assets']['process']['scss'] = true;
			}

			if (
				isset( $old_options->processorEsbuild ) &&
				$old_options->processorEsbuild
			) {
				$new_settings['assets']['minify']['js'] = true;
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			update_option( 'blockstudio_settings', $new_settings );
			delete_option( 'blockstudio_options' );
		}
	}

	/**
	 * Remove stale side effects from disabled prerender subfeatures once.
	 *
	 * @return void
	 */
	private function migrate_to_766(): void {
		if ( class_exists( Static_Prerender_Runtime::class ) ) {
			Static_Prerender_Runtime::migrate_inactive_features();
		}
	}

	/**
	 * Store and autoload the options read on every request.
	 *
	 * Earlier versions stored them with autoload off, and update_option()
	 * changes the autoload of an existing row only together with its value.
	 * Existing values stay available to concurrent requests. Large page
	 * manifests and templates follow WordPress's default autoload policy;
	 * their writers reevaluate that policy whenever the values change.
	 *
	 * @return void
	 */
	private function migrate_to_7714(): void {
		$small = array(
			'blockstudio_build_lock_cleanup_version',
			'blockstudio_collection_post_types_signature',
		);

		if ( Build_Cache::autoload_populate_state() ) {
			$small[] = 'blockstudio_populate_cache_version';
		}

		wp_set_options_autoload( $small, true );

		foreach ( array( 'blockstudio_pages_collection_manifests', 'blockstudio_pages_template_for' ) as $option ) {
			$value = get_option( $option, null );

			if ( null !== $value ) {
				$autoload = wp_determine_option_autoload_value( $option, $value, maybe_serialize( $value ), null );
				wp_set_options_autoload( array( $option ), in_array( $autoload, wp_autoload_values_to_autoload(), true ) );
			}
		}

		$this->store_missing_request_options();
	}

	/**
	 * Store the options read on every request where they do not exist.
	 *
	 * A missing option is not part of the autoloaded options, so each request
	 * without an object cache queried for it. Every value here is the one its
	 * reader falls back to, so it reads the same as no option.
	 *
	 * @return void
	 */
	private function store_missing_request_options(): void {
		add_option( 'blockstudio_settings', array() );
		add_option( 'blockstudio_static_prerender_feature_state', array(), '', true );
		add_option( 'blockstudio_build_lock_cleanup_version', '', '', true );
		add_option( 'blockstudio_collection_post_types_signature', '', '', true );
		add_option( 'blockstudio_populate_cache_version', '0', '', Build_Cache::autoload_populate_state() );
		add_option( 'blockstudio_pages_collection_manifests', array() );
		add_option( 'blockstudio_pages_template_for', array() );
	}
}

register_activation_hook(
	BLOCKSTUDIO_DIR . '/blockstudio.php',
	function () {
		$migrator = new Migrate();
		$migrator->handle_first_activation();
	}
);

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
			'5.2.0' => array( $this, 'migrate_to_520' ),
		);
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
}

register_activation_hook(
	BLOCKSTUDIO_DIR . '/blockstudio.php',
	function () {
		$migrator = new Migrate();
		$migrator->handle_first_activation();
	}
);

add_action(
	'plugins_loaded',
	function () {
		$stored_version = get_option( 'blockstudio_version' );
		if ( BLOCKSTUDIO_VERSION !== $stored_version ) {
			$migrator = new Migrate();
			$migrator->check_and_update();
		}
	}
);

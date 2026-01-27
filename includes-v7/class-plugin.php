<?php
/**
 * Main Plugin class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Main Plugin singleton class.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->load_classes();
		$this->init();
	}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception When attempting to unserialize.
	 * @return void
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Load all class files.
	 *
	 * @return void
	 */
	private function load_classes(): void {
		$classes_dir = BLOCKSTUDIO_DIR . '/includes-v7/classes/';

		require_once $classes_dir . 'migrate.php';
		require_once $classes_dir . 'files.php';
		require_once $classes_dir . 'settings.php';
		require_once $classes_dir . 'block.php';
		require_once $classes_dir . 'render.php';
		require_once $classes_dir . 'build.php';
		require_once $classes_dir . 'populate.php';
		require_once $classes_dir . 'field.php';
		require_once $classes_dir . 'esmodules.php';
		require_once $classes_dir . 'esmodulescss.php';
		require_once $classes_dir . 'assets.php';
		require_once $classes_dir . 'tailwind.php';
		require_once $classes_dir . 'llm.php';
		require_once $classes_dir . 'utils.php';
		require_once $classes_dir . 'library.php';
		require_once $classes_dir . 'admin.php';
		require_once $classes_dir . 'blocks.php';
		require_once $classes_dir . 'configurator.php';
		require_once $classes_dir . 'rest.php';
		require_once $classes_dir . 'extensions.php';
		require_once $classes_dir . 'builder.php';
		require_once $classes_dir . 'examples.php';
		require_once $classes_dir . 'register.php';
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	private function init(): void {
		if ( class_exists( 'Blockstudio\Assets' ) ) {
			new Assets();
		}

		add_action(
			'init',
			function () {
				if ( class_exists( 'Blockstudio\Build' ) ) {
					Build::init(
						array(
							'dir' => Build::getBuildDir(),
						)
					);
				}
			}
		);

		do_action( 'blockstudio_init', $this );
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function version(): string {
		return BLOCKSTUDIO_VERSION;
	}

	/**
	 * Get plugin directory path.
	 *
	 * @return string
	 */
	public function dir(): string {
		return BLOCKSTUDIO_DIR;
	}

	/**
	 * Get plugin file path.
	 *
	 * @return string
	 */
	public function file(): string {
		return BLOCKSTUDIO_FILE;
	}
}

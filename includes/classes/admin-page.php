<?php
/**
 * Admin page class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Blockstudio admin overview page under Tools.
 */
class Admin_Page {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'blockstudio-admin';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Check whether the admin page is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		/**
		 * Filter whether the Blockstudio admin overview page is enabled.
		 *
		 * @param bool $enabled Whether the admin page is enabled.
		 */
		return (bool) apply_filters( 'blockstudio/admin/enabled', true );
	}

	/**
	 * Register the overview page under Tools.
	 *
	 * @return void
	 */
	public function register_admin_page(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_management_page(
			'Blockstudio Overview',
			'Blockstudio',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the overview page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		echo '<script>document.title = "Blockstudio Overview";</script>';
		echo '<style>'
			. '.tools_page_' . esc_attr( self::PAGE_SLUG ) . ' #wpcontent { padding-left: 0; }'
			. '.tools_page_' . esc_attr( self::PAGE_SLUG ) . ' .wrap { margin: 0; }'
			. '.tools_page_' . esc_attr( self::PAGE_SLUG ) . ' #wpfooter { display: none; }'
			. '#wpbody-content { padding-bottom: 0; }'
			. '</style>';
		echo '<div class="wrap"><div id="blockstudio-admin"></div></div>';
	}

	/**
	 * Enqueue the overview bundle.
	 *
	 * @param string $hook The admin hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook || ! $this->is_enabled() ) {
			return;
		}

		$asset_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/admin/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'blockstudio-admin-page',
			BLOCKSTUDIO_URL . 'includes/admin/assets/admin/index.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? BLOCKSTUDIO_VERSION,
			true
		);

		wp_localize_script(
			'blockstudio-admin-page',
			'blockstudioAdminPage',
			array(
				'adminUrl'   => admin_url(),
				'blocks'     => array_values( Build::blocks() ),
				'extensions' => Build::extensions(),
				'logo'       => BLOCKSTUDIO_URL . 'includes/assets/icon.svg',
				'paths'      => Build::paths(),
				'version'    => BLOCKSTUDIO_VERSION,
			)
		);
	}
}

new Admin_Page();

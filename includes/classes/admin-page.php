<?php
/**
 * Admin page class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Type;

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
		$style_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/admin/style-index.css';

		wp_enqueue_script(
			'blockstudio-admin-page',
			BLOCKSTUDIO_URL . 'includes/admin/assets/admin/index.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? BLOCKSTUDIO_VERSION,
			true
		);

		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				'blockstudio-admin-page-style',
				BLOCKSTUDIO_URL . 'includes/admin/assets/admin/style-index.css',
				array( 'wp-components' ),
				$asset['version'] ?? BLOCKSTUDIO_VERSION
			);
			wp_style_add_data( 'blockstudio-admin-page-style', 'rtl', 'replace' );
		}

		wp_localize_script(
			'blockstudio-admin-page',
			'blockstudioAdminPage',
			$this->get_admin_data()
		);
	}

	/**
	 * Get localized admin page data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_admin_data(): array {
		return array(
			'adminUrl' => admin_url(),
			'logo'     => BLOCKSTUDIO_URL . 'includes/assets/icon.svg',
			'overview' => array(
				'blocks'     => $this->get_blocks(),
				'extensions' => $this->get_extensions(),
				'fields'     => $this->get_fields(),
				'pages'      => $this->get_pages(),
				'schemas'    => $this->get_schemas(),
			),
			'version'  => BLOCKSTUDIO_VERSION,
		);
	}

	/**
	 * Get block rows for the overview.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	private function get_blocks(): array {
		$rows = array();

		foreach ( Build::blocks() as $block ) {
			if ( ! $block instanceof WP_Block_Type ) {
				continue;
			}

			$rows[] = array(
				'apiVersion'      => (int) ( $block->api_version ?? 0 ),
				'attributesCount' => count( (array) ( $block->attributes ?? array() ) ),
				'category'        => (string) ( $block->category ?? '' ),
				'id'              => (string) $block->name,
				'name'            => (string) $block->name,
				'render'          => $this->get_render_mode( $block ),
				'title'           => (string) ( $block->title ?? $block->name ),
			);
		}

		$this->sort_rows( $rows, 'title', 'name' );

		return $rows;
	}

	/**
	 * Get extension rows for the overview.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	private function get_extensions(): array {
		$rows = array();

		foreach ( array_values( Build::extensions() ) as $index => $extension ) {
			if ( ! $extension instanceof WP_Block_Type ) {
				continue;
			}

			$targets = $this->get_block_targets_label( $extension->name ?? '' );

			$rows[] = array(
				'apiVersion'      => (int) ( $extension->api_version ?? 0 ),
				'attributesCount' => count( (array) ( $extension->attributes ?? array() ) ),
				'category'        => (string) ( $extension->category ?? '' ),
				'id'              => 'extension-' . (string) $index,
				'priority'        => isset( $extension->blockstudio['extend']['priority'] )
					? (string) $extension->blockstudio['extend']['priority']
					: '',
				'render'          => $this->get_render_mode( $extension ),
				'targets'         => $targets,
				'title'           => (string) ( $extension->title ?? $targets ),
			);
		}

		$this->sort_rows( $rows, 'targets', 'title' );

		return $rows;
	}

	/**
	 * Get field rows for the overview.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	private function get_fields(): array {
		$rows = array();

		foreach ( Field_Registry::instance()->all() as $key => $field ) {
			$name = (string) ( $field['name'] ?? $key );

			$rows[] = array(
				'attributesCount' => count( $field['attributes'] ?? array() ),
				'id'              => (string) $key,
				'name'            => $name,
				'title'           => (string) ( $field['title'] ?? $name ),
			);
		}

		$this->sort_rows( $rows, 'title', 'name' );

		return $rows;
	}

	/**
	 * Get page rows for the overview.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_pages(): array {
		$rows = array();

		foreach ( Pages::pages() as $key => $page ) {
			$name = (string) ( $page['name'] ?? $key );

			$rows[] = array(
				'id'         => $name,
				'name'       => $name,
				'postId'     => isset( $page['post_id'] ) ? (string) $page['post_id'] : '',
				'postStatus' => (string) ( $page['postStatus'] ?? '' ),
				'postType'   => (string) ( $page['postType'] ?? 'page' ),
				'slug'       => (string) ( $page['slug'] ?? $name ),
				'sync'       => ! empty( $page['sync'] ) ? 'Yes' : 'No',
				'template'   => $this->get_page_template_engine( $page ),
				'templateFor' => (string) ( $page['templateFor'] ?? '' ),
				'title'      => (string) ( $page['title'] ?? $name ),
			);
		}

		$this->sort_rows( $rows, 'title', 'name' );

		return $rows;
	}

	/**
	 * Get schema rows for the overview.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	private function get_schemas(): array {
		$rows = array();

		foreach ( Database::get_all() as $key => $schema ) {
			$parts       = explode( ':', $key, 2 );
			$block_name  = $parts[0] ?? '';
			$schema_name = $parts[1] ?? 'default';

			$rows[] = array(
				'block'        => (string) $block_name,
				'capabilities' => $this->get_schema_capabilities_label( $schema['capability'] ?? array() ),
				'fieldsCount'  => count( $schema['fields'] ?? array() ),
				'id'           => (string) $key,
				'name'         => (string) $schema_name,
				'storage'      => (string) ( $schema['storage'] ?? '' ),
				'userScoped'   => ! empty( $schema['userScoped'] ) ? 'Yes' : 'No',
			);
		}

		$this->sort_rows( $rows, 'block', 'name' );

		return $rows;
	}

	/**
	 * Get the render mode for a block type.
	 *
	 * @param WP_Block_Type $block The block type.
	 * @return string
	 */
	private function get_render_mode( WP_Block_Type $block ): string {
		if ( ! empty( $block->blockstudio['component'] ) ) {
			return 'Component';
		}

		if ( ! empty( $block->render_callback ) ) {
			return 'Server';
		}

		return 'Static';
	}

	/**
	 * Get a readable block targets label.
	 *
	 * @param mixed $targets The block targets.
	 * @return string
	 */
	private function get_block_targets_label( $targets ): string {
		if ( is_array( $targets ) ) {
			return implode(
				', ',
				array_map(
					static fn( $target ) => (string) $target,
					$targets
				)
			);
		}

		return (string) $targets;
	}

	/**
	 * Get a concise template engine label for a page.
	 *
	 * @param array $page The page data.
	 * @return string
	 */
	private function get_page_template_engine( array $page ): string {
		if ( ! empty( $page['is_blade'] ) ) {
			return 'Blade';
		}

		if ( ! empty( $page['is_twig'] ) ) {
			return 'Twig';
		}

		$template_path = $page['template_path'] ?? '';
		$extension     = strtolower( pathinfo( $template_path, PATHINFO_EXTENSION ) );

		if ( 'php' === $extension ) {
			return 'PHP';
		}

		return strtoupper( $extension );
	}

	/**
	 * Get a readable capabilities label for a schema.
	 *
	 * @param array $capabilities The schema capabilities.
	 * @return string
	 */
	private function get_schema_capabilities_label( array $capabilities ): string {
		$labels = array();

		foreach ( $capabilities as $capability => $enabled ) {
			if ( ! $enabled ) {
				continue;
			}

			$labels[] = ucfirst( (string) $capability );
		}

		return implode( ', ', $labels );
	}

	/**
	 * Sort rows by one or two keys.
	 *
	 * @param array  $rows         The rows.
	 * @param string $primary_key  The primary sort key.
	 * @param string $secondary_key The secondary sort key.
	 * @return void
	 */
	private function sort_rows( array &$rows, string $primary_key, string $secondary_key = 'id' ): void {
		usort(
			$rows,
			static function ( array $left, array $right ) use ( $primary_key, $secondary_key ): int {
				$primary = strnatcasecmp(
					(string) ( $left[ $primary_key ] ?? '' ),
					(string) ( $right[ $primary_key ] ?? '' )
				);

				if ( 0 !== $primary ) {
					return $primary;
				}

				return strnatcasecmp(
					(string) ( $left[ $secondary_key ] ?? '' ),
					(string) ( $right[ $secondary_key ] ?? '' )
				);
			}
		);
	}
}

new Admin_Page();

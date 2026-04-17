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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
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

		$asset      = require $asset_file;
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
			'adminUrl'        => admin_url(),
			'databases'       => $this->get_databases(),
			'logo'            => BLOCKSTUDIO_URL . 'includes/assets/icon.svg',
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'overview'        => array(
				'blocks'     => $this->get_blocks(),
				'extensions' => $this->get_extensions(),
				'fields'     => $this->get_fields(),
				'pages'      => $this->get_pages(),
				'schemas'    => $this->get_schemas(),
			),
			'registryEnabled' => null !== $this->load_blocks_json(),
			'restUrl'         => esc_url_raw( rest_url( 'blockstudio/v1' ) ),
			'version'         => BLOCKSTUDIO_VERSION,
		);
	}

	/**
	 * Get database metadata for the admin browser.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_databases(): array {
		$rows = array();

		foreach ( Database::get_all() as $key => $schema ) {
			$parts       = explode( ':', $key, 2 );
			$block_name  = $parts[0] ?? '';
			$schema_name = $parts[1] ?? 'default';
			$label       = 'default' === $schema_name
				? $block_name
				: $block_name . ' / ' . $schema_name;

			$rows[] = array(
				'block'      => (string) $block_name,
				'fields'     => array_values(
					array_map(
						static fn( $field_name ) => (string) $field_name,
						array_keys( $schema['fields'] ?? array() )
					)
				),
				'id'         => (string) $key,
				'label'      => (string) $label,
				'name'       => (string) $schema_name,
				'storage'    => (string) ( $schema['storage'] ?? '' ),
				'userScoped' => ! empty( $schema['userScoped'] ),
			);
		}

		$this->sort_rows( $rows, 'label', 'id' );

		return $rows;
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
					'id'          => $name,
					'name'        => $name,
					'postId'      => isset( $page['post_id'] ) ? (string) $page['post_id'] : '',
					'postStatus'  => (string) ( $page['postStatus'] ?? '' ),
					'postType'    => (string) ( $page['postType'] ?? 'page' ),
					'slug'        => (string) ( $page['slug'] ?? $name ),
					'sync'        => ! empty( $page['sync'] ) ? 'Yes' : 'No',
					'template'    => $this->get_page_template_engine( $page ),
					'templateFor' => (string) ( $page['templateFor'] ?? '' ),
					'title'       => (string) ( $page['title'] ?? $name ),
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
	 * Get registry block rows from configured remote registries.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_registry_data(): array {
		$config = $this->load_blocks_json();

		if ( ! $config ) {
			return array();
		}

		$directory = $config['directory'] ?? 'blockstudio';
		$local_dir = get_stylesheet_directory() . '/' . $directory;
		$rows      = array();

		foreach ( $config['registries'] ?? array() as $name => $ref ) {
			$resolved = $this->resolve_registry_ref( $ref );

			if ( ! $resolved ) {
				continue;
			}

			$response = wp_remote_get(
				$resolved['url'],
				array(
					'headers' => $resolved['headers'],
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$registry = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $registry ) || empty( $registry['blocks'] ) ) {
				continue;
			}

			foreach ( $registry['blocks'] as $block ) {
				$block_name = $block['name'] ?? '';

				if ( '' === $block_name ) {
					continue;
				}

				$rows[] = array(
					'category'    => (string) ( $block['category'] ?? '' ),
					'description' => (string) ( $block['description'] ?? '' ),
					'id'          => $name . '/' . $block_name,
					'name'        => $block_name,
					'registry'    => (string) $name,
					'status'      => is_dir( $local_dir . '/' . $block_name ) ? 'installed' : 'available',
					'title'       => (string) ( $block['title'] ?? $block_name ),
					'type'        => (string) ( $block['type'] ?? '' ),
				);
			}
		}

		$this->sort_rows( $rows, 'registry', 'name' );

		return $rows;
	}

	/**
	 * Load and parse blocks.json from the active theme.
	 *
	 * @return array|null
	 */
	private function load_blocks_json(): ?array {
		$path = get_stylesheet_directory() . '/blocks.json';

		if ( ! file_exists( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
		$config = json_decode( file_get_contents( $path ), true );

		if ( ! is_array( $config ) ) {
			return null;
		}

		return $config;
	}

	/**
	 * Resolve a registry reference (string URL or object with url + headers).
	 *
	 * @param mixed $ref The registry reference from blocks.json.
	 * @return array{url: string, headers: array<string, string>}|null
	 */
	private function resolve_registry_ref( $ref ): ?array {
		if ( is_string( $ref ) ) {
			return array(
				'url'     => $ref,
				'headers' => array(),
			);
		}

		if ( ! is_array( $ref ) || empty( $ref['url'] ) ) {
			return null;
		}

		$headers = array();

		foreach ( $ref['headers'] ?? array() as $key => $value ) {
			$headers[ $key ] = preg_replace_callback(
				'/\$\{([^}]+)\}/',
				static function ( $matches ) {
					$env_value = getenv( $matches[1] );
					return false !== $env_value ? $env_value : '';
				},
				$value
			);
		}

		return array(
			'url'     => $ref['url'],
			'headers' => $headers,
		);
	}

	/**
	 * Register REST API routes for the admin page.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'blockstudio/v1',
			'/registry/blocks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_registry_blocks' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			'blockstudio/v1',
			'/registry/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_import' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'registry' => array(
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_string( $param ) && '' !== $param;
						},
					),
					'block'    => array(
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_string( $param ) && '' !== $param && false === strpos( $param, '..' );
						},
					),
				),
			)
		);

		register_rest_route(
			'blockstudio/v1',
			'/admin/databases',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_database_records' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'key'    => array(
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_string( $param ) && '' !== $param;
						},
					),
					'limit'  => array(
						'default'           => 20,
						'validate_callback' => static function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
					'offset' => array(
						'default'           => 0,
						'validate_callback' => static function ( $param ) {
							return is_numeric( $param ) && (int) $param >= 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Handle a request for registry block data.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_registry_blocks(): \WP_REST_Response {
		return rest_ensure_response( $this->get_registry_data() );
	}

	/**
	 * Handle a block import request.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_import( \WP_REST_Request $request ) {
		$registry_name = $request->get_param( 'registry' );
		$block_name    = $request->get_param( 'block' );
		$config        = $this->load_blocks_json();

		if ( ! $config ) {
			return new \WP_Error( 'no_config', 'No blocks.json found.', array( 'status' => 400 ) );
		}

		$ref = $config['registries'][ $registry_name ] ?? null;

		if ( ! $ref ) {
			return new \WP_Error( 'unknown_registry', 'Registry not found.', array( 'status' => 400 ) );
		}

		$resolved = $this->resolve_registry_ref( $ref );

		if ( ! $resolved ) {
			return new \WP_Error( 'invalid_registry', 'Invalid registry configuration.', array( 'status' => 400 ) );
		}

		$response = wp_remote_get(
			$resolved['url'],
			array(
				'headers' => $resolved['headers'],
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'fetch_failed', 'Could not fetch registry.', array( 'status' => 502 ) );
		}

		$registry = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $registry ) || empty( $registry['blocks'] ) ) {
			return new \WP_Error( 'invalid_registry_data', 'Invalid registry data.', array( 'status' => 502 ) );
		}

		$block = null;

		foreach ( $registry['blocks'] as $entry ) {
			if ( ( $entry['name'] ?? '' ) === $block_name ) {
				$block = $entry;
				break;
			}
		}

		if ( ! $block ) {
			return new \WP_Error( 'block_not_found', 'Block not found in registry.', array( 'status' => 404 ) );
		}

		$base_url  = $registry['baseUrl'] ?? '';
		$directory = $config['directory'] ?? 'blockstudio';
		$target    = get_stylesheet_directory() . '/' . $directory . '/' . $block_name;

		$written = 0;

		foreach ( $block['files'] ?? array() as $file ) {
			$file_url      = $base_url . '/' . $block_name . '/' . $file;
			$file_response = wp_remote_get(
				$file_url,
				array(
					'headers' => $resolved['headers'],
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $file_response ) || 200 !== wp_remote_retrieve_response_code( $file_response ) ) {
				continue;
			}

			$file_path = $target . '/' . $file;
			$file_dir  = dirname( $file_path );

			if ( ! is_dir( $file_dir ) ) {
				wp_mkdir_p( $file_dir );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct write for imported block files.
			file_put_contents( $file_path, wp_remote_retrieve_body( $file_response ) );
			++$written;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'block'   => $block_name,
				'files'   => $written,
			)
		);
	}

	/**
	 * Handle an admin request for database rows.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_database_records( \WP_REST_Request $request ) {
		$key    = (string) $request->get_param( 'key' );
		$limit  = min( max( (int) $request->get_param( 'limit' ), 1 ), 100 );
		$offset = max( (int) $request->get_param( 'offset' ), 0 );
		$schema = Database::get_all()[ $key ] ?? null;

		if ( ! is_array( $schema ) ) {
			return new \WP_Error( 'unknown_database', 'Database not found.', array( 'status' => 404 ) );
		}

		$result = Database::execute(
			'paginate',
			$key,
			array(
				'filters' => array(),
				'limit'   => $limit,
				'offset'  => $offset,
			)
		);
		$items  = is_array( $result['items'] ?? null ) ? $result['items'] : array();
		$total  = max( 0, (int) ( $result['total'] ?? 0 ) );

		return rest_ensure_response(
			array(
				'items'  => array_values( $items ),
				'limit'  => $limit,
				'offset' => $offset,
				'total'  => $total,
			)
		);
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

<?php
/**
 * Admin class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioPlugin;
use stdClass;

/**
 * Admin class used for editor-related functions.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action(
			'admin_head',
			function () {
				echo '<style>.toplevel_page_blockstudio .wp-menu-image.svg { height: 34px !important; }</style>';
			}
		);

		add_action( 'admin_head', array( $this, 'output_loading_screen_styles' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'wp_footer', array( __CLASS__, 'capture_frontend_assets' ) );
	}

	/**
	 * Output loading screen styles and scripts.
	 *
	 * @return void
	 */
	public function output_loading_screen_styles(): void {
		$screen = get_current_screen();

		if ( 'toplevel_page_blockstudio' !== $screen->id ) {
			return;
		}
		?>
		<style>
			@keyframes pulse {
				0% {
					opacity: 0.1;
				}
				50% {
					opacity: 0.2;
				}
				100% {
					opacity: 0.1;
				}
			}

			.blockstudio-loading-screen {
				position: absolute;
				top: 0;
				bottom: 0;
				left: 0;
				z-index: 99999;
				width: 100%;
				height: calc(100% - var(--wp-admin--admin-bar--height));
				display: flex;
				align-items: center;
				justify-content: center;
			}

			.blockstudio-loading-screen svg {
				width: 96px;
				height: 96px;
				animation: pulse 2s infinite;
			}

			.toplevel_page_blockstudio #wpfooter,
			.toplevel_page_blockstudio .notice {
				display: none !important;
			}

			.toplevel_page_blockstudio #wpcontent,
			.toplevel_page_blockstudio #wpbody-content {
				padding: 0 !important;
			}

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking if block param exists.
			if ( isset( $_GET['block'] ) ) :
				?>
			#adminmenumain, #wpadminbar {
				display: none !important;
			}

			#wpcontent, #wpfooter {
				margin-left: 0 !important;
			}

			html, #wpbody {
				padding-top: 0 !important;
			}

			<?php endif; ?>
		</style>
		<script>
			const loadingScreen = document.createElement('div');
			loadingScreen.classList.add('blockstudio-loading-screen');
			loadingScreen.innerHTML = `<svg width="320px" height="320px" viewBox="0 0 320 320" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"> <g id="assets" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="blockstudio/logoIntersect" fill="#A7AAAD"> <path d="M160,0 C288.012593,0 320,31.9874065 320,160 C320,288.012593 288.012593,320 160,320 C31.9874065,320 0,288.012593 0,160 C0,31.9874065 31.9874065,0 160,0 Z M54.8183853,132.448671 C32.3763603,216.203448 47.6970087,242.73959 131.451786,265.181615 C215.206564,287.62364 241.742705,272.302991 264.18473,188.548214 C286.626755,104.793436 271.306107,78.257295 187.551329,55.81527 C103.796552,33.3732451 77.2604103,48.6938935 54.8183853,132.448671 Z" id="outer"></path> <path d="M159.501558,106.310445 C116.146895,106.310445 105.31356,117.143779 105.31356,160.498442 C105.31356,203.853105 116.146895,214.68644 159.501558,214.68644 C202.856221,214.68644 213.689555,203.853105 213.689555,160.498442 C213.689555,117.143779 202.856221,106.310445 159.501558,106.310445 Z" id="inner" transform="translate(159.5016, 160.4984) rotate(-45) translate(-159.5016, -160.4984)"></path> </g> </g> </svg>`;

			const observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					if (mutation.target.id === 'wpwrap' && !document.querySelector('.blockstudio-loading-screen')) {
						document.querySelector('#wpwrap').appendChild(loadingScreen);
						const adminMenuWidth = document.querySelector('#adminmenuback').offsetWidth;
						loadingScreen.style.left = `${adminMenuWidth}px`;
						loadingScreen.style.width = `calc(100% - ${adminMenuWidth}px)`;
					}

					if (mutation.target.id === 'blockstudio' && mutation.addedNodes.length > 0) {
						document.querySelector('.blockstudio-loading-screen').style.display = 'none';
						document.body.classList.remove('wp-core-ui')
						observer.disconnect();
					}
				});
			});

			observer.observe(document, {
				attributes: true,
				childList: true,
				subtree: true,
			});
		</script>
		<?php
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		$icon_base64  = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMjAiIGhlaWdodD0iMzIwIiB2aWV3Qm94PSIwIDAgMzIwIDMyMCI+PGcgZmlsbD0iI0E3QUFBRCIgZmlsbC1ydWxlPSJldmVub2RkIj48cGF0aCBkPSJNMTYwLDAgQzI4OC4wMTI1OTMsMCAzMjAsMzEuOTg3NDA2NSAzMjAsMTYwIEMzMjAsMjg4LjAxMjU5MyAyODguMDEyNTkzLDMyMCAxNjAsMzIwIEMzMS45ODc0MDY1LDMyMCAwLDI4OC4wMTI1OTMgMCwxNjAgQzAsMzEuOTg3NDA2NSAzMS45ODc0MDY1LDAgMTYwLDAgWiBNNTQuODE4Mzg1MywxMzIuNDQ4NjcxIEMzMi4zNzYzNjAzLDIxNi4yMDM0NDggNDcuNjk3MDA4NywyNDIuNzM5NTkgMTMxLjQ1MTc4NiwyNjUuMTgxNjE1IEMyMTUuMjA2NTY0LDI4Ny42MjM2NCAyNDEuNzQyNzA1LDI3Mi4zMDI5OTEgMjY0LjE4NDczLDE4OC41NDgyMTQgQzI4Ni42MjY3NTUsMTA0Ljc5MzQzNiAyNzEuMzA2MTA3LDc4LjI1NzI5NSAxODcuNTUxMzI5LDU1LjgxNTI3IEMxMDMuNzk2NTUyLDMzLjM3MzI0NTEgNzcuMjYwNDEwMyw0OC42OTM4OTM1IDU0LjgxODM4NTMsMTMyLjQ0ODY3MSBaIi8+PHBhdGggZD0iTTE1OS41MDE1NTgsMTA2LjMxMDQ0NSBDMTE2LjE0Njg5NSwxMDYuMzEwNDQ1IDEwNS4zMTM1NiwxMTcuMTQzNzc5IDEwNS4zMTM1NiwxNjAuNDk4NDQyIEMxMDUuMzEzNTYsMjAzLjg1MzEwNSAxMTYuMTQ2ODk1LDIxNC42ODY0NCAxNTkuNTAxNTU4LDIxNC42ODY0NCBDMjAyLjg1NjIyMSwyMTQuNjg2NDQgMjEzLjY4OTU1NSwyMDMuODUzMTA1IDIxMy42ODk1NTUsMTYwLjQ5ODQ0MiBDMjEzLjY4OTU1NSwxMTcuMTQzNzc5IDIwMi44NTYyMjEsMTA2LjMxMDQ0NSAxNTkuNTAxNTU4LDEwNi4zMTA0NDUgWiIgdHJhbnNmb3JtPSJyb3RhdGUoLTQ1IDE1OS41MDIgMTYwLjQ5OCkiLz48L2c+PC9zdmc+';
		$icon_data_uri = 'data:image/svg+xml;base64,' . $icon_base64;

		$hook_suffix = add_menu_page(
			'Blockstudio',
			'Blockstudio',
			'manage_options',
			'blockstudio',
			array( __CLASS__, 'page' ),
			$icon_data_uri,
			99999
		);

		add_action( "load-{$hook_suffix}", array( __CLASS__, 'get_all_assets' ) );
	}

	/**
	 * Assets needed for the editor to function.
	 *
	 * @return void
	 */
	public static function assets(): void {
		$handles = array(
			'dashicons',
			'lodash',
			'react',
			'react-dom',
			'wp-api-fetch',
			'wp-block-editor',
			'wp-components',
			'wp-data',
			'wp-edit-blocks',
			'wp-edit-post',
			'wp-editor',
			'wp-element',
			'wp-i18n',
			'wp-polyfill',
			'wp-primitives',
			'wp-reset-editor-styles',
		);

		foreach ( $handles as $handle ) {
			wp_enqueue_style( $handle );
		}
	}

	/**
	 * Capture frontend assets.
	 *
	 * @return void
	 */
	public static function capture_frontend_assets(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading query param for asset capture.
		$special_id = isset( $_GET['blockstudio_editor_capture_assets_id'] )
			? sanitize_key( $_GET['blockstudio_editor_capture_assets_id'] )
			: '';

		$expected_id = strtolower(
			get_transient( 'blockstudio_editor_expected_capture_assets_id' )
		);

		if ( empty( $special_id ) || $special_id !== $expected_id ) {
			return;
		}

		global $wp_scripts, $wp_styles;

		$frontend_scripts = array();
		$frontend_styles  = array();

		self::get_assets_data(
			$frontend_scripts,
			$frontend_styles,
			$wp_scripts,
			$wp_styles,
			'frontend'
		);

		set_transient(
			'blockstudio_editor_captured_frontend_scripts',
			$frontend_scripts,
			HOUR_IN_SECONDS
		);
		set_transient(
			'blockstudio_editor_captured_frontend_styles',
			$frontend_styles,
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Check if a user is allowed for editor.
	 *
	 * @return bool Whether the user is allowed.
	 */
	public static function is_allowed(): bool {
		$user  = wp_get_current_user();
		$ids   = Settings::get( 'users/ids' );
		$roles = Settings::get( 'users/roles' );

		return in_array( $user->ID, is_array( $ids ) ? $ids : array( $ids ), true ) ||
			count(
				array_intersect(
					$user->roles,
					is_array( $roles ) ? $roles : array( $roles )
				) ?? array()
			) > 0;
	}

	/**
	 * Ensure assets have a full url.
	 *
	 * @param string $src The source URL.
	 *
	 * @return string The full URL.
	 */
	public static function ensure_full_url( $src ): string {
		if ( 0 === strpos( $src, '/' ) ) {
			return site_url() . $src;
		}

		return $src;
	}

	/**
	 * Get all enqueued assets from editor, admin, and frontend context.
	 *
	 * @return array The assets array.
	 */
	public static function get_all_assets(): array {
		$cached_assets = get_transient( 'blockstudio_editor_all_assets' );

		if ( false !== $cached_assets ) {
			return $cached_assets;
		}

		global $wp_scripts, $wp_styles;

		$original_wp_scripts = clone $wp_scripts;
		$original_wp_styles  = clone $wp_styles;

		$contexts      = array( 'block_editor', 'admin' );
		$final_scripts = array();
		$final_styles  = array();

		$capture_id = wp_generate_password( 20, false );
		set_transient(
			'blockstudio_editor_expected_capture_assets_id',
			$capture_id,
			10 * MINUTE_IN_SECONDS
		);
		wp_remote_get(
			add_query_arg(
				'blockstudio_editor_capture_assets_id',
				$capture_id,
				home_url()
			)
		);

		foreach ( $contexts as $context ) {
			$wp_scripts->queue = array();
			$wp_styles->queue  = array();

			switch ( $context ) {
				case 'block_editor':
					do_action( 'enqueue_block_editor_assets' );
					break;
				case 'admin':
					do_action( 'admin_enqueue_scripts' );
					break;
			}

			self::get_assets_data(
				$final_scripts,
				$final_styles,
				$wp_scripts,
				$wp_styles,
				$context
			);
		}

		$frontend_scripts = get_transient( 'blockstudio_editor_captured_frontend_scripts' );
		$frontend_styles  = get_transient( 'blockstudio_editor_captured_frontend_styles' );

		if ( $frontend_scripts ) {
			foreach ( $frontend_scripts as $handle => $script ) {
				$final_scripts[ $handle ] = $script;
			}
		}

		if ( $frontend_styles ) {
			foreach ( $frontend_styles as $handle => $style ) {
				$final_styles[ $handle ] = $style;
			}
		}

		$wp_scripts = $original_wp_scripts;
		$wp_styles  = $original_wp_styles;

		$all_assets = array(
			'scripts' => $final_scripts,
			'styles'  => $final_styles,
		);

		set_transient(
			'blockstudio_editor_all_assets',
			$all_assets,
			HOUR_IN_SECONDS
		);

		return $all_assets;
	}

	/**
	 * Get assets data.
	 *
	 * @param array  $final_scripts The scripts array.
	 * @param array  $final_styles  The styles array.
	 * @param object $wp_scripts    The WP_Scripts object.
	 * @param object $wp_styles     The WP_Styles object.
	 * @param string $context       The context.
	 *
	 * @return void
	 */
	public static function get_assets_data(
		&$final_scripts,
		&$final_styles,
		$wp_scripts,
		$wp_styles,
		$context
	): void {
		foreach ( $wp_scripts->registered as $handle => $script ) {
			$src_data = array();

			if ( is_string( $script->src ) ) {
				$src_data['src'] = self::ensure_full_url( $script->src );
			}
			if ( isset( $script->extra['after'] ) ) {
				$src_data['inline'] = implode( '', $script->extra['after'] );
			}
			if ( ! empty( $src_data ) ) {
				$final_scripts[ $handle ] = array_merge(
					$src_data,
					array(
						'deps'    => $script->deps,
						'context' => $context,
						'type'    => 'script',
					)
				);
			}
		}

		foreach ( $wp_styles->registered as $handle => $style ) {
			$src_data = array();

			if ( is_string( $style->src ) ) {
				$src_data['src'] = self::ensure_full_url( $style->src );
			}
			if ( isset( $style->extra['after'] ) ) {
				$src_data['inline'] = implode( '', $style->extra['after'] );
			}
			if ( ! empty( $src_data ) ) {
				$final_styles[ $handle ] = array_merge(
					$src_data,
					array(
						'deps'    => $style->deps,
						'context' => $context,
						'type'    => 'style',
					)
				);
			}
		}
	}

	/**
	 * Get admin data.
	 *
	 * @param bool $editor Whether this is for the editor.
	 *
	 * @return array The admin data.
	 */
	public static function data( $editor = true ): array {
		global $wp_roles;
		$post = get_post();

		$image_sizes     = get_intermediate_image_sizes();
		$editor_only     = array();
		$editor_only_data = array(
			'imageSizes' => $image_sizes,
		);

		if ( $editor ) {
			$data          = Build::data();
			$data_sorted   = Build::data_sorted();
			$editor_markup = Settings::get( 'editor/markup' );
			$files         = Build::files();
			$functions     = get_defined_functions();
			$overrides     = Build::overrides();
			$paths         = Build::paths();
			$templates     = Files::get_folder_structure_with_contents(
				BLOCKSTUDIO_DIR . '/includes-v7/templates'
			);
			$all_assets    = self::get_all_assets();
			$scripts       = $all_assets['scripts'];
			$styles        = $all_assets['styles'];

			$editor_only = array(
				'optionsFilters'       => Settings::get_filters(),
				'optionsFiltersValues' => Settings::get_filters_values(),
				'optionsJson'          => Settings::get_json(),
				'optionsOptions'       => Settings::get_options(),
				'optionsRoles'         => array_keys( $wp_roles->roles ),
				'optionsSchema'        => json_decode(
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local schema file.
					file_get_contents( BLOCKSTUDIO_DIR . '/includes-v7/schemas/blockstudio.json' ),
					true
				),
				'optionsUsers'         => Settings::get( 'users/ids' )
					? array_reduce(
						get_users( array( 'include' => Settings::get( 'users/ids' ) ) ),
						function ( $carry, $user ) {
							$carry[] = $user->data;
							return $carry;
						},
						array()
					)
					: array(),
				'plugin'               => BlockstudioPlugin::getData(),
				'pluginVersion'        => get_plugin_data( BLOCKSTUDIO )['Version'],
				'plugins'              => get_plugins(),
				'pluginsPath'          => plugin_dir_path( BLOCKSTUDIO_DIR ),
				'settings'             => get_user_meta( get_current_user_id(), 'blockstudio_settings' )
					? wp_json_encode(
						get_user_meta( get_current_user_id(), 'blockstudio_settings', true ) ?: new stdClass()
					)
					: new stdClass(),
			);

			$editor_only_data = array(
				'blocks'       => $data,
				'blocksSorted' => $data_sorted,
				'editorMarkup' => $editor_markup,
				'files'        => $files,
				'functions'    => $functions,
				'overrides'    => $overrides,
				'paths'        => $paths,
				'scripts'      => $scripts,
				'styles'       => $styles,
				'templates'    => $templates,
			);
		}

		$blocks     = Build::blocks();
		$extensions = Build::extensions();

		return array_merge(
			array(
				'ajax'             => admin_url( 'admin-ajax.php' ),
				'adminUrl'         => admin_url(),
				'allowEditor'      => self::is_allowed() ? 'true' : 'false',
				'canEdit'          => self::is_allowed() ? 'true' : 'false',
				'data'             => array_merge(
					array(
						'blocksNative' => $blocks,
						'extensions'   => $extensions,
					),
					$editor_only_data
				),
				'isTailwindActive' => Build::is_tailwind_active() ? 'true' : 'false',
				'llmTxtUrl'        => LLM::get_txt_url(),
				'loader'           => plugin_dir_url( BLOCKSTUDIO ) . 'includes-v7/editor/vs',
				'logo'             => plugins_url( 'includes-v7/admin/assets/fabrikatLogo.svg', __FILE__ ),
				'nonce'            => wp_create_nonce( 'ajax-nonce' ),
				'nonceRest'        => wp_create_nonce( 'wp_rest' ),
				'options'          => Settings::get_all(),
				'postId'           => $post->ID ?? ( get_the_ID() ?? null ),
				'postType'         => $post->post_type ?? null,
				'rest'             => esc_url_raw( rest_url() ),
				'site'             => site_url(),
				'tailwindUrl'      => Tailwind::get_cdn_url(),
				'userId'           => get_current_user_id() ?? null,
				'userRole'         => wp_get_current_user()->roles[0] ?? null,
			),
			$editor_only
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public static function page(): void {
		$editor_scripts = include BLOCKSTUDIO_DIR . '/includes-v7/admin/assets/admin/index.tsx.asset.php';

		wp_enqueue_script(
			'blockstudio-admin',
			plugins_url( 'includes-v7/admin/assets/admin/index.tsx.js', BLOCKSTUDIO ),
			$editor_scripts['dependencies'],
			$editor_scripts['version'],
			true
		);

		wp_localize_script( 'blockstudio-admin', 'blockstudioAdmin', self::data() );
		wp_enqueue_style( 'wp-components' );

		$all_assets  = self::get_all_assets();
		$blocks      = Build::blocks();
		$data        = Build::data();
		$data_sorted = Build::data_sorted();
		$extensions  = Build::extensions();
		$files       = Build::files();
		$overrides   = Build::overrides();
		$paths       = Build::paths();
		$scripts     = $all_assets['scripts'];
		$styles      = $all_assets['styles'];
		$templates   = Files::get_folder_structure_with_contents(
			BLOCKSTUDIO_DIR . '/includes-v7/templates'
		);
		?>
		<script>
			console.log('data: ', <?php echo wp_json_encode( $data ); ?>);
			console.log('dataSorted: ', <?php echo wp_json_encode( $data_sorted ); ?>);
			console.log('extends: ', <?php echo wp_json_encode( $extensions ); ?>);
			console.log('files: ', <?php echo wp_json_encode( $files ); ?>);
			console.log('native: ', <?php echo wp_json_encode( $blocks ); ?>);
			console.log('overrides: ', <?php echo wp_json_encode( $overrides ); ?>);
			console.log('paths:', <?php echo wp_json_encode( $paths ); ?>);
			console.log('scripts: ', <?php echo wp_json_encode( $scripts ); ?>);
			console.log('styles: ', <?php echo wp_json_encode( $styles ); ?>);
			console.log('templates: ', <?php echo wp_json_encode( $templates ); ?>);
		</script>
		<div id="blockstudio">
		</div>
		<?php
	}
}

new Admin();

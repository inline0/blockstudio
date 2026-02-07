<?php
/**
 * REST API class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Block_Type_Registry;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API endpoints for Blockstudio Gutenberg integration.
 *
 * This class provides REST endpoints used by the Gutenberg block editor.
 *
 * Endpoint Categories:
 *
 * 1. Data Retrieval (GET):
 *    - /data: Block data, sorted data, and files
 *    - /blocks: All block configurations
 *    - /blocks-sorted: Blocks organized by directory
 *    - /files: All discovered block files
 *    - /files/dist: Compiled asset contents
 *    - /icons: Icon set data for icon picker
 *
 * 2. Settings (POST):
 *    - /editor/options/save: Save plugin options (DB or JSON)
 *
 * 3. Gutenberg Integration (POST):
 *    - /gutenberg/block/render/{name}: Server-side block render
 *    - /gutenberg/block/render/all: Batch render multiple blocks
 *    - /gutenberg/block/update: Live preview updates during editing
 *
 * 4. Attribute Building (POST):
 *    - /attributes/build: Convert block.json fields to WP attributes
 *    - /attributes/populate: Fetch dynamic options for select fields
 *
 * Security:
 * - Most endpoints require Admin::is_allowed() permission
 * - Gutenberg endpoints use edit_post capability checks
 * - All path parameters validated to prevent directory traversal
 * - WP_Filesystem used for all file operations
 *
 * Response Format:
 * - Success: { code, message, data: { status: 200, ... } }
 * - Error: WP_Error with code, message, and status
 *
 * @since 2.3.0
 */
class Rest {

	/**
	 * Constructor.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		self::register_endpoints();
	}

	/**
	 * Success response.
	 *
	 * @since 5.2.0
	 *
	 * @param string $code    The response code.
	 * @param string $message The response message.
	 * @param array  $data    Additional data.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function response( $code, $message, array $data = array() ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
				'data'    => array_merge(
					array(
						'status' => 200,
					),
					$data
				),
			)
		);
	}

	/**
	 * Error response.
	 *
	 * @since 5.2.0
	 *
	 * @param string $code    The error code.
	 * @param string $message The error message.
	 * @param array  $data    Additional data.
	 *
	 * @return WP_Error The error response.
	 */
	public function error( $code, $message, array $data = array() ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array_merge(
				array(
					'status' => 500,
				),
				$data
			)
		);
	}

	/**
	 * Return response or error.
	 *
	 * @since 5.2.0
	 *
	 * @param bool   $condition The condition to check.
	 * @param string $code      The response code.
	 * @param array  $message   Array with 'success' and 'error' keys.
	 * @param array  $data      Additional data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function response_or_error( $condition, $code, $message, array $data = array() ) {
		if ( ! $condition ) {
			return $this->error( $code, $message['error'], $data );
		}

		return $this->response( $code, $message['success'], $data );
	}

	/**
	 * Initialize WP_Filesystem.
	 *
	 * @since 5.2.8
	 *
	 * @return bool|null Whether filesystem was initialized.
	 */
	public function filesystem(): ?bool {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return WP_Filesystem();
	}

	/**
	 * Add REST endpoints.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function register_endpoints() {
		add_action(
			'rest_api_init',
			function () {
				$permission      = fn() => Admin::is_allowed();
				$permission_edit = function ( $request ) {
					global $post;

					$post_id = isset( $request['post_id'] )
						? (int) $request['post_id']
						: 0;

					if ( $post_id > 0 ) {
						$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Setting post context for permission check.

						if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
							return new WP_Error(
								'block_cannot_read',
								__(
									'Sorry, you are not allowed to read blocks of this post.',
									'blockstudio'
								),
								array(
									'status' => rest_authorization_required_code(),
								)
							);
						}
					} elseif ( ! current_user_can( 'edit_posts' ) ) {
							return new WP_Error(
								'block_cannot_read',
								__(
									'Sorry, you are not allowed to read blocks as this user.',
									'blockstudio'
								),
								array(
									'status' => rest_authorization_required_code(),
								)
							);
					}

					return true;
				};

				register_rest_route(
					'blockstudio/v1',
					'/data',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'data' ),
						'permission_callback' => $permission,
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/blocks',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'blocks' ),
						'permission_callback' => $permission,
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/blocks-sorted',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'blocks_sorted' ),
						'permission_callback' => $permission,
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/icons',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'icons' ),
						'permission_callback' => is_admin(),
						'args'                => array(
							'set'    => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param ) &&
										false === strpos( $param, '..' );
								},
							),
							'subSet' => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param ) &&
										false === strpos( $param, '..' );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/files',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'files' ),
						'permission_callback' => $permission,
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/files/dist',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'files_dist' ),
						'permission_callback' => $permission,
						'args'                => array(
							'path' => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param ) &&
										false === strpos( $param, '..' );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/editor/options/save',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_options_save' ),
						'permission_callback' => $permission,
						'args'                => array(
							'json'    => array(
								'validate_callback' => function ( $param ) {
									return is_bool( $param );
								},
							),
							'options' => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/attributes/build',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'attributes_build' ),
						'permission_callback' => $permission,
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/attributes/populate',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'attributes_populate' ),
						'permission_callback' => is_admin(),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/gutenberg/block/update',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'gutenberg_block_update' ),
						'permission_callback' => $permission_edit,
						'args'                => array(
							'block'        => array(
								'validate_callback' => function ( $param ) {
									return is_array( $param );
								},
							),
							'filesChanged' => array(
								'validate_callback' => function ( $param ) {
									return is_array( $param );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/gutenberg/block/render/(?P<name>[a-z0-9-]+/[a-z0-9-]+)',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'gutenberg_block_render' ),
						'permission_callback' => $permission_edit,
						'args'                => array(
							'context'    => array(
								'validate_callback' => function () {
									return true;
								},
							),
							'attributes' => array(
								'validate_callback' => function () {
									return true;
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/gutenberg/block/render/all',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'gutenberg_block_render_all' ),
						'permission_callback' => $permission_edit,
						'args'                => array(
							'data' => array(
								'validate_callback' => function () {
									return true;
								},
							),
						),
					)
				);
			}
		);
	}

	/**
	 * /data Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @return array The data array.
	 */
	public function data(): array {
		return array(
			'data'       => Build::data(),
			'dataSorted' => Build::data_sorted(),
			'files'      => Build::files(),
		);
	}

	/**
	 * /blocks Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @return array The blocks data.
	 */
	public function blocks(): array {
		return Build::data();
	}

	/**
	 * /blocks-sorted Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @return array The sorted blocks data.
	 */
	public function blocks_sorted(): array {
		return Build::data_sorted();
	}

	/**
	 * /files Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @return array The files data.
	 */
	public function files(): array {
		return Build::files();
	}

	/**
	 * /files/dist Endpoint.
	 *
	 * @since 4.0.5
	 *
	 * @param array $data The request data.
	 *
	 * @return array The files data.
	 */
	public function files_dist( $data ): array {
		global $wp_filesystem;
		$path = $data['path'];

		$rii = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path )
		);

		$files = array();
		foreach ( $rii as $file ) {
			if ( ! $file->isDir() ) {
				$files[] = $file->getPathname();
			}
		}

		$result = array();
		foreach ( $files as $file ) {
			$result[ $file ] = $wp_filesystem->get_contents( $file );
		}

		return $result;
	}

	/**
	 * /icons Endpoint.
	 *
	 * @since 3.1.0
	 *
	 * @param array $data The request data.
	 *
	 * @return mixed|WP_Error The icons data or error.
	 */
	public function icons( $data ) {
		global $wp_filesystem;
		$code = 'icons';
		if ( ! $this->filesystem() ) {
			return $this->error( $code, 'Failed to initialize WP_Filesystem' );
		}

		$set     = sanitize_text_field( $data['set'] ?? '' );
		$sub_set = sanitize_text_field( $data['subSet'] ?? '' );

		$path =
			BLOCKSTUDIO_DIR .
			'/includes/icons/' .
			$set .
			( $sub_set ? '-' . $sub_set : '' ) .
			'.json';

		if (
			$wp_filesystem->exists( $path ) &&
			'json' === pathinfo( $path, PATHINFO_EXTENSION )
		) {
			return json_decode( $wp_filesystem->get_contents( $path ), true );
		} else {
			return $this->error( $code, 'Invalid icon set or subset' );
		}
	}

	/**
	 * /editor/options/save Endpoint.
	 *
	 * @since 5.2.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_options_save( $data ) {
		global $wp_filesystem;
		$code = 'save_options';
		if ( ! $this->filesystem() ) {
			return $this->error( $code, 'Unable to initialize WP_Filesystem' );
		}

		$message = array(
			'success' => 'Options saved',
			'error'   => 'Options saving failed',
		);

		delete_option( 'blockstudio_settings' );
		$result = update_option(
			'blockstudio_settings',
			json_decode( urldecode( $data['options'] ) )
		);

		$json_path = Settings::json_path();
		if ( $data['json'] ) {
			$wp_filesystem->put_contents(
				Settings::json_path(),
				urldecode( $data['options'] )
			);
		} elseif ( $wp_filesystem->exists( $json_path ) ) {
			$wp_filesystem->delete( $json_path );
		}

		return $this->response_or_error( $result, $code, $message );
	}

	/**
	 * /attributes/build Endpoint.
	 *
	 * @since 5.2.0
	 *
	 * @param WP_REST_Request $arguments The REST request.
	 *
	 * @return array The built attributes.
	 */
	public function attributes_build( WP_REST_Request $arguments ): array {
		$attributes = array();
		Build::build_attributes( $arguments->get_params(), $attributes );

		return $attributes;
	}

	/**
	 * /attributes/populate Endpoint.
	 *
	 * @since 5.1.0
	 *
	 * @param WP_REST_Request $arguments The REST request.
	 *
	 * @return array The populated attributes.
	 */
	public function attributes_populate( WP_REST_Request $arguments ): array {
		$attributes = array();
		Build::build_attributes( array( $arguments->get_params() ), $attributes );

		return array_values( $attributes )[0]['options'] ?? array();
	}

	/**
	 * /gutenberg/block/render Endpoint.
	 *
	 * @since 5.4.3
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response The response.
	 */
	public function gutenberg_block_render( $request ) {
		global $post;

		$post_id = isset( $request['post_id'] ) ? (int) $request['post_id'] : 0;

		if ( $post_id > 0 ) {
			$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Setting post context for rendering.
			setup_postdata( $post );
		}

		$registry   = WP_Block_Type_Registry::get_instance();
		$registered = $registry->get_registered( $request['name'] );

		if ( null === $registered || ! $registered->is_dynamic() ) {
			return new WP_Error(
				'block_invalid',
				__( 'Invalid block.', 'blockstudio' ),
				array(
					'status' => 404,
				)
			);
		}

		$attributes = $request->get_param( 'attributes' );

		$block = array(
			'blockName'    => $request['name'],
			'attrs'        => array_merge(
				$attributes,
				array(
					'_BLOCKSTUDIO_CONTEXT' => $request->get_param( 'context' ),
				)
			),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$data = array(
			'rendered' => render_block( $block ),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * /gutenberg/block/render/all Endpoint.
	 *
	 * @since 5.6.5
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response The response.
	 */
	public function gutenberg_block_render_all( $request ) {
		$blocks          = $request->get_param( 'data' );
		$rendered_blocks = array();

		foreach ( $blocks as $block ) {
			$_GET       = $block['post'];
			$block_data = array(
				'blockName'    => $block['name'],
				'attrs'        => array_merge(
					$block['attributes'],
					array(
						'_BLOCKSTUDIO_CONTEXT' => $block['context'],
					)
				),
				'innerHTML'    => '',
				'innerContent' => array(),
			);

			$rendered_blocks[ $block['clientId'] ] = render_block( $block_data );
		}

		return rest_ensure_response( $rendered_blocks );
	}

	/**
	 * /gutenberg/block/update Endpoint.
	 *
	 * @since 5.2.0
	 *
	 * @param array $data The request data.
	 *
	 * @return array The updated block data.
	 */
	public function gutenberg_block_update( $data ): array {
		$files         = $data['filesChanged'] ?? array();
		$block         = $data['block'];
		$block_name    = $block['name'];
		$block         = Build::data()[ $block_name ];
		$files_changed = array();

		foreach ( $files as $name => $content ) {
			$file = pathinfo( $name );

			if (
				str_ends_with( $name, '.php' ) ||
				str_ends_with( $name, '.twig' )
			) {
				set_transient(
					'blockstudio_gutenberg_' .
						$block_name .
						'_' .
						$file['basename'],
					$content
				);
				$files_changed[ $name ] = $content;
			}
			if ( Assets::is_css( $name ) ) {
				if ( Settings::get( 'assets/process/scss' ) ) {
					$content = Assets::compile_scss( $content, $name );
				}
			}
			if ( Assets::is_css( $name ) || str_ends_with( $name, '.js' ) ) {
				$files_changed[ Assets::get_id( $file['filename'], $block ) .
						'-' .
						$file['extension'] ] = Assets::render_inline(
							$file['basename'],
							$content,
							$block,
							'gutenberg',
							true
						);
			}
		}

		return array(
			'block'        => $block,
			'filesChanged' => $files_changed,
		);
	}
}

new Rest();

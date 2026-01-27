<?php
/**
 * REST API class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WP_Block_Type_Registry;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use ZipArchive;

/**
 * Handles REST API endpoints.
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
			require_once ABSPATH . 'wp-admin/includes-v7/file.php';
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
						$post = get_post( $post_id );

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
					} else {
						if ( ! current_user_can( 'edit_posts' ) ) {
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
					'/editor/plugin/activate',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_plugin_activate' ),
						'permission_callback' => $permission,
						'args'                => array(
							'path' => array(
								'validate_callback' => function ( $param ) {
									if ( ! current_user_can( 'activate_plugins' ) ) {
										return false;
									}

									if ( false !== strpos( $param, '..' ) ) {
										return false;
									}

									return true;
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/editor/file/create',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_file_create' ),
						'permission_callback' => $permission,
						'args'                => array(
							'files'        => array(
								'validate_callback' => function ( $files ) {
									if ( ! is_array( $files ) ) {
										return false;
									}

									foreach ( $files as $file ) {
										if (
											! isset( $file['path'] ) ||
											! is_string( $file['path'] )
										) {
											return false;
										}
										if ( false !== strpos( $file['path'], '..' ) ) {
											return false;
										}
									}

									return true;
								},
							),
							'importedFile' => array(
								'validate_callback' => function ( $imported_file ) {
									return is_array( $imported_file ) &&
										isset( $imported_file['id'] );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/editor/file/delete',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_file_delete' ),
						'permission_callback' => $permission,
						'args'                => array(
							'files' => array(
								'validate_callback' => function ( $files ) {
									if ( ! is_array( $files ) ) {
										return false;
									}

									foreach ( $files as $file ) {
										if ( ! is_string( $file ) ) {
											return false;
										}
										if ( false !== strpos( $file, '..' ) ) {
											return false;
										}
									}

									return true;
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/editor/file/rename',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_file_rename' ),
						'permission_callback' => $permission,
						'args'                => array(
							'oldPath' => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param ) &&
										false === strpos( $param, '..' );
								},
							),
							'newPath' => array(
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
					'/editor/block/save',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_block_save' ),
						'permission_callback' => $permission,
						'args'                => array(
							'block'  => array(
								'validate_callback' => function ( $param ) {
									return is_array( $param );
								},
							),
							'files'  => array(
								'validate_callback' => function ( $files ) {
									if ( ! is_array( $files ) ) {
										return false;
									}

									foreach ( $files as $k => $v ) {
										if ( ! is_string( $v ) ) {
											return false;
										}
										if ( false !== strpos( $k, '..' ) ) {
											return false;
										}
									}

									return true;
								},
							),
							'folder' => array(
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
					'/editor/block/test',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_block_test' ),
						'permission_callback' => $permission,
						'args'                => array(
							'name'    => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param );
								},
							),
							'content' => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/editor/block/render',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_block_render' ),
						'permission_callback' => $permission,
						'args'                => array(
							'name'    => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param );
								},
							),
							'content' => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/editor/zip/create',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_zip_create' ),
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
					'/editor/processor/scss',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_processor_scss' ),
						'permission_callback' => $permission,
						'args'                => array(
							'content' => array(
								'validate_callback' => function ( $param ) {
									return is_array( $param );
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
					'/editor/settings/save',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_settings_save' ),
						'permission_callback' => $permission,
						'args'                => array(
							'userId'  => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param );
								},
							),
							'options' => array(
								'validate_callback' => function ( $param ) {
									return is_array( $param );
								},
							),
						),
					)
				);

				register_rest_route(
					'blockstudio/v1',
					'/editor/tailwind/save',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'editor_tailwind_save' ),
						'permission_callback' => $permission,
						'args'                => array(
							'content' => array(
								'validate_callback' => function ( $param ) {
									return is_string( $param );
								},
							),
							'id'      => array(
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
					'/gutenberg/block/render' . '/(?P<name>[a-z0-9-]+/[a-z0-9-]+)',
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
			'/includes-v7/icons/' .
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
	 * /editor/plugin/activate Endpoint.
	 *
	 * @since 2.5.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_plugin_activate( $data ) {
		global $wp_filesystem;
		$code = 'activate_plugin';
		if ( ! $this->filesystem() ) {
			return $this->error( $code, 'Failed to initialize WP_Filesystem' );
		}

		require_once ABSPATH . '/wp-admin/includes-v7/plugin.php';

		$message = array(
			'success' => 'Plugin activated',
			'error'   => 'Plugin activation failed',
		);

		$plugin_path = sanitize_text_field( $data['path'] );

		if ( ! $wp_filesystem->exists( $plugin_path ) ) {
			return $this->error( $code, 'Plugin does not exist' );
		}

		if ( is_plugin_active( $plugin_path ) ) {
			return $this->response_or_error(
				true,
				$code,
				'Plugin is already active'
			);
		}

		$result = activate_plugin( $plugin_path );

		return $this->response_or_error( ! is_wp_error( $result ), $code, $message );
	}

	/**
	 * /editor/file/create Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_file_create( $data ) {
		$code = 'create_file';
		global $wp_filesystem;
		if ( ! $this->filesystem() ) {
			return $this->error( $code, 'Unable to initialize WP_Filesystem' );
		}

		require_once ABSPATH . '/wp-includes/post.php';

		$files         = array();
		$imported_file = $data['importedFile'] ?? false;

		try {
			foreach ( $data['files'] as $f ) {
				$sanitized_path = sanitize_text_field( $f['path'] );

				if (
					false !== strpos( $sanitized_path, '..' ) ||
					wp_is_stream( $sanitized_path )
				) {
					return $this->error( $code, 'Invalid path detected' );
				}

				if ( isset( $f['folderOnly'] ) ) {
					if ( ! $wp_filesystem->mkdir( $sanitized_path, 0775 ) ) {
						return $this->error(
							$code,
							"Failed to create directory: $sanitized_path"
						);
					}

					if ( $imported_file ) {
						$path          = get_attached_file( $imported_file['id'] );
						$name          = basename( $path );
						$new_file_path = trailingslashit( $sanitized_path ) . $name;

						if ( ! $wp_filesystem->copy( $path, $new_file_path ) ) {
							return $this->error(
								$code,
								"Failed to copy file to: $new_file_path"
							);
						}

						if (
							is_wp_error(
								unzip_file( $new_file_path, $sanitized_path )
							)
						) {
							return $this->error(
								$code,
								"Failed to unzip file: $new_file_path"
							);
						}

						$wp_filesystem->delete( $new_file_path );
						wp_delete_attachment( $imported_file['id'] );
					}
				} else {
					$dir_path_only = dirname( $sanitized_path );

					if (
						! $wp_filesystem->is_dir( $dir_path_only ) &&
						! $wp_filesystem->mkdir( $dir_path_only, 0775 )
					) {
						return $this->error(
							$code,
							"Failed to create directory: $dir_path_only"
						);
					}

					if (
						isset( $f['instance'] ) &&
						! $wp_filesystem->is_dir(
							trailingslashit( $dir_path_only ) . 'blocks'
						)
					) {
						$wp_filesystem->mkdir(
							trailingslashit( $dir_path_only ) . 'blocks',
							0775
						);
					}

					if (
						! $wp_filesystem->put_contents(
							$sanitized_path,
							$f['content'] ?? ''
						)
					) {
						return $this->error(
							$code,
							"Failed to write to file: $sanitized_path"
						);
					}
				}

				$type    = $wp_filesystem->is_dir( $sanitized_path )
					? 'Folder'
					: 'File';
				$files[] = "$type created: " . $sanitized_path;
			}

			clearstatcache();

			return $this->response( $code, $files );
		} catch ( Exception $e ) {
			return $this->error( $code, $e->getMessage() );
		}
	}

	/**
	 * /editor/file/delete Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_file_delete( $data ) {
		global $wp_filesystem;
		$code = 'delete_file';
		if ( ! $this->filesystem() ) {
			return $this->error( $code, 'Unable to initialize WP_Filesystem' );
		}

		$message = array(
			'success' => 'File deleted',
			'error'   => 'File deletion failed',
		);

		try {
			$files = array();
			foreach ( $data['files'] as $f ) {
				$sanitized_path = sanitize_text_field( $f );

				$type    = $wp_filesystem->is_dir( $sanitized_path )
					? 'Folder'
					: 'File';
				$files[] = "$type deleted: " . $sanitized_path;

				if (
					$wp_filesystem->is_dir( $sanitized_path ) &&
					! $wp_filesystem->rmdir( $sanitized_path, true )
				) {
					return $this->error(
						$code,
						"Failed to delete directory: $sanitized_path"
					);
				} elseif ( $wp_filesystem->is_file( $sanitized_path ) ) {
					$dir            = pathinfo( $sanitized_path )['dirname'];
					$compiled_asset = Assets::get_compiled_filename(
						$sanitized_path
					);
					$other_php_files = count( glob( $dir . '/' . '*.php' ) ) >= 1;

					if ( ! $wp_filesystem->delete( $sanitized_path ) ) {
						return $this->error(
							$code,
							"Failed to delete file: $sanitized_path"
						);
					}

					$other_files = count( glob( $dir . '/' . '*.*' ) ) >= 1;

					if ( ! $other_php_files && file_exists( $compiled_asset ) ) {
						$wp_filesystem->delete( $compiled_asset );
					}

					if ( ! $other_files ) {
						$wp_filesystem->delete( $dir . '/_dist', true );
					}
				}
			}

			clearstatcache();

			return $this->response( $code, $files );
		} catch ( Exception $e ) {
			return $this->error(
				$code,
				$message['error'],
				array(
					'error' => $e,
				)
			);
		}
	}

	/**
	 * /editor/file/rename Endpoint.
	 *
	 * @since 5.2.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_file_rename( $data ) {
		global $wp_filesystem;
		if ( ! $this->filesystem() ) {
			return $this->error( 'rename', 'Unable to initialize WP_Filesystem' );
		}

		$old_path = sanitize_text_field( $data['oldPath'] );
		$new_path = sanitize_text_field( $data['newPath'] );

		$is_dir = $wp_filesystem->is_dir( $old_path );
		$type   = $is_dir ? 'folder' : 'file';

		$code    = "rename_$type";
		$message = array(
			'success' => ucfirst( $type ) . ' renamed',
			'error'   => ucfirst( $type ) . ' rename failed',
		);

		if ( ! $wp_filesystem->exists( $old_path ) ) {
			return $this->error( $code, ucfirst( $type ) . ' does not exist' );
		}

		if ( $wp_filesystem->exists( $new_path ) ) {
			return $this->error(
				$code,
				ucfirst( $type ) . ' with the new name already exists'
			);
		}

		$result = $wp_filesystem->move( $old_path, $new_path );
		clearstatcache();

		return $this->response_or_error( $result, $code, $message );
	}

	/**
	 * /editor/block/save Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_block_save( $data ) {
		global $wp_filesystem;
		$code = 'save_block';
		if ( ! $this->filesystem() ) {
			return $this->error( $code, 'Unable to initialize WP_Filesystem' );
		}

		$message = array(
			'success' => 'Block saved',
			'error'   => 'Block save failed',
		);

		try {
			foreach ( $data['files'] as $k => $v ) {
				$sanitized_path    = sanitize_text_field( $k );
				$sanitized_content = $v;

				if ( $wp_filesystem->exists( $sanitized_path ) ) {
					if (
						! $wp_filesystem->put_contents(
							$sanitized_path,
							$sanitized_content
						)
					) {
						return $this->error(
							$code,
							'Failed to write to file: ' . $sanitized_path
						);
					}

					Assets::process(
						$sanitized_path,
						$data['block']['scopedClass']
					);
				}
			}

			clearstatcache();

			return $this->response( $code, $message['success'] );
		} catch ( Exception $e ) {
			return $this->error(
				$code,
				$message['error'],
				array(
					'error' => $e,
				)
			);
		}
	}

	/**
	 * /editor/block/test Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_block_test( $data ) {
		$code    = 'test_block';
		$message = array(
			'success' => 'Block tested',
			'error'   => 'Block test failed',
		);

		$content = $data['content'];
		$blocks  = Build::data();

		$err = fn( $error ) => $this->error(
			$code,
			$message,
			array(
				'error' => $error,
			)
		);

		if ( $blocks[ $data['name'] ]['twig'] ?? false ) {
			if ( ! $this->render_block_content( $data, urldecode( $data['content'] ) ) ) {
				return $err( 'Twig error' );
			}
		} elseif ( $this->test_code( $content ) ) {
			return $err( 'PHP error' );
		}

		return $this->response( $code, $message['success'] );
	}

	/**
	 * /editor/block/render Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_block_render( $data ) {
		$code    = 'render_block';
		$message = array(
			'success' => 'Block rendered',
			'error'   => 'Block render failed',
		);

		$blocks = Build::data();

		$success = fn() => $this->response(
			$code,
			$message['success'],
			array(
				'content' => $this->render_block_content( $data, urldecode( $data['content'] ) ),
			)
		);
		$err     = fn( $error ) => $this->error(
			$code,
			$message['error'],
			array(
				'error' => $error,
			)
		);

		try {
			if ( $blocks[ $data['name'] ]['twig'] ) {
				if ( $this->render_block_content( $data, urldecode( $data['content'] ) ) ) {
					return $success();
				} else {
					return $err( 'Twig error' );
				}
			} else {
				if ( $this->test_code( urldecode( $data['content'] ) ) ) {
					return $err( 'PHP error' );
				}

				return $success();
			}
		} catch ( Exception $e ) {
			return $err( $e );
		}
	}

	/**
	 * /editor/zip/create Endpoint.
	 *
	 * @since 5.2.0
	 *
	 * @param array $data The request data.
	 *
	 * @return void|WP_Error
	 */
	public function editor_zip_create( $data ) {
		global $wp_filesystem;
		$code = 'create_zip';
		if ( ! $this->filesystem() ) {
			return $this->error( $code, 'Unable to initialize WP_Filesystem' );
		}

		$message = array(
			'success' => 'ZIP created',
			'error'   => 'ZIP creation failed',
		);

		$path = $data['path'] ?? '';

		if ( ! $path || ! $wp_filesystem->exists( $path ) ) {
			return $this->error( $code, $message['error'] );
		}

		$files     = array();
		$root_path = realpath( $path );

		if ( $wp_filesystem->is_file( $path ) ) {
			$files[] = new SplFileInfo( $path );
		} else {
			$rii = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$path,
					FilesystemIterator::SKIP_DOTS
				),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $rii as $file ) {
				$files[] = $file;
			}
		}

		$zip_path = wp_upload_dir()['basedir'] . '/blockstudio-zip-temp.zip';

		if ( $this->create_zip( $files, $zip_path, $root_path ) ) {
			header( 'Content-type: application/zip' );
			header(
				'Content-Disposition: attachment; filename=blockstudio-export.zip'
			);
			echo $wp_filesystem->get_contents( $zip_path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$wp_filesystem->delete( $zip_path );
			exit();
		} else {
			return $this->error( $code, $message['error'] );
		}
	}

	/**
	 * /editor/processor/scss Endpoint.
	 *
	 * @since 3.0.8
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_processor_scss( $data ) {
		$code    = 'process_scss';
		$message = array(
			'success' => 'SCSS processed',
			'error'   => 'SCSS processing failed',
		);

		$compiled = array();

		try {
			foreach ( $data['content'] as $asset ) {
				$compiler                    = Assets::get_scss_compiler( $asset['path'] );
				$compiled[ $asset['path'] ]  = $compiler
					->compileString( $asset['content'] )
					->getCss();
			}

			return $this->response(
				$code,
				$message['success'],
				array(
					'compiled' => $compiled,
				)
			);
		} catch ( SassException $e ) {
			return $this->error(
				$code,
				$message['error'],
				array(
					'error' => $e->getMessage(),
				)
			);
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
	 * /editor/settings/save Endpoint.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_settings_save( $data ) {
		$code    = 'save_settings';
		$message = array(
			'success' => 'Settings saved',
			'error'   => 'Settings saving failed',
		);

		delete_user_meta( $data['userId'], 'blockstudio_settings' );
		$result = update_user_meta(
			$data['userId'],
			'blockstudio_settings',
			json_decode( urldecode( $data['settings'] ) )
		);

		return $this->response_or_error( $result, $code, $message );
	}

	/**
	 * /editor/tailwind/save Endpoint.
	 *
	 * @since 5.5.0
	 *
	 * @param array $data The request data.
	 *
	 * @return WP_Error|WP_REST_Response The response or error.
	 */
	public function editor_tailwind_save( $data ) {
		global $wp_filesystem;
		if ( ! $this->filesystem() ) {
			return $this->error( 'rename', 'Unable to initialize WP_Filesystem' );
		}

		$code    = 'tailwind_add';
		$message = array(
			'success' => 'Tailwind compiled',
			'error'   => 'Tailwind compiling failed',
		);

		$path = Tailwind::get_css_path( $data['id'] ?? 'editor' );
		$dir  = dirname( $path );

		try {
			if ( ! $wp_filesystem->is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$wp_filesystem->put_contents( $path, urldecode( $data['content'] ) );
			clearstatcache();

			return $this->response( $code, $message['success'] );
		} catch ( Exception $e ) {
			return $this->error(
				$code,
				$message['error'],
				array(
					'error' => $e,
				)
			);
		}
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
			$post = get_post( $post_id );
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
				$files_changed[
					Assets::get_id( $file['filename'], $block ) .
						'-' .
						$file['extension']
				] = Assets::render_inline(
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

	/**
	 * Render block content for testing.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $data    The block data.
	 * @param string $content The block content.
	 *
	 * @return false|string|null The rendered content.
	 */
	public function render_block_content( $data, $content ) {
		$blocks  = Build::data();
		$block   = $blocks[ $data['name'] ];
		$example = $block['example']['attributes'] ?? array();

		return blockstudio_render_block(
			array(
				'name' => $data['name'],
				'data' => array_merge(
					array(
						'_BLOCKSTUDIO_EDITOR_STRING' => $content,
					),
					$example
				),
			)
		);
	}

	/**
	 * Test code for syntax errors.
	 *
	 * @since 2.3.0
	 *
	 * @param string $snippet The code snippet to test.
	 *
	 * @return bool Whether the code has errors.
	 */
	public function test_code( $snippet ): bool {
		if ( empty( $snippet ) ) {
			return false;
		}

		ob_start();
		$result = @eval( ' ?>' . urldecode( $snippet ) . '<?php ' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		ob_end_clean();

		return false === $result;
	}

	/**
	 * Create a ZIP archive.
	 *
	 * @since 5.2.0
	 *
	 * @param array  $files       The files to include.
	 * @param string $destination The ZIP file path.
	 * @param string $root_path   The root path for relative paths.
	 *
	 * @return bool Whether the ZIP was created successfully.
	 */
	public function create_zip( $files, $destination, $root_path ): bool {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $destination, ZIPARCHIVE::CREATE ) ) {
			return false;
		}

		foreach ( $files as $name => $file ) {
			if ( ! $file->isDir() ) {
				$file_path     = $file->getRealPath();
				$relative_path = is_object( $name )
					? $name->getBasename()
					: substr( $file_path, strlen( $root_path ) + 1 );
				$zip->addFile( $file_path, $relative_path );
			}
		}

		return $zip->close();
	}
}

new Rest();

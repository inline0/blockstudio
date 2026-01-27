<?php

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
 * Rest class.
 *
 * @date   26/02/2022
 * @since  2.3.0
 */
class Rest
{
    /**
     * Construct.
     *
     * @date   26/02/2022
     * @since  2.3.0
     */
    function __construct()
    {
        self::registerEndpoints();
    }

    /**
     * Success response.
     *
     * @date   11/08/2023
     * @since  5.2.0
     *
     * @param  $code
     * @param  $message
     * @param  array  $data
     *
     * @return WP_REST_Response
     */
    function response($code, $message, array $data = []): WP_REST_Response
    {
        return new WP_REST_Response([
            'code' => $code,
            'message' => $message,
            'data' => array_merge(
                [
                    'status' => 200,
                ],
                $data
            ),
        ]);
    }

    /**
     * Error response.
     *
     * @date   11/08/2023
     * @since  5.2.0
     *
     * @param  $code
     * @param  $message
     * @param  array  $data
     *
     * @return WP_Error
     */
    function error($code, $message, array $data = []): WP_Error
    {
        return new WP_Error(
            $code,
            $message,
            array_merge(
                [
                    'status' => 500,
                ],
                $data
            )
        );
    }

    /**
     * Return response or error.
     *
     * @date   11/08/2023
     * @since  5.2.0
     *
     * @param  $condition
     * @param  $code
     * @param  $message
     * @param  array  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function responseOrError($condition, $code, $message, array $data = [])
    {
        if (!$condition) {
            return $this->error($code, $message['error'], $data);
        }

        return $this->response($code, $message['success'], $data);
    }

    /**
     * Return response or error.
     *
     * @date   30/09/2023
     * @since  5.2.8
     *
     * @return WP_Error|WP_REST_Response
     */
    function filesystem(): ?bool
    {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return WP_Filesystem();
    }

    /**
     * Add REST endpoints.
     *
     * @date   26/02/2022
     * @since  2.3.0
     */
    function registerEndpoints()
    {
        add_action('rest_api_init', function () {
            $permission = fn() => Admin::isAllowed();
            $permissionEdit = function ($request) {
                global $post;

                $post_id = isset($request['post_id'])
                    ? (int) $request['post_id']
                    : 0;

                if ($post_id > 0) {
                    $post = get_post($post_id);

                    if (!$post || !current_user_can('edit_post', $post->ID)) {
                        return new WP_Error(
                            'block_cannot_read',
                            __(
                                'Sorry, you are not allowed to read blocks of this post.'
                            ),
                            [
                                'status' => rest_authorization_required_code(),
                            ]
                        );
                    }
                } else {
                    if (!current_user_can('edit_posts')) {
                        return new WP_Error(
                            'block_cannot_read',
                            __(
                                'Sorry, you are not allowed to read blocks as this user.'
                            ),
                            [
                                'status' => rest_authorization_required_code(),
                            ]
                        );
                    }
                }

                return true;
            };

            register_rest_route('blockstudio/v1', '/data', [
                'methods' => 'GET',
                'callback' => [$this, 'data'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('blockstudio/v1', '/blocks', [
                'methods' => 'GET',
                'callback' => [$this, 'blocks'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('blockstudio/v1', '/blocks-sorted', [
                'methods' => 'GET',
                'callback' => [$this, 'blocksSorted'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('blockstudio/v1', '/icons', [
                'methods' => 'GET',
                'callback' => [$this, 'icons'],
                'permission_callback' => is_admin(),
                'args' => [
                    'set' => [
                        'validate_callback' => function ($param) {
                            return is_string($param) &&
                                strpos($param, '..') === false;
                        },
                    ],
                    'subSet' => [
                        'validate_callback' => function ($param) {
                            return is_string($param) &&
                                strpos($param, '..') === false;
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/files', [
                'methods' => 'GET',
                'callback' => [$this, 'files'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('blockstudio/v1', '/files/dist', [
                'methods' => 'POST',
                'callback' => [$this, 'filesDist'],
                'permission_callback' => $permission,
                'args' => [
                    'path' => [
                        'validate_callback' => function ($param) {
                            return is_string($param) &&
                                strpos($param, '..') === false;
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/plugin/activate', [
                'methods' => 'POST',
                'callback' => [$this, 'editorPluginActivate'],
                'permission_callback' => $permission,
                'args' => [
                    'path' => [
                        'validate_callback' => function ($param) {
                            if (!current_user_can('activate_plugins')) {
                                return false;
                            }

                            if (strpos($param, '..') !== false) {
                                return false;
                            }

                            return true;
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/file/create', [
                'methods' => 'POST',
                'callback' => [$this, 'editorFileCreate'],
                'permission_callback' => $permission,
                'args' => [
                    'files' => [
                        'validate_callback' => function ($files) {
                            if (!is_array($files)) {
                                return false;
                            }

                            foreach ($files as $file) {
                                if (
                                    !isset($file['path']) ||
                                    !is_string($file['path'])
                                ) {
                                    return false;
                                }
                                if (strpos($file['path'], '..') !== false) {
                                    return false;
                                }
                            }

                            return true;
                        },
                    ],
                    'importedFile' => [
                        'validate_callback' => function ($importedFile) {
                            return is_array($importedFile) &&
                                isset($importedFile['id']);
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/file/delete', [
                'methods' => 'POST',
                'callback' => [$this, 'editorFileDelete'],
                'permission_callback' => $permission,
                'args' => [
                    'files' => [
                        'validate_callback' => function ($files) {
                            if (!is_array($files)) {
                                return false;
                            }

                            foreach ($files as $file) {
                                if (!is_string($file)) {
                                    return false;
                                }
                                if (strpos($file, '..') !== false) {
                                    return false;
                                }
                            }

                            return true;
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/file/rename', [
                'methods' => 'POST',
                'callback' => [$this, 'editorFileRename'],
                'permission_callback' => $permission,
                'args' => [
                    'oldPath' => [
                        'validate_callback' => function ($param) {
                            return is_string($param) &&
                                strpos($param, '..') === false;
                        },
                    ],
                    'newPath' => [
                        'validate_callback' => function ($param) {
                            return is_string($param) &&
                                strpos($param, '..') === false;
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/block/save', [
                'methods' => 'POST',
                'callback' => [$this, 'editorBlockSave'],
                'permission_callback' => $permission,
                'args' => [
                    'block' => [
                        'validate_callback' => function ($param) {
                            return is_array($param);
                        },
                    ],
                    'files' => [
                        'validate_callback' => function ($files) {
                            if (!is_array($files)) {
                                return false;
                            }

                            foreach ($files as $k => $v) {
                                if (!is_string($v)) {
                                    return false;
                                }
                                if (strpos($k, '..') !== false) {
                                    return false;
                                }
                            }

                            return true;
                        },
                    ],
                    'folder' => [
                        'validate_callback' => function ($param) {
                            return is_string($param) &&
                                strpos($param, '..') === false;
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/block/test', [
                'methods' => 'POST',
                'callback' => [$this, 'editorBlockTest'],
                'permission_callback' => $permission,
                'args' => [
                    'name' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                    'content' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/block/render', [
                'methods' => 'POST',
                'callback' => [$this, 'editorBlockRender'],
                'permission_callback' => $permission,
                'args' => [
                    'name' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                    'content' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/zip/create', [
                'methods' => 'POST',
                'callback' => [$this, 'editorZipCreate'],
                'permission_callback' => $permission,
                'args' => [
                    'path' => [
                        'validate_callback' => function ($param) {
                            return is_string($param) &&
                                strpos($param, '..') === false;
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/processor/scss', [
                'methods' => 'POST',
                'callback' => [$this, 'editorProcessorScss'],
                'permission_callback' => $permission,
                'args' => [
                    'content' => [
                        'validate_callback' => function ($param) {
                            return is_array($param);
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/options/save', [
                'methods' => 'POST',
                'callback' => [$this, 'editorOptionsSave'],
                'permission_callback' => $permission,
                'args' => [
                    'json' => [
                        'validate_callback' => function ($param) {
                            return is_bool($param);
                        },
                    ],
                    'options' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/settings/save', [
                'methods' => 'POST',
                'callback' => [$this, 'editorSettingsSave'],
                'permission_callback' => $permission,
                'args' => [
                    'userId' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                    'options' => [
                        'validate_callback' => function ($param) {
                            return is_array($param);
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/editor/tailwind/save', [
                'methods' => 'POST',
                'callback' => [$this, 'editorTailwindSave'],
                'permission_callback' => $permission,
                'args' => [
                    'content' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                    'id' => [
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ],
                ],
            ]);

            register_rest_route('blockstudio/v1', '/attributes/build', [
                'methods' => 'POST',
                'callback' => [$this, 'attributesBuild'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('blockstudio/v1', '/attributes/populate', [
                'methods' => 'POST',
                'callback' => [$this, 'attributesPopulate'],
                'permission_callback' => is_admin(),
            ]);

            register_rest_route('blockstudio/v1', '/gutenberg/block/update', [
                'methods' => 'POST',
                'callback' => [$this, 'gutenbergBlockUpdate'],
                'permission_callback' => $permissionEdit,
                'args' => [
                    'block' => [
                        'validate_callback' => function ($param) {
                            return is_array($param);
                        },
                    ],
                    'filesChanged' => [
                        'validate_callback' => function ($param) {
                            return is_array($param);
                        },
                    ],
                ],
            ]);

            register_rest_route(
                'blockstudio/v1',
                '/gutenberg/block/render' . '/(?P<name>[a-z0-9-]+/[a-z0-9-]+)',
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'gutenbergBlockRender'],
                    'permission_callback' => $permissionEdit,
                    'args' => [
                        'context' => [
                            'validate_callback' => function () {
                                return true;
                            },
                        ],
                        'attributes' => [
                            'validate_callback' => function () {
                                return true;
                            },
                        ],
                    ],
                ]
            );

            register_rest_route(
                'blockstudio/v1',
                '/gutenberg/block/render/all',
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'gutenbergBlockRenderAll'],
                    'permission_callback' => $permissionEdit,
                    'args' => [
                        'data' => [
                            'validate_callback' => function () {
                                return true;
                            },
                        ],
                    ],
                ]
            );
        });
    }

    /**
     * /data Endpoint.
     *
     * @date   12/03/2022
     * @since  2.3.0
     *
     * @throws SassException
     */
    function data(): array
    {
        return [
            'data' => Build::data(),
            'dataSorted' => Build::dataSorted(),
            'files' => Build::files(),
        ];
    }

    /**
     * /blocks Endpoint.
     *
     * @date   26/02/2022
     * @since  2.3.0
     */
    function blocks(): array
    {
        return Build::data();
    }

    /**
     * /blocks-sorted Endpoint.
     *
     * @date   26/02/2022
     * @since  2.3.0
     *
     * @throws SassException
     */
    function blocksSorted(): array
    {
        return Build::dataSorted();
    }

    /**
     * /files Endpoint.
     *
     * @date   11/03/2022
     * @since  2.3.0
     *
     * @throws SassException
     */
    function files(): array
    {
        return Build::files();
    }

    /**
     * /files/dist Endpoint.
     *
     * @date   11/02/2023
     * @since  4.0.5
     *
     * @param  $data
     *
     * @return array
     */
    function filesDist($data): array
    {
        global $wp_filesystem;
        $path = $data['path'];

        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        $files = [];
        foreach ($rii as $file) {
            if (!$file->isDir()) {
                $files[] = $file->getPathname();
            }
        }

        $data = [];
        foreach ($files as $file) {
            $data[$file] = $wp_filesystem->get_contents($file);
        }

        return $data;
    }

    /**
     * /icons Endpoint.
     *
     * @date   25/11/2022
     * @since  3.1.0
     *
     * @param  $data
     *
     * @return mixed|WP_Error
     */
    function icons($data)
    {
        global $wp_filesystem;
        $code = 'icons';
        if (!$this->filesystem()) {
            return $this->error($code, 'Failed to initialize WP_Filesystem');
        }

        $set = sanitize_text_field($data['set'] ?? '');
        $subSet = sanitize_text_field($data['subSet'] ?? '');

        $path =
            BLOCKSTUDIO_DIR .
            '/includes/icons/' .
            $set .
            ($subSet ? '-' . $subSet : '') .
            '.json';

        if (
            $wp_filesystem->exists($path) &&
            pathinfo($path, PATHINFO_EXTENSION) === 'json'
        ) {
            return json_decode($wp_filesystem->get_contents($path), true);
        } else {
            return $this->error($code, 'Invalid icon set or subset');
        }
    }

    /**
     * /editor/plugin/activate Endpoint.
     *
     * @date   28/07/2022
     * @since  2.5.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorPluginActivate($data)
    {
        global $wp_filesystem;
        $code = 'activate_plugin';
        if (!$this->filesystem()) {
            return $this->error($code, 'Failed to initialize WP_Filesystem');
        }

        require_once ABSPATH . '/wp-admin/includes/plugin.php';

        $message = [
            'success' => 'Plugin activated',
            'error' => 'Plugin activation failed',
        ];

        $pluginPath = sanitize_text_field($data['path']);

        if (!$wp_filesystem->exists($pluginPath)) {
            return $this->error($code, 'Plugin does not exist');
        }

        if (is_plugin_active($pluginPath)) {
            return $this->responseOrError(
                true,
                $code,
                'Plugin is already active'
            );
        }

        $result = activate_plugin($pluginPath);

        return $this->responseOrError(!is_wp_error($result), $code, $message);
    }

    /**
     * /editor/file/create Endpoint.
     *
     * @date   09/03/2022
     * @since  2.3.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorFileCreate($data)
    {
        $code = 'create_file';
        global $wp_filesystem;
        if (!$this->filesystem()) {
            return $this->error($code, 'Unable to initialize WP_Filesystem');
        }

        require_once ABSPATH . '/wp-includes/post.php';

        $files = [];
        $importedFile = $data['importedFile'] ?? false;

        try {
            foreach ($data['files'] as $f) {
                $sanitizedPath = sanitize_text_field($f['path']);

                if (
                    strpos($sanitizedPath, '..') !== false ||
                    wp_is_stream($sanitizedPath)
                ) {
                    return $this->error($code, 'Invalid path detected');
                }

                if (isset($f['folderOnly'])) {
                    if (!$wp_filesystem->mkdir($sanitizedPath, 0775)) {
                        return $this->error(
                            $code,
                            "Failed to create directory: $sanitizedPath"
                        );
                    }

                    if ($importedFile) {
                        $path = get_attached_file($importedFile['id']);
                        $name = basename($path);
                        $newFilePath = trailingslashit($sanitizedPath) . $name;

                        if (!$wp_filesystem->copy($path, $newFilePath)) {
                            return $this->error(
                                $code,
                                "Failed to copy file to: $newFilePath"
                            );
                        }

                        if (
                            is_wp_error(
                                unzip_file($newFilePath, $sanitizedPath)
                            )
                        ) {
                            return $this->error(
                                $code,
                                "Failed to unzip file: $newFilePath"
                            );
                        }

                        $wp_filesystem->delete($newFilePath);
                        wp_delete_attachment($importedFile['id']);
                    }
                } else {
                    $dirPathOnly = dirname($sanitizedPath);

                    if (
                        !$wp_filesystem->is_dir($dirPathOnly) &&
                        !$wp_filesystem->mkdir($dirPathOnly, 0775)
                    ) {
                        return $this->error(
                            $code,
                            "Failed to create directory: $dirPathOnly"
                        );
                    }

                    if (
                        isset($f['instance']) &&
                        !$wp_filesystem->is_dir(
                            trailingslashit($dirPathOnly) . 'blocks'
                        )
                    ) {
                        $wp_filesystem->mkdir(
                            trailingslashit($dirPathOnly) . 'blocks',
                            0775
                        );
                    }

                    if (
                        !$wp_filesystem->put_contents(
                            $sanitizedPath,
                            $f['content'] ?? ''
                        )
                    ) {
                        return $this->error(
                            $code,
                            "Failed to write to file: $sanitizedPath"
                        );
                    }
                }

                $type = $wp_filesystem->is_dir($sanitizedPath)
                    ? 'Folder'
                    : 'File';
                $files[] = "$type created: " . $sanitizedPath;
            }

            clearstatcache();

            return $this->response($code, $files);
        } catch (Exception $e) {
            return $this->error($code, $e->getMessage());
        }
    }

    /**
     * /editor/file/delete Endpoint.
     *
     * @date   10/03/2022
     * @since  2.3.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorFileDelete($data)
    {
        global $wp_filesystem;
        $code = 'delete_file';
        if (!$this->filesystem()) {
            return $this->error($code, 'Unable to initialize WP_Filesystem');
        }

        $message = [
            'success' => 'File deleted',
            'error' => 'File deletion failed',
        ];

        try {
            $files = [];
            foreach ($data['files'] as $f) {
                $sanitizedPath = sanitize_text_field($f);

                $type = $wp_filesystem->is_dir($sanitizedPath)
                    ? 'Folder'
                    : 'File';
                $files[] = "$type deleted: " . $sanitizedPath;

                if (
                    $wp_filesystem->is_dir($sanitizedPath) &&
                    !$wp_filesystem->rmdir($sanitizedPath, true)
                ) {
                    return $this->error(
                        $code,
                        "Failed to delete directory: $sanitizedPath"
                    );
                } elseif ($wp_filesystem->is_file($sanitizedPath)) {
                    $dir = pathinfo($sanitizedPath)['dirname'];
                    $compiledAsset = Assets::getCompiledFileName(
                        $sanitizedPath
                    );
                    $otherPhpFiles = count(glob($dir . '/' . '*.php')) >= 1;

                    if (!$wp_filesystem->delete($sanitizedPath)) {
                        return $this->error(
                            $code,
                            "Failed to delete file: $sanitizedPath"
                        );
                    }

                    $otherFiles = count(glob($dir . '/' . '*.*')) >= 1;

                    if (!$otherPhpFiles && file_exists($compiledAsset)) {
                        $wp_filesystem->delete($compiledAsset);
                    }

                    if (!$otherFiles) {
                        $wp_filesystem->delete($dir . '/_dist', true);
                    }
                }
            }

            clearstatcache();

            return $this->response($code, $files);
        } catch (Exception $e) {
            return $this->error($code, $message['error'], [
                'error' => $e,
            ]);
        }
    }

    /**
     * /editor/file/rename Endpoint.
     *
     * @date   11/08/2023
     * @since  5.2.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorFileRename($data)
    {
        global $wp_filesystem;
        if (!$this->filesystem()) {
            return $this->error('rename', 'Unable to initialize WP_Filesystem');
        }

        $oldPath = sanitize_text_field($data['oldPath']);
        $newPath = sanitize_text_field($data['newPath']);

        $isDir = $wp_filesystem->is_dir($oldPath);
        $type = $isDir ? 'folder' : 'file';

        $code = "rename_$type";
        $message = [
            'success' => ucfirst($type) . ' renamed',
            'error' => ucfirst($type) . ' rename failed',
        ];

        if (!$wp_filesystem->exists($oldPath)) {
            return $this->error($code, ucfirst($type) . ' does not exist');
        }

        if ($wp_filesystem->exists($newPath)) {
            return $this->error(
                $code,
                ucfirst($type) . ' with the new name already exists'
            );
        }

        $result = $wp_filesystem->move($oldPath, $newPath);
        clearstatcache();

        return $this->responseOrError($result, $code, $message);
    }

    /**
     * /editor/block/save Endpoint.
     *
     * @date   02/03/2022
     * @since  2.3.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     * @throws  SassException
     */
    function editorBlockSave($data)
    {
        global $wp_filesystem;
        $code = 'save_block';
        if (!$this->filesystem()) {
            return $this->error($code, 'Unable to initialize WP_Filesystem');
        }

        $message = [
            'success' => 'Block saved',
            'error' => 'Block save failed',
        ];

        try {
            foreach ($data['files'] as $k => $v) {
                $sanitizedPath = sanitize_text_field($k);
                $sanitizedContent = $v;

                if ($wp_filesystem->exists($sanitizedPath)) {
                    if (
                        !$wp_filesystem->put_contents(
                            $sanitizedPath,
                            $sanitizedContent
                        )
                    ) {
                        return $this->error(
                            $code,
                            'Failed to write to file: ' . $sanitizedPath
                        );
                    }

                    Assets::process(
                        $sanitizedPath,
                        $data['block']['scopedClass']
                    );
                }
            }

            clearstatcache();

            return $this->response($code, $message['success']);
        } catch (Exception $e) {
            return $this->error($code, $message['error'], [
                'error' => $e,
            ]);
        }
    }

    /**
     * /editor/block/test Endpoint.
     *
     * @date   02/03/2022
     * @since  2.3.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorBlockTest($data)
    {
        $code = 'test_block';
        $message = [
            'success' => 'Block tested',
            'error' => 'Block test failed',
        ];

        $content = $data['content'];
        $blocks = Build::data();

        $err = fn($error) => $this->error($code, $message, [
            'error' => $error,
        ]);

        if ($blocks[$data['name']]['twig'] ?? false) {
            if (!$this->renderBlock($data, urldecode($data['content']))) {
                return $err('Twig error');
            }
        } elseif ($this->testCode($content)) {
            return $err('PHP error');
        }

        return $this->response($code, $message['success']);
    }

    /**
     * /editor/block/render Endpoint.
     *
     * @date   06/03/2022
     * @since  2.3.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorBlockRender($data)
    {
        $code = 'render_block';
        $message = [
            'success' => 'Block rendered',
            'error' => 'Block render failed',
        ];

        $blocks = Build::data();

        $success = fn() => $this->response($code, $message['success'], [
            'content' => $this->renderBlock($data, urldecode($data['content'])),
        ]);
        $err = fn($error) => $this->error($code, $message['error'], [
            'error' => $error,
        ]);

        try {
            if ($blocks[$data['name']]['twig']) {
                if ($this->renderBlock($data, urldecode($data['content']))) {
                    return $success();
                } else {
                    return $err('Twig error');
                }
            } else {
                if ($this->testCode(urldecode($data['content']))) {
                    return $err('PHP error');
                }

                return $success();
            }
        } catch (Exception $e) {
            return $err($e);
        }
    }

    /**
     * /editor/zip/create Endpoint.
     *
     * @date   12/08/2023
     * @since  5.2.0
     *
     * @param  $data
     *
     * @return void|WP_Error
     */
    function editorZipCreate($data)
    {
        global $wp_filesystem;
        $code = 'create_zip';
        if (!$this->filesystem()) {
            return $this->error($code, 'Unable to initialize WP_Filesystem');
        }

        $message = [
            'success' => 'ZIP created',
            'error' => 'ZIP creation failed',
        ];

        $path = $data['path'] ?? '';

        if (!$path || !$wp_filesystem->exists($path)) {
            return $this->error($code, $message['error']);
        }

        $files = [];
        $rootPath = realpath($path);

        if ($wp_filesystem->is_file($path)) {
            $files[] = new SplFileInfo($path);
        } else {
            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $path,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($rii as $file) {
                $files[] = $file;
            }
        }

        $zipPath = wp_upload_dir()['basedir'] . '/blockstudio-zip-temp.zip';

        if ($this->createZip($files, $zipPath, $rootPath)) {
            header('Content-type: application/zip');
            header(
                'Content-Disposition: attachment; filename=blockstudio-export.zip'
            );
            echo $wp_filesystem->get_contents($zipPath);
            $wp_filesystem->delete($zipPath);
            exit();
        } else {
            return $this->error($code, $message['error']);
        }
    }

    /**
     * /editor/processor/scss Endpoint.
     *
     * @date   11/10/2022
     * @since  3.0.8
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorProcessorScss($data)
    {
        $code = 'process_scss';
        $message = [
            'success' => 'SCSS processed',
            'error' => 'SCSS processing failed',
        ];

        $compiled = [];

        try {
            foreach ($data['content'] as $asset) {
                $compiler = Assets::getScssCompiler($asset['path']);
                $compiled[$asset['path']] = $compiler
                    ->compileString($asset['content'])
                    ->getCss();
            }

            return $this->response($code, $message['success'], [
                'compiled' => $compiled,
            ]);
        } catch (SassException $e) {
            return $this->error($code, $message['error'], [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * /editor/options/save Endpoint.
     *
     * @date   30/08/2023
     * @since  5.2.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorOptionsSave($data)
    {
        global $wp_filesystem;
        $code = 'save_options';
        if (!$this->filesystem()) {
            return $this->error($code, 'Unable to initialize WP_Filesystem');
        }

        $message = [
            'success' => 'Options saved',
            'error' => 'Options saving failed',
        ];

        delete_option('blockstudio_settings');
        $result = update_option(
            'blockstudio_settings',
            json_decode(urldecode($data['options']))
        );

        $jsonPath = Settings::jsonPath();
        if ($data['json']) {
            $wp_filesystem->put_contents(
                Settings::jsonPath(),
                urldecode($data['options'])
            );
        } elseif ($wp_filesystem->exists($jsonPath)) {
            $wp_filesystem->delete($jsonPath);
        }

        return $this->responseOrError($result, $code, $message);
    }

    /**
     * /editor/settings/save Endpoint.
     *
     * @date   02/03/2022
     * @since  2.3.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorSettingsSave($data)
    {
        $code = 'save_settings';
        $message = [
            'success' => 'Settings saved',
            'error' => 'Settings saving failed',
        ];

        delete_user_meta($data['userId'], 'blockstudio_settings');
        $result = update_user_meta(
            $data['userId'],
            'blockstudio_settings',
            json_decode(urldecode($data['settings']))
        );

        return $this->responseOrError($result, $code, $message);
    }

    /**
     * /editor/tailwind/save Endpoint.
     *
     * @date   10/05/2024
     * @since  5.5.0
     *
     * @param  $data
     *
     * @return WP_Error|WP_REST_Response
     */
    function editorTailwindSave($data)
    {
        global $wp_filesystem;
        if (!$this->filesystem()) {
            return $this->error('rename', 'Unable to initialize WP_Filesystem');
        }

        $code = 'tailwind_add';
        $message = [
            'success' => 'Tailwind compiled',
            'error' => 'Tailwind compiling failed',
        ];

        $path = Tailwind::getCSSPath($data['id'] ?? 'editor');
        $dir = dirname($path);

        try {
            if (!$wp_filesystem->is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            $wp_filesystem->put_contents($path, urldecode($data['content']));
            clearstatcache();

            return $this->response($code, $message['success']);
        } catch (Exception $e) {
            return $this->error($code, $message['error'], [
                'error' => $e,
            ]);
        }
    }

    /**
     * /attributes/build Endpoint.
     *
     * @date   07/08/2023
     * @since  5.2.0
     *
     * @param  WP_REST_Request  $arguments
     *
     * @return array
     */
    function attributesBuild(WP_REST_Request $arguments): array
    {
        $attributes = [];
        Build::buildAttributes($arguments->get_params(), $attributes);

        return $attributes;
    }

    /**
     * /attributes/populate Endpoint.
     *
     * @date   31/07/2023
     * @since  5.1.0
     *
     * @param  WP_REST_Request  $arguments
     *
     * @return array
     */
    function attributesPopulate(WP_REST_Request $arguments): array
    {
        $attributes = [];
        Build::buildAttributes([$arguments->get_params()], $attributes);

        return array_values($attributes)[0]['options'] ?? [];
    }

    /**
     * /gutenberg/block/render Endpoint.
     *
     * @date   29/02/2024
     * @since  5.4.3
     *
     * @param  $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    function gutenbergBlockRender($request)
    {
        global $post;

        $postId = isset($request['post_id']) ? (int) $request['post_id'] : 0;

        if ($postId > 0) {
            $post = get_post($postId);
            setup_postdata($post);
        }

        $registry = WP_Block_Type_Registry::get_instance();
        $registered = $registry->get_registered($request['name']);

        if (null === $registered || !$registered->is_dynamic()) {
            return new WP_Error('block_invalid', __('Invalid block.'), [
                'status' => 404,
            ]);
        }

        $attributes = $request->get_param('attributes');

        $block = [
            'blockName' => $request['name'],
            'attrs' => array_merge($attributes, [
                '_BLOCKSTUDIO_CONTEXT' => $request->get_param('context'),
            ]),
            'innerHTML' => '',
            'innerContent' => [],
        ];

        $data = [
            'rendered' => render_block($block),
        ];

        return rest_ensure_response($data);
    }

    /**
     * /gutenberg/block/render/all Endpoint.
     *
     * @date   04/10/2024
     * @since  5.6.5
     *
     * @param  $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    function gutenbergBlockRenderAll($request)
    {
        $blocks = $request->get_param('data');
        $renderedBlocks = [];

        foreach ($blocks as $block) {
            $_GET = $block['post'];
            $blockData = [
                'blockName' => $block['name'],
                'attrs' => array_merge($block['attributes'], [
                    '_BLOCKSTUDIO_CONTEXT' => $block['context'],
                ]),
                'innerHTML' => '',
                'innerContent' => [],
            ];

            $renderedBlocks[$block['clientId']] = render_block($blockData);
        }

        return rest_ensure_response($renderedBlocks);
    }

    /**
     * /gutenberg/block/update Endpoint.
     *
     * @date   24/08/2023
     * @since  5.2.0
     *
     * @param  $data
     *
     * @return array
     */
    function gutenbergBlockUpdate($data): array
    {
        $files = $data['filesChanged'] ?? [];
        $block = $data['block'];
        $blockName = $block['name'];
        $block = Build::data()[$blockName];
        $filesChanged = [];

        foreach ($files as $name => $content) {
            $file = pathinfo($name);

            if (
                Files::endsWith($name, '.php') ||
                Files::endsWith($name, '.twig')
            ) {
                set_transient(
                    'blockstudio_gutenberg_' .
                        $blockName .
                        '_' .
                        $file['basename'],
                    $content
                );
                $filesChanged[$name] = $content;
            }
            if (Assets::isCss($name)) {
                if (Settings::get('assets/process/scss')) {
                    $content = Assets::compileScss($content, $name);
                }
            }
            if (Assets::isCss($name) || Files::endsWith($name, '.js')) {
                $filesChanged[
                    Assets::getId($file['filename'], $block) .
                        '-' .
                        $file['extension']
                ] = Assets::renderInline(
                    $file['basename'],
                    $content,
                    $block,
                    'gutenberg',
                    true
                );
            }
        }

        return [
            'block' => $block,
            'filesChanged' => $filesChanged,
        ];
    }

    /**
     * Twig code.
     *
     * @date   07/03/2022
     * @since  2.3.0
     *
     * @param  $data
     * @param  $content
     *
     * @return false|string|null
     */
    function renderBlock($data, $content)
    {
        $blocks = Build::data();
        $block = $blocks[$data['name']];
        $example = $block['example']['attributes'] ?? [];

        return blockstudio_render_block([
            'name' => $data['name'],
            'data' => array_merge(
                [
                    '_BLOCKSTUDIO_EDITOR_STRING' => $content,
                ],
                $example
            ),
        ]);
    }

    /**
     * Test code.
     *
     * @date   03/03/2022
     * @since  2.3.0
     *
     * @param  $snippet
     *
     * @return bool
     */
    function testCode($snippet): bool
    {
        if (empty($snippet)) {
            return false;
        }

        ob_start();
        $result = @eval(' ?>' . urldecode($snippet) . '<?php ');
        ob_end_clean();

        return false === $result;
    }

    /**
     * Create zip.
     *
     * @date   12/08/2023
     * @since  5.2.0
     *
     * @param  $files
     * @param  $destination
     * @param  $rootPath
     *
     * @return bool
     */
    function createZip($files, $destination, $rootPath): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($destination, ZIPARCHIVE::CREATE) !== true) {
            return false;
        }

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = is_object($name)
                    ? $name->getBasename()
                    : substr($filePath, strlen($rootPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    }
}

new Rest();

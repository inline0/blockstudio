<?php

namespace Blockstudio;

use BlockstudioPlugin;
use stdClass;

/**
 * Admin class used for editor-related functions.
 *
 * @date   05/03/2022
 * @since  2.3.0
 */
class Admin
{
    /**
     * Construct.
     *
     * @date   05/03/2022
     * @since  2.3.0
     */
    function __construct()
    {
        add_action('admin_head', function () {
            echo '<style>.toplevel_page_blockstudio .wp-menu-image.svg { height: 34px !important; }</style>';
        });

        add_action('admin_head', function () {
            $screen = get_current_screen();

            if ($screen->id !== 'toplevel_page_blockstudio') {
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

                <?php if (isset($_GET['block'])): ?>
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
        });

        add_action('admin_menu', function () {
            $iconBase64 =
                'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMjAiIGhlaWdodD0iMzIwIiB2aWV3Qm94PSIwIDAgMzIwIDMyMCI+PGcgZmlsbD0iI0E3QUFBRCIgZmlsbC1ydWxlPSJldmVub2RkIj48cGF0aCBkPSJNMTYwLDAgQzI4OC4wMTI1OTMsMCAzMjAsMzEuOTg3NDA2NSAzMjAsMTYwIEMzMjAsMjg4LjAxMjU5MyAyODguMDEyNTkzLDMyMCAxNjAsMzIwIEMzMS45ODc0MDY1LDMyMCAwLDI4OC4wMTI1OTMgMCwxNjAgQzAsMzEuOTg3NDA2NSAzMS45ODc0MDY1LDAgMTYwLDAgWiBNNTQuODE4Mzg1MywxMzIuNDQ4NjcxIEMzMi4zNzYzNjAzLDIxNi4yMDM0NDggNDcuNjk3MDA4NywyNDIuNzM5NTkgMTMxLjQ1MTc4NiwyNjUuMTgxNjE1IEMyMTUuMjA2NTY0LDI4Ny42MjM2NCAyNDEuNzQyNzA1LDI3Mi4zMDI5OTEgMjY0LjE4NDczLDE4OC41NDgyMTQgQzI4Ni42MjY3NTUsMTA0Ljc5MzQzNiAyNzEuMzA2MTA3LDc4LjI1NzI5NSAxODcuNTUxMzI5LDU1LjgxNTI3IEMxMDMuNzk2NTUyLDMzLjM3MzI0NTEgNzcuMjYwNDEwMyw0OC42OTM4OTM1IDU0LjgxODM4NTMsMTMyLjQ0ODY3MSBaIi8+PHBhdGggZD0iTTE1OS41MDE1NTgsMTA2LjMxMDQ0NSBDMTE2LjE0Njg5NSwxMDYuMzEwNDQ1IDEwNS4zMTM1NiwxMTcuMTQzNzc5IDEwNS4zMTM1NiwxNjAuNDk4NDQyIEMxMDUuMzEzNTYsMjAzLjg1MzEwNSAxMTYuMTQ2ODk1LDIxNC42ODY0NCAxNTkuNTAxNTU4LDIxNC42ODY0NCBDMjAyLjg1NjIyMSwyMTQuNjg2NDQgMjEzLjY4OTU1NSwyMDMuODUzMTA1IDIxMy42ODk1NTUsMTYwLjQ5ODQ0MiBDMjEzLjY4OTU1NSwxMTcuMTQzNzc5IDIwMi44NTYyMjEsMTA2LjMxMDQ0NSAxNTkuNTAxNTU4LDEwNi4zMTA0NDUgWiIgdHJhbnNmb3JtPSJyb3RhdGUoLTQ1IDE1OS41MDIgMTYwLjQ5OCkiLz48L2c+PC9zdmc+';
            $iconDataUri = 'data:image/svg+xml;base64,' . $iconBase64;

            $hookSuffix = add_menu_page(
                'Blockstudio',
                'Blockstudio',
                'manage_options',
                'blockstudio',
                [__CLASS__, 'page'],
                $iconDataUri,
                99999
            );

            add_action("load-{$hookSuffix}", [__CLASS__, 'getAllAssets']);
        });
        add_action('wp_footer', [__CLASS__, 'captureFrontendAssets']);
    }

    /**
     * Assets needed for the editor to function.
     *
     * @date   05/03/2022
     * @since  2.3.0
     */
    public static function assets()
    {
        foreach (
            [
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
            ]
            as $handle
        ) {
            wp_enqueue_style($handle);
        }
    }

    /**
     * Capture frontend assets.
     *
     * @date   27/09/2023
     * @since  5.2.8
     */
    public static function captureFrontendAssets()
    {
        $specialId = isset($_GET['blockstudio_editor_capture_assets_id'])
            ? sanitize_key($_GET['blockstudio_editor_capture_assets_id'])
            : '';

        $expectedId = strtolower(
            get_transient('blockstudio_editor_expected_capture_assets_id')
        );

        if (empty($specialId) || $specialId !== $expectedId) {
            return;
        }

        global $wp_scripts, $wp_styles;

        $frontendScripts = [];
        $frontendStyles = [];

        self::getAssetsData(
            $frontendScripts,
            $frontendStyles,
            $wp_scripts,
            $wp_styles,
            'frontend'
        );

        set_transient(
            'blockstudio_editor_captured_frontend_scripts',
            $frontendScripts,
            HOUR_IN_SECONDS
        );
        set_transient(
            'blockstudio_editor_captured_frontend_styles',
            $frontendStyles,
            HOUR_IN_SECONDS
        );
    }

    /**
     * Check if a user is allowed for editor.
     *
     * @date   29/05/2023
     * @since  4.3.0
     * @return bool
     */
    public static function isAllowed(): bool
    {
        $user = wp_get_current_user();
        $ids = Settings::get('users/ids');
        $roles = Settings::get('users/roles');

        return in_array($user->ID, is_array($ids) ? $ids : [$ids]) ||
            count(
                array_intersect(
                    $user->roles,
                    is_array($roles) ? $roles : [$roles]
                ) ?? []
            ) > 0;
    }

    /**
     * Ensure assets have a full url, as some scripts and styles in WordPress use a relative path.
     *
     * @date   23/08/2023
     * @since  5.2.0
     *
     * @param  $src
     *
     * @return string
     */
    public static function ensureFullUrl($src): string
    {
        if (strpos($src, '/') === 0) {
            return site_url() . $src;
        }

        return $src;
    }

    /**
     * Get all enqueued assets from editor, admin, and frontend context.
     *
     * @date   22/08/2023
     * @since  5.2.0
     *
     * @return array
     */
    public static function getAllAssets(): array
    {
        $cachedAssets = get_transient('blockstudio_editor_all_assets');
        if ($cachedAssets !== false) {
            return $cachedAssets;
        }

        global $wp_scripts, $wp_styles;

        $originalWpScripts = clone $wp_scripts;
        $originalWpStyles = clone $wp_styles;

        $contexts = ['block_editor', 'admin'];
        $finalScripts = [];
        $finalStyles = [];

        $capture_id = wp_generate_password(20, false);
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

        foreach ($contexts as $context) {
            $wp_scripts->queue = [];
            $wp_styles->queue = [];

            switch ($context) {
                case 'block_editor':
                    do_action('enqueue_block_editor_assets');
                    break;
                case 'admin':
                    do_action('admin_enqueue_scripts');
                    break;
            }

            self::getAssetsData(
                $finalScripts,
                $finalStyles,
                $wp_scripts,
                $wp_styles,
                $context
            );
        }

        $frontendScripts = get_transient(
            'blockstudio_editor_captured_frontend_scripts'
        );
        $frontendStyles = get_transient(
            'blockstudio_editor_captured_frontend_styles'
        );

        if ($frontendScripts) {
            foreach ($frontendScripts as $handle => $script) {
                $finalScripts[$handle] = $script;
            }
        }

        if ($frontendStyles) {
            foreach ($frontendStyles as $handle => $style) {
                $finalStyles[$handle] = $style;
            }
        }

        $wp_scripts = $originalWpScripts;
        $wp_styles = $originalWpStyles;

        $allAssets = [
            'scripts' => $finalScripts,
            'styles' => $finalStyles,
        ];

        set_transient(
            'blockstudio_editor_all_assets',
            $allAssets,
            HOUR_IN_SECONDS
        );

        return $allAssets;
    }

    /**
     * Get assets data.
     *
     * @date   30/09/2023
     * @since  5.2.8
     *
     * @param  $finalScripts
     * @param  $finalStyles
     * @param  $wpScripts
     * @param  $wpStyles
     * @param  $context
     */
    public static function getAssetsData(
        &$finalScripts,
        &$finalStyles,
        $wpScripts,
        $wpStyles,
        $context
    ) {
        foreach ($wpScripts->registered as $handle => $script) {
            $srcData = [];
            if (is_string($script->src)) {
                $srcData['src'] = self::ensureFullUrl($script->src);
            }
            if (isset($script->extra['after'])) {
                $srcData['inline'] = implode('', $script->extra['after']);
            }
            if (!empty($srcData)) {
                $finalScripts[$handle] = array_merge($srcData, [
                    'deps' => $script->deps,
                    'context' => $context,
                    'type' => 'script',
                ]);
            }
        }

        foreach ($wpStyles->registered as $handle => $style) {
            $srcData = [];
            if (is_string($style->src)) {
                $srcData['src'] = self::ensureFullUrl($style->src);
            }
            if (isset($style->extra['after'])) {
                $srcData['inline'] = implode('', $style->extra['after']);
            }
            if (!empty($srcData)) {
                $finalStyles[$handle] = array_merge($srcData, [
                    'deps' => $style->deps,
                    'context' => $context,
                    'type' => 'style',
                ]);
            }
        }
    }

    /**
     * Get admin data.
     *
     * @date   12/05/2024
     * @since  5.6.0
     */
    public static function data($editor = true): array
    {
        global $wp_roles;
        $post = get_post();

        $imageSizes = get_intermediate_image_sizes();

        $editorOnly = [];
        $editorOnlyData = [
            'imageSizes' => $imageSizes,
        ];

        if ($editor) {
            $data = Build::data();
            $dataSorted = Build::dataSorted();
            $editorMarkup = Settings::get('editor/markup');
            $files = Build::files();
            $functions = get_defined_functions();
            $overrides = Build::overrides();
            $paths = Build::paths();
            $templates = Files::getFolderStructureWithContents(
                BLOCKSTUDIO_DIR . '/includes-v7/templates'
            );
            $allAssets = self::getAllAssets();
            $scripts = $allAssets['scripts'];
            $styles = $allAssets['styles'];

            $editorOnly = [
                'optionsFilters' => Settings::getFilters(),
                'optionsFiltersValues' => Settings::getFiltersValues(),
                'optionsJson' => Settings::getJson(),
                'optionsOptions' => Settings::getOptions(),
                'optionsRoles' => array_keys($wp_roles->roles),
                'optionsSchema' => json_decode(
                    file_get_contents(
                        BLOCKSTUDIO_DIR . '/includes-v7/schemas/blockstudio.json'
                    ),
                    true
                ),
                'optionsUsers' => Settings::get('users/ids')
                    ? array_reduce(
                        get_users(['include' => Settings::get('users/ids')]),
                        function ($carry, $user) {
                            $carry[] = $user->data;

                            return $carry;
                        },
                        []
                    )
                    : [],
                'plugin' => BlockstudioPlugin::getData(),
                'pluginVersion' => get_plugin_data(BLOCKSTUDIO)['Version'],
                'plugins' => get_plugins(),
                'pluginsPath' => plugin_dir_path(BLOCKSTUDIO_DIR),
                'settings' => get_user_meta(
                    get_current_user_id(),
                    'blockstudio_settings'
                )
                    ? json_encode(
                        get_user_meta(
                            get_current_user_id(),
                            'blockstudio_settings',
                            true
                        ) ?:
                        new stdClass()
                    )
                    : new stdClass(),
            ];

            $editorOnlyData = [
                'blocks' => $data,
                'blocksSorted' => $dataSorted,
                'editorMarkup' => $editorMarkup,
                'files' => $files,
                'functions' => $functions,
                'overrides' => $overrides,
                'paths' => $paths,
                'scripts' => $scripts,
                'styles' => $styles,
                'templates' => $templates,
            ];
        }

        $blocks = Build::blocks();
        $extensions = Build::extensions();

        return array_merge(
            [
                'ajax' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'allowEditor' => self::isAllowed() ? 'true' : 'false',
                'canEdit' => Admin::isAllowed() ? 'true' : 'false',
                'data' => array_merge(
                    [
                        'blocksNative' => $blocks,
                        'extensions' => $extensions,
                    ],
                    $editorOnlyData
                ),
                'isTailwindActive' => Build::isTailwindActive()
                    ? 'true'
                    : 'false',
                'llmTxtUrl' => LLM::getTxtUrl(),
                'loader' => plugin_dir_url(BLOCKSTUDIO) . 'includes-v7/editor/vs',
                'logo' => plugins_url(
                    'includes-v7/admin/assets/fabrikatLogo.svg',
                    __FILE__
                ),
                'nonce' => wp_create_nonce('ajax-nonce'),
                'nonceRest' => wp_create_nonce('wp_rest'),
                'options' => Settings::getAll(),
                'postId' => $post->ID ?? (get_the_ID() ?? null),
                'postType' => $post->post_type ?? null,
                'rest' => esc_url_raw(rest_url()),
                'site' => site_url(),
                'tailwindUrl' => Tailwind::get_cdn_url(),
                'userId' => get_current_user_id() ?? null,
                'userRole' => wp_get_current_user()->roles[0] ?? null,
            ],
            $editorOnly
        );
    }

    /**
     * Render the admin page with all necessary data for the editor to function.
     *
     * @date   05/03/2022
     * @since  2.3.0
     */
    public static function page()
    {
        $editorScripts = include BLOCKSTUDIO_DIR .
            '/includes-v7/admin/assets/admin/index.tsx.asset.php';
        wp_enqueue_script(
            'blockstudio-admin',
            plugins_url(
                'includes-v7/admin/assets/admin/index.tsx.js',
                BLOCKSTUDIO
            ),
            $editorScripts['dependencies'],
            $editorScripts['version']
        );
        wp_localize_script(
            'blockstudio-admin',
            'blockstudioAdmin',
            self::data()
        );

        wp_enqueue_style('wp-components');

        $allAssets = self::getAllAssets();
        $blocks = Build::blocks();
        $data = Build::data();
        $dataSorted = Build::dataSorted();
        $extensions = Build::extensions();
        $files = Build::files();
        $overrides = Build::overrides();
        $paths = Build::paths();
        $scripts = $allAssets['scripts'];
        $styles = $allAssets['styles'];
        $templates = Files::getFolderStructureWithContents(
            BLOCKSTUDIO_DIR . '/includes-v7/templates'
        );
        ?>
        <script>
            console.log('data: ', <?php echo json_encode($data); ?>);
            console.log('dataSorted: ', <?php echo json_encode(
                $dataSorted
            ); ?>);
            console.log('extends: ', <?php echo json_encode($extensions); ?>);
            console.log('files: ', <?php echo json_encode($files); ?>);
            console.log('native: ', <?php echo json_encode($blocks); ?>);
            console.log('overrides: ', <?php echo json_encode($overrides); ?>);
            console.log('paths:', <?php echo json_encode($paths); ?>);
            console.log('scripts: ', <?php echo json_encode($scripts); ?>);
            console.log('styles: ', <?php echo json_encode($styles); ?>);
            console.log('templates: ', <?php echo json_encode($templates); ?>);
        </script>
        <div id="blockstudio">
        </div>
		<?php
    }
}

new Admin();

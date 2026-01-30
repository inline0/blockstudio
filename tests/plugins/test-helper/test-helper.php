<?php
/**
 * Plugin Name: Blockstudio Test Helper
 * Description: Exposes REST endpoints for unit testing PHP functions and configures test blocks
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple frontend endpoint for E2E test setup.
 * Usage: ?blockstudio_e2e_setup=1
 * Returns JSON with test data and exits early.
 */
add_action('template_redirect', function () {
    if (!isset($_GET['blockstudio_e2e_setup'])) {
        return;
    }

    // Create a test post for E2E tests
    $post_id = wp_insert_post([
        'post_title' => 'E2E Test Post ' . time(),
        'post_content' => '',
        'post_status' => 'publish',
        'post_type' => 'post',
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'post_id' => $post_id,
        'message' => 'E2E test post created',
    ]);
    exit;
}, 1);

/**
 * Configure Blockstudio to find test blocks in the theme directory.
 * The test blocks are copied to /wp-content/themes/{active-theme}/blockstudio/
 * This filter tells Blockstudio where to look for blocks.
 */
add_filter('blockstudio/settings/blocks/paths', function ($paths) {
    // Add the theme directory path where test blocks will be placed
    $theme_blockstudio_path = get_stylesheet_directory() . '/blockstudio';

    if (is_dir($theme_blockstudio_path)) {
        $paths[] = $theme_blockstudio_path;
    }

    return $paths;
}, 10, 1);

/**
 * Provide editor assets for classes autocomplete.
 * This enables the wp-block-library-theme CSS which contains "is-large" etc.
 */
add_filter('blockstudio/editor/assets', function () {
    return [
        'blockstudio-editor-test',
        'wp-block-library-theme',
        'this-does-not-exist',
    ];
});

/**
 * Provide editor markup.
 */
add_filter('blockstudio/settings/editor/markup', function () {
    return '<style>body { font-family: sans-serif; }</style>';
});

/**
 * Provide test conditions.
 */
add_filter('blockstudio/blocks/conditions', function () {
    return ['test' => true];
});

/**
 * Provide populate data for select fields, colors, and gradients.
 * These match the fabrikat theme configuration.
 */
add_filter('blockstudio/blocks/attributes/populate', function ($options) {
    // Default populate options
    $options['default'] = [
        ['value' => 'custom-1', 'label' => 'Custom 1'],
        ['value' => 'custom-2', 'label' => 'Custom 2'],
        ['value' => 'custom-3', 'label' => 'Custom 3'],
    ];

    // Array populate options
    $options['array'] = ['custom-1', 'custom-2', 'custom-3'];

    // Colors
    $options['colors'] = [
        ['name' => 'red', 'value' => '#ff0000', 'slug' => 'red'],
        ['name' => 'green', 'value' => '#00ff00', 'slug' => 'green'],
        ['name' => 'blue', 'value' => '#0000ff', 'slug' => 'blue'],
    ];

    // Gradients
    $options['gradients'] = [
        ['name' => 'JShine', 'value' => 'linear-gradient(135deg,#12c2e9 0%,#c471ed 50%,#f64f59 100%)', 'slug' => 'jshine'],
        ['name' => 'Moonlit Asteroid', 'value' => 'linear-gradient(135deg,#0F2027 0%, #203A43 0%, #2c5364 100%)', 'slug' => 'moonlit-asteroid'],
        ['name' => 'Rastafarie', 'value' => 'linear-gradient(135deg,#1E9600 0%, #FFF200 0%, #FF0000 100%)', 'slug' => 'rastafari'],
    ];

    return $options;
});

/**
 * Filter block attributes for lineNumbers.
 */
add_filter('blockstudio/blocks/attributes', function ($attribute, $block) {
    if (isset($attribute['id']) && $attribute['id'] === 'lineNumbers') {
        $attribute['default'] = true;
        $attribute['conditions'] = [
            [
                [
                    'id' => 'language',
                    'operator' => '==',
                    'value' => 'css',
                ],
            ],
        ];
    }
    return $attribute;
}, 10, 2);

/**
 * Filter block attributes render for copyButton.
 */
add_filter('blockstudio/blocks/attributes/render', function ($value, $key, $block) {
    if ($key === 'copyButton' && ($block['name'] ?? false) === 'blockstudio-element/code') {
        $value = true;
    }
    return $value;
}, 10, 3);

/**
 * Add SCSS import paths.
 */
add_filter('blockstudio/assets/process/scss/importPaths', function ($paths) {
    $paths[] = get_template_directory() . '/misc/';
    return $paths;
});

/**
 * Force block discovery after all plugins are loaded.
 */
add_action('plugins_loaded', function () {
    // Trigger block discovery if Blockstudio is available
    if (class_exists('Blockstudio\\Build')) {
        // Blocks should auto-discover, but we can force a refresh if needed
    }
}, 100);

add_action('rest_api_init', function () {
    // ==========================================================================
    // SNAPSHOT ENDPOINT - Get ALL Build class data at once
    // Supports both v6 (camelCase) and v7 (snake_case) method names
    // ==========================================================================
    register_rest_route('blockstudio-test/v1', '/snapshot', [
        'methods' => 'GET',
        'callback' => function () {
            try {
                if (!class_exists('Blockstudio\\Build')) {
                    return new WP_Error('not_loaded', 'Blockstudio Build class not loaded', ['status' => 500]);
                }

                // Detect v6 vs v7 by checking method names
                $is_v7 = method_exists('Blockstudio\\Build', 'assets_admin');

                if ($is_v7) {
                    return [
                        'blocks' => \Blockstudio\Build::blocks(),
                        'data' => \Blockstudio\Build::data(),
                        'extensions' => \Blockstudio\Build::extensions(),
                        'files' => \Blockstudio\Build::files(),
                        'assetsAdmin' => \Blockstudio\Build::assets_admin(),
                        'assetsBlockEditor' => \Blockstudio\Build::assets_block_editor(),
                        'assetsGlobal' => \Blockstudio\Build::assets_global(),
                        'paths' => \Blockstudio\Build::paths(),
                        'overrides' => \Blockstudio\Build::overrides(),
                        'assets' => \Blockstudio\Build::assets(),
                        'blade' => \Blockstudio\Build::blade(),
                    ];
                } else {
                    // v6 uses camelCase
                    return [
                        'blocks' => \Blockstudio\Build::blocks(),
                        'data' => \Blockstudio\Build::data(),
                        'extensions' => \Blockstudio\Build::extensions(),
                        'files' => \Blockstudio\Build::files(),
                        'assetsAdmin' => \Blockstudio\Build::assetsAdmin(),
                        'assetsBlockEditor' => \Blockstudio\Build::assetsBlockEditor(),
                        'assetsGlobal' => \Blockstudio\Build::assetsGlobal(),
                        'paths' => \Blockstudio\Build::paths(),
                        'overrides' => \Blockstudio\Build::overrides(),
                        'assets' => \Blockstudio\Build::assets(),
                        'blade' => \Blockstudio\Build::blade(),
                    ];
                }
            } catch (\Throwable $e) {
                return new WP_Error('php_error', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), ['status' => 500]);
            }
        },
        'permission_callback' => '__return_true',
    ]);

    // Get all blocks from Build class
    register_rest_route('blockstudio-test/v1', '/build/all', [
        'methods' => 'GET',
        'callback' => function () {
            if (!class_exists('Blockstudio\\Build')) {
                return new WP_Error('not_loaded', 'Blockstudio Build class not loaded', ['status' => 500]);
            }
            return \Blockstudio\Build::blocks();
        },
        'permission_callback' => '__return_true',
    ]);

    // Get Build class data for a specific block by name
    register_rest_route('blockstudio-test/v1', '/build/(?P<block>.+)', [
        'methods' => 'GET',
        'callback' => function ($request) {
            if (!class_exists('Blockstudio\\Build')) {
                return new WP_Error('not_loaded', 'Blockstudio Build class not loaded', ['status' => 500]);
            }

            $blockName = $request->get_param('block');
            $blocks = \Blockstudio\Build::getBlocks();

            // Find matching block by name
            foreach ($blocks as $path => $data) {
                if (($data['name'] ?? '') === $blockName) {
                    return [
                        'path' => $path,
                        'name' => $data['name'] ?? null,
                        'title' => $data['title'] ?? null,
                        'category' => $data['category'] ?? null,
                        'fields' => $data['fields'] ?? [],
                        'attributes' => $data['attributes'] ?? [],
                        'assets' => $data['assets'] ?? [],
                        'meta' => $data['meta'] ?? [],
                        'render' => $data['render'] ?? null,
                        'supports' => $data['supports'] ?? [],
                    ];
                }
            }

            return new WP_Error('not_found', "Block '{$blockName}' not found", ['status' => 404]);
        },
        'permission_callback' => '__return_true',
    ]);

    // Get all registered Blockstudio blocks from WP_Block_Type_Registry
    register_rest_route('blockstudio-test/v1', '/registered', [
        'methods' => 'GET',
        'callback' => function () {
            $registry = WP_Block_Type_Registry::get_instance();
            $all = $registry->get_all_registered();
            $blocks = [];

            foreach ($all as $name => $block) {
                if (str_starts_with($name, 'blockstudio/')) {
                    $blocks[$name] = [
                        'attributes' => $block->attributes ?? [],
                        'supports' => $block->supports ?? [],
                        'category' => $block->category ?? null,
                        'title' => $block->title ?? null,
                    ];
                }
            }

            return $blocks;
        },
        'permission_callback' => '__return_true',
    ]);

    // Render a block
    register_rest_route('blockstudio-test/v1', '/render', [
        'methods' => 'POST',
        'callback' => function ($request) {
            $blockName = $request->get_param('blockName');
            $attrs = $request->get_param('attrs') ?? [];
            $innerHTML = $request->get_param('innerHTML') ?? '';

            $html = render_block([
                'blockName' => $blockName,
                'attrs' => $attrs,
                'innerHTML' => $innerHTML,
                'innerBlocks' => [],
            ]);

            return [
                'html' => $html,
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    // Get assets for a block (CSS/JS files)
    register_rest_route('blockstudio-test/v1', '/assets/(?P<block>.+)', [
        'methods' => 'GET',
        'callback' => function ($request) {
            if (!class_exists('Blockstudio\\Build')) {
                return new WP_Error('not_loaded', 'Blockstudio Build class not loaded', ['status' => 500]);
            }

            $blockName = $request->get_param('block');
            $blocks = \Blockstudio\Build::getBlocks();

            foreach ($blocks as $path => $data) {
                if (($data['name'] ?? '') === $blockName) {
                    return [
                        'assets' => $data['assets'] ?? [],
                        'path' => $path,
                    ];
                }
            }

            return new WP_Error('not_found', "Block '{$blockName}' not found", ['status' => 404]);
        },
        'permission_callback' => '__return_true',
    ]);

    // Health check endpoint
    register_rest_route('blockstudio-test/v1', '/health', [
        'methods' => 'GET',
        'callback' => function () {
            return [
                'status' => 'ok',
                'blockstudio_loaded' => class_exists('Blockstudio\\Build'),
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo('version'),
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    // ==========================================================================
    // COMPILED ASSETS ENDPOINT - Get actual content of compiled _dist files
    // ==========================================================================
    register_rest_route('blockstudio-test/v1', '/compiled-assets', [
        'methods' => 'GET',
        'callback' => function () {
            $theme_blockstudio_path = get_stylesheet_directory() . '/blockstudio';
            $compiled = [];

            if (!is_dir($theme_blockstudio_path)) {
                return new WP_Error('not_found', 'Blockstudio theme directory not found', ['status' => 404]);
            }

            // Find all _dist directories recursively
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($theme_blockstudio_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $path = $file->getPathname();
                    $relativePath = str_replace($theme_blockstudio_path . '/', '', $path);

                    // Only include files in _dist directories
                    if (strpos($relativePath, '_dist/') !== false) {
                        $extension = $file->getExtension();

                        // Only include CSS and JS files
                        if (in_array($extension, ['css', 'js'])) {
                            $content = file_get_contents($path);

                            // Normalize the key by removing hashes/timestamps from filename
                            // style-8c61297c7ad6a7f39af80a70d8992118.css -> style.css
                            // script-1746475334.js -> script.js
                            $normalizedFilename = preg_replace('/-[a-f0-9]{32}\./', '.', $file->getBasename());
                            $normalizedFilename = preg_replace('/-\d{10,}\./', '.', $normalizedFilename);

                            $normalizedKey = dirname($relativePath) . '/' . $normalizedFilename;

                            $compiled[$normalizedKey] = [
                                'content' => $content,
                                'size' => strlen($content),
                                'extension' => $extension,
                            ];
                        }
                    }
                }
            }

            ksort($compiled);
            return $compiled;
        },
        'permission_callback' => '__return_true',
    ]);

    // ==========================================================================
    // E2E TEST DATA SETUP - Create ALL dummy data needed for E2E tests
    // Posts: 1386 (Native), 1388 (Native Render)
    // Media: 8 (video), 1604 (image), 1605 (image)
    // User: 644, Term: 6
    // ==========================================================================
    register_rest_route('blockstudio-test/v1', '/e2e/setup', [
        'methods' => 'POST',
        'callback' => function () {
            global $wpdb;

            // ==========================================
            // CLEANUP: Delete all existing posts first
            // ==========================================
            $all_posts = get_posts([
                'post_type' => ['post', 'page', 'attachment', 'wp_block'],
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
            ]);
            foreach ($all_posts as $post_id) {
                wp_delete_post($post_id, true);
            }

            $created = [
                'posts' => [],
                'media' => [],
                'users' => [],
                'terms' => [],
            ];

            // Helper to create post with specific ID
            $create_post_with_id = function ($id, $title, $name, $type = 'post') use ($wpdb, &$created) {
                if (!get_post($id)) {
                    $wpdb->insert($wpdb->posts, [
                        'ID' => $id,
                        'post_author' => 1,
                        'post_date' => '2022-07-09 06:36:30',
                        'post_date_gmt' => '2022-07-09 06:36:30',
                        'post_content' => '',
                        'post_title' => $title,
                        'post_excerpt' => '',
                        'post_status' => 'publish',
                        'comment_status' => 'closed',
                        'ping_status' => 'closed',
                        'post_password' => '',
                        'post_name' => $name,
                        'post_modified' => '2023-06-17 13:57:45',
                        'post_modified_gmt' => '2023-06-17 13:57:45',
                        'post_type' => $type,
                        'guid' => home_url("/?p=$id"),
                    ]);
                    $created['posts'][] = $id;
                    return true;
                }
                return false;
            };

            // Helper to create dummy image file
            $create_dummy_image = function ($filename, $width = 100, $height = 100) {
                $upload_dir = wp_upload_dir();
                $file_path = $upload_dir['path'] . '/' . $filename;

                // Create directory if needed
                if (!file_exists($upload_dir['path'])) {
                    wp_mkdir_p($upload_dir['path']);
                }

                // Create a simple PNG image
                $image = imagecreatetruecolor($width, $height);
                $bg_color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
                imagefill($image, 0, 0, $bg_color);
                imagepng($image, $file_path);
                imagedestroy($image);

                return [
                    'path' => $file_path,
                    'url' => $upload_dir['url'] . '/' . $filename,
                    'subdir' => $upload_dir['subdir'],
                ];
            };

            // Helper to create dummy video file (just a small binary file)
            $create_dummy_video = function ($filename) {
                $upload_dir = wp_upload_dir();
                $file_path = $upload_dir['path'] . '/' . $filename;

                if (!file_exists($upload_dir['path'])) {
                    wp_mkdir_p($upload_dir['path']);
                }

                // Create minimal MP4 file header (not a real video, but enough for testing)
                file_put_contents($file_path, str_repeat("\x00", 1000));

                return [
                    'path' => $file_path,
                    'url' => $upload_dir['url'] . '/' . $filename,
                    'subdir' => $upload_dir['subdir'],
                ];
            };

            // Helper to create attachment with specific ID
            $create_attachment_with_id = function ($id, $filename, $mime_type, $file_info) use ($wpdb, &$created) {
                if (!get_post($id)) {
                    $wpdb->insert($wpdb->posts, [
                        'ID' => $id,
                        'post_author' => 1,
                        'post_date' => '2022-12-01 00:00:00',
                        'post_date_gmt' => '2022-12-01 00:00:00',
                        'post_content' => '',
                        'post_title' => pathinfo($filename, PATHINFO_FILENAME),
                        'post_excerpt' => '',
                        'post_status' => 'inherit',
                        'comment_status' => 'open',
                        'ping_status' => 'closed',
                        'post_password' => '',
                        'post_name' => sanitize_title(pathinfo($filename, PATHINFO_FILENAME)),
                        'post_modified' => '2022-12-01 00:00:00',
                        'post_modified_gmt' => '2022-12-01 00:00:00',
                        'post_type' => 'attachment',
                        'post_mime_type' => $mime_type,
                        'guid' => $file_info['url'],
                    ]);

                    // Set _wp_attached_file meta
                    update_post_meta($id, '_wp_attached_file', ltrim($file_info['subdir'], '/') . '/' . $filename);

                    // Set attachment metadata for images
                    if (strpos($mime_type, 'image') !== false) {
                        $metadata = [
                            'width' => 100,
                            'height' => 100,
                            'file' => ltrim($file_info['subdir'], '/') . '/' . $filename,
                            'sizes' => [
                                'thumbnail' => [
                                    'file' => $filename,
                                    'width' => 100,
                                    'height' => 100,
                                    'mime-type' => $mime_type,
                                ],
                            ],
                        ];
                        update_post_meta($id, '_wp_attachment_metadata', $metadata);
                    }

                    $created['media'][] = $id;
                    return true;
                }
                return false;
            };

            // ==========================================
            // CREATE POSTS
            // ==========================================

            // Post 1386 - "Native" (used in text.ts for populate)
            $create_post_with_id(1386, 'Native', 'native');

            // Post 1388 - "Native Render" (used in select-fetch and other tests)
            $create_post_with_id(1388, 'Native Render', 'native-render');

            // Post 1483 - Used in text.ts for navigation
            $create_post_with_id(1483, 'Nav Post', 'nav-post');

            // Post 560 - Used in select/fetch.ts test (searchable as "test")
            $create_post_with_id(560, 'Test', 'test-select-fetch');

            // Post 533 - Used in select/fetch.ts test
            $create_post_with_id(533, 'Et adipisci quia aut', 'et-adipisci-quia-aut');

            // Additional posts for select/fetch.ts (searching for "e" should return 9 results)
            // Posts: Native(1386), Native Render(1388), Test(560), Et adipisci(533) = 4 posts with "e"
            // Need 5 more to get 9 total
            $create_post_with_id(534, 'Example One', 'example-one');
            $create_post_with_id(535, 'Reference', 'reference');
            $create_post_with_id(536, 'Guide', 'guide');
            $create_post_with_id(537, 'Release', 'release');
            $create_post_with_id(538, 'Feature', 'feature');

            // Create "Reusable" post for text.ts populate tests
            $reusable_id = wp_insert_post([
                'post_title' => 'Reusable',
                'post_name' => 'reusable',
                'post_content' => '',
                'post_status' => 'publish',
            ]);
            if ($reusable_id && !is_wp_error($reusable_id)) {
                $created['posts'][] = $reusable_id;
            }

            // ==========================================
            // CREATE REUSABLE BLOCK PATTERNS (wp_block)
            // ==========================================
            // These are required for reusable.ts test

            // Pattern 2643 - contains type-text block
            $pattern_2643_content = '<!-- wp:blockstudio/type-text /--><!-- wp:blockstudio/type-textarea /-->';
            $existing_2643 = get_post(2643);
            if (!$existing_2643 || $existing_2643->post_type !== 'wp_block') {
                $wpdb->delete($wpdb->posts, ['ID' => 2643]);
                $wpdb->insert($wpdb->posts, [
                    'ID' => 2643,
                    'post_author' => 1,
                    'post_date' => current_time('mysql'),
                    'post_date_gmt' => current_time('mysql', 1),
                    'post_content' => $pattern_2643_content,
                    'post_title' => 'Test Pattern 1',
                    'post_status' => 'publish',
                    'post_name' => 'test-pattern-1',
                    'post_type' => 'wp_block',
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', 1),
                ]);
                $created['patterns'][] = 2643;
            }

            // Pattern 2644 - contains type-text block
            $pattern_2644_content = '<!-- wp:blockstudio/type-text /--><!-- wp:blockstudio/type-textarea /-->';
            $existing_2644 = get_post(2644);
            if (!$existing_2644 || $existing_2644->post_type !== 'wp_block') {
                $wpdb->delete($wpdb->posts, ['ID' => 2644]);
                $wpdb->insert($wpdb->posts, [
                    'ID' => 2644,
                    'post_author' => 1,
                    'post_date' => current_time('mysql'),
                    'post_date_gmt' => current_time('mysql', 1),
                    'post_content' => $pattern_2644_content,
                    'post_title' => 'Test Pattern 2',
                    'post_status' => 'publish',
                    'post_name' => 'test-pattern-2',
                    'post_type' => 'wp_block',
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', 1),
                ]);
                $created['patterns'][] = 2644;
            }

            // Note: Removed "Sample Post" loop - those posts interfered with select-fetch test
            // which expects exactly 9 results when searching for "e"

            // ==========================================
            // CREATE MEDIA ATTACHMENTS
            // ==========================================

            // Attachment 8 - gutenbergEdit.mp4 (video)
            $video_file = $create_dummy_video('gutenbergEdit.mp4');
            $create_attachment_with_id(8, 'gutenbergEdit.mp4', 'video/mp4', $video_file);

            // Attachment 1604 - blockstudioEDDRetina.png (image)
            $image1_file = $create_dummy_image('blockstudioEDDRetina.png', 200, 200);
            $create_attachment_with_id(1604, 'blockstudioEDDRetina.png', 'image/png', $image1_file);

            // Attachment 1605 - blockstudioSEO.png (image)
            $image2_file = $create_dummy_image('blockstudioSEO.png', 200, 200);
            $create_attachment_with_id(1605, 'blockstudioSEO.png', 'image/png', $image2_file);

            // ==========================================
            // CREATE USER
            // ==========================================

            $user_id = 644;
            if (!get_user_by('id', $user_id)) {
                $wpdb->insert($wpdb->users, [
                    'ID' => $user_id,
                    'user_login' => 'testuser644',
                    'user_pass' => wp_hash_password('testpass'),
                    'user_nicename' => 'testuser644',
                    'user_email' => 'testuser644@example.com',
                    'user_registered' => current_time('mysql'),
                    'display_name' => 'Test User 644',
                ]);
                $created['users'][] = $user_id;
            }

            // ==========================================
            // CREATE TERMS
            // ==========================================

            $term_id = 6;
            $existing_term = $wpdb->get_var($wpdb->prepare(
                "SELECT term_id FROM $wpdb->terms WHERE term_id = %d",
                $term_id
            ));

            if (!$existing_term) {
                $wpdb->insert($wpdb->terms, [
                    'term_id' => $term_id,
                    'name' => 'Test Category 6',
                    'slug' => 'test-category-6',
                ]);

                $wpdb->insert($wpdb->term_taxonomy, [
                    'term_id' => $term_id,
                    'taxonomy' => 'category',
                    'description' => 'Test category for E2E tests',
                    'count' => 0,
                ]);
                $created['terms'][] = $term_id;
            }

            // Create additional categories and tags
            $cat = wp_insert_term('Test Category', 'category');
            if (!is_wp_error($cat)) {
                $created['terms'][] = $cat['term_id'];
            }

            $tag = wp_insert_term('Test Tag', 'post_tag');
            if (!is_wp_error($tag)) {
                $created['terms'][] = $tag['term_id'];
            }

            // Clean caches
            wp_cache_flush();

            // Clear Blockstudio transients to ensure fresh asset capture
            delete_transient('blockstudio_editor_all_assets');
            delete_transient('blockstudio_editor_captured_frontend_scripts');
            delete_transient('blockstudio_editor_captured_frontend_styles');
            delete_transient('blockstudio_editor_expected_capture_assets_id');

            return [
                'success' => true,
                'created' => $created,
                'message' => 'E2E test data created: posts (1386, 1388, 1483), media (8, 1604, 1605), user (644), term (6)',
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    // Debug endpoint for checking cssClasses settings and cached transient
    register_rest_route('blockstudio-test/v1', '/e2e/debug-styles', [
        'methods' => 'GET',
        'callback' => function () {
            if (!class_exists('Blockstudio\\Settings')) {
                return new WP_Error('not_loaded', 'Blockstudio Settings class not loaded', ['status' => 500]);
            }

            $css_classes_setting = \Blockstudio\Settings::get('blockEditor/cssClasses');
            $css_variables_setting = \Blockstudio\Settings::get('blockEditor/cssVariables');

            // Check the cached transient (don't call get_all_assets which requires admin context)
            $cached_assets = get_transient('blockstudio_editor_all_assets');
            $styles = $cached_assets ? ($cached_assets['styles'] ?? []) : [];

            // Get the style handles that would be passed to frontend
            $chosen_css_class_styles = $css_classes_setting ?: [];
            $css_classes = [];

            if (is_array($chosen_css_class_styles) && count($chosen_css_class_styles) > 0) {
                foreach ($styles as $key => $style) {
                    if (in_array($key, $chosen_css_class_styles, true)) {
                        $css_classes[] = $key;
                    }
                }
            }

            return [
                'settings' => [
                    'blockEditor/cssClasses' => $css_classes_setting,
                    'blockEditor/cssVariables' => $css_variables_setting,
                ],
                'transient_exists' => $cached_assets !== false,
                'cached_styles_count' => count($styles),
                'cached_style_handles' => array_keys($styles),
                'matched_css_classes' => $css_classes,
                'wp_block_library_theme_in_styles' => isset($styles['wp-block-library-theme']),
                'wp_block_library_theme_data' => $styles['wp-block-library-theme'] ?? null,
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    // Get E2E test data IDs (for tests to use dynamic IDs instead of hardcoded)
    register_rest_route('blockstudio-test/v1', '/e2e/data', [
        'methods' => 'GET',
        'callback' => function () {
            // Get first few posts
            $posts = get_posts([
                'numberposts' => 5,
                'post_status' => 'publish',
            ]);

            // Get first few terms
            $categories = get_terms([
                'taxonomy' => 'category',
                'number' => 5,
                'hide_empty' => false,
            ]);

            $tags = get_terms([
                'taxonomy' => 'post_tag',
                'number' => 5,
                'hide_empty' => false,
            ]);

            // Get users
            $users = get_users([
                'number' => 5,
            ]);

            return [
                'posts' => array_map(fn($p) => ['id' => $p->ID, 'title' => $p->post_title], $posts),
                'categories' => array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name], is_array($categories) ? $categories : []),
                'tags' => array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name], is_array($tags) ? $tags : []),
                'users' => array_map(fn($u) => ['id' => $u->ID, 'name' => $u->display_name], $users),
            ];
        },
        'permission_callback' => '__return_true',
    ]);
});

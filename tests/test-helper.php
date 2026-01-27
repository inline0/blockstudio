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
});

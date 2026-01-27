<?php

/**
 * Plugin Name: Blockstudio
 * Plugin URI: https://blockstudio.dev
 * Description: The block framework for WordPress.
 * Author: Blockstudio
 * Version: 6.0.2
 * Requires PHP: 8.2
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */

use Blockstudio\Assets;

if (!defined('BLOCKSTUDIO_VERSION')) {
    define('BLOCKSTUDIO_VERSION', '6.0.2');
}
if (!defined('BLOCKSTUDIO')) {
    define('BLOCKSTUDIO', __FILE__);
}
if (!defined('BLOCKSTUDIO_DIR')) {
    define('BLOCKSTUDIO_DIR', __DIR__);
}
if (!defined('BLOCKSTUDIO_STORE_URL')) {
    define('BLOCKSTUDIO_STORE_URL', 'https://blockstudio.dev');
}
if (!defined('BLOCKSTUDIO_ITEM_ID')) {
    define('BLOCKSTUDIO_ITEM_ID', 31);
}
if (!defined('BLOCKSTUDIO_ITEM_NAME')) {
    define('BLOCKSTUDIO_ITEM_NAME', 'Blockstudio');
}

/**
 * Exit if accessed directly.
 *
 * @date   28/09/2020
 * @since  1.0.0
 */
if (!defined('ABSPATH')) {
    exit();
}

if (!function_exists('blockstudioInit')) {
    function blockstudioInit()
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        require_once BLOCKSTUDIO_DIR . '/vendor/autoload.php';

        $pluginPath = plugin_dir_path(BLOCKSTUDIO);

        require_once $pluginPath . 'includes/classes/migrate.php';
        require_once $pluginPath . 'includes/classes/files.php';
        require_once $pluginPath . 'includes/classes/settings.php';
        require_once $pluginPath . 'includes/classes/block.php';
        require_once $pluginPath . 'includes/classes/render.php';
        require_once $pluginPath . 'includes/classes/build.php';
        require_once $pluginPath . 'includes/classes/populate.php';
        require_once $pluginPath . 'includes/classes/field.php';
        require_once $pluginPath . 'includes/classes/esmodules.php';
        require_once $pluginPath . 'includes/classes/esmodulescss.php';
        require_once $pluginPath . 'includes/classes/assets.php';
        if (class_exists('Blockstudio\Assets')) {
            new Assets();
        }
        require_once $pluginPath . 'includes/classes/tailwind.php';
        require_once $pluginPath . 'includes/classes/llm.php';
        require_once $pluginPath . 'includes/classes/utils.php';
        require_once $pluginPath . 'includes/classes/library.php';
        require_once $pluginPath . 'includes/classes/admin.php';
        require_once $pluginPath . 'includes/classes/blocks.php';
        require_once $pluginPath . 'includes/classes/configurator.php';
        require_once $pluginPath . 'includes/classes/rest.php';
        require_once $pluginPath . 'includes/classes/extensions.php';
        require_once $pluginPath . 'includes/classes/builder.php';
        require_once $pluginPath . 'includes/classes/examples.php';
        require_once $pluginPath . 'includes/classes/register.php';
        require_once $pluginPath . 'includes/functions/functions.php';

        if (!class_exists('EDD_SL_Plugin_Updater')) {
            include $pluginPath . 'includes/admin/edd-sl-updater.php';
        }

        require_once $pluginPath . 'includes/admin/blockstudio-updater.php';

        BlockstudioPlugin::init(
            'blockstudio_',
            BLOCKSTUDIO_ITEM_NAME,
            BLOCKSTUDIO_STORE_URL,
            BLOCKSTUDIO_ITEM_ID,
            BLOCKSTUDIO
        );

        add_action('init', function () {
            if (class_exists('Blockstudio\Build')) {
                Blockstudio\Build::init([
                    'dir' => Blockstudio\Build::getBuildDir(),
                ]);
            }
        });

        $initialized = true;
    }
}

blockstudioInit();

/**
 * Register a category.
 *
 * @date   09/03/2021
 * @since  1.3.0
 */
if (version_compare(get_bloginfo('version'), '5.8.0', '>=')) {
    add_filter(
        'block_categories_all',
        function ($categories, $post) {
            return array_merge($categories, [
                [
                    'slug' => 'blockstudio',
                    'title' => __('Blockstudio', 'blockstudio'),
                ],
            ]);
        },
        10,
        2
    );
} else {
    add_filter(
        'block_categories',
        function ($categories, $post) {
            return array_merge($categories, [
                [
                    'slug' => 'blockstudio',
                    'title' => __('Blockstudio', 'blockstudio'),
                ],
            ]);
        },
        10,
        2
    );
}

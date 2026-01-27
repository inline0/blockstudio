<?php
/**
 * PHPUnit Bootstrap for Blockstudio Tests
 *
 * Sets up Brain Monkey for WordPress function mocking.
 */

declare(strict_types=1);

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants that may be needed
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

if (!defined('BLOCKSTUDIO_VERSION')) {
    define('BLOCKSTUDIO_VERSION', '7.0.0-test');
}

if (!defined('BLOCKSTUDIO_FILE')) {
    define('BLOCKSTUDIO_FILE', dirname(__DIR__) . '/blockstudio.php');
}

if (!defined('BLOCKSTUDIO_DIR')) {
    define('BLOCKSTUDIO_DIR', dirname(__DIR__));
}

// Load the plugin's autoloader for classes
spl_autoload_register(function ($class) {
    $prefix = 'Blockstudio\\';
    $baseDir = dirname(__DIR__) . '/includes/classes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    // Convert class name to file name (e.g., Utils -> utils.php)
    $file = $baseDir . strtolower(str_replace('\\', '/', $relativeClass)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

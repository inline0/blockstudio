<?php

declare(strict_types=1);

use Blockstudio\Settings;

// Valid settings paths
Settings::get('tailwind/enabled');
Settings::get('users/ids');
Settings::get('assets/enqueue');

// Invalid settings paths (should error)
Settings::get('tailwind/enabld');           // typo
Settings::get('not/a/real/path');           // unknown
Settings::get('users/idz');                  // typo

// Valid blockstudio hooks
add_filter('blockstudio/render', function ($html) { return $html; });
add_action('blockstudio/init', function () {});
add_filter('blockstudio/admin/enabled', '__return_false');

// Invalid blockstudio hooks (should error)
add_filter('blockstudio/rendrr', function ($html) { return $html; });    // typo
add_action('blockstudio/nonexistent/hook', function () {});               // unknown

// Settings dynamic path (should NOT error)
add_filter('blockstudio/settings/tailwind/enabled', '__return_true');

// Non-blockstudio hook (should NOT error)
add_filter('the_content', function ($content) { return $content; });

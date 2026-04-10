<?php

add_filter('blockstudio/render', function ($html) { return $html; });
add_action('blockstudio/init', function () {});
add_filter('blockstudio/admin/enabled', '__return_false');
add_filter('blockstudio/buffer/output', function ($html) { return $html; });
add_filter('blockstudio/assets/process/scss/prelude', function () { return ''; });

<?php

add_filter('blockstudio/static_prerender/public_urls', function ($urls) { return $urls; });
add_filter('blockstudio/static_prerender/cacheable_html', function ($cacheable) { return $cacheable; });
add_action('blockstudio/islands/rendered', function () {});
add_filter('blockstudio/site_templates/paths', function ($paths) { return $paths; });
add_action('blockstudio/pages/reconciled', function () {});
add_filter('blockstudio/canvas/inventory', function ($inventory) { return $inventory; });
add_action('blockstudio/db/before_create', function () {});
add_filter('blockstudio/cache/dir', function ($dir) { return $dir; });
add_filter('blockstudio/discovery/sources', function ($sources) { return $sources; });
add_filter('blockstudio/field_types', function ($types) { return $types; });
add_filter('blockstudio/block_tags/prefixes', function ($prefixes) { return $prefixes; });
add_filter('blockstudio/blocks/components/rich_text/render', function ($html) { return $html; });
add_filter('blockstudio/ui/inventory', function ($inventory) { return $inventory; });
add_filter('blockstudio/tailwind/css', function ($css) { return $css; });
add_filter('blockstudio/path', function () { return ''; });
add_filter('blockstudio/url', function ($url) { return $url; });

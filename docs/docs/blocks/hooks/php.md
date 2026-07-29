---
title: PHP Hooks
description: PHP filters and actions available in Blockstudio.
path: "blocks/hooks/php"
order: 48
section: "Blocks"
subsection: "Hooks"
meta_title: "PHP Hooks"
meta_description: "PHP filters and actions available in Blockstudio."
---

# PHP Hooks

Below you'll find a list of all available PHP hooks that can be used to extend
or adjust the functionality of the plugin.

## Path

This filter allows you to adjust the path of the block folder. By default, this
is `blockstudio` inside the currently active theme. Alternatively it is possible
to [create a new instance](/docs/blocks/registration#instances) for multiple
source directories.

```php title="functions.php"
add_filter('blockstudio/path', function() {
  $path = get_stylesheet_directory() . '/blocks';
  return $path;
});
```

## Init

### global

This action fires after the plugin has registered all blocks.

```php title="functions.php"
add_action('blockstudio/init', function($blocks) {
  // All blocks have been registered
});
```

### instance

This action fires after the plugin has registered all blocks of a specific
instance.
The `$instance` segment is the instance path relative to `wp-content`.

```php title="functions.php"
$instance = 'themes/my-theme/client-blocks';
add_action("blockstudio/init/$instance", function() {
  // All blocks of this instance have been registered
});
```

### global/before

This action fires before the plugin has registered all blocks.

```php title="functions.php"
add_action('blockstudio/init/before', function() {
  // Before blocks are registered
});
```

### global/before/$instance

This action fires before the plugin has registered all blocks of a specific
instance.

```php title="functions.php"
$instance = 'themes/my-theme/client-blocks';
add_action("blockstudio/init/before/$instance", function() {
  // Before blocks of this instance are registered
});
```

## Blocks

### render

This filter allows you to adjust the output of a block before it is rendered.

```php title="functions.php"
add_filter('blockstudio/blocks/render', function($content, $block, $attributes) {
  // Modify the block content
  return $content;
}, 10, 3);
```

### meta

This filter allows you to adjust the data of the block.json file before it is
registered.

```php title="functions.php"
add_filter('blockstudio/blocks/meta', function($meta, $block) {
  if (str_starts_with($block['name'], 'marketing')) {
    $meta['icon'] = 'megaphone';
  }
  return $meta;
}, 10, 2);
```

The above code would change the icon of all blocks starting with **marketing**.

### conditions

This filter allows you to add custom conditions which can be used within blocks.

```php title="functions.php"
add_filter('blockstudio/blocks/conditions', function($conditions) {
  $conditions['myCustomCondition'] = function($condition) {
    return $condition['value'] === 'expected';
  };
  return $conditions;
});
```

### attributes

This filter adjusts one field definition before the block is registered. The
filtered definition drives the editor control and render-time value validation.
See [Filtering](/docs/blocks/attributes/filtering) for more information.

```php title="functions.php"
add_filter('blockstudio/blocks/attributes', function($attribute, $block) {
  // Modify this field definition before registration.
  return $attribute;
}, 10, 2);
```

### attributes/render

This filter allows you to adjust the attributes of a block before it is
rendered. See [Filtering](/docs/blocks/attributes/filtering) for more information.

```php title="functions.php"
add_filter('blockstudio/blocks/attributes/render', function($attributes, $block) {
  // Modify attributes before rendering
  return $attributes;
}, 10, 2);
```

### attributes/populate

This filter allows you to add custom data to the options of a `checkbox`,
`select` or `radio` field. See [Populating options](/docs/blocks/attributes/populating-options) for more information.

```php title="functions.php"
add_filter('blockstudio/blocks/attributes/populate', function($options, $attribute, $block) {
  if ($attribute['populate'] === 'myCustomOptions') {
    return [
      ['value' => '1', 'label' => 'Option 1'],
      ['value' => '2', 'label' => 'Option 2'],
    ];
  }
  return $options;
}, 10, 3);
```

### components/use_block_props/render

This filter allows you to adjust the output of the `useBlockProps` content.

```php title="functions.php"
add_filter('blockstudio/blocks/components/use_block_props/render', function($props, $block) {
  $props['class'] .= ' my-custom-class';
  return $props;
}, 10, 2);
```

### components/inner_blocks/render

This filter allows you to adjust the output of the `<InnerBlocks />` content.

```php title="functions.php"
add_filter('blockstudio/blocks/components/inner_blocks/render', function($content, $block) {
  return '<div class="inner-wrapper">' . $content . '</div>';
}, 10, 2);
```

### components/inner_blocks/frontend/wrap

This filter allows you to remove the `<InnerBlocks />` wrapper from the
frontend.

```php title="functions.php"
add_filter('blockstudio/blocks/components/inner_blocks/frontend/wrap', function($wrap, $block) {
  return false; // Remove wrapper
}, 10, 2);
```

### components/rich_text/render

This filter allows you to adjust the output of the `<RichText />` content.

```php title="functions.php"
add_filter('blockstudio/blocks/components/rich_text/render', function($content, $attribute, $block) {
  return wp_kses_post($content);
}, 10, 3);
```

## Block Tags

### blockstudio/block_tags/builders

This filter registers block-array builders used by block tags and by custom
blocks selected through `blockstudio/parser/element_mapping`. Each builder
receives parsed attributes and raw inner content.

```php title="functions.php"
add_filter('blockstudio/block_tags/builders', function($builders, $parser) {
  $builders['theme/paragraph'] = function(array $attributes, string $inner) {
    $attributes['content'] = trim($inner);

    return [
      'blockName' => 'theme/paragraph',
      'attrs' => [
        'blockstudio' => [
          'attributes' => $attributes,
        ],
      ],
      'innerBlocks' => [],
      'innerHTML' => '',
      'innerContent' => [],
    ];
  };

  return $builders;
}, 10, 2);
```

The compatible `blockstudio/block_tags/renderers` and
`blockstudio/parser/renderers` filters run after this hook and can override the
same block name.

### blockstudio/block_tags/tag_aliases

This filter lets you register custom dashed authoring tags that resolve to real
block names before raw HTML fallback. It is useful when a theme wants a concise
project-specific tag surface while still rendering standard Blockstudio or core
blocks.

```php title="functions.php"
add_filter('blockstudio/block_tags/tag_aliases', function($aliases) {
  $aliases['theme-button'] = 'bsui/button';
  $aliases['theme-card'] = 'theme/card';

  return $aliases;
});
```

```html title="pages/home/index.php"
<theme-button href="/download" label="Download" />
<theme-card title="Fast authoring">
  <p>Children are parsed as nested blocks.</p>
</theme-card>
```

### blockstudio/block_tags/prefixes

This filter lets you map prefix shorthands to one or more block namespaces. Each key is a lowercase prefix without dashes. Each value can be a namespace string or an ordered list of namespaces.

```php title="functions.php"
add_filter('blockstudio/block_tags/prefixes', function($prefixes) {
  $prefixes['theme'] = ['theme-components', 'bsui'];

  return $prefixes;
});
```

```html title="pages/home/index.php"
<theme-card title="Homepage" />
<theme-button label="Get started" />
<theme-ui-feature-matrix />
```

`<theme-card>` resolves to `theme-components/card`. `<theme-button>` tries
`theme-components/button` first, then falls back to `bsui/button`. Explicit
`blockstudio/block_tags/tag_aliases` entries take precedence for the same tag,
and unresolved prefixed tags are left unchanged.

Prefixes compose: if a prefixed tag does not resolve directly and its slug is
itself a registered prefix tag, resolution recurses. With `theme => ['theme-components']`
and `ui => ['bsui']`, `<theme-ui-input>` falls through to the `ui` prefix and
resolves `bsui/input`. Registered prefix and alias tags also render in
block-template output, not only in page content.

## Settings

### path

This filter allows you to adjust the path of the `blockstudio.json` file. By
default, this is `blockstudio.json` inside the currently active theme.

```php title="functions.php"
add_filter('blockstudio/settings/path', function() {
  return get_stylesheet_directory() . '/config/blockstudio.json';
});
```

<!-- GENERATED_SETTINGS_START -->

### users/ids

This filter allows you to enable the editor for specific user IDs.

```php title="functions.php"
add_filter('blockstudio/settings/users/ids', function() {
  return [1];
});
```

### users/roles

This filter allows you to enable the editor for specific user roles.

```php title="functions.php"
add_filter('blockstudio/settings/users/roles', function() {
  return ['administrator', 'editor'];
});
```

### assets/enqueue

This filter allows you to enable/disable the enqueueing of assets in frontend and editor.

```php title="functions.php"
add_filter('blockstudio/settings/assets/enqueue', function() {
  return false;
});
```

### assets/reset/enabled

This filter allows you to enable/disable the removal of WordPress core block styles and the editor utility layout reset.

```php title="functions.php"
add_filter('blockstudio/settings/assets/reset/enabled', function() {
  return true;
});
```

### assets/reset/full_width

This filter allows you to control which post types use full-width editing.

```php title="functions.php"
add_filter('blockstudio/settings/assets/reset/full_width', function() {
  return ['page'];
});
```

### assets/minify/css

This filter allows you to enable/disable the minification of CSS.

```php title="functions.php"
add_filter('blockstudio/settings/assets/minify/css', function() {
  return true;
});
```

### assets/minify/js

This filter allows you to enable/disable the minification of JS.

```php title="functions.php"
add_filter('blockstudio/settings/assets/minify/js', function() {
  return true;
});
```

### assets/process/scss

This filter allows you to enable/disable the processing of SCSS in .css files.

```php title="functions.php"
add_filter('blockstudio/settings/assets/process/scss', function() {
  return true;
});
```

### assets/process/scss_files

This filter allows you to enable/disable the processing of .scss files to CSS.

```php title="functions.php"
add_filter('blockstudio/settings/assets/process/scss_files', function() {
  return true;
});
```

### cache/enabled

This filter allows you to enable/disable all Blockstudio file-backed runtime and editor asset caches.

```php title="functions.php"
add_filter('blockstudio/settings/cache/enabled', function() {
  return false;
});
```

### cache/path

This filter allows you to change the Blockstudio file-backed cache directory.

```php title="functions.php"
add_filter('blockstudio/settings/cache/path', function() {
  return 'cache/blockstudio';
});
```

### content/enabled

This filter allows you to enable/disable Content Sync.

```php title="functions.php"
add_filter('blockstudio/settings/content/enabled', function() {
  return true;
});
```

### content/id

This filter allows you to set the Content Sync ownership namespace.

```php title="functions.php"
add_filter('blockstudio/settings/content/id', function() {
  return 'docs';
});
```

### content/path

This filter allows you to set the Content Sync file directory.

```php title="functions.php"
add_filter('blockstudio/settings/content/path', function() {
  return 'content';
});
```

### content/include_page_sync_managed

This filter allows you to include or exclude Page Sync managed posts.

```php title="functions.php"
add_filter('blockstudio/settings/content/include_page_sync_managed', function() {
  return false;
});
```

### content/authors

This filter allows you to configure Content Sync author handling.

```php title="functions.php"
add_filter('blockstudio/settings/content/authors', function() {
  return 'ignore';
});
```

### content/post_types

This filter allows you to configure Content Sync post type allowlists.

```php title="functions.php"
add_filter('blockstudio/settings/content/post_types', function() {
  return ['team_member'];
});
```

### content/taxonomies

This filter allows you to configure Content Sync taxonomy allowlists.

```php title="functions.php"
add_filter('blockstudio/settings/content/taxonomies', function() {
  return ['category', 'post_tag'];
});
```

### content/media

This filter allows you to configure Content Sync media handling.

```php title="functions.php"
add_filter('blockstudio/settings/content/media', function() {
  return 'manifest';
});
```

### tailwind/enabled

This filter allows you to enable/disable Tailwind.

```php title="functions.php"
add_filter('blockstudio/settings/tailwind/enabled', function() {
  return true;
});
```

### tailwind/config

This filter allows you to add a custom Tailwind CSS configuration.

```php title="functions.php"
add_filter('blockstudio/settings/tailwind/config', function() {
  return '@theme { --color-primary: pink; }';
});
```

### ui/enabled

This filter allows you to enable/disable bundled UI components.

```php title="functions.php"
add_filter('blockstudio/settings/ui/enabled', function() {
  return true;
});
```

### editor/format_on_save

This filter allows you to enable/disable the formatting of code upon saving.

```php title="functions.php"
add_filter('blockstudio/settings/editor/format_on_save', function() {
  return true;
});
```

### editor/assets

This filter allows you to enqueue additional assets in the editor.

```php title="functions.php"
add_filter('blockstudio/settings/editor/assets', function() {
  return ['my-stylesheet', 'another-stylesheet'];
});
```

### editor/markup

This filter allows you to add additional markup to the end of the editor.

```php title="functions.php"
add_filter('blockstudio/settings/editor/markup', function() {
  return '<style>body { background: black; }</style>';
});
```

### block_editor/disable_loading

This filter allows you to disable the loading of blocks inside the Block Editor.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/disable_loading', function() {
  return true;
});
```

### block_editor/enhance

This filter allows you to enable Blockstudio editor affordances such as cleaner focus styles and hover/selection outlines.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/enhance', function() {
  return true;
});
```

### block_editor/css_classes

This filter allows you to add stylesheets whose classes should be available for choice in the class field.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/css_classes', function() {
  return ['my-stylesheet', 'another-stylesheet'];
});
```

### block_editor/css_variables

This filter allows you to add stylesheets whose CSS variables should be available for autocompletion in the code field.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/css_variables', function() {
  return ['my-stylesheet', 'another-stylesheet'];
});
```

### block_editor/blocks/allow

This filter allows you to control the block names that are allowed in the inserter.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/allow', function() {
  return ['core/*', 'acf/*', 'my-theme/*'];
});
```

### block_editor/blocks/deny

This filter allows you to remove block names from the inserter.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/deny', function() {
  return ['core/embed', 'core/freeform'];
});
```

### block_editor/blocks/directory

This filter allows you to enable or disable the WordPress block directory assets in the editor.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/directory', function() {
  return false;
});
```

### block_editor/blocks/categories/allow

This filter allows you to control which block category slugs remain available.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/categories/allow', function() {
  return ['text', 'media', 'design'];
});
```

### block_editor/blocks/categories/deny

This filter allows you to remove block category slugs from the inserter.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/categories/deny', function() {
  return ['embed'];
});
```

### block_editor/blocks/categories/rename

This filter allows you to rename block category labels by slug.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/categories/rename', function() {
  return [
    'text' => 'Writing',
    'design' => 'Layout'
  ];
});
```

### block_editor/blocks/categories/order

This filter allows you to order block category slugs in the inserter.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/categories/order', function() {
  return ['my-theme', 'text', 'media'];
});
```

### block_editor/blocks/styles/deny

This filter allows you to unregister PHP-registered block styles by block name.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/styles/deny', function() {
  return [
    'my-theme/card' => ['outline'],
    'my-theme/media' => ['framed']
  ];
});
```

### block_editor/blocks/legacy_widgets/hide

This filter allows you to hide additional legacy widgets from the legacy widget block.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/blocks/legacy_widgets/hide', function() {
  return ['archives', 'calendar'];
});
```

### block_editor/patterns/core

This filter allows you to enable or disable WordPress core block patterns.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/core', function() {
  return false;
});
```

### block_editor/patterns/remote

This filter allows you to enable or disable remote patterns from the WordPress pattern directory.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/remote', function() {
  return false;
});
```

### block_editor/patterns/theme

This filter allows you to enable or disable theme-provided block patterns.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/theme', function() {
  return false;
});
```

### block_editor/patterns/blockstudio

This filter allows you to enable or disable Blockstudio file-based patterns.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/blockstudio', function() {
  return false;
});
```

### block_editor/patterns/categories/allow

This filter allows you to control which pattern category slugs remain available.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/categories/allow', function() {
  return ['featured', 'buttons', 'columns'];
});
```

### block_editor/patterns/categories/deny

This filter allows you to remove pattern category slugs from the pattern inserter.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/categories/deny', function() {
  return ['gallery'];
});
```

### block_editor/patterns/categories/rename

This filter allows you to rename pattern category labels by slug.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/categories/rename', function() {
  return [
    'featured' => 'Featured Layouts'
  ];
});
```

### block_editor/patterns/categories/order

This filter allows you to order pattern category slugs in the inserter.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/patterns/categories/order', function() {
  return ['featured', 'buttons'];
});
```

### block_editor/media/openverse

This filter allows you to enable or disable the Openverse media category in the block editor media inserter.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/media/openverse', function() {
  return false;
});
```

### block_editor/media/image_sizes/allow

This filter allows you to control which image size names remain available in editor media controls.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/media/image_sizes/allow', function() {
  return ['thumbnail', 'large'];
});
```

### block_editor/media/image_sizes/deny

This filter allows you to remove image size names from editor media controls.

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/media/image_sizes/deny', function() {
  return ['medium_large'];
});
```

### ai/enable_context_generation

This filter allows you to enable or disable context file generation for LLM tool integration. When enabled, the context file assembles up-to-date block data, Blockstudio settings of the current install, all relevant schemas, and Blockstudio documentation into a single source for use with AI development tools.

```php title="functions.php"
add_filter('blockstudio/settings/ai/enable_context_generation', function() {
  return true;
});
```

### block_tags/enabled

This filter allows you to enable/disable page-level block tag rendering.

```php title="functions.php"
add_filter('blockstudio/settings/block_tags/enabled', function() {
  return true;
});
```

### block_tags/prefixes

This filter allows you to register prefix to namespace shorthands for block tags.

```php title="functions.php"
add_filter('blockstudio/settings/block_tags/prefixes', function() {
  return [
    'theme' => ['theme-components', 'bsui']
  ];
});
```

### dev/grab/enabled

This filter allows you to enable/disable the element grabber.

```php title="functions.php"
add_filter('blockstudio/settings/dev/grab/enabled', function() {
  return false;
});
```

### dev/perf

This filter allows you to enable/disable the performance profiler.

```php title="functions.php"
add_filter('blockstudio/settings/dev/perf', function() {
  return false;
});
```

### dev/canvas/enabled

This filter allows you to enable/disable the canvas.

```php title="functions.php"
add_filter('blockstudio/settings/dev/canvas/enabled', function() {
  return false;
});
```

### dev/canvas/admin_bar

This filter allows you to show/hide the WordPress admin bar on the canvas.

```php title="functions.php"
add_filter('blockstudio/settings/dev/canvas/admin_bar', function() {
  return true;
});
```

<!-- GENERATED_SETTINGS_END -->

## Canvas

### blockstudio/canvas/inventory

This filter adjusts the inventory Canvas reports for a selection. The callback
receives the inventory DTO, the original selection, and the options it was
called with.

```php title="functions.php"
add_filter('blockstudio/canvas/inventory', function(array $result, array $selection, array $options) {
  return $result;
}, 10, 3);
```

### blockstudio/canvas/documents

This filter adjusts the rendered documents Canvas returns, with the same three
arguments as the inventory filter.

### blockstudio/canvas/item_loaded

This action fires once per inventory record as it is loaded, receiving the
inventory type, the canonical record ID, and the normalized record.

### blockstudio/canvas/source_compiled

This action fires after a source file is compiled for Canvas, receiving the
selected source path and the canonical source record.

## Bundled UI

### blockstudio/ui/directories

This filter adjusts the directories scanned for bundled UI components.

### blockstudio/ui/inventory

This filter adjusts the public UI family inventory.

### blockstudio/ui/examples

This filter adjusts the UI example records, receiving the examples and the
public UI inventory they were derived from.

## Topology

### blockstudio/blocks/topology_refreshed

This action fires after the block topology is rebuilt.

### blockstudio/pages/topology_refreshed

This action fires after the page topology is rebuilt.

### blockstudio/pages/layout_error

This action fires when a page layout fails to render, rather than failing
silently.

## Runtime

### blockstudio/runtime/initialized

This action fires once the runtime has resolved its configuration.

### blockstudio/runtime/changed

This action fires when the resolved runtime identity changes, which is what
moves cache namespaces and static prerender state.

### blockstudio/performance/default_config

This filter adjusts the performance defaults before a project's own
`blockstudio.json` values are layered on top.

## Content

### blockstudio/content/orphan_action

This filter decides what content sync does with a record that no longer has a
source.

## Bootstrap

### blockstudio/url

This filter changes the public URL used for Blockstudio's editor and admin
assets. Composer integrations must register it before loading Blockstudio's
autoload file. The second argument is the physical package directory.

```php title="functions.php"
add_filter(
  'blockstudio/url',
  function(string $url, string $directory) {
    return get_stylesheet_directory_uri() . '/vendor/blockstudio/blockstudio/';
  },
  10,
  2
);
```

## Runtime Cache

### blockstudio/cache/dir

This filter receives the resolved cache base directory before a runtime,
editor-assets, or other cache scope is appended.

```php title="functions.php"
add_filter('blockstudio/cache/dir', function(string $directory) {
  return WP_CONTENT_DIR . '/cache/blockstudio';
});
```

### blockstudio/cache/context

This filter adds a serializable runtime variant to cache identities. Use it
when the same discovery source can select different logical inventories.

```php title="functions.php"
add_filter('blockstudio/cache/context', function($context, string $scope) {
  return [
    'preview' => get_query_var('preview_id'),
    'site' => get_current_blog_id(),
  ];
}, 10, 2);
```

### blockstudio/cache/site_key

This filter changes the network/blog directory identity for hosts with their
own tenant boundary. The returned value is sanitized before use.

```php title="functions.php"
add_filter(
  'blockstudio/cache/site_key',
  function(string $key, int $networkId, int $blogId) {
    return sprintf('tenant-%d-%d', $networkId, $blogId);
  },
  10,
  3
);
```

### blockstudio/runtime/identity

This filter adds host facts that affect every object in a runtime scope.
Item-specific source fingerprints should be passed as explicit dependencies by
the owning API instead.

```php title="functions.php"
add_filter(
  'blockstudio/runtime/identity',
  function(array $identity, string $scope) {
    $identity['deployment'] = getenv('RELEASE_SHA') ?: 'local';
    return $identity;
  },
  10,
  2
);
```

### blockstudio/cache/watch_debounce

Filters the number of seconds a validated file-watch snapshot can be reused
without another filesystem stat pass. The default is `0` in local and
development environments and `20` elsewhere.

```php title="functions.php"
add_filter('blockstudio/cache/watch_debounce', function() {
  return 30;
});
```

### blockstudio/cache/max_files_per_scope

Filters how many published payloads each runtime cache scope retains.

```php title="functions.php"
add_filter('blockstudio/cache/max_files_per_scope', function($maximum, $scope) {
  return $scope === 'runtime' ? 50 : $maximum;
}, 10, 2);
```

### blockstudio/cache/protected_paths

Cache paths retention pruning must never evict. Blockstudio seeds it with
every file the installed early-serve map references.

```php title="functions.php"
add_filter('blockstudio/cache/protected_paths', function(array $paths, string $scope, string $directory) {
  if ('static-prerender' === $scope) {
    $paths[] = $directory . '/pinned.html';
  }
  return $paths;
}, 10, 3);
```

### blockstudio/cache/outcome

This action reports a shared cache scope and outcome such as `hit`, `build`,
`miss-stale`, `stale-last-good`, or `write-failure`.

```php title="functions.php"
add_action('blockstudio/cache/outcome', function(string $scope, string $reason) {
  my_metrics()->increment("blockstudio.cache.{$scope}.{$reason}");
}, 10, 2);
```

### blockstudio/static_prerender/request_bypass

This filter receives the final anonymous-safety decision plus server and cookie
inputs. Returning `true` always bypasses static serving and generation.

```php title="functions.php"
add_filter(
  'blockstudio/static_prerender/request_bypass',
  function(bool $bypass, array $server, array $cookies) {
    return $bypass || isset($cookies['commerce_session']);
  },
  10,
  3
);
```

### blockstudio/static_prerender/public_urls

This filter supplies or adjusts the complete public URL inventory used by the
scheduled warmer and explicit graph tooling.

```php title="functions.php"
add_filter('blockstudio/static_prerender/public_urls', function(array $urls) {
  $urls[] = home_url('/custom-route/');
  return array_values(array_unique($urls));
});
```

What the filter returns is not the final inventory. The result is passed through
a cacheability pass that drops anything matching a configured dynamic path, and
anything bypassed by default: `/wp-admin`, `/wp-json`, `/wp-login.php`,
`/xmlrpc.php`, and any path containing `/feed/`, `/search/` or `/preview/`. A URL
you add can therefore be discarded without a warning. If a route you expect is
missing from the built inventory, check it against those rules before assuming
the filter did not run.

### blockstudio/static_prerender/render_internal

This filter implements the optional in-process batch transport. Return a
normalized result array or leave the value unchanged to let the renderer use
its configured HTTP fallback.

```php title="functions.php"
add_filter(
  'blockstudio/static_prerender/render_internal',
  function($result, string $url, array $options) {
    return my_static_renderer()->render($url, $options);
  },
  10,
  3
);
```

### blockstudio/static_prerender/cacheable_html

This filter makes the final decision after Blockstudio verifies that output is a
complete HTML document without the no-cache marker, and that the observed
response status is a success. The third argument is the observed status, or
`null` when no status was declared.

```php title="functions.php"
add_filter('blockstudio/static_prerender/cacheable_html', function(bool $cacheable, string $html, ?int $status) {
  return $cacheable && !str_contains($html, 'data-personalized');
}, 10, 3);
```

### blockstudio/static_prerender/outcome

This action reports static-prerender outcomes including hits, misses, writes,
graph writes, stale entries, and failures.

```php title="functions.php"
add_action('blockstudio/static_prerender/outcome', function(string $reason) {
  my_metrics()->increment("blockstudio.prerender.{$reason}");
});
```

### blockstudio/tailwind/cache_max_files

Filters the maximum number of compiled Tailwind CSS entries. The default is
`1000`.

```php title="functions.php"
add_filter('blockstudio/tailwind/cache_max_files', function() {
  return 1500;
});
```

### blockstudio/tailwind/cache_max_age

Filters the maximum age of compiled Tailwind CSS entries in seconds. The
default is 30 days and the minimum is one hour.

```php title="functions.php"
add_filter('blockstudio/tailwind/cache_max_age', function() {
  return 14 * DAY_IN_SECONDS;
});
```

## Admin

### enabled

This filter allows you to enable or disable the Blockstudio admin page under
**Tools > Blockstudio**.

```php title="functions.php"
add_filter('blockstudio/admin/enabled', function() {
  return false;
});
```

## Assets

### editor/canvas/body_class

When the asset reset is enabled, this filter adjusts the sanitized frontend
body classes copied into the block editor canvas.

```php title="functions.php"
add_filter('blockstudio/editor/canvas/body_class', function(array $classes) {
  return array_values(array_diff($classes, ['logged-in']));
});
```

### enable

This filter allows you to disable the asset processing and enqueueing of a
specific asset type.

```php title="functions.php"
add_filter('blockstudio/assets/enable', function($enable, $type, $block) {
  if ($type === 'css' && $block['name'] === 'my/block') {
    return false;
  }
  return $enable;
}, 10, 3);
```

### disable

This filter allows you to disable specific assets by their ID. Assets matching
an ID in the returned array will not be rendered on the frontend.

```php title="functions.php"
add_filter('blockstudio/assets/disable', function($disabled) {
  $disabled[] = 'my-block-style';
  return $disabled;
});
```

### process/scss/import_paths

This filter allows you to add additional paths to the `@import` statement of the
SCSS compiler.

```php title="functions.php"
add_filter('blockstudio/assets/process/scss/import_paths', function($paths) {
  $paths[] = get_stylesheet_directory() . '/scss';
  return $paths;
});
```

### process/scss/prelude

This filter allows you to prepend shared Sass code before each SCSS file is
compiled.

```php title="functions.php"
add_filter('blockstudio/assets/process/scss/prelude', function($prelude, $path, $scss) {
  return '@import "functions";' . "\n"
    . '@import "variables";' . "\n"
    . '@import "mixins";';
}, 10, 3);
```

### process/css/content

This filter allows you to adjust the content of the CSS file before it is being
compiled.

```php title="functions.php"
add_filter('blockstudio/assets/process/css/content', function($content, $block) {
  return str_replace('old-class', 'new-class', $content);
}, 10, 2);
```

### process/js/content

This filter allows you to adjust the content of the JS file before it is being
compiled.

```php title="functions.php"
add_filter('blockstudio/assets/process/js/content', function($content, $block) {
  return $content;
}, 10, 2);
```

## Buffer

### enabled

Blockstudio buffers the frontend response so block style and script tags
rendered inside the body can be hoisted into the head and footer, and so
Tailwind can scan the complete document. Buffering means holding and scanning
the whole document on every frontend request; a site that renders no
Blockstudio block assets and does not use Tailwind can return `false` to skip
both.

```php title="functions.php"
add_filter('blockstudio/buffer/enabled', '__return_false');
```

### output

This filter receives the complete buffered HTML document before it is sent.
Blockstudio's own asset hoisting runs at priority `1000000` and Tailwind
compilation at `999999`, so run earlier to see the document before them or
later to see the final output.

```php title="functions.php"
add_filter('blockstudio/buffer/output', function(string $html) {
  return $html;
}, 2000000);
```

## Render

### dependencies

This filter reports the selected template and asset dependencies whenever
Blockstudio renders a block. It is useful for static exporters and other
in-process dependency collectors.

```php title="functions.php"
add_filter(
  'blockstudio/render/dependencies',
  function($paths, $name, $block, $isEditor, $isPreview) {
    my_dependency_graph()->add($name, $paths);
    return $paths;
  },
  10,
  5
);
```

### global

This filter allows you to adjust the output of the page before it is being
rendered.

```php title="functions.php"
add_filter('blockstudio/render', function($content) {
  return $content;
});
```

### head

This filter allows you to adjust the output of the `<head>` tag before it is
being rendered.

```php title="functions.php"
add_filter('blockstudio/render/head', function($content) {
  return $content . '<meta name="custom" content="value">';
});
```

### footer

This filter allows you to adjust the output of the `</body>` before it is being
rendered.

```php title="functions.php"
add_filter('blockstudio/render/footer', function($content) {
  return $content . '<script>console.log("loaded")</script>';
});
```

## Error Handling

### error/logged

Action fired after an error is logged. Use this to send errors to external
logging services.

```php title="functions.php"
add_action('blockstudio/error/logged', function($message, $level, $context) {
  if ($level === 'error') {
    my_external_logger($message, $context);
  }
}, 10, 3);
```

### error/exception

Action fired after an exception is handled.

```php title="functions.php"
add_action('blockstudio/error/exception', function($exception, $context) {
  my_error_tracker($exception);
}, 10, 2);
```

## Database

All database hooks receive a single `$params` array.

### db/before_create

Action fired before a record is created.

```php title="functions.php"
add_action('blockstudio/db/before_create', function($params) {
  // $params: block, schema, data, storage
});
```

### db/after_create

Action fired after a record is created.

```php title="functions.php"
add_action('blockstudio/db/after_create', function($params) {
  // $params: block, schema, record, storage
  wp_mail($params['record']['email'], 'Welcome!', 'Thanks for subscribing.');
});
```

### db/before_update

Action fired before a record is updated.

```php title="functions.php"
add_action('blockstudio/db/before_update', function($params) {
  // $params: block, schema, id, data, storage
});
```

### db/after_update

Action fired after a record is updated.

```php title="functions.php"
add_action('blockstudio/db/after_update', function($params) {
  // $params: block, schema, id, record, storage
});
```

### db/before_delete

Action fired before a record is deleted.

```php title="functions.php"
add_action('blockstudio/db/before_delete', function($params) {
  // $params: block, schema, id, storage
});
```

### db/after_delete

Action fired after a record is deleted.

```php title="functions.php"
add_action('blockstudio/db/after_delete', function($params) {
  // $params: block, schema, id, storage
});
```

## RPC

All RPC hooks receive a single `$params` array.

### rpc/before_call

Action fired before an RPC function is called.

```php title="functions.php"
add_action('blockstudio/rpc/before_call', function($params) {
  // $params: block, function, params
});
```

### rpc/after_call

Action fired after an RPC function is called.

```php title="functions.php"
add_action('blockstudio/rpc/after_call', function($params) {
  // $params: block, function, params, result
});
```

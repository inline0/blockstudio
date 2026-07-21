---
title: Blockstudio 7.5
description: Custom field types, file-backed Site Editor templates, block islands, Storh database storage, and bsui/parser refinements.
date: "2026-07-05"
author: Dennis
path: "blockstudio-7-5"
order: 1
section: "Blog"
meta_title: "Blockstudio 7.5"
meta_description: "Custom field types, file-backed Site Editor templates, block islands, Storh database storage, and bsui/parser refinements."
---

Blockstudio 7.5 rounds out Blockstudio's file-first workflow: custom field
types, file-backed Site Editor templates, block islands, Storh database storage,
composable block tags, and a set of parser plus `bsui/*` refinements.

Reusable custom fields already let projects define a group of existing fields
once and reuse it with `type: "custom/{name}"`. Custom field types solve a
different problem. They let a theme or plugin register a new namespaced field
type, provide its own editor control, and store the value as normal
Blockstudio data.

The short version:

- **Logical discovery sources**: runtimes can supply a composed logical theme
  inventory, including provenance and cache identity, while Blockstudio keeps
  ownership of discovery, sibling resolution, generated assets, Canvas, and
  Tailwind behavior.
- **PHP field type registry**: themes and plugins can register namespaced field
  types such as `acme/dimensions` with `bs_register_field_type()` or the
  `blockstudio/field_types` filter.
- **Editor control registry**: custom editor scripts can call
  `window.blockstudio.registerFieldType()` and render the matching React
  control in the normal Blockstudio field UI.
- **Structured values**: custom fields can store strings, numbers, booleans,
  arrays, or objects without coercing object values through option field
  normalization.
- **Storage support**: post meta and option-backed custom fields can declare
  their value type and REST schema, including object and array values.
- **Native integration**: custom field types work inside groups, tabs,
  repeaters, reusable custom fields, templates, and extension template paths.
- **Site Editor templates**: themes can define `wp_template` and
  `wp_template_part` sources from `template.json`, `part.json`, and
  Blockstudio template files.
- **Block islands**: blocks can render a cache-safe shell first, then hydrate
  or batch-fetch request-specific fragments after the page loads.
- **Storh database storage**: `bs.db` can use an indexed file-per-record store
  in uploads, with public integer IDs preserved and JSONC-to-Storh migration
  through WP-CLI.
- **Parser and bsui refinements**: mapped richtext paragraphs need less custom
  parser glue, `InnerBlocks` can use tokenized `allowedBlocks`, nested
  `bs_render_block()` output matches frontend rendering in editor previews,
  and `bsui/button` gains icons plus easier styling hooks.
- **Composable block tags**: prefix tags can resolve through another prefix
  (`<dv-ui-input>` to `bsui/input`), and prefix and alias tags render in
  block-template output, not only in page content.
- **Automatic cache pruning**: the runtime, editor asset, and Tailwind caches
  prune stale entries so long-running projects do not accumulate cache files.

## File-backed Site Editor templates

Pages and patterns already let teams keep content structures in files. 7.5
extends that model to the Site Editor itself.

```text
theme/
  templates/
    front-page/
      template.json
      index.php
  parts/
    header/
      part.json
      index.php
```

```json title="templates/front-page/template.json"
{
  "slug": "front-page",
  "title": "Front Page"
}
```

```php title="templates/front-page/index.php"
<div class="site-shell">
  <block name="core/template-part" slug="header" />

  <main>
    <h1>Welcome</h1>
    <block name="core/post-content" />
  </main>
</div>
```

Blockstudio compiles the source through the same parser used by pages and
patterns, then exposes the result to WordPress as a normal Site Editor template
or template part. The Site Editor sees the template, the frontend can render it,
and WordPress still owns customizations.

That last part matters. File-backed templates are the source fallback. If a user
saves a template in the Site Editor, WordPress creates the normal
`wp_template` or `wp_template_part` database customization and that version
wins. Reset the customization, and the latest file source is visible again.

## Block islands

Most Blockstudio blocks are server-rendered. That is a good fit for WordPress,
but it creates a familiar caching problem: one personalized block can make the
whole page hard to cache.

Block islands split that boundary at the block level.

```json title="block.json"
{
  "name": "acme/cart-count",
  "blockstudio": {
    "island": "dynamic"
  }
}
```

A dynamic island renders only a cache-safe placeholder in the first response.
The browser then sends one batched request for all ready dynamic islands on the
page, and Blockstudio renders those fragments in the visitor's real request
context.

That means a full page cache can store the page shell while the cart count,
account controls, permission-gated UI, or other per-user pieces still stay
fresh.

```php title="placeholder.php"
<div class="cart-count-placeholder">Loading cart...</div>
```

```php title="index.php"
<div class="cart-count">
  <?php echo esc_html(acme_cart_count()); ?>
</div>
```

For blocks that already render cache-safe HTML and only need a frontend mount
signal, use a hydrated island:

```json title="block.json"
{
  "name": "acme/tabs",
  "blockstudio": {
    "island": "hydrate"
  }
}
```

Hydrated islands do not make a REST request. They render normally and receive a
`blockstudio:island:hydrate` event on the frontend.

Dynamic islands also work through the programmatic render paths:

```php
bs_render_block([
  'name' => 'acme/cart-count',
  'data' => ['productId' => 42],
]);
```

And through block tags:

```html
<bs:acme-cart-count product-id="42" />
```

The endpoint is deliberately narrow. It only renders registered Blockstudio
blocks that are dynamic and explicitly marked as dynamic islands. Attributes are
filtered to the block schema and the island allow-list, and each marker carries
a signature generated by the serving WordPress site.

## Storh database storage

Blockstudio's database layer already supports tables, SQLite, JSONC, post meta,
and custom post types. 7.5 adds `storage: "storh"` for projects that want a
file-backed store with safer concurrent writes and schema-derived indexes, but
do not want live records committed next to block source files.

```php title="db.php"
return [
    'storage' => 'storh',
    'fields'  => [
        'title'  => ['type' => 'string', 'required' => true],
        'status' => ['type' => 'string'],
        'count'  => ['type' => 'integer'],
    ],
];
```

Storh records live in uploads:

```text
wp-content/uploads/blockstudio/db/my-theme-my-block/default/
```

The public API stays unchanged. `bs.db()`, REST, and `Db::get()` still return
integer `id` values. Internally, Blockstudio maps those IDs to Storh's UUIDv7
document IDs, so existing client code does not need to change.

Every declared schema field is indexed for equality filters, numeric and
date-like fields get range indexes, and explicit unique hints are passed
through. For projects that started with JSONC seed files, the new CLI migration
keeps the source file in place and writes an equivalent Storh store:

```bash
wp bs db migrate my-theme/app subscribers --to=storh
```

That gives JSONC a clearer role as a simple, committable seed/config format,
while Storh becomes the better default file backend for writable production
data.

## Parser and bsui refinements

7.5 also rounds off smaller pieces that tend to show up once a project leans
harder on file-backed templates and bundled UI.

Element mapping can now handle the common richtext paragraph case without a
custom builder. If `<p>` maps to a registered non-core Blockstudio block with a
`content` richtext attribute, simple inner text is copied to that attribute.
Nested tags and mapped child elements still parse as inner blocks.

`InnerBlocks` accepts tokenized `allowedBlocks` in templates:

```php title="index.php"
<InnerBlocks allowedBlocks="<?php echo esc_attr(wp_json_encode([
  'core/paragraph',
  'category:content',
  'bsui/field',
])); ?>" />
```

Supported tokens are `namespace/*`, `category:<slug>`, and `@theme`. They are
expanded on the server before the editor receives the template, so the frontend
render path stays unchanged.

Programmatic embeds are also more predictable. `bs_render_block()` and
`bs_block()` now render embedded blocks as frontend-resolved HTML even when
called from inside another block's editor preview. That means nested blocks do
not leak raw `<RichText />` or `<InnerBlocks />` pseudo-components into preview
markup.

For bundled UI, `bsui/button` now supports an `icon` attribute and
`iconPosition`. Bundled `bsui/*` inline styles are emitted in an `@layer bsui`
cascade layer, and button variants can be extended with
`blockstudio/ui/button/variants-style` plus the existing
`blockstudio/blocks/attributes` filter.

Block tags compose further, too. A prefix can resolve through another prefix, so
`<dv-ui-input />` with a `dv` brand prefix over a `ui` namespace prefix resolves
`bsui/input`. Registered prefix and alias tags now also render in block-template
output, not just page content, so a template can emit them directly instead of
calling `bs_render_block()`.

Finally, the runtime, editor asset, and Tailwind caches now prune stale entries
automatically, so long-running projects no longer accumulate unbounded cache
files on disk.

## Why custom field types?

Blockstudio should stay useful out of the box, but real projects often have
domain-specific controls: spacing token pickers, design-system color roles,
responsive dimensions, icon sources, product selectors, or editorial controls
that map to a structured value.

Before 7.5, those projects could either compose several built-in fields or fork
the editor. 7.5 gives them a supported middle ground: Blockstudio owns the
field lifecycle, and the project owns the control.

```php title="functions.php"
add_action('init', function () {
    wp_register_script(
        'acme-field-dimensions',
        get_stylesheet_directory_uri() . '/fields/dimensions.js',
        ['blockstudio-blocks', 'wp-components', 'wp-element'],
        '1.0.0',
        true
    );

    bs_register_field_type('acme/dimensions', [
        'attribute'     => 'object',
        'default'       => [],
        'editor_script' => 'acme-field-dimensions',
        'storage'       => [
            'type'        => 'object',
            'rest_schema' => [
                'type'                 => 'object',
                'additionalProperties' => ['type' => 'string'],
            ],
        ],
    ]);
});
```

```js title="fields/dimensions.js"
const { createElement: el } = wp.element;
const { TextControl } = wp.components;

window.blockstudio.registerFieldType('acme/dimensions', {
  component(props) {
    const value =
      props.value &&
      typeof props.value === 'object' &&
      !Array.isArray(props.value)
        ? props.value
        : {};
    const sides = props.sides || ['top', 'right', 'bottom', 'left'];

    return el(
      'div',
      {},
      sides.map((side) =>
        el(TextControl, {
          key: side,
          label: side,
          value: value[side] || '',
          onChange(nextValue) {
            props.onChange({
              ...value,
              [side]: nextValue,
            });
          },
        }),
      ),
    );
  },
});
```

Then the field can be used in `block.json` like a native field:

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "id": "margin",
        "type": "acme/dimensions",
        "label": "Margin",
        "sides": ["top", "bottom"],
        "default": {
          "top": "md",
          "bottom": "md"
        }
      }
    ]
  }
}
```

## Normal template values

The saved value is available in PHP, Twig, and Blade like any other
Blockstudio attribute.

```php title="index.php"
<div class="<?php echo esc_attr('mt-' . ($a['margin']['top'] ?? '')); ?>">
  <?php echo esc_html($a['title'] ?? ''); ?>
</div>
```

The same object paths also work in extension templates:

```json title="extend.json"
{
  "blockstudio": {
    "extend": "core/group",
    "attributes": [
      {
        "id": "margin",
        "type": "acme/dimensions",
        "set": [
          {
            "attribute": "class",
            "value": "mt-{attributes.margin.top} mb-{attributes.margin.bottom}"
          }
        ]
      }
    ]
  }
}
```

## Compatibility notes

Existing built-in field types are unchanged. Existing `custom/{name}` reusable
field groups are unchanged too. Custom field type names must be lowercase and
namespaced, for example `acme/dimensions`; the `custom/` and `blockstudio/`
namespaces stay reserved.

If PHP registers a field type but its editor script does not register the
matching control, Blockstudio keeps the saved value and shows a non-editable
notice in the editor. If a field type is not registered in PHP, Blockstudio
leaves it out of generated WordPress attributes.

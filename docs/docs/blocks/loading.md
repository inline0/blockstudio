---
title: Loading
description: Control how blocks are loaded in the editor.
path: "blocks/loading"
order: 34
section: "Blocks"
subsection: "Editor Behavior"
meta_title: "Loading"
meta_description: "Control how blocks are loaded in the editor."
---

# Loading

Blockstudio blocks are dynamic, meaning that they are rendered on the server to
be displayed in Gutenberg. Blockstudio preloads the rendered output for blocks
that already exist in the editor payload and reuses it for the first editor
paint when possible.

This avoids a separate renderer request for each saved block on initial editor
load. Nested Blockstudio blocks inside synced patterns and reusable
`core/block` references are included in the preload payload.

## Fallback renders

The editor still calls the Blockstudio renderer when a block is inserted,
edited, or cannot be matched to preloaded output. This can happen when the
attributes, context, or inner block tree no longer match the preloaded entry.
The fallback keeps the preview accurate while allowing most initial renders to
come from preloaded data.

On slower hosts, many fallback renderer requests can still cause timeouts or
delay other editor assets. For blocks that do not need an immediate preview,
loading can be disabled.

## Media hydration

File fields and media-enabled attributes are hydrated from the initial editor
payload where possible. Blockstudio follows nested groups, tabs, repeaters, and
field ID prefixes when preparing the media data.

When a field stores a URL return format, Blockstudio treats it as a URL and does
not try to resolve it through the WordPress media endpoint. If attachment data
is not available in the preload payload, the editor may still request
`/wp/v2/media` as a fallback.

## Disabling

Rendering template loading can be disabled by setting the `disableLoading` key in `block.json` to `true`. A placeholder will be rendered in place of the block template. All field settings will still be available when opening the sidebar.

```json title="block.json"
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "blockstudio": {
    "blockEditor": {
      "disableLoading": true
    }
  }
}
```

Alternatively, block loading can be disabled globally using the `blockEditor/disableLoading` setting:

```php title="functions.php"
add_filter('blockstudio/settings/block_editor/disable_loading', function() {
  return true;
});
```

---
title: Canvas
description: A visual workspace and public inventory API for Blockstudio content.
path: "dev/canvas"
order: 60
section: "Dev"
meta_title: "Canvas"
meta_description: "A visual workspace and public inventory API for Blockstudio content."
---

# Canvas

The canvas provides a zoomed-out, Figma-like overview of all Blockstudio-managed pages. Each page renders as a live iframe artboard in a single horizontal row. Pan with your trackpad, zoom with pinch or Ctrl+scroll.

## Activation

The canvas is available as a hidden admin page. Navigate to:

```
/wp-admin/admin.php?page=blockstudio-canvas
```

The page is only accessible when `dev.canvas.enabled` is `true` and the current user has the `edit_posts` capability.

## Settings

Enable the canvas and configure its behavior in your theme's `blockstudio.json`:

```json title="blockstudio.json"
{
  "dev": {
    "canvas": {
      "enabled": true,
      "adminBar": false
    }
  }
}
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `enabled` | `boolean` | `false` | Enable the canvas feature. |
| `adminBar` | `boolean` | `true` | Show the WordPress admin bar inside artboard iframes. Set to `false` for a cleaner preview. |

Both settings are also available as filters:

```php
add_filter('blockstudio/settings/dev/canvas/enabled', '__return_true');
add_filter('blockstudio/settings/dev/canvas/admin_bar', '__return_false');
```

## Views

Switch between views using the dropdown menu in the top-right corner.

- **Pages**: All Blockstudio-managed pages as full-width artboards in a single row.
- **Blocks**: All registered Blockstudio blocks in a grid layout, each rendered with its default attribute values.

## Public Inventory and Document API

`Canvas` exposes a versioned, consumer-neutral PHP contract for tools that need
the same registered content without rebuilding Blockstudio's discovery or
rendering logic:

```php
use Blockstudio\Canvas;

$all = Canvas::inventory();

$changed = Canvas::inventory([
  'blocks' => ['theme/hero'],
  'pages' => ['about'],
]);

$documents = Canvas::documents([
  'patterns' => ['theme/featured'],
  'templates' => ['front-page'],
]);
```

The current version is available as `Canvas::SCHEMA_VERSION`. Inventory results
contain:

- `inventory`: normalized `pages`, `blocks`, `patterns`, `templates`, `parts`,
  and public `ui` family examples
- `order`: the stable cross-type display order
- `sources`: exact selected source paths with provenance on each record; a page
  record's `source` is its stable source identity while `path` is the physical
  render template path
- `warnings` and `errors`: structured issues instead of emitted output
- `deleted`: requested identifiers that no longer exist
- `selection`: the normalized selection and whether it was targeted

`Canvas::documents()` adds one complete render document per selected record.
Each document contains its body, assembled HTML, dependency-closed block names,
and exact CSS, JavaScript, modules, interactivity bootstrap, bundled UI globals,
and Tailwind output. Page documents use a semantic `<main>` content wrapper;
callers can override or disable it through the shared Render document options.
Every selected page renders in its own restored frontend WordPress context, so
query conditionals, body-class filters, shortcodes, layouts, redirects, and
`wp_enqueue_scripts` callbacks observe that page rather than the admin or REST
request that requested the Canvas result. Caller-provided body classes are
merged with the page's contextual classes, and frontend enqueue registries are
isolated between documents.

### Exact selection semantics

Selection is strict:

- No recognized type keys loads every type.
- `true`, `null`, or `"*"` loads every record for that type.
- A string or list loads only exact IDs, names, slugs, paths, or source paths.
- An empty string/list or `false` loads none of that type.
- Once any type key is present, omitted types are not discovered, synced,
  rendered, or compiled.

This makes changed-only requests safe for large projects. Existing blocks and
pages resolve through their canonical registries. When a frontend request has
only the persisted page projection loaded, Canvas performs a read-only source
discovery and merges matching post IDs and permalinks without synchronizing
content. The same identity merge applies when a logical discovery source
populates the source-backed registry first, so contextual page rendering still
uses the matching managed WordPress post. New live-session topology is
discovered only around the changed directory, while selected pattern and Site
Editor template sources compile only after selection.

The REST refresh endpoint follows the same rule for its existing `blocks` and
`pages` query parameters. For example,
`?blocks=theme/hero` returns that block and no pages; use both parameters for a
mixed response. Calling the endpoint without either parameter preserves the
complete legacy response.

## Live Mode

Live mode uses Server-Sent Events (SSE) to detect file changes and update the canvas in real-time. When you edit a block template, page template, or stylesheet, the affected artboards refresh automatically within about one second.

Toggle live mode from the dropdown menu. A green pulsing indicator appears when active. The setting persists across sessions via localStorage.

Live mode tracks changes to `.php`, `.json`, `.css`, `.scss`, `.js`, `.twig`, and `.html` files inside block and page directories. Known edits refresh only their registered block or page. A genuinely new block or page gets one directory-scoped topology pass, so creating content during an open live session still works without rescanning unrelated sources.

## Focus Mode

Click any artboard label to enter focus mode. The selected artboard fills the viewport at full width with vertical scrolling. Press Escape or click the close button to return to the overview.

## Controls

| Action | Input |
|--------|-------|
| Pan | Scroll / trackpad two-finger drag |
| Zoom | Ctrl+scroll or pinch |
| Fit to view | Dropdown menu |
| Zoom to 100% | Dropdown menu |
| Focus artboard | Click artboard label |
| Exit focus | Escape / close button |

## Security

The canvas script and page data are only loaded when all conditions are met:

- The `dev.canvas.enabled` setting is `true`.
- The current user has the `edit_posts` capability.

Public visitors never see the canvas script or page metadata.

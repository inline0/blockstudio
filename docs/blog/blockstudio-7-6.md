---
title: Blockstudio 7.6
description: Public canvas and rendering contracts, generic theme runtime ownership, stricter PHPStan layers, and managed project hooks.
date: '2026-07-25'
author: Dennis
path: 'blockstudio-7-6'
order: 1
section: 'Blog'
meta_title: 'Blockstudio 7.6'
meta_description: 'Public canvas and rendering contracts, generic theme runtime ownership, stricter PHPStan layers, and managed project hooks.'
---

Blockstudio 7.6 makes its existing runtime capabilities available through
stable, consumer-neutral contracts. Tools can inspect registered content,
render structured compositions, assemble complete frontend documents, and ask
for an exact changed subset without rebuilding Blockstudio internals.

The short version:

- **Versioned Canvas data**: `Blockstudio\Canvas::inventory()` exposes pages,
  blocks, patterns, Site Editor templates and parts, layouts, public UI
  examples, ordering, source provenance, deleted identifiers, warnings, and
  errors.
- **Exact changed-content selection**: a blocks-only, pages-only, mixed, or
  empty request does not discover, synchronize, render, or compile omitted
  content types.
- **Programmatic compositions**: `Blockstudio\Render` normalizes and renders
  single blocks, nested declarations, inner content, root/layer families, and
  deterministic example data through the normal Blockstudio pipeline.
- **Complete frontend documents**: selected output includes dependency-closed
  CSS, JavaScript, modules, interactivity bootstrap, bundled UI globals, and
  Tailwind output while excluding editor and unrelated assets.
- **Public UI families**: the bundled `bsui/*` registrations remain the
  implementation, while `Blockstudio\Ui::inventory()` and
  `Blockstudio\Ui::examples()` expose complete component families instead of
  individual internal layers.
- **Stricter PHPStan projects**: opt-in theme and extreme-theme layers extend
  the existing Blockstudio PHPStan package for projects that want a stronger
  default.
- **Managed commit checks**: projects can let `blockstudio.json` install a
  Blockstudio-owned PHPStan pre-commit hook without hand-maintaining generated
  hook files.
- **Generic theme runtime**: `blockstudio.json` now owns typed, invalidating
  runtime settings, theme defaults, media metadata and rendering, Tailwind
  composition helpers, intent preloading, opt-in measurements, and generic
  WordPress optimizations.

## Canvas inventory and exact selection

`Canvas::inventory()` reads the canonical Blockstudio registries and returns a
stable schema rather than creating a second content model:

```php
use Blockstudio\Canvas;

$changed = Canvas::inventory([
    'blocks' => ['theme/hero'],
    'pages' => ['about'],
]);
```

Once any supported type key is present, omitted types stay unloaded. An empty
list explicitly selects nothing, while `true`, `null`, or `"*"` selects every
record of that type. Requested identifiers that no longer exist are reported
under `deleted`.

`Canvas::documents()` adds one complete frontend document for every selected
record. Pattern and Site Editor template sources compile only after selection;
known block and page edits use their canonical registrations, and genuinely
new live-session topology gets a directory-scoped discovery pass.

## Structured rendering and asset closure

The public renderer accepts a normalized declaration or convenient root/layer
composition:

```php
use Blockstudio\Render;

$document = Render::document([
    'root' => 'theme/card',
    'example' => [
        'data' => [
            'heading' => 'Example card',
        ],
        'layers' => [
            [
                'name' => 'theme/button',
                'data' => [
                    'label' => 'Continue',
                ],
            ],
        ],
    ],
]);
```

The result separates rendered body HTML from the assembled document and its
styles, scripts, modules, interactivity bootstrap, bundled UI assets, and
Tailwind output. Dependencies referenced by selected templates are included;
unrelated and editor-only assets are not.

These APIs are designed for preview tools, component galleries, exporters, and
other integrations without knowledge of any particular host product.

## Generic runtime ownership

Themes can select `compat`, `speed`, or `strict` under
`performance.profile`. Compat preserves WordPress defaults. The opt-in profiles
enable independent WordPress cleanup, same-origin intent preloading, stable
lazy images, and media metadata while allowing every child value to be
overridden.

`Blockstudio\Settings` now exposes strict boolean, integer, string, and array
accessors; raw-source values; parse errors; deterministic fingerprints; and
automatic source invalidation. `Blockstudio\Runtime_Settings` resolves the
effective profile and provides a stable configuration hash.

Theme images can be inventoried with
`Blockstudio\Media_Metadata_Builder`, read through
`Blockstudio\Media_Metadata`, and rendered with `bs_media_image()`. Tailwind
class composition is available directly through `bs_tw_merge()` and
`bs_tw_variants()`. The browser assets and public hooks use only Blockstudio
names.

The existing `Blockstudio\Pages` and `Blockstudio\Patterns` APIs remain the
canonical file-backed content surfaces. Theme defaults can opt into
development page reconciliation without adding another page or pattern
abstraction.

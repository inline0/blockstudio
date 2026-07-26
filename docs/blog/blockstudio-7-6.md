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
  Tailwind output while excluding editor and unrelated assets. Page documents
  also run in an isolated, restored frontend query and enqueue context.
- **Public UI families**: the bundled `bsui/*` registrations remain the
  implementation, while `Blockstudio\Ui::inventory()` and
  `Blockstudio\Ui::examples()` expose complete component families instead of
  individual internal layers.
- **Stricter PHPStan projects**: opt-in theme and extreme-theme layers extend
  the existing Blockstudio PHPStan package for projects that want a stronger
  default.
- **Managed project analysis**: `blockstudio.json` owns the canonical PHPStan
  preset, roots, exclusions, scan limit, and opt-in generated pre-commit hook
  without hand-maintaining command or hook files.
- **Generic theme runtime**: `blockstudio.json` now owns typed, invalidating
  runtime settings, theme defaults, media metadata and rendering, Tailwind
  composition helpers, intent preloading, opt-in measurements, and generic
  WordPress optimizations.
- **One cache and prerender boundary**: build, Tailwind, render, fragment,
  static HTML, graph, queue, and diagnostic state share one multisite-safe
  runtime identity with atomic writes, single-flight recovery, bounded pruning,
  incremental dependency graphs, and anonymous-safe early serving.

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
Each page observes its own queried object, route, contextual body classes,
redirect protection, and `wp_enqueue_scripts` lifecycle without leaking those
globals or dependency queues into the next selected document. Source-backed
registries also resolve matching managed post identity by stable page key or
source path before rendering, including when a logical discovery source loaded
the page inventory first.

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

## Unified caching and static prerendering

All persistent runtime output now lives below `cache.path`, isolated by
network, site, runtime identity, and scope. The identity covers Blockstudio,
settings, WordPress, PHP, theme, active plugins, logical discovery, host
context, and explicit source dependencies. Tailwind no longer writes a
separate cache beneath uploads.

Cold objects use one builder and atomic publication. Concurrent requests wait
for that result, a failed refresh can serve stale last-good output, bounded
pruning removes old objects, and cache outcomes are available to diagnostics.

The opt-in static prerender runtime supports ordinary signature-mode fills and
explicit incremental dependency graphs. A graph build recalculates only pages
whose shared or page-specific dependencies changed, retains unrelated pages,
tracks skipped dynamic results, garbage-collects deleted routes, and can
rewrite output to a deployment host/home without changing source URLs.

Optional early serving uses an owned per-site map and `advanced-cache.php`
drop-in to serve eligible anonymous HTML before WordPress boots. It excludes
control headers, query strings, admin/REST/Ajax/cron, feeds, search, previews,
non-GET requests, dynamic path boundaries, and personalized cookies. Complete
graphs are validated before atomic cutover; foreign cache artifacts are never
overwritten; multisite entries are removed independently on disable or
deactivation.

## Canonical block-tag migrations

Project prefixes and aliases still work, but Blockstudio now consistently emits
the portable `<bs:namespace-slug>` spelling. A standalone migration utility can
translate a caller-supplied legacy prefix and alias map without booting
WordPress. Dry-run is the default; the JSON report records exact mappings,
source hashes, unknown and ambiguous cases, dynamic tags, and documentation
examples that require review. Paired, nested, self-closing, and PHP-string tags
are covered, while unrelated custom elements remain untouched.

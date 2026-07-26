---
title: Blockstudio 7.6
description: Ask a project what it contains, render any part of it on demand, and move production behaviour into blockstudio.json.
version: '7.6.0'
author: Dennis
path: 'blockstudio-7-6'
date: "2026-07-27"
order: 1
section: 'Blog'
meta_title: 'Blockstudio 7.6'
meta_description: 'Ask a project what it contains, render any part of it on demand, and move production behaviour into blockstudio.json.'
---

Blockstudio 7.6 makes a project readable and renderable from PHP, and moves
production frontend defaults into `blockstudio.json`: an inventory API,
standalone rendering, template helpers for media and Tailwind, performance
profiles, one cache directory, static prerendering with early serving, stricter
analysis presets, and generated project tooling for coding agents.

Two calls carry most of the release. `Canvas::inventory()` returns every page,
block, pattern, Site Editor template, template part, and bundled UI example a
project registers. `Render::document()` returns finished HTML for any of them,
with exactly the assets that render used.

The short version:

- **Project inventory**: `Canvas::inventory()` returns every registered record
  with its sources and a stable display order.
- **Targeted selection**: naming blocks and pages narrows discovery, syncing,
  rendering, and compilation to those records.
- **Standalone rendering**: `Blockstudio\Render` returns finished HTML for a
  block, a composition, or a complete document.
- **UI component families**: `Blockstudio\Ui` groups the bundled library into
  one public family per component, with an example each.
- **Stable image dimensions**: `bs_media_image()` renders theme assets with real
  dimensions from a generated `assets/media.json`.
- **Tailwind class helpers**: `bs_tw_merge()` and `bs_tw_variants()` compose
  classes in PHP with conflict-aware merging.
- **Performance profiles**: `performance.profile` switches production frontend
  defaults on at once; child values override it.
- **Typed settings access**: `Settings::get_bool()` and its siblings read
  `blockstudio.json` with real types and surface parse errors.
- **One cache directory**: every generated file lives below `cache.path`, keyed
  by a runtime identity.
- **Static prerendering**: anonymous HTML can be cached, invalidated through a
  dependency graph, and served before WordPress boots.
- **Analysis presets**: `theme`, `extreme-theme`, and `wordpress-render` extend
  `blockstudio/phpstan` past block templates.
- **Managed commit hook**: `blockstudio-githooks sync` writes and owns a
  pre-commit hook that runs the configured preset.
- **Project contract**: `blockstudio-agents` writes an `AGENTS.md` describing
  what a project authors, which features it enables, and what its preset
  enforces.
- **Documentation index**: the AI context file is now an index of every
  document, its purpose, and the identifiers it owns.
- **Canonical block tags**: a standalone command rewrites prefix and alias
  shorthands to `<bs:namespace-slug>`, with a dry run.
- **Compat by default**: nothing above changes a site until it is named in
  `blockstudio.json`.

## Reading and rendering a project

### Project inventory

`Canvas::inventory()` returns everything a project registers as one plain array,
without rendering anything.

```php
use Blockstudio\Canvas;

$result = Canvas::inventory();

foreach ($result['inventory']['blocks'] as $block) {
    echo $block['name'] . "\n";
}
```

Records are grouped by type: `pages`, `blocks`, `patterns`, `templates`,
`parts`, and `ui`. `order` is the stable display order across all types,
`sources` maps each record to its source paths, and `warnings` and `errors`
collect problems as structured entries.

Block records carry `id`, `name`, `title`, `source`, `path`, `provenance`, and a
ready-to-render `declaration`. Page records keep `source` as the stable identity
and `path` as the template that renders.

### Targeted selection

Naming records narrows the work instead of filtering afterwards.

```php
$changed = Canvas::inventory([
    'blocks' => ['mytheme/hero'],
    'pages' => ['about'],
]);
```

That returns one block and one page. `patterns`, `templates`, `parts`, and `ui`
come back empty, and their sources are never read, synced, rendered, or
compiled.

The rules are exact:

- No type keys at all loads every type.
- `true`, `null`, or `"*"` loads every record of that type.
- A string or a list matches exactly on ID, name, slug, path, or source path.
- An empty string, an empty list, or `false` loads none of that type.
- Once any type key is present, the omitted types stay untouched.

Identifiers that no longer exist come back under `deleted` instead of failing,
and the Canvas REST refresh endpoint applies the same rule to its `blocks` and
`pages` parameters.

`Canvas::documents()` takes the same selection and adds one rendered document
per record. Selected pages render in a restored frontend context, so query
conditionals, body classes, shortcodes, layouts, redirects, and enqueue
callbacks see that page, not the request that asked for it.

### Standalone rendering

`Blockstudio\Render` returns rendered HTML as a string, through the same
pipeline the frontend uses.

```php
use Blockstudio\Render;

$html = Render::composition([
    'root' => 'mytheme/card',
    'data' => ['heading' => 'Example card'],
    'layers' => [
        ['name' => 'mytheme/button', 'data' => ['label' => 'Continue']],
    ],
]);
```

One declaration shape sits underneath, with four keys: `name`, `attributes`,
`content`, and `children`. `root`, `data`, `inner`, `innerBlocks`, `layers`, and
`example` are input conveniences that `Render::normalize()` folds into it.
Children render recursively, and malformed input throws an exception naming the
offending block.

`Render::document()` wraps output in a complete standalone HTML document.

```php
$document = Render::document('mytheme/hero', [
    'title' => 'Hero preview',
]);

echo $document['html'];
```

`body` is the block output alone, `html` the assembled document, `blocks` the
block names that contributed, and `assets` splits the markup into `head`,
`footer`, `styles`, `scripts`, `modules`, `interactivity`, `ui`, and `tailwind`.
Assets are scoped to what rendered, so editor-only assets never appear.

`Render::document_from_html()` wraps HTML rendered elsewhere, and
`Render::content()` renders serialized block content without a document. Call
`Blockstudio\Batch_Render::reset()` between documents in one PHP process;
`Canvas::documents()` already does.

### UI component families

`Blockstudio\Ui` exposes the bundled UI library as one entry per public
component instead of a list of internal parts.

`Ui::inventory()` returns one entry per family with its root block, title, and
the implementation blocks it needs. `Ui::examples()` adds a deterministic
declaration per family: defaults preserved, required child layers nested under
their parents, and stable illustrative text where a field would be empty. That
declaration is canonical, so it goes straight into `Render::document()`.

Canvas groups the same way. Bundled UI appears under the `ui` type as one
example per family, and implementation registrations stay out of `blocks`.

## Template helpers

### Stable image dimensions

`bs_media_image()` renders theme assets with real width, height, and a reserved
aspect ratio, so images outside the media library stop shifting layout.

```php
<?php
echo bs_media_image([
    'src' => 'assets/images/hero.webp',
    'alt' => 'Hero',
]);
```

The output is a `figure.blockstudio-media` with an inline `aspect-ratio` around
an `img` carrying real `width`, `height`, `loading`, and `decoding`. Dimensions
come from a generated `assets/media.json`, written by
`Blockstudio\Media_Metadata_Builder` in a build step or by hand.

`src` takes a theme-relative path, an absolute path, or a full URL, and
`attachmentId` covers media library images. `eager` opts out of lazy loading
above the fold, and `sources` emits a `<picture>`.

With `performance.media.lazy` on, the real URL moves to `data-src` behind a
correctly sized placeholder.

### Tailwind class helpers

`bs_tw_merge()` and `bs_tw_variants()` compose Tailwind classes in PHP
templates, using the engine Blockstudio already bundles.

`bs_tw_merge('px-2 text-sm', 'px-4')` returns `text-sm px-4`: the conflicting
`px-2` is dropped and the later value wins. Nested arrays of class values are
accepted.

`bs_tw_variants()` returns a small callable for component variants:

```php
$button = bs_tw_variants([
    'base' => 'inline-flex items-center',
    'variants' => [
        'size' => ['sm' => 'h-8 px-3', 'lg' => 'h-12 px-6'],
    ],
    'defaultVariants' => ['size' => 'sm'],
]);

echo $button(['size' => 'lg', 'class' => 'rounded']);
```

That prints `inline-flex items-center h-12 px-6 rounded`. Base classes,
variants, default variants, compound variants, and a per-call `class` or
`className` are all supported.

## Production configuration

### Performance profiles

`performance.profile` turns production frontend defaults on in one place.

```json title="blockstudio.json"
{
  "performance": {
    "profile": "speed",
    "preload": {
      "links": "off"
    }
  }
}
```

`compat` is the default and changes nothing. `speed` and `strict` enable the
same set: generator, emoji, feed, discovery, and adjacent-post output removed
from the head; oEmbed discovery and host scripts dropped; XML-RPC, pingbacks,
and remote editor discovery disabled; generic core frontend assets removed;
image output defaults applied; Heartbeat throttled outside editors; same-origin
documents prefetched on hover or focus intent; and Blockstudio's image loader in
use.

Every child value overrides the profile, as `preload.links` does above. Removing
core frontend assets is the one to test first, because a page that depends on
them breaks visibly. `Runtime_Settings::current()` exposes the resolved profile.

`themeDefaults` covers three things themes otherwise hand-roll: `titleTag`,
`suppressDirectoryUpdates` to hide the active child and parent theme from update
results, and `syncPagesInDevelopment` to reconcile file-backed pages locally.

### Typed settings access

`Blockstudio\Settings` reads configuration with real types instead of returning
whatever the JSON held.

```php
use Blockstudio\Settings;

$lazy = Settings::get_bool('performance/media/lazy');
$margin = Settings::get_string('performance/media/rootMargin', '300px');
$ttl = Settings::get_int('performance/staticPrerender/ttl', 86400);
$roles = Settings::get_array('users/roles');

foreach (Settings::errors() as $error) {
    error_log($error);
}
```

Malformed JSON is reported through `Settings::errors()` instead of resolving to
defaults. `Settings::get_raw()` returns only what the active source declared,
which separates a value set to `false` from a value never set.

`Settings::fingerprint()` returns a stable identity for the effective settings,
and access reloads when `blockstudio.json` changes.

### One cache directory

Every generated file now lives below `cache.path`, which defaults to
`wp-content/blockstudio/cache`.

```json title="blockstudio.json"
{
  "cache": {
    "enabled": true,
    "path": "cache/blockstudio"
  }
}
```

Build payloads, block registration data, editor assets, compiled Tailwind CSS,
render documents, island fragments, static HTML, graph indexes, warm queues, and
diagnostics all sit under that root. A relative path resolves from
`WP_CONTENT_DIR`, an absolute path points it at a dedicated volume.

Objects are filed under the current network and site, then under a runtime
identity: a fingerprint of the Blockstudio, WordPress, and PHP versions,
effective settings, active theme and plugins, discovery sources, and the source
files involved. Change an input and the affected objects become new ones; the
old ones are pruned.

Cold builds are single-flight: one request builds and atomically publishes the
result while others wait. A failed refresh keeps serving the last good object
while the failure stays observable, and
`Blockstudio\Runtime_Cache::purge('tailwind')` clears one scope.

## Static prerendering

Static prerendering caches complete anonymous HTML documents and can serve them
before WordPress boots. It is off by default.

```json title="blockstudio.json"
{
  "performance": {
    "staticPrerender": {
      "enabled": true,
      "ttl": 3600,
      "earlyServe": true,
      "dynamicPaths": ["/account", "/checkout"]
    }
  }
}
```

### Eligibility

Only complete anonymous HTML documents are eligible. Non-GET requests, query
strings, admin, REST, Ajax, cron, feeds, search, previews, nonce-bearing
requests, personalized cookies, and any path under `dynamicPaths` are bypassed.
A response can opt out entirely with a `<!-- blockstudio:no-cache -->` marker.

Path matching respects boundaries, so `/account` covers `/account/orders` but
not `/accounting`.

### Signature and graph modes

`invalidate` chooses how entries expire. The default `signature` mode fills an
identity-keyed response on an anonymous miss and rotates after site-level
changes, which suits an ordinary site.

`graph` mode is for explicit build and deploy tooling. It records each page's
own source dependencies, so a plan rebuilds only the affected URLs.

```php
use Blockstudio\Static_Prerender_Runtime;

$plan = Static_Prerender_Runtime::build_plan($sourceUrls);

foreach ($plan['affectedUrls'] as $url) {
    $result = render_with_dependencies($url);

    Static_Prerender_Runtime::persist_built_response(
        $url,
        $result['html'],
        $result['files'],
        $result['virtualHashes']
    );
}

Static_Prerender_Runtime::garbage_collect(array_keys($plan['live']));
```

### Early serving

With `earlyServe` on, Blockstudio owns a small per-site route map and an
`advanced-cache.php` drop-in that streams an eligible hit before WordPress,
plugins, or the theme boot.

It never overwrites a foreign map, drop-in, or `WP_CACHE` declaration. A graph
artifact validates every referenced HTML file before the map is swapped, so a
half-rendered deploy leaves the previous graph active. Multisite entries stay
isolated by host, home path, and site ID.

### Warming and status

Three WP-CLI commands cover day-to-day use:

```bash
wp bs prerender warm
wp bs prerender status
wp bs prerender purge
```

`status` reports the active identity, file and byte counts, graph records, queue
state, and per-scope hit, miss, build, and failure counters.

## Analysis and agent tooling

### Analysis presets

`blockstudio/phpstan` gains three layers above its block analysis, plus one
command that runs whichever layer a project selected.

| Preset | What it adds |
| --- | --- |
| `base` | The existing schema, template, hook, settings, and API analysis. |
| `theme` | Theme roots, style headers, assets, selector scoping, field defaults, repeater bounds. |
| `extreme-theme` | PHPStan `max`, unsafe PHP, escaping, Tailwind, JavaScript, and Interactivity checks. |
| `wordpress-render` | A caller-supplied live render probe on top of `extreme-theme`. |

Defaults come from `blockstudio.json`, so interactive runs, CI, and commit hooks
share one configuration:

```json title="blockstudio.json"
{
  "phpstan": {
    "preset": "theme",
    "roots": ["."],
    "excludePaths": ["fixtures/**"],
    "maxFiles": 10000
  }
}
```

```bash
vendor/bin/blockstudio-phpstan --root . -- --no-progress
```

Exit codes are `0` on pass, `1` for diagnostics, and `2` for invalid usage. The
wrapper composes its configuration in the system temporary directory, so no
generated config, baseline, or cache lands in the project, and it raises
PHPStan's memory limit to `1G`.

It also bundles a BC Math provider, so the JavaScript checks no longer need
`ext-bcmath`.

### Managed commit hook

One setting and one command turn the configured preset into a commit gate.

```json title="blockstudio.json"
{
  "githooks": {
    "commit": true
  }
}
```

```bash
vendor/bin/blockstudio-githooks sync
```

Sync writes the hook and an ownership record inside Git's common directory,
points `core.hooksPath` at the managed directory, and chains whatever pre-commit
hook was configured before. The hook resolves the repository and project root at
commit time, so linked checkouts and paths containing spaces work.

The hook runs `vendor/bin/blockstudio-phpstan` and nothing else; it never
formats or rewrites files. Setting `commit` to `false` and syncing again, or
running `blockstudio-githooks remove`, restores the recorded hook path and
deletes only Blockstudio-owned files.

### Project contract

`blockstudio-agents` writes an `AGENTS.md` that states where blocks live, what a
page is in this project, which features are enabled, and what the analyser will
reject.

```bash
vendor/bin/blockstudio-agents
```

The document is derived, not templated. A theme with a `style.css` header,
blocks, and file-backed pages gets a different contract than a plugin that only
registers blocks, carrying the counts, directories, namespaces, and template
languages the scanner found. `blockstudio.json` decides which features appear,
so a project without static prerendering never sees it mentioned.

The correctness section is read from the selected preset's own rule files, layer
by layer, so a preset that gains a rule changes every generated contract with
it.

Ownership works like the commit hook. A file without the generated marker is
refused rather than replaced unless `--force` says otherwise, a notes region at
the end survives regeneration, `--check` exits `1` when the contract is out of
date, and `--stdout` prints it without touching the filesystem.

### Documentation index

`blockstudio-llm.txt` is now an index instead of a concatenation. Every
published document appears once, in its section and subsection, with its route,
its source file, a one-line purpose, and the identifiers it owns: settings
paths, hook names, PHP functions and classes, and commands.

```text
## Docs / Dev / Inspection Tools

### Canvas
route: /docs/dev/canvas
file: docs/docs/dev/canvas.md
purpose: A visual workspace and public inventory API for Blockstudio content.
settings: dev/canvas/enabled, ui
hooks: blockstudio/settings/dev/canvas/admin_bar, blockstudio/settings/dev/canvas/enabled
php: Blockstudio\Canvas, Canvas::documents(), Canvas::inventory()
```

An agent reads the index, finds the one document that owns the identifier it
cares about, and opens that document. The previous half-megabyte concatenation
is still generated and published at `/blockstudio-llm-full.txt`; it is no longer
the first thing anyone is handed.

## Canonical block tags

A standalone command rewrites prefix and alias shorthands to the canonical
`<bs:namespace-slug>` spelling, which records the real block name and resolves
without a project filter loaded.

With a `brand` prefix over `theme-components` and a `ui` prefix over `bsui`,
this shorthand:

```html
<brand-card>
  <ui-input />
</brand-card>
```

means the same as:

```html
<bs:theme-components-card>
  <bs:bsui-input />
</bs:theme-components-card>
```

Prefixes and exact aliases still work and are not deprecated. The command does
not load WordPress and writes nothing without `--apply`:

```bash
php vendor/blockstudio/blockstudio/bin/migrate-block-tags.php \
  --root="$PWD" \
  --prefix-map=/tmp/prefixes.json \
  --known-blocks=/tmp/blocks.json \
  --report=/tmp/tag-migration.json \
  --dry-run
```

`--prefix-map` maps each prefix to its namespaces and `--known-blocks` lists
registered block names. The report records legacy-to-canonical mappings with
occurrence counts, per-file hashes, and separate lists for unknown tags,
ambiguous aliases, and dynamic tags. `--apply` refuses to write while any remain
unless `--allow-unresolved` is passed.

Paired, nested, and self-closing markup is handled, including literal tags in
PHP strings. Only tag names change, a second run produces no changes, and
examples inside comments, code fences, `<pre>`, or `<code>` are report-only.

## Default behavior

Installing 7.6 changes nothing on its own.

**The performance profile defaults to `compat`.** Head output, oEmbed, XML-RPC,
Heartbeat, image defaults, link prefetching, lazy loading, and the frontend
`<title>` stay as WordPress and the theme left them.

**These stay off unless enabled:** every `performance.staticPrerender`,
`performance.measurement`, `performance.media`, and `themeDefaults` key, plus
`githooks.commit`, `dev.canvas.enabled`, `ui.enabled`, `tailwind.enabled`, and
`blockTags.enabled`. Static prerendering writes no files and installs no drop-in
while disabled.

**Canvas and Render are passive.** They read existing registries and render only
what a caller asks for; neither hooks into frontend output nor registers routes.
The Canvas admin screen still requires `dev.canvas.enabled` and `edit_posts`.

**Stricter analysis requires naming it.** Installing `blockstudio/phpstan`
enables only the base extension, and the canonical command runs `base` unless a
project names a preset.

**Generated files require running their command.** `githooks.commit` does
nothing until `blockstudio-githooks sync` runs, and `AGENTS.md` until
`blockstudio-agents` does.

## Upgrading from 7.5

Requirements are unchanged: PHP 8.2 or newer, WordPress 6.7 or newer.

```bash
composer require blockstudio/blockstudio
```

**Caches rebuild once.** The runtime identity includes the Blockstudio version,
so the first request after upgrading is cold and old objects are pruned. The old
Tailwind cache under `wp-content/uploads/blockstudio/tailwind/cache` can be
deleted; a snippet that globbed it should call
`Blockstudio\Runtime_Cache::purge('tailwind')` instead.

**Generated files have owners.** `assets/media.json` belongs in version control
or a build step. The route map, `advanced-cache.php` drop-in, and `WP_CACHE`
declaration exist only while `earlyServe` is enabled, and the pre-commit hook
only while synced. Everything else generated lives below `cache.path` and is
safe to delete.

**Nothing was removed or renamed.** `bs_render_block()`, `bs_block()`,
`Blockstudio\Pages`, and `Blockstudio\Patterns` remain the canonical file-backed
content surfaces.

**Rolling back is per feature.** `performance.profile` back to `compat`,
`staticPrerender.enabled` to `false`, `githooks.commit` to `false` plus a
`sync`, and `cache.enabled` to `false` each undo one layer.

A reasonable adoption order:

1. Set `performance.profile` to `speed`, then check head output, feeds, and any
   plugin relying on oEmbed or XML-RPC.
2. Generate `assets/media.json`, move templates to `bs_media_image()`, then turn
   on `performance.media.lazy`.
3. Enable `performance.staticPrerender` with `earlyServe` off, verify hits with
   `wp bs prerender status`, then turn `earlyServe` on.
4. Run `blockstudio-phpstan --preset theme` until it is clean, before putting any
   preset behind `githooks.commit`.

## Where the details live

- [Canvas](/docs/dev/canvas) and its
  [inventory and document API](/docs/dev/canvas#public-inventory-and-document-api)
- [Rendering](/docs/blocks/rendering#structured-compositions-and-complete-documents)
  and [UI Components](/docs/blocks/ui-components#public-inventory-and-examples)
- [Settings](/docs/general/settings) and
  [performance](/docs/general/settings#performance)
- [Performance Profiler](/docs/dev/perf): the
  [cache root](/docs/dev/perf#file-backed-caches) and
  [static prerendering](/docs/dev/perf#static-prerendering)
- [Tailwind CSS](/docs/tailwind#template-composition-helpers)
- [PHPStan](/docs/dev/phpstan): [presets](/docs/dev/phpstan#analysis-presets),
  [canonical command](/docs/dev/phpstan#canonical-command),
  [managed commit hook](/docs/dev/phpstan#managed-commit-hook), and the
  [project contract](/docs/dev/phpstan#project-contract-for-coding-agents)
- [AI Integration](/docs/dev/ai): the documentation index and the full text
- [Migration](/docs/dev/migration/v7#canonical-block-tags) and
  [PHP Hooks](/docs/blocks/hooks/php)

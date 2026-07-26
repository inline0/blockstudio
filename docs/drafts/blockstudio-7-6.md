---
title: Blockstudio 7.6
description: Ask a project what it contains, render any part of it on demand, and move production behaviour into blockstudio.json.
status: 'unpublished-prerelease-draft'
draft: true
version: '7.6.0'
developmentLine: '7.6.0.x-dev'
author: Dennis
path: 'blockstudio-7-6'
section: 'Drafts'
meta_title: 'Blockstudio 7.6 (unpublished prerelease draft)'
meta_description: 'Unpublished draft notes for the Blockstudio 7.6.0.x-dev development line. Not a release announcement.'
---

> **Unpublished draft.** These notes describe the `7.6.0.x-dev` development
> line. Nothing here is tagged or released, the plugin headers in this
> repository still read `7.5.2`, and this file sits outside the published blog
> collection on purpose, so it has no release date and no published ordering.
> Treat every command below as development software.

A Blockstudio project is a folder of files, which is easy to work with right
up until something outside a normal page view needs to know what is in it. A
preview screen, a component gallery, a screenshot job, a documentation site, a
deploy script: each of those wants the same two things. A list of every block
and page the project registers, and the finished HTML for any one of them.
Until now the only dependable way to get either was to request a real frontend
URL and read the response, or to reimplement file discovery by hand and hope it
stayed in sync.

The reverse direction had a matching problem. Once a project holds a few
hundred blocks and pages, "one file changed, show me the result" turned into
"rebuild everything", because there was no way to ask for a subset and be
certain the rest stayed untouched.

7.6 answers both questions with two plain PHP calls: one that lists what the
project contains, one that renders any part of it. The rest of the release is
what those answers need in order to be useful in production. `blockstudio.json`
grows a place to declare how the finished site behaves, every generated file
moves under one directory, and the optional static analysis gets strong enough
to catch a broken template before anyone renders it.

## The short version

- **Ask what your project contains.** One call returns every registered page,
  block, pattern, Site Editor template, template part, and bundled UI example,
  with the source file behind each one.
- **Ask for only what changed.** Name the blocks and pages you care about and
  nothing else is discovered, synced, rendered, or compiled.
- **Render one component without a page.** Get finished HTML for a block, a
  nested composition, or a complete standalone document carrying exactly the
  CSS, JavaScript, and Tailwind output that output needs.
- **Build a component gallery from the bundled UI** with one example per
  component family instead of a list of internal parts.
- **Read your settings from PHP with real types**, see parse errors instead of
  silent nulls, and get a stable fingerprint of the effective configuration.
- **Turn on a set of production defaults at once** rather than collecting a
  dozen snippets: head cleanup, link prefetching on intent, stable lazy images.
- **Render images that never shift layout**, from a generated metadata file that
  records real dimensions for theme assets.
- **Compose Tailwind classes in PHP templates** with conflict-aware merging and
  small variant helpers, using the engine already bundled with Blockstudio.
- **Keep every generated file in one directory** so a host can back it up, move
  it to a fast volume, or wipe it.
- **Serve cached anonymous HTML before WordPress boots**, optionally rebuilding
  only the pages whose sources actually changed.
- **Analyse a whole theme, not only blocks**, when you opt into the stronger
  presets, and make that analysis a commit gate without writing a hook yourself.
- **Rewrite old block-tag shorthands to the portable spelling**, with a dry run
  and a report before anything is written.

## Seeing everything the project contains

`Blockstudio\Canvas::inventory()` reads the registries Blockstudio already
builds and returns them as one plain array. It does not create a second content
model and it does not render anything.

```php
use Blockstudio\Canvas;

$result = Canvas::inventory();

foreach ($result['inventory']['blocks'] as $block) {
    echo $block['name'] . ' (' . $block['title'] . ")\n";
}
```

```text
mytheme/card (Card)
mytheme/hero (Hero)
```

The result has a fixed set of keys. `inventory` holds the records grouped by
type: `pages`, `blocks`, `patterns`, `templates`, `parts`, and `ui`. `order` is
the stable display order across all types, so a gallery does not have to invent
one. `sources` maps each selected record to the exact source paths it came from.
`warnings` and `errors` collect problems as structured entries rather than
printing them. `schemaVersion` is `Canvas::SCHEMA_VERSION`, currently `1`, so a
tool can check the shape it received.

Each block record carries `id`, `name`, `title`, `source`, `path`,
`provenance`, and a ready-to-render `declaration` filled with the block's
default attribute values. Page records distinguish the two paths worth
distinguishing: `source` is the stable source identity, `path` is the physical
template that renders.

## Asking for only what changed

Pass a selection and Blockstudio narrows the work rather than filtering a full
result at the end.

```php
$changed = Canvas::inventory([
    'blocks' => ['mytheme/hero'],
    'pages' => ['about'],
]);
```

That returns one block and one page. `patterns`, `templates`, `parts`, and `ui`
come back as empty lists, and their sources were never read, synced, rendered,
or compiled. On a large project this is the difference between a second and a
minute.

The rules are deliberately strict, because a preview tool that guesses is worse
than one that refuses:

- No recognised type keys at all loads every type.
- `true`, `null`, or `"*"` loads every record of that type.
- A string or a list loads only exact matches on ID, name, slug, path, or
  source path.
- An empty string, an empty list, or `false` loads none of that type.
- As soon as any type key is present, the omitted types stay untouched.

Identifiers you asked for that no longer exist are reported under `deleted`
instead of failing, which is what a deploy script wants when a block was removed
between two runs.

```php
$result = Canvas::inventory(['blocks' => ['mytheme/removed']]);

echo count($result['inventory']['blocks']) . "\n";
echo implode(', ', $result['deleted']['blocks']) . "\n";
```

```text
0
mytheme/removed
```

The Canvas REST refresh endpoint follows the same rule for its existing
`blocks` and `pages` query parameters. `?blocks=mytheme/hero` returns that block
and no pages; calling it with neither parameter keeps the complete legacy
response.

`Canvas::documents()` takes the same selection and adds one rendered document
per selected record.

```php
$documents = Canvas::documents(['blocks' => ['mytheme/hero']]);

foreach ($documents['documents']['blocks'] as $entry) {
    echo $entry['id'];
    echo $entry['document']['body'];
}
```

Selected pages are the interesting case. Each one renders inside its own
restored frontend context, so query conditionals, body classes, shortcodes,
layouts, redirects, and `wp_enqueue_scripts` callbacks see that page rather
than the admin or REST request that asked for it. The enqueue registry is reset
between documents, so page two never inherits page one's stylesheets.

## Rendering one component, or a whole document

`Blockstudio\Render` is the same pipeline your frontend uses, exposed as
functions that return strings instead of echoing.

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

Under the surface there is exactly one declaration shape, and it has four keys.
`Render::normalize()` shows what the call above becomes:

```php
[
    [
        'name' => 'mytheme/card',
        'attributes' => ['heading' => 'Example card'],
        'content' => '',
        'children' => [
            [
                'name' => 'mytheme/button',
                'attributes' => ['label' => 'Continue'],
                'content' => '',
                'children' => [],
            ],
        ],
    ],
]
```

`root`, `data`, `inner`, `innerBlocks`, `layers`, and `example` are input
conveniences. They exist because different callers naturally write different
shapes, and they are all normalised into `name`, `attributes`, `content`, and
`children` before anything renders. Children render recursively through the
normal Blockstudio pipeline, so a nested declaration behaves exactly like the
same nesting in a page.

Malformed input throws rather than rendering something surprising. A name that
is not `namespace/slug`, a list where an associative array belongs, attributes
that are not an associative array, content that is not a string, or a block that
is not registered all raise an exception naming the offending block.

`Render::document()` wraps rendered output in a complete standalone HTML
document:

```php
$document = Render::document('mytheme/hero', [
    'title' => 'Hero preview',
]);

echo $document['html'];
```

The return value separates what most tools need separately. `body` is the
rendered block output on its own. `html` is the assembled document. `blocks` is
the list of block names that contributed. `assets` splits the document markup
into `head`, `footer`, `styles`, `scripts`, `modules`, `interactivity`, `ui`,
and `tailwind`, so a gallery can inject only the pieces it wants. `warnings` and
`errors` report problems without printing them.

Assets are scoped to what the output actually uses. Styles and scripts belonging
to blocks that did not render are not included, and editor-only assets never
are.

Two smaller entry points round it out. `Render::document_from_html()` builds the
same document around HTML you rendered yourself, given the root block names.
`Render::content()` renders serialized WordPress block content without creating
a document at all. If you render several documents in one PHP process, call
`Blockstudio\Batch_Render::reset()` between them; `Canvas::documents()` already
does.

## The bundled UI as component families

The bundled UI library is built from small blocks. A select is a root, a
trigger, a popup, and a set of options. That is the right shape for authoring
and the wrong shape for a component gallery, which ends up listing internal
fragments as if they were components.

`Blockstudio\Ui` groups them:

```php
use Blockstudio\Ui;

foreach (Ui::examples() as $example) {
    echo $example['title'] . ' (' . $example['rootName'] . ")\n";
}
```

`Ui::inventory()` returns one entry per public family, each with its root block,
its title, and the implementation blocks that family needs. `Ui::examples()`
adds a deterministic declaration per family: defaults preserved, required child
layers nested under their declared parents, and stable illustrative text where a
field would otherwise be empty. Because that declaration is already in the
canonical shape, it can go straight into `Render::document()`.

The Canvas inventory uses this too. Bundled UI appears under the `ui` type as
one example per family, and the bundled implementation registrations are left
out of the ordinary `blocks` type.

## Settings you can read, and defaults you can turn on

`Blockstudio\Settings` gained typed accessors, so reading configuration no
longer means casting whatever comes back.

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

Malformed JSON is reported through `Settings::errors()` instead of quietly
resolving to defaults. `Settings::get_raw()` returns only what the active source
actually declared, which matters when an integration needs to know the
difference between "set to false" and "not set". `Settings::fingerprint()`
returns a stable identity for the effective settings, and normal access reloads
automatically when `blockstudio.json` changes.

The new `performance` tree is where production behaviour lives. It starts from a
profile:

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

`compat` is the default and changes nothing about generic WordPress behaviour.
`speed` and `strict` switch on the same opt-in set: removing generator, emoji,
feed, discovery, and adjacent-post output from the head, dropping oEmbed
discovery and its host scripts, disabling XML-RPC and pingbacks, disabling
remote editor discovery surfaces, removing generic core frontend styles and
scripts for themes that own their asset output, applying image output defaults,
throttling Heartbeat outside editor screens, prefetching same-origin documents
after hover or focus intent, and using Blockstudio's image loader. Removing core
frontend styles and scripts is the one to test first, because it is immediately
visible if something on the page still depends on them.

Every child value overrides the profile, which is what the example above does:
take the `speed` set, but leave link prefetching off.

The resolved profile is readable at runtime:

```php
use Blockstudio\Runtime_Settings;

$runtime = Runtime_Settings::current();

if ($runtime->enabled('media/lazy')) {
    $hash = $runtime->hash();
}
```

`themeDefaults` covers three small things that most themes otherwise hand-roll:
WordPress title-tag support, hiding the active child and parent theme from
directory update results, and reconciling file-backed pages when their sources
change during local development.

### Images that do not shift

Layout shift from images is usually a missing width and height, and the reason
they are missing is that theme assets are not in the media library. 7.6
generates that information into a file.

```php
use Blockstudio\Media_Metadata_Builder;

(new Media_Metadata_Builder())->write(get_stylesheet_directory(), true);
```

That writes deterministic dimensions to `assets/media.json`. Templates then
render through one helper:

```php
<?php
echo bs_media_image([
    'src' => 'assets/images/hero.webp',
    'alt' => 'Hero',
]);
```

With metadata present and the default `compat` profile, the output carries real
dimensions and a reserved aspect ratio:

```html
<figure class="blockstudio-media" style="aspect-ratio:1600/900;">
  <img
    alt="Hero"
    class="blockstudio-media__image"
    decoding="async"
    loading="lazy"
    width="1600"
    height="900"
    src="https://example.com/wp-content/themes/mytheme/assets/images/hero.webp"
  />
</figure>
```

`src` may be a theme-relative path, an absolute path, or a full URL. Pass
`attachmentId` with an optional `size` for media library images, `eager` to skip
lazy loading for something above the fold, `class` and `style` for the figure,
and `sources` to emit a `<picture>` with art-directed variants. When
`performance.media.lazy` is on, the real URL moves to `data-src` behind a
generated placeholder of the correct size, and an intersection observer swaps it
in.

### Tailwind classes in PHP

Blockstudio already bundles a Tailwind engine to compile your CSS. 7.6 exposes
its class utilities to templates, so a theme no longer needs its own merge
implementation.

```php
echo bs_tw_merge('px-2 text-sm', 'px-4');
```

```text
text-sm px-4
```

The conflicting `px-2` is dropped and the later value wins. Nested arrays of
class values are accepted too.

`bs_tw_variants()` returns a small callable for component variants:

```php
$button = bs_tw_variants([
    'base' => 'inline-flex items-center',
    'variants' => [
        'size' => [
            'sm' => 'h-8 px-3',
            'lg' => 'h-12 px-6',
        ],
    ],
    'defaultVariants' => [
        'size' => 'sm',
    ],
]);

echo $button([]) . "\n";
echo $button(['size' => 'lg', 'class' => 'rounded']) . "\n";
```

```text
inline-flex items-center h-8 px-3
inline-flex items-center h-12 px-6 rounded
```

Base classes, variants, default variants, compound variants, and a per-call
`class` or `className` are all supported.

## One cache directory, and optional static HTML

7.5 moved the runtime and editor caches out of uploads. 7.6 finishes the job.
Build payloads, prebuilt block registration data, editor assets, compiled
Tailwind CSS, render documents, island fragments, static HTML, graph indexes,
warm queues, and cache diagnostics now all live below `cache.path`, which
defaults to `wp-content/blockstudio/cache`. Tailwind no longer keeps a separate
cache under uploads.

```json title="blockstudio.json"
{
  "cache": {
    "enabled": true,
    "path": "cache/blockstudio"
  }
}
```

A relative path resolves from `WP_CONTENT_DIR`; an absolute path lets a host
point the whole thing at a dedicated writable volume. Every object is filed
under the current network and site, then under a fingerprint of everything that
could change its contents: the Blockstudio version, your effective settings, the
WordPress and PHP versions, the active theme, the active plugins, any logical
discovery sources, host context, and the exact source files the operation
depended on. Blockstudio calls that fingerprint the runtime identity. Change any
input and the affected objects become new objects; the old ones are pruned
rather than overwritten in place.

Cold builds are single-flight. One request builds and atomically publishes the
result while concurrent requests wait for it, so a cache miss under load does
not become the same work repeated twenty times. If a refresh fails and a valid
previous object exists, that stale last-good copy is served while the failure
stays observable.

Clearing one scope is one call:

```php
Blockstudio\Runtime_Cache::purge('tailwind');
```

Static prerendering is the opt-in layer on top, and it is off by default:

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

Only complete anonymous HTML documents are eligible. Non-GET requests, query
strings, admin, REST, Ajax, cron, feeds, search, previews, nonce-bearing
requests, personalized cookies, and any path under `dynamicPaths` are bypassed,
and a response can opt out of caching entirely with a
`<!-- blockstudio:no-cache -->` marker. Path matching respects boundaries, so
`/account` covers `/account/orders` but not `/accounting`.

`invalidate` chooses how entries expire. The default `signature` mode fills an
identity-keyed response on a normal anonymous miss and rotates after site-level
changes, which suits an ordinary WordPress site. `graph` mode is for explicit
build and deploy tooling: it records each page's own source dependencies, so a
plan can rebuild only the affected URLs and keep everything else.

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

With `earlyServe` on, Blockstudio owns a small per-site route map and an
`advanced-cache.php` drop-in that streams an eligible hit before WordPress,
plugins, or the theme boot. It never overwrites a foreign map, drop-in, or
`WP_CACHE` declaration. A graph artifact validates every referenced HTML file
before the map is swapped, so a half-rendered deploy leaves the previous graph
active. On multisite, entries share the map but stay isolated by host, home
path, and site ID, and disabling the feature removes only the current site's
entry.

Three WP-CLI commands cover day-to-day use:

```bash
wp bs prerender warm
wp bs prerender status
wp bs prerender purge
```

`status` reports the active identity, file and byte counts, graph records, queue
state, and per-scope hit, miss, build, and failure counters.

## Stronger analysis, when you ask for it

The `blockstudio/phpstan` package has validated block templates, schemas,
settings paths, and hook names since it shipped. 7.6 adds layers above that
base, and a single command to run whichever layer a project chose.

| Preset | What it adds |
| --- | --- |
| `base` | The existing schema, template, hook, settings, and API analysis. |
| `theme` | WordPress theme roots, style headers, Blockstudio assets, selector scoping, field defaults, repeater bounds. |
| `extreme-theme` | PHPStan `max`, unsafe PHP, output escaping, Tailwind, JavaScript, and Interactivity API checks. |
| `wordpress-render` | A caller-supplied live WordPress render probe on top of `extreme-theme`. |

The command reads its defaults from `blockstudio.json`, so interactive runs, CI,
and commit hooks all use the same configuration:

```json title="blockstudio.json"
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
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

Exit codes are `0` when analysis passes, `1` when PHPStan reports diagnostics,
and `2` for invalid usage or configuration. The wrapper composes its PHPStan
configuration in the system temporary directory and deletes it on exit; it never
writes generated configuration, baselines, or caches into your project. It also
defaults PHPStan to a `1G` memory limit, because a Composer-managed WordPress
project analysed under a typical `128M` CLI ceiling simply dies.

The package now bundles a BC Math compatibility provider, so a fresh install no
longer needs a host-provided `ext-bcmath` extension to run the JavaScript
checks.

### A commit hook Blockstudio maintains

Making that analysis a commit gate used to mean writing and maintaining a shell
script. Now it is one setting plus one sync:

```json title="blockstudio.json"
{
  "phpstan": {
    "preset": "extreme-theme",
    "roots": ["."]
  },
  "githooks": {
    "commit": true
  }
}
```

```bash
vendor/bin/blockstudio-githooks sync
```

Sync writes the generated pre-commit hook and an ownership record inside Git's
common directory, points `core.hooksPath` at the managed directory, and chains
whatever pre-commit hook was configured before. Repeated syncs refresh only the
generated file, so upgrading the package is idempotent. The hook resolves the
active repository and project root at commit time, which means linked checkouts
and paths containing spaces work.

The hook runs `vendor/bin/blockstudio-phpstan` and nothing else. It does not
format, and it never rewrites your files. Set `commit` to `false` and run `sync`
again, or run `vendor/bin/blockstudio-githooks remove`, to restore the recorded
hook path and delete only Blockstudio-owned files. Files it does not own are
never touched, and if someone changed `core.hooksPath` after the hook was
enabled, removal preserves that newer setting.

## Moving old block tags to the portable spelling

Project prefixes and exact aliases still work, and nothing about them is
deprecated. But `<bs:namespace-slug>` is the spelling Blockstudio now uses
everywhere it generates a tag, because it records the real registered block name
and does not depend on a project filter being loaded.

For a project with a `brand` prefix over the `theme-components` namespace and a
`ui` prefix over `bsui`, this shorthand:

```html
<brand-card>
  <ui-input />
</brand-card>
```

means the same thing as this canonical markup:

```html
<bs:theme-components-card>
  <bs:bsui-input />
</bs:theme-components-card>
```

The difference is that the second version still resolves in a project that never
registered those prefixes.

A standalone command can inventory and rewrite the older spelling. It does not
load WordPress, and it does not write unless you pass `--apply`:

```bash
php vendor/blockstudio/blockstudio/bin/migrate-block-tags.php \
  --root="$PWD" \
  --prefix-map=/tmp/prefixes.json \
  --known-blocks=/tmp/blocks.json \
  --report=/tmp/tag-migration.json \
  --dry-run
```

```json title="/tmp/prefixes.json"
{ "brand": ["theme-components"], "ui": ["bsui"] }
```

```json title="/tmp/blocks.json"
["theme-components/card", "bsui/input", "core/paragraph"]
```

The report records the exact legacy-to-canonical mappings with occurrence
counts, before and after hashes per file, and separate lists for tags it could
not resolve. Unknown tags, ambiguous aliases that map to more than one block,
and dynamic tags built from variables are reported rather than guessed.
Documentation examples found inside comments, code fences, `<pre>`, or `<code>`
are report-only, so this post would not be rewritten by running the tool over
it.

Paired, nested, and self-closing markup is handled, including literal tags
inside PHP strings. Only tag names change; attributes are preserved. Unrelated
custom elements are ignored, and running the command again over canonical output
produces no changes. `--apply` refuses to write while unknown, ambiguous, or
dynamic cases remain, unless you deliberately add `--allow-unresolved`.

## Nothing switches on unexpectedly

This is a large change set, so it is worth being precise about what installing
it changes on its own.

**The performance profile defaults to `compat`, which changes nothing.** Head
output, oEmbed, XML-RPC, Heartbeat, image defaults, link prefetching, and lazy
loading all stay exactly as WordPress and your theme left them until you select
`speed` or `strict`, or set an individual child value.

**These stay off unless you enable them:**
`performance.staticPrerender.enabled`,
`performance.staticPrerender.earlyServe`,
`performance.staticPrerender.warm.enabled`,
`performance.measurement.enabled`, `performance.media.lazy`,
`performance.media.skeleton`, `performance.media.metadata`,
`themeDefaults.suppressDirectoryUpdates`,
`themeDefaults.syncPagesInDevelopment`, `githooks.commit`,
`dev.canvas.enabled`, `ui.enabled`, `tailwind.enabled`, and
`blockTags.enabled`. Static prerendering in particular writes no files and
installs no drop-in while it is disabled.

**One default is on: `themeDefaults.titleTag`.** Blockstudio calls
`add_theme_support('title-tag')` unless you set it to `false`. Almost every
modern theme already does this, and the call is harmless when repeated. But if
your theme prints its own `<title>` in a template instead of letting `wp_head`
render it, you will get two title elements. Set it to `false` in that case:

```json title="blockstudio.json"
{
  "themeDefaults": {
    "titleTag": false
  }
}
```

**Canvas and Render are passive.** `Canvas::inventory()` reads registries that
already exist. `Canvas::documents()` and `Render` render only what a caller asks
for. Neither hooks into frontend output, neither registers routes, and neither
runs unless your code calls it. The Canvas admin screen is separate and still
requires both `dev.canvas.enabled` and the `edit_posts` capability.

**Stricter analysis requires naming it.** Installing `blockstudio/phpstan`
enables only the base extension, exactly as before. The canonical command runs
the `base` preset unless a project selects another one, either through
`phpstan.preset` in `blockstudio.json` or with `--preset` on the command line.
The `theme`, `extreme-theme`, and `wordpress-render` layers are never reached by
default, so installing or upgrading the package cannot suddenly hold a project
to PHPStan `max`, output escaping, Tailwind, JavaScript, and Interactivity
checks. If you want those, ask for them.

**Generated hooks require running the generator.** Setting `githooks.commit` to
`true` does nothing on its own. The hook appears only when you run
`vendor/bin/blockstudio-githooks sync`, and removing it is a documented command
rather than a manual cleanup.

## Upgrading from 7.5

Requirements are unchanged: PHP 8.2 or newer, WordPress 6.7 or newer.

7.6.0.x-dev is a development line, not a release. There is no stable 7.6 to
install and no tagged version to pin. If you want to try it in a Composer
project, track the development branch explicitly:

```json title="composer.json"
{
  "require": {
    "blockstudio/blockstudio": "7.6.0.x-dev"
  }
}
```

If your project sets `minimum-stability` to `stable`, add the flag so Composer
accepts a development version for this one package:

```bash
composer require "blockstudio/blockstudio:7.6.0.x-dev@dev"
```

Do this on a copy of the site, with a database you can throw away. A development
branch can change shape between two checkouts.

**Caches rebuild once.** The runtime identity includes the Blockstudio version,
so the first request after upgrading is cold, and old objects are pruned rather
than reused. Nothing needs clearing by hand. Tailwind's old cache under
`wp-content/uploads/blockstudio/tailwind/cache` is no longer read and can be
deleted whenever convenient. If you previously cleared that directory with a
snippet that globs it, replace the snippet with
`Blockstudio\Runtime_Cache::purge('tailwind')`.

**Generated files have owners.** `assets/media.json` is written by
`Media_Metadata_Builder` and should be committed or regenerated as part of a
build. The static prerender route map, the `advanced-cache.php` drop-in, and the
`WP_CACHE` declaration are created only when `earlyServe` is enabled, and
removed when it is disabled or Blockstudio is deactivated. The pre-commit hook
is written by `blockstudio-githooks sync` and removed by `remove`. Everything
else generated lives below `cache.path` and is safe to delete.

**Enable one thing at a time.** A reasonable order:

1. Upgrade with no configuration changes and confirm the site behaves as before.
2. Set `performance.profile` to `speed` and check your head output, feeds, and
   any plugin that relies on oEmbed or XML-RPC.
3. Generate `assets/media.json`, switch a few templates to `bs_media_image()`,
   then turn on `performance.media.lazy`.
4. Enable `performance.staticPrerender` with `earlyServe` off, verify hits with
   `wp bs prerender status`, and only then turn `earlyServe` on.
5. Run `vendor/bin/blockstudio-phpstan --preset theme` by hand until it is
   clean, before putting any preset behind `githooks.commit`.

**Rolling back is per feature.** Set `performance.profile` back to `compat` to
drop the frontend changes. Set `staticPrerender.enabled` to `false` to remove
the map, the drop-in, and the `WP_CACHE` declaration. Set `githooks.commit` to
`false` and run `sync`, or run `blockstudio-githooks remove`, to restore your
previous hook path. Set `cache.enabled` to `false` to disable the persistent
caches entirely. To leave the line, reinstall 7.5 and delete the cache
directory.

**Nothing was removed or renamed.** Prefix and alias block tags still resolve.
`bs_render_block()`, `bs_block()`, `Blockstudio\Pages`, and
`Blockstudio\Patterns` are unchanged and remain the canonical file-backed
content surfaces; the new APIs sit beside them rather than replacing them. The
only relocations are on disk: the Tailwind cache, and the generated assets of
read-only discovery entries, which now land in a `generated` scope under the
cache root instead of uploads.

## Where the details live

- [Canvas](/docs/dev/canvas) covers the admin screen, live mode, and the
  [inventory and document API](/docs/dev/canvas#public-inventory-and-document-api)
  with the full selection rules.
- [Rendering](/docs/blocks/rendering#structured-compositions-and-complete-documents)
  documents the declaration shape, the document options, and exactly which
  assets a document includes.
- [UI Components](/docs/blocks/ui-components#public-inventory-and-examples)
  covers the family inventory and the generated examples.
- [Settings](/docs/general/settings) lists every key, and
  [its performance section](/docs/general/settings#performance) gives the compat
  and speed defaults side by side.
- [Performance Profiler](/docs/dev/perf) documents the
  [shared cache root](/docs/dev/perf#file-backed-caches) and
  [static prerendering](/docs/dev/perf#static-prerendering), including warming,
  graph builds, and early serving.
- [Tailwind CSS](/docs/tailwind#template-composition-helpers) covers
  `bs_tw_merge()` and `bs_tw_variants()`.
- [PHPStan](/docs/dev/phpstan) documents every check, the
  [presets](/docs/dev/phpstan#analysis-presets), the
  [canonical command](/docs/dev/phpstan#canonical-command), and the
  [managed commit hook](/docs/dev/phpstan#managed-commit-hook).
- [Migration](/docs/dev/migration/v7#canonical-block-tags) covers the tag
  migration command and its report.
- [PHP Hooks](/docs/blocks/hooks/php) lists the new cache, runtime identity, and
  static prerender filters.

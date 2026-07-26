---
title: Performance Profiler
description: Built-in profiler for tracking block render times, template phases, and asset processing.
path: "dev/perf"
order: 62
section: "Dev"
meta_title: "Performance Profiler"
meta_description: "Built-in profiler for tracking block render times, template phases, and asset processing."
---

# Performance Profiler

Blockstudio includes a lightweight profiler that tracks render performance
across the entire pipeline. It shows per-block timings, phase breakdowns,
and cache hit rates.

## Activation

Add `?blockstudio-perf` to any frontend URL:

```
https://example.com/my-page/?blockstudio-perf
```

Or enable it permanently in `blockstudio.json`:

```json title="blockstudio.json"
{
  "dev": {
    "perf": true
  }
}
```

Or via filter:

```php
add_filter('blockstudio/settings/dev/perf', '__return_true');
```

The profiler only outputs data for logged-in users with `edit_posts`
capability.

## Output

### Debug panel

A fixed panel appears at the bottom of the page with a table showing every
tracked metric, its duration, and call count.

### Server-Timing headers

Timing data is also sent as `Server-Timing` HTTP headers, visible in the
browser DevTools Network tab under the "Timing" section. This works even
if the page HTML is cached by a CDN.

## Tracked metrics

| Metric | Description |
|--------|-------------|
| `total` | Total Blockstudio processing time |
| `block-tags` | Block tag parser (`<bs:>` and `<block>` replacement) |
| `assets` | Asset discovery and injection |
| `tailwind` | Tailwind CSS compilation |
| `phase:transform` | Attribute transformation (per block render) |
| `phase:template` | PHP/Twig template compilation (per block render) |
| `phase:twig` | Twig-specific compilation (per block render) |
| `phase:components` | Component replacement: InnerBlocks, RichText, useBlockProps (per block render) |
| `block:{name}` | Individual block type render time with call count |

## File-backed caches

Blockstudio also includes persistent file-backed caches. They are enabled by
default through the `cache.enabled` setting and are written to:

```
wp-content/blockstudio/cache/
```

This one configured root owns runtime build payloads, prebuilt block
registration data, editor assets, Tailwind CSS, rendered documents, island
fragments, static HTML, graph indexes, warm queues, and cache diagnostics. This
avoids repeating discovery, parsing, compilation, and rendering work while
keeping all generated runtime state under one host-manageable boundary.

The default deliberately sits outside `uploads`, where hardened hosts may
block PHP cache payloads. Change the location with `cache.path`; relative paths
resolve from `WP_CONTENT_DIR`, while absolute paths support dedicated writable
cache volumes:

```json title="blockstudio.json"
{
  "cache": {
    "enabled": true,
    "path": "cache/blockstudio"
  }
}
```

For environment-specific configuration, use either the setting filter or the
resolved-directory filter:

```php
add_filter('blockstudio/settings/cache/path', function () {
    return '/srv/wordpress-cache/blockstudio';
});

add_filter('blockstudio/cache/dir', function (string $directory): string {
    return WP_CONTENT_DIR . '/cache/blockstudio';
});
```

Changing the path starts with a cold cache. Old files under
`wp-content/uploads/blockstudio/cache` are no longer read and can be removed.

Every scope is nested under a network/blog identity and a complete runtime
identity. Cache entries change when their inputs change, including watched
block files, field files, asset dependencies, settings, the active theme,
active plugins, logical discovery inventories, WordPress, PHP, and Blockstudio
versions. Item-specific dependency hashes isolate changed-only builds from
unrelated objects.

[Logical discovery sources](/docs/dev/discovery-sources) also contribute their
source ID, content fingerprint, and watch inputs. Alternate runtime selections
can add a `blockstudio/cache/context` value to isolate all cache scopes.
Hosts whose tenant boundary differs from WordPress network/blog IDs can filter
`blockstudio/cache/site_key`; the result is sanitized before it becomes a path
segment.

On a warm frontend request, Blockstudio hydrates the runtime registry before it
constructs discovery sources. A valid cache hit therefore skips recursive
source discovery, block construction, field parsing, and repeated cache-key
tree walks.

In `production` and `staging`, a validated watch snapshot is trusted for 20
seconds before Blockstudio stats its files and directories again. `local` and
`development` environments default to zero so source edits are visible
immediately. Change that interval with:

```php
add_filter('blockstudio/cache/watch_debounce', function () {
    return 30;
});
```

The debounce can delay automatic source-change detection by at most the
configured number of seconds. Set it to `0` when a production-like environment
is also used for active file authoring.

Cold cache keys use a single-flight lock. One request builds the payload while
concurrent requests wait for the atomically published result instead of
repeating the same discovery or compilation work. Atomic publication prevents
partial objects. When a refresh fails, a valid stale last-good object remains
available, the failure is observable, and the next request can recover.

Runtime scopes retain 20 published payloads by default. Adjust the cap per
scope when an integration intentionally creates more cache contexts:

```php
add_filter(
    'blockstudio/cache/max_files_per_scope',
    function (int $maximum, string $scope): int {
        return 'runtime' === $scope ? 50 : $maximum;
    },
    10,
    2
);
```

Disable the persistent caches in `blockstudio.json`:

```json title="blockstudio.json"
{
  "cache": {
    "enabled": false
  }
}
```

Or via filter:

```php
add_filter('blockstudio/settings/cache/enabled', '__return_false');
```

The in-memory per-request render cache below is intentionally separate, but its
persistent artifacts use this same filesystem boundary.

### Tailwind Cache

Tailwind's compiled CSS cache now uses the shared runtime root under its
network/site/runtime namespace and `tailwind` scope. It stores up to 1,000
entries for 30 days by default, enough to retain page-level keys for a complete
site instead of evicting them after a small number of routes.

Tailwind also reuses a warm compiler for the same CSS input inside long-lived
processes and persists compiler construction state between processes. Cold
page candidate sets still compile independently, but they do not need to
rebuild the framework parser each time.

Use these filters to change retention:

```php
add_filter('blockstudio/tailwind/cache_max_files', function () {
    return 1500;
});

add_filter('blockstudio/tailwind/cache_max_age', function () {
    return 14 * DAY_IN_SECONDS;
});
```

The minimum age is one hour.

## Static prerendering

Static prerendering is an opt-in anonymous HTML cache. A cacheable response must
be a complete HTML document and may opt out with
`<!-- blockstudio:no-cache -->`. Blockstudio bypasses non-GET requests, query
strings, WordPress admin, REST, Ajax, cron, feeds, search, preview and canvas
headers, nonce-bearing requests, declared dynamic paths, password/comment
cookies, and logged-in requests by default.

```json title="blockstudio.json"
{
  "performance": {
    "staticPrerender": {
      "enabled": true,
      "ttl": 3600,
      "invalidate": "graph",
      "earlyServe": true,
      "serveLoggedIn": false,
      "dynamicPaths": ["/account", "/checkout"],
      "warm": {
        "enabled": true,
        "interval": 900,
        "concurrency": 4,
        "transport": "http"
      }
    }
  }
}
```

Logged-in users can consume an existing hit only when `serveLoggedIn` is true;
personalized responses never create cache entries. Dynamic paths use path
boundaries, so `/account` matches `/account/orders` but not `/accounting`.

### Signature and graph modes

`signature` mode fills an identity-keyed response on a normal anonymous miss.
It is convenient for ordinary WordPress sites and rotates after site-level
changes.

`graph` mode is for deterministic build/deploy integrations. Call
`Static_Prerender_Runtime::build_plan()` with the live source URLs, render only
`affectedUrls`, then record each result with
`persist_built_response()` or `persist_skipped_response()`. The plan compares
shared files and each page's exact dependency hashes. Deleted URLs and
unreferenced HTML are removed by `garbage_collect()`.

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

$targets = array_keys($plan['live']);
Static_Prerender_Runtime::garbage_collect($targets);
```

Optional target host/home arguments rewrite routes for a deployment without
using the first page path as the site root. Batch tools can use
`Static_Prerender_Batch_Renderer::render()` and stable `N/TOTAL` shards. An
`internal` transport is supplied by the generic
`blockstudio/static_prerender/render_internal` filter; optional HTTP fallback
uses the normal warm header.

### Early serving and atomic deployment

With `earlyServe` enabled, Blockstudio owns a small
`wp-content/blockstudio-static-prerender-map.php`, an
`advanced-cache.php` drop-in, and one exact `WP_CACHE` declaration. The drop-in
reads a per-site route map and streams an eligible hit before WordPress,
plugins, or the theme boot.

Blockstudio never overwrites a foreign map, drop-in, or `WP_CACHE` declaration.
Graph artifacts validate every referenced HTML file before the map is swapped;
the previous graph remains active if validation fails. Multisite entries share
the map but are isolated by host, home path, and site ID. Disabling the feature
or deactivating Blockstudio removes only the current site's entry and removes
shared artifacts only after the final owned entry is gone.

Deployment tools can activate a fully rendered graph atomically:

```php
$entry = Static_Prerender_Runtime::artifact_entry($targetUrls);

Blockstudio\Static_Prerender_Early_Serve::install_artifact_entry(
    $entry,
    Static_Prerender_Runtime::cache_root_path()
);
```

Configuration stays authoritative. `install_artifact_entry()` returns `false`
unless both `enabled` and `earlyServe` are true, so a deploy can never install a
map, drop-in, and `WP_CACHE` declaration that the next admin request would
remove again.

### Warming, maintenance, and observability

The warm queue is durable, URL-coalescing, single-flight, and recovers timed-out
claims. Content and site changes force a replacement job even when the current
signature is unchanged; interval passes enqueue only stale work.

```bash
wp bs prerender warm
wp bs prerender status
wp bs prerender purge
```

`status` reports the active identity, files, bytes, graph records, queue state,
and per-scope hit/miss/build/failure counters. Observe individual response
outcomes with `blockstudio/static_prerender/outcome` and all shared cache
outcomes with `blockstudio/cache/outcome`.

## In-Process Batch Rendering

Long-lived exporters and static-site builders can render several WordPress
documents in one PHP process without rebuilding Blockstudio registrations.
Reset request-scoped counters, assets, island state, page layout state, and
Tailwind's per-document generation between documents:

```php
foreach ($routes as $route) {
    Blockstudio\Batch_Render::reset();
    $html = render_route($route);

    write_static_page($route, $html);
}
```

`Batch_Render::reset()` keeps discovery and registered block data warm. It is
not a cache clear.

To collect source dependencies for each rendered block, observe
`blockstudio/render/dependencies`:

```php
$dependencies = [];

add_filter(
    'blockstudio/render/dependencies',
    function (
        array $paths,
        string $name,
        array $block,
        bool $is_editor,
        bool $is_preview
    ) use (&$dependencies): array {
        $dependencies[$name] = array_values(array_unique([
            ...($dependencies[$name] ?? []),
            ...$paths,
        ]));

        return $paths;
    },
    10,
    5
);
```

The reported paths include the selected render template, discovered template
files, and registered block asset files. A collector can add separately loaded
files to its own dependency graph.

## Render cache

Self-closing block tags (no inner content) are cached in memory during
page rendering. When the same block with identical attributes appears
multiple times, subsequent renders are served from cache. The cache is
per-request and cleared after the page finishes rendering.

The profiler shows reduced render call counts when the cache is active,
making it easy to verify caching is working.

---
title: Static Prerendering
description: Serve anonymous pages as static HTML, optionally before WordPress boots.
path: "production/static-prerendering"
order: 57
section: "Production"
meta_title: "Static Prerendering"
meta_description: "Serve anonymous pages as static HTML, optionally before WordPress boots."
---

# Static Prerendering

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

While the feature is disabled it writes no files, installs no drop-in, and
registers no request or admin hooks. `earlyServe: false` likewise registers no
Early Serve admin callback, and `warm.enabled: false` registers no cron callback
or schedule. Turning either feature off removes previously owned state once;
subsequent requests do no filesystem or cron cleanup checks.

## Signature and graph modes

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
`Static_Prerender_Batch_Renderer::render()` and stable `N/TOTAL` shards;
`Static_Prerender_Batch_Renderer::parse_shard()` and `select_shard()` back the
shard option, so a caller can compute the same partition the renderer would.
Inside a build, `Static_Prerender_Batch_Renderer::is_rendering()` answers
whether the current request is a batch render rather than a visitor, so a
template can skip work that should not run during a build. An `internal`
transport is supplied by the generic
`blockstudio/static_prerender/render_internal` filter; optional HTTP fallback
uses the normal warm header.

## Early serving and atomic deployment

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

Installing an artifact promotes its documents into a dedicated artifact scope
that retention pruning never touches, and files the map references are immune
to eviction wherever they live, so live render traffic cannot age mapped
routes out of early serving.

Mapped graph routes always use the activated artifact. When a safe anonymous
route is not in that map but WordPress has since rendered and persisted it,
Early Serve falls back to the identity-keyed runtime store before booting
WordPress. This supports generated or long-tail routes outside a deliberately
bounded deployment graph. The fallback uses the activated graph identity and
the same host, path normalization, TTL, cookie, header, query-string, dynamic
path, feed, and search bypass rules as mapped routes.

## Warming, maintenance, and observability

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

## Teardown

Installations without a plugin deactivation hook, such as Composer-bundled
setups, can remove everything the feature owns in one command:

```bash
wp bs teardown
```

It removes the cron events and the static prerender state an installation
owns: the current site's map entry, and the shared drop-in and `WP_CACHE`
declaration once the final owned entry is gone. Configuration files are left
untouched, so re-enabling the feature starts cleanly.

## Adopting it in production

A reasonable adoption order:

1. Set `performance.profile` to `speed`, then check head output, feeds, and any
   plugin relying on oEmbed or XML-RPC.
2. Generate `assets/media.json`, move templates to `bs_media_image()`, then turn
   on `performance.media.lazy`.
3. Enable `performance.staticPrerender` with `earlyServe` off, verify hits with
   `wp bs prerender status`, then turn `earlyServe` on.

Rolling back is per feature: setting `staticPrerender.enabled` to `false`
removes Blockstudio-owned Early Serve state and warm schedules once, then leaves
the prerender runtime unregistered. Setting `performance.profile` back to
`compat` undoes the profile. Each leaves the other layers in place.

> **[Performance](/docs/production/performance)**
>
> The profiles, the profiler, and the render techniques underneath.

> **[Caching](/docs/production/caching)**
>
> The one cache directory every generated artifact lives under.

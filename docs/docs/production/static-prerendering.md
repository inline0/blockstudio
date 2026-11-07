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
strings, WordPress admin, REST, Ajax, cron, feeds, search results below the
search base at the site's home path (such as `/search/term/`), preview and
canvas headers, nonce-bearing requests, declared dynamic paths, password/comment
cookies, and logged-in requests by default. A page whose path only contains a
`search` or `preview` segment deeper down, such as `/docs/blocks/preview/`, is
cached like any other page.

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
      "replayHeaders": ["Link"],
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

## Switching it off per environment

Every value above can also be set with a settings filter. Blockstudio boots
before the active theme, from a plugin or from the theme's Composer autoload,
so it resolves its runtime settings again once the theme has loaded and decides
on the static prerender hooks at `after_setup_theme`. A filter added by a plugin
or in the theme's `functions.php` therefore takes effect:

```php title="functions.php"
add_filter('blockstudio/settings/performance/staticPrerender/enabled', function ($enabled) {
    return 'local' === wp_get_environment_type() ? false : $enabled;
});
```

Switching the feature off this way works like switching it off in
`blockstudio.json`: no request, content, admin, or cron hooks are registered,
and a site that was served early before has its Early Serve entry removed once.
A filter added later, for example on `init`, does not change which hooks are
registered.

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

### What graph mode tracks

With the default HTTP transport, `Static_Prerender_Batch_Renderer::render()`
sends each warm request a signed token, and the request reports the files it
read: the template WordPress loaded, every rendered Blockstudio block's
template, template files, and asset files, files added through
`blockstudio/render/dependencies`, and, for a synced page, its definition,
template, content, and layout files. The report is written to the site's cache
directory, which the renderer and the web server already share for the rendered
HTML. On the next build `build_plan()` hashes those files
again and marks only the routes whose files changed, together with new routes,
routes past their `ttl`, and every route when a shared file changes
(`blockstudio.json`, `theme.json`, `style.css`, `functions.php`, `header.php`,
`footer.php`, everything under `inc/`, `parts/`, and `templates/`, and the
sources of Blockstudio's bundled UI assets). The shared signature hashes
sources, never compiled output, so the `_dist` files that a build's first
render compiles do not move it: `artifact_entry()` derives the build id from
the same signature that `build_plan()` saw before rendering.

Content that lives only in the database is not a file and is not tracked:
posts edited in the admin, options, generated rows, or prices. A build that has
to pick such changes up passes `$force` to `build_plan()` or renders the
affected URLs itself. The `internal` transport records the dependencies its
filter returns.

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

`install_artifact_entry()` keeps the entry's `build_id`, so a deploy can compare
it with the entry `installed_map_entries()` returns afterwards. The map is a PHP
file read with `include`, and every write drops it from the opcode cache, so
that read sees the new entry in the same process even when `opcache.enable_cli`
is on.

Installing an artifact promotes its documents into a dedicated artifact scope
that retention pruning never touches, and files the map references are immune
to eviction wherever they live, so live render traffic cannot age mapped
routes out of early serving. A route key is not fully content-addressed, so a
re-render can change a document without changing its key; installation
compares bytes and replaces a differing artifact document atomically, leaves
identical documents untouched, and keeps an artifact document whose store copy
has disappeared. `Static_Prerender_Early_Serve::last_promotion()` reports how
many routes were promoted, replaced, skipped, or kept by the last install, so a
deploy can log it.

A stored document pins the build assets it links by URL, and those files
keep their own retention, so one can outlive the other. Before serving, both
the PHP cache and the drop-in check that a linked content-hashed Tailwind
stylesheet still exists on disk. A document whose stylesheet is gone is handed
to WordPress instead of served unstyled, which republishes the stylesheet and
the document together. The same check runs before a response is stored, so a
document that could not be served is never written.

A request whose `Accept` header contains `text/markdown` bypasses both the
PHP store and the drop-in, because stored documents are HTML and a page may
answer that request with its [source Markdown](/docs/pages-and-patterns/pages#raw-markdown).
Served documents therefore send `Vary: Accept`. Updating Blockstudio rewrites
an older drop-in on the next map write.

Mapped graph routes always use the activated artifact. When a safe anonymous
route is not in that map but WordPress has since rendered and persisted it,
Early Serve falls back to the identity-keyed runtime store before booting
WordPress. This supports generated or long-tail routes outside a deliberately
bounded deployment graph. The fallback uses the activated graph identity and
the same host, path normalization, TTL, cookie, header, query-string, dynamic
path, and feed bypass rules as mapped routes, and it only finds documents the
runtime stored, which never include search results.

## Response headers

A stored document is replayed without the request that rendered it, so a
header a theme or plugin sends with `header()` would be missing from every
hit. Blockstudio stores the headers named in `replayHeaders` with each document
and sends them again from both the PHP cache and the early-serve drop-in. The
default, `["Link"]`, covers headers such as a Markdown alternate
(`rel="alternate"; type="text/markdown"`), `rel="describedby"`, or
`rel="canonical"`. Names match case-insensitively, and the setting filter
changes the list like any other value:

```php title="functions.php"
add_filter('blockstudio/settings/performance/staticPrerender/replayHeaders', function (array $headers): array {
    $headers[] = 'X-Robots-Tag';

    return $headers;
});
```

Set `replayHeaders` to `[]` to disable header replay. An explicit list replaces
the default, so include `Link` when adding other header names if you still
want to replay link headers.

Headers are captured in the process that renders the page: from
`headers_list()` when a live miss is stored, and from the warm request itself
when a build or the warmer renders over HTTP, so a header the web server adds
on its own, and adds again to every served document, is never stored. Only
when an HTTP render writes no report are the headers of its response used. The
`internal` transport returns them as `headers` in its result, and
`persist_built_response()` takes them as its last argument, either as
`Name: value` lines or as a name to value map.

They are written to `<key>.headers.json` beside the document, follow it
through artifact promotion, garbage collection, and retention pruning, and are
removed when a re-render sends none. Cookie, caching, and transport headers are
never stored or replayed, even when listed: `Set-Cookie`, `Cache-Control`,
`Expires`, `Pragma`, `Age`, `ETag`, `Last-Modified`, `Vary`, `Content-Type`,
`Content-Length`, `Content-Encoding`, `Transfer-Encoding`, `Location`,
`Status`, `Date`, and the hop-by-hop and authentication headers. A served
document sets its own `Content-Type` and `Vary`.

Stored headers are checked against the list again when they are sent. The PHP
cache reads the current setting; the drop-in reads the copy in the site's map
entry, which the next artifact install or map sync refreshes. Documents stored
before an upgrade have no header file and are served exactly as before until
they are rendered again.

## Warming, maintenance, and observability

The warm queue is durable, URL-coalescing, single-flight, and recovers timed-out
claims. Content and site changes force a replacement job even when the current
signature is unchanged; interval passes enqueue only stale work. Each pass,
scheduled or through `wp bs prerender warm`, renders up to `warm.concurrency`
jobs. `graph` mode turns the per-URL warmer off, because graph artifacts are
built and activated as one unit.

```bash
wp bs prerender warm
wp bs prerender status
wp bs prerender purge
wp bs prerender map status
wp bs prerender map prune --dry-run
```

`status` reports the active identity, files, bytes, graph records, queue state,
the early-serve map report, and per-scope hit/miss/build/failure counters.

The early-serve map is shared by every site of an installation.
`map status` (and `Static_Prerender_Early_Serve::map_report()`) lists entries
that early serving can no longer use: `orphaned` entries whose site no longer
exists and `dead` entries whose directory is gone, plus the number of `missing`
route documents in the remaining entries. `map prune`
(`Static_Prerender_Early_Serve::prune_map()`) removes orphaned and dead
entries under the map lock and reports each with its reason; `--dry-run` only
reports. Removing the last entry removes the map, the drop-in, and the
`WP_CACHE` declaration. Deleting a site from a multisite network removes its
entries right away, and cache cleanup never collects the namespace an
activated artifact is served from, so entries only go dead when their files are
removed by other means.

Observe individual response
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

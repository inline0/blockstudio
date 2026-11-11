---
title: Caching
description: The one file-backed cache directory that owns every generated runtime artifact.
path: "production/caching"
order: 58
section: "Production"
meta_title: "Caching"
meta_description: "The one file-backed cache directory that owns every generated runtime artifact."
---

# Caching

Blockstudio includes persistent file-backed caches. They are enabled by
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

## Identity and invalidation

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

The runtime payload also stores resolved block asset paths, sizes, timestamps,
and module stylesheet lists. Warm requests reuse that metadata instead of
repeating asset-output lookups and module-directory scans for each rendered
block. Asset URL and disable filters still run for the current request, and
`assets.inlineLimit` keeps its page-wide budget and cascade ordering. No asset
output metadata is collected when `assets.enqueue` or persistent caching is
disabled. A stale last-good build falls back to resolving its assets from disk.

Unchanged resolved settings also reuse their fingerprint within the request.
Reloading or changing settings invalidates it; mutable object or reference
values are rehashed rather than reusing a potentially stale fingerprint.

### Request options

Blockstudio stores the small flags and versions read on every request as
autoloaded WordPress options. Missing rows are initialized with their existing
fallback values during activation or the version migration. With no persistent
object cache and small collection configuration, these reads use WordPress's
already-loaded options instead of a separate option-priming query.

Collection manifests and template mappings follow WordPress's size-aware
autoload policy, including when their values grow or shrink. Large configurations
stay out of the autoloaded options. On sites with a persistent object cache, the
frequently changed populate-cache version stays separate to avoid repeatedly
invalidating the shared autoloaded-options cache. Options that are not autoloaded
are still primed together with at most one query, rather than queried one by one.

The migration runs automatically for plugin, must-use plugin, and Composer
installs, including packages loaded by the theme. It updates existing autoload
metadata without deleting cached configuration. No manual migration is needed.

### File watching and payloads

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
configured number of seconds. Required compiled assets, cached nonempty raw
assets, and module stylesheets are still checked on every cache hit. Deleting
one invalidates the runtime payload immediately instead of serving stale asset
metadata until the window expires. Set the debounce to `0` when a
production-like environment is also used for active file authoring.

Cache payloads are guarded PHP files. Small payloads use plain arrays when the
writing process has active OPcache shared memory and the destination is not
excluded. OPcache can then reuse the compiled array on later includes; modifying
the returned data still uses PHP's normal copy-on-write behavior.

Without shared-memory OPcache, payloads remain compressed serialized data. This
also applies when OPcache is full, its API is unavailable or restricted, or its
blacklist or `opcache.max_file_size` excludes the file. File-cache-only OPcache
does not select the plain-array format. See the [PHP OPcache configuration](https://www.php.net/manual/en/opcache.configuration.php)
for those host-level controls.

The default plain-array format is capped at 1 MiB, measured against both the
serialized data and generated PHP source. Larger payloads remain compressed to
avoid large-array compilation costs when a CLI process without OPcache reads
a cache warmed by web workers.
Small plain files still need parsing on such a reader, and newly written files
may need compilation during `opcache.file_update_protection`.

The `blockstudio/cache/compress` filter overrides the format per scope, for
example when WP-CLI warms cache files for OPcache-enabled web workers. Returning
`false` also overrides the size guard: only do so when every reader can handle
the generated PHP file. If zlib is unavailable, plain PHP remains the fallback.

## Concurrency and retention

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

Retention never evicts a file the installed early-serve map references, and
the `blockstudio/cache/protected_paths` filter marks additional paths that
pruning must leave alone.

Before a PHP cache file is pruned or a cache tree is deleted, Blockstudio
force-invalidates that file in OPcache when its API is available. This prevents
deleted generations from remaining valid compiled scripts. Invalidation applies
to the calling process's OPcache instance: a CLI purge cannot clear a separate
PHP-FPM cache. OPcache reclaims invalidated memory through its normal restart
cycle, not necessarily immediately on deletion.

Unused namespaces are collected automatically instead of accumulating on every
plugin activation and PHP or WordPress update. A deployment snippet that used
to glob the old Tailwind cache directory should call
`Blockstudio\Runtime_Cache::purge('tailwind')` instead.

A namespace retires when the runtime identity moves, for example after a
WordPress core update. The first cleanup sweep that finds it retired writes a
`.retired` marker into it, and the tree is collected once `cache.namespaceGrace`
seconds (a day by default) have passed since then. A tree that is written
again in the meantime starts its grace over, and neither the current namespace
of a scope nor a namespace the early-serve map still serves an activated
artifact from is collected; the grace of the latter starts once the next
activation moves its map entry elsewhere. Pages cached outside WordPress keep
linking the content-hashed stylesheets they were rendered with, so a site that
serves `tailwind.output: "link"` pages through a CDN or long browser cache
should raise the grace to the longest time such a copy can live:

```json title="blockstudio.json"
{
  "cache": {
    "namespaceGrace": 777600
  }
}
```

When an upgraded installation contains the pre-7.6 flat
`cache/runtime` directory, Blockstudio atomically renames it out of the active
path. A WP-Cron job then removes at most 500 filesystem entries per pass. This
keeps old payload and `.build.lock` trees from consuming disk without making
the request that detects a large directory recursively delete it. The cleanup
runs only while `cache.enabled` is active; change its batch size with
`blockstudio/cache/legacy_cleanup_batch_size` when a host needs smaller or
larger passes.

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

The in-memory per-request render cache described under
[Performance](/docs/production/performance#render-cache) is intentionally
separate, but its persistent artifacts use this same filesystem boundary.

## Draining a legacy lock backlog

Releases before 7.6.14 left one `.build.lock` file behind for every content
change, and a busy site can carry hundreds of thousands of them. After
upgrading, WP-Cron removes them in bounded batches on its own. To drain the
backlog immediately and hand your host a number, run the CLI:

```bash
wp bs cache status
wp bs cache cleanup
```

`status` counts the remaining legacy locks and reports when the next batch
runs. `cleanup` runs batches until nothing idle remains, then collects cache
namespaces whose grace has passed. Locks newer than an hour or still
held by a running request are left for the next pass. Never delete lock files
by hand while the site is serving traffic; a held lock is the election that
keeps concurrent requests from building in parallel.

`wp bs cache clear` purges the whole runtime cache for the current site, or
one scope across every namespace with `--scope=runtime`. The next request
rebuilds cold, so run it at low traffic.

## Tailwind cache

Tailwind's compiled CSS cache uses the shared runtime root under its
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

Cache files are content-hashed, and a cached page links one by URL, so eviction
and a stored page can disagree about which files still matter. Prerendered
documents are checked against their linked stylesheet before they are served,
so an evicted file costs one re-render rather than an unstyled page. Raising
`cache_max_files` past the number of distinct page-level keys avoids the
re-render entirely.

> **[Static Prerendering](/docs/production/static-prerendering)**
>
> The anonymous HTML cache that lives under this same root.

---
title: Caching
description: The one file-backed cache directory that owns every generated runtime artifact.
path: "production/caching"
order: 72
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

Unused namespaces are collected automatically instead of accumulating on every
plugin activation and PHP or WordPress update. A deployment snippet that used
to glob the old Tailwind cache directory should call
`Blockstudio\Runtime_Cache::purge('tailwind')` instead.

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

> **[Static Prerendering](/docs/production/static-prerendering)**
>
> The anonymous HTML cache that lives under this same root.

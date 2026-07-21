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
wp-content/uploads/blockstudio/cache/
```

These caches store runtime build payloads, prebuilt block registration data,
and resolved editor asset payloads. This avoids repeating block discovery,
field parsing, asset dependency resolution, and editor asset assembly work on
every request.

Cache entries are invalidated when their inputs change, including watched block
files, field files, asset files and dependencies, settings, active plugins,
WordPress, PHP, and Blockstudio versions.

[Logical discovery sources](/docs/dev/discovery-sources) also contribute their
source ID, content fingerprint, and watch inputs. Alternate runtime selections
can add a `blockstudio/cache/context` value to isolate all cache scopes.

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

This cache is separate from the per-request render cache below and from the
Tailwind CSS cache.

## Render cache

Self-closing block tags (no inner content) are cached in memory during
page rendering. When the same block with identical attributes appears
multiple times, subsequent renders are served from cache. The cache is
per-request and cleared after the page finishes rendering.

The profiler shows reduced render call counts when the cache is active,
making it easy to verify caching is working.

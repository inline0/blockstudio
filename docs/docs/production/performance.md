---
title: Performance
description: Production frontend defaults, the built-in profiler, and the render techniques underneath.
path: "production/performance"
order: 56
section: "Production"
meta_title: "Performance"
meta_description: "Production frontend defaults, the built-in profiler, and the render techniques underneath."
---

# Performance

Production performance in Blockstudio is one profile switch, a set of
measurement tools to verify what it does, and two render techniques that keep
repeated work out of the page.

## Performance profiles

`performance.profile` turns production frontend defaults on in one place:

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

Every child value overrides the profile, as `preload.links` does above.
Removing core frontend assets is the one to test first, because a page that
depends on them breaks visibly. `Runtime_Settings::current()` exposes the
resolved profile, and the full option table lives in the
[settings reference](/docs/general/settings#performance).

Measurements are available programmatically:

```php title="functions.php"
use Blockstudio\Performance_Measurement;

$snapshot = Performance_Measurement::snapshot();
```

When `measurement.headers` is enabled, Blockstudio sends
`X-Blockstudio-Performance-Profile`,
`X-Blockstudio-Performance-Config`, and optionally
`X-Blockstudio-Performance-Time`. The
`blockstudio/performance/measurement_enabled` action receives the resolved
runtime settings.

## Profiler

Blockstudio includes a lightweight profiler that tracks render performance
across the entire pipeline. It shows per-block timings, phase breakdowns,
and cache hit rates.

### Activation

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

### Output

#### Debug panel

A fixed panel appears at the bottom of the page with a table showing every
tracked metric, its duration, and call count.

#### Server-Timing headers

Timing data is also sent as `Server-Timing` HTTP headers, visible in the
browser DevTools Network tab under the "Timing" section. This works even
if the page HTML is cached by a CDN.

### Tracked metrics

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

> **[Static Prerendering](/docs/production/static-prerendering)**
>
> Serve anonymous pages as static HTML, optionally before WordPress boots.

> **[Caching](/docs/production/caching)**
>
> The persistent file-backed caches behind every request.

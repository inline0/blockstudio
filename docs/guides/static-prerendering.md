---
title: Serving WordPress as Static HTML
description: Turn on Blockstudio's static prerender, verify what it caches, keep logged-in markup safe, and put a CDN in front of it.
path: "static-prerendering"
order: 16
section: "Guides"
meta_title: "Serving WordPress as Static HTML"
meta_description: "Turn on Blockstudio's static prerender, verify what it caches, keep logged-in markup safe, and put a CDN in front of it."
---

# Serving WordPress as Static HTML

Most pages on a marketing site or documentation site are the same for every
anonymous visitor. WordPress still boots, loads every plugin, runs the theme,
and renders the blocks for each request. A page cache plugin can help, but it
is one more moving part with its own rules about what is safe to store.

Blockstudio ships an opt-in static prerender. It stores complete anonymous HTML
documents under its own cache directory and, if you allow it, serves them from
an `advanced-cache.php` drop-in before WordPress boots. Everything is
configured in `blockstudio.json`.

This guide turns the feature on step by step, shows how to check what is and
is not cached, explains the one rule that keeps logged-in visitors safe, and
ends with a CDN setup that only caches what the origin marks as cacheable.

## How a request is served

With the prerender enabled, an eligible request ends in one of three ways:

1. **Early hit.** The drop-in finds a stored document in its route map and
   streams it before WordPress loads. The response carries
   `X-Blockstudio-Static-Prerender: HIT-EARLY`.
2. **Hit.** WordPress boots, and at `template_redirect` Blockstudio finds a
   fresh stored document and sends it instead of rendering the template. The
   response carries `X-Blockstudio-Static-Prerender: HIT`.
3. **Miss.** WordPress renders the page normally. If the visitor is anonymous
   and the result is a complete HTML document with status 200, Blockstudio
   stores it for the next request.

Requests that are not eligible never touch the cache at all.

## Step 1: Enable the profile and the cache

Start with the performance profile and the prerender, with early serving off:

```json title="blockstudio.json"
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "performance": {
    "profile": "speed",
    "staticPrerender": {
      "enabled": true,
      "ttl": 86400,
      "earlyServe": false,
      "dynamicPaths": ["/account", "/checkout", "/login"],
      "warm": {
        "enabled": true,
        "interval": 900,
        "concurrency": 4
      }
    }
  }
}
```

What each part does:

- `profile: "speed"` turns on the production frontend defaults: head noise,
  oEmbed discovery, XML-RPC, and generic core frontend assets are removed, and
  same-origin links are prefetched on intent. Removing core frontend assets is
  the setting to test first, because a page that depends on them breaks
  visibly. Any child value overrides the profile.
- `staticPrerender.enabled` registers the runtime. While it is `false`, the
  feature writes no files and registers no request hooks.
- `ttl` is the maximum age of a stored document in seconds (default `86400`).
- `dynamicPaths` lists path prefixes that must always render live.
- `warm` schedules a background warmer that re-renders stale public URLs every
  `interval` seconds.

The `$schema` line covers every key, so an editor with JSON schema support
flags a mistake such as `"ttl": 0` (the minimum is `1`).

## Step 2: Know what is eligible

Blockstudio is conservative about what it stores. These requests always
bypass the cache:

- anything other than `GET`
- any URL with a query string
- `wp-admin`, `wp-json`, `wp-login.php`, `xmlrpc.php`, Ajax, and cron
- feeds, search, and preview paths
- requests that send an `X-WP-Nonce` header
- paths under `dynamicPaths`
- visitors with a `wordpress_logged_in_*`, `wp-postpass_*`, or
  `comment_author_email_*` cookie, unless `serveLoggedIn` is on

Dynamic paths match on path boundaries. With the configuration above:

| Request | Bypassed |
| --- | --- |
| `/pricing/` | no |
| `/account/orders/` | yes |
| `/accounting/` | no |
| `/checkout` | yes |
| `/pricing/?utm_source=x` | yes |
| `/blog/feed/` | yes |

On the storing side, only a complete document is kept: the response must start
with `<!doctype html` or `<html`, must have status 200, and must not contain
the opt-out marker. A template that should never be stored can print the
marker anywhere in the page:

```html
<!-- blockstudio:no-cache -->
```

Error pages are never stored, so a themed 404 can never be replayed as a
success.

## Step 3: Verify hits

Visit a few pages in a private window, then ask Blockstudio what it holds:

```bash
wp bs prerender status
```

```json
{
    "enabled": true,
    "invalidate": "signature",
    "identity": "e6cdd3fcda14ad6ded85a763bd01a33b",
    "cacheRoot": ".../wp-content/blockstudio/cache/sites/.../static-prerender",
    "files": 0,
    "bytes": 0,
    "records": 0,
    "queue": {
        "pending": 0,
        "processing": 0,
        "done": 0,
        "unique": 0,
        "bytes": 0
    },
    ...
    "diagnostics": []
}
```

`files` and `bytes` grow as documents are stored, `queue` shows the warmer's
backlog, and `diagnostics` carries per-scope hit, miss, build, and failure
counters. To check a single page, request it twice as an anonymous visitor and
look for the header:

```bash
curl -s -o /dev/null -D - https://example.com/pricing/ | grep -i x-blockstudio-static-prerender
```

The second response carries `X-Blockstudio-Static-Prerender: HIT`. Use a
`GET` as above: `curl -I` sends a `HEAD` request, which always bypasses the
cache.

## Step 4: Serve before WordPress boots

Once hits look right, set `"earlyServe": true`. Blockstudio then owns three
small pieces of state: a `wp-content/advanced-cache.php` drop-in, a shared
`wp-content/blockstudio-static-prerender-map.php` route map, and a
`WP_CACHE` declaration in `wp-config.php`. They are installed on the next
admin request.

A few rules keep this safe on shared hosting and multisite:

- Blockstudio never overwrites a drop-in, map, or `WP_CACHE` line it does not
  own. If another caching plugin already owns `advanced-cache.php`, early
  serving stays off.
- If `WP_CACHE` is already defined as `true`, `wp-config.php` is left alone.
  On hosts where `wp-config.php` is not writable, define it yourself.
- Before serving, both the drop-in and the PHP path check that the
  content-hashed Tailwind stylesheet a document links still exists. If it does
  not, the request goes to WordPress instead of being served unstyled.
- Multisite entries share one map but are isolated by host, home path, and
  site ID.
- Response headers named in `replayHeaders`, `Link` by default, are stored
  with each document and sent again on every hit, so a `Link` header a page
  sends with `header()` survives early serving. Cookie and caching headers are
  never replayed.

Early hits carry `X-Blockstudio-Static-Prerender: HIT-EARLY`. Turning
`earlyServe` or `enabled` off removes the state Blockstudio owns once, and
`wp bs teardown` removes it explicitly for Composer installs that have no
deactivation hook.

## Logged-in visitors and identical markup

By default, logged-in visitors always get a live render. Setting
`"serveLoggedIn": true` lets them consume an existing stored document as well,
which makes the site as fast for editors and customers as it is for anonymous
visitors.

Personalized responses never create cache entries: a logged-in miss renders
live and is not stored. The consequence is easy to overlook. With
`serveLoggedIn` on, a logged-in visitor gets the anonymous document whenever
one exists, and a personalized live render only when it does not. Any template
branch on the current user shows up only on misses, so the same page changes
between visits, and the personalized branch is effectively dead on cached
routes.

The rule that follows: on every route outside `dynamicPaths`, logged-in markup
must be byte-identical to anonymous markup. That means no
`is_user_logged_in()` or `wp_get_current_user()` branches in templates that
render on static routes:

```php title="header.php"
<?php // Do not do this on a static route. ?>
<?php if ( is_user_logged_in() ) : ?>
  <a href="/account/">Hi, <?php echo esc_html( wp_get_current_user()->display_name ); ?></a>
<?php else : ?>
  <a href="/login/">Sign in</a>
<?php endif; ?>
```

There are two clean ways out. Move the route into `dynamicPaths` when the
whole page is personal, such as an account dashboard. Or keep the page static
and render the personal part as a dynamic block island, which ships a
cache-safe placeholder in the stored HTML and fetches the real fragment in the
visitor's own request:

```php title="header.php"
<?php echo bs_block( array( 'name' => 'acme/greeting' ) ); ?>
```

The [Personalized Fragments on Cached Pages](/guides/block-islands) guide
builds that block.

## Signature or graph invalidation

`invalidate` chooses how stored documents expire.

| Aspect | `signature` (default) | `graph` |
| --- | --- | --- |
| Who fills the cache | anonymous misses and the warmer | your build step, plus anonymous misses |
| What expires entries | TTL, post saves, site-level changes | a new build, with TTL as an upper bound |
| Background warmer | available | off |
| Suits | ordinary sites | sites with a deploy pipeline |

`signature` mode needs no tooling. Saving a post purges or re-warms the home
page and that post's permalink, and site-level changes such as switching the
theme rotate the cache identity.

`graph` mode is for deterministic deploys. A build computes a plan with
`Static_Prerender_Runtime::build_plan()`, renders only the affected URLs,
records each result, and activates the finished set atomically with
`Static_Prerender_Early_Serve::install_artifact_entry()`. If validation of
any referenced document fails, the previous set stays active. Mapped routes
are served from the activated artifact until the next build replaces it, so
content edited in wp-admin reaches those routes with the next deploy. The
per-URL warmer is off in this mode because artifacts are built and activated
as one unit. A typical production shape looks like this:

```json title="blockstudio.json"
{
  "performance": {
    "profile": "speed",
    "staticPrerender": {
      "enabled": true,
      "invalidate": "graph",
      "ttl": 2592000,
      "earlyServe": true,
      "serveLoggedIn": true,
      "dynamicPaths": [
        "/account",
        "/cart",
        "/checkout",
        "/login",
        "/reset-password"
      ]
    }
  }
}
```

The 30-day `ttl` only bounds how old a served document may get. Freshness
comes from the deploy that rebuilds the graph. The build API, sharding, and
the internal render transport are covered in the
[Static Prerendering reference](/docs/production/static-prerendering#signature-and-graph-modes).

## Putting a CDN in front

The prerender already tells you which responses are safe to share: exactly
the ones that carry a `HIT` header. Let the origin turn that into a cache
header and let the CDN follow the origin. With nginx in front of PHP-FPM:

```nginx title="nginx.conf"
map $upstream_http_x_blockstudio_static_prerender $edge_cache_control {
    "~^HIT"  "public, s-maxage=604800";
    default  "";
}

server {
    listen 80;
    server_name example.com;
    root /var/www/html;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        add_header Cache-Control $edge_cache_control;
    }
}
```

The regular expression matches both `HIT` and `HIT-EARLY`. For every other
response the variable is empty, and nginx does not add a header with an empty
value, so misses, dynamic paths, and logged-in renders leave the origin
without a shared cache lifetime.

On the CDN side:

- Cache HTML only when the origin sends a cache lifetime. Disable any default
  edge TTL for responses without one.
- Bypass the edge for the same cookies Blockstudio treats as personal
  (`wordpress_logged_in_`, `wp-postpass_`, `comment_author_email_`) and for
  your `dynamicPaths`, `wp-admin`, and `wp-json`.
- Purge the CDN after every deploy that changes pages, templates, or assets.
  The origin cache rotates on its own; the edge does not know it should.

If you use Tailwind with `"output": "link"`, the stylesheet URL is content
hashed, so that path can be served with a long-lived immutable cache header.

## Maintenance commands

```bash
wp bs prerender warm
wp bs prerender status
wp bs prerender purge
wp bs teardown
```

`warm` enqueues stale public URLs and renders up to `warm.concurrency` of them
per run, printing a JSON summary. `purge` removes the current site's stored
documents and queue. `teardown` removes the cron events and the early-serve
state this installation owns, and leaves configuration files untouched.

Roll out in this order: profile first, then the prerender with `earlyServe`
off, then early serving once `status` and the headers look right. Each layer
can be rolled back on its own by setting its value back.

## Next steps

- [Static Prerendering reference](/docs/production/static-prerendering): graph builds, artifact activation, retention, and observability hooks.
- [Performance](/docs/production/performance): what the `speed` profile changes and how to measure it.
- [Caching](/docs/production/caching): the cache directory every stored document lives under.
- [Personalized Fragments on Cached Pages](/guides/block-islands): per-visitor content on static routes.
- [Deploying a Blockstudio Site](/guides/deploying): where pages sync, purge, and warm fit in a release.

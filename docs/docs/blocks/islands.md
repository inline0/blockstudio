---
title: Islands
description: Split blocks into cache-safe placeholders and client-rendered fragments.
path: "blocks/islands"
order: 42
section: "Blocks"
subsection: "Client Behavior"
meta_title: "Islands"
meta_description: "Split blocks into cache-safe placeholders and client-rendered fragments."
---

# Islands

Block islands let a block keep the normal Blockstudio rendering pipeline while
moving request-specific output out of the initial HTML response.

This is useful when a page should be cached aggressively, but one block still
depends on the current visitor, session, cart, permissions, or another live
request context.

Blockstudio blocks are still dynamic WordPress blocks. Islands do not turn them
into precompiled static HTML. Instead, Blockstudio changes what is rendered on
the first request:

- hydrated islands render their normal cache-safe HTML and receive a mount event
- dynamic islands render a cache-safe placeholder first, then fetch the real
  fragment from the visitor's browser

## Modes

### Hydrated Islands

Use a hydrated island when the server output is safe to cache and the block only
needs a frontend mount signal.

```json title="block.json"
{
  "name": "acme/tabs",
  "blockstudio": {
    "island": "hydrate"
  }
}
```

The full block HTML is present in the initial response. The island runtime does
not make a REST request for it. It dispatches `blockstudio:island:hydrate` on the
marker so frontend code can attach behavior.

### Dynamic Islands

Use a dynamic island when the real block output must be rendered in the
visitor's request context.

```json title="block.json"
{
  "name": "acme/cart-count",
  "blockstudio": {
    "island": "dynamic"
  }
}
```

The initial response contains only a placeholder. The browser batches all ready
dynamic islands into a single request to:

```text
POST /wp-json/blockstudio/v1/island/render
```

The endpoint renders only registered Blockstudio blocks that are dynamic and
have `blockstudio.island.mode` set to `dynamic`.

## Configuration

The `island` value can be a shorthand or an object.

```json title="block.json"
{
  "blockstudio": {
    "island": true
  }
}
```

`true` and `"hydrate"` both mean hydrated. `"dynamic"` means dynamic.

```json title="block.json"
{
  "blockstudio": {
    "island": {
      "mode": "dynamic",
      "tag": "section",
      "attributes": ["productId", "variant"],
      "placeholder": "cart-placeholder.php",
      "loading": "visible",
      "event": "cart:updated",
      "cache": {
        "ttl": 60,
        "per": "user"
      }
    }
  }
}
```

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `mode` | `"hydrate"` or `"dynamic"` | `"hydrate"` | Island mode. |
| `tag` | `string` | `"div"` | Marker wrapper tag. |
| `attributes` | `string[]` | all block attributes | Attribute allow-list sent to dynamic fragment requests. |
| `placeholder` | `string` | auto-detected | Relative placeholder template path. |
| `loading` | `"eager"` or `"visible"` | `"eager"` | When the runtime should fetch a dynamic island. |
| `event` | `string` | unset | Window event name that refreshes a dynamic island. |
| `cache` | `boolean` or `object` | `false` | Optional fragment cache policy. |

## Dynamic Placeholders

Dynamic islands should render a placeholder that is safe in a shared full-page
cache. Do not include visitor-specific data in the placeholder branch.

Blockstudio looks for placeholders in this order:

1. the explicit `island.placeholder` path
2. `placeholder.php`
3. `placeholder.blade.php`
4. `placeholder.twig`
5. `placeholder.html`
6. the normal template with placeholder flags

```php title="placeholder.php"
<div class="cart-count-placeholder">
  Loading cart...
</div>
```

If no placeholder file exists, use the template flags in your normal template:

```php title="index.php"
<?php if ( $isIslandPlaceholder ) : ?>
  <div class="cart-count-placeholder">Loading cart...</div>
<?php else : ?>
  <div class="cart-count">
    <?php echo esc_html( acme_cart_count() ); ?>
  </div>
<?php endif; ?>
```

The same values are available in PHP, Twig, and Blade templates:

- `isIsland`
- `isIslandPlaceholder`
- `isIslandFragment`
- `islandPhase`

`islandPhase` is `"normal"`, `"hydrate"`, `"placeholder"`, or `"fragment"`.

## Runtime Behavior

Blockstudio injects the island runtime only when the rendered page contains an
island marker. Pages without islands ship no extra island JavaScript.

For dynamic islands, the runtime:

1. collects all ready markers
2. de-duplicates identical block name, attributes, and signature pairs
3. sends one batched REST request
4. swaps each placeholder with the returned fragment
5. dispatches `blockstudio:island:rendered`
6. dispatches `blockstudio:island:hydrate`

`loading: "eager"` islands render as soon as the runtime starts. `loading:
"visible"` islands wait for `IntersectionObserver`. If several visible islands
enter the viewport together, they are batched into one request.

`event` refreshes an island when the named window event fires:

```js
window.dispatchEvent(new Event('cart:updated'));
```

Failures keep the placeholder in place and dispatch
`blockstudio:island:error`.

## Fragment Endpoint

The island endpoint is public, but it is not a general block-render API.

Each marker carries:

- the block name
- the allow-listed attributes
- a server-generated signature

The endpoint only renders when:

- the block is registered
- the block is a dynamic WordPress block
- the block is marked as a dynamic Blockstudio island
- the incoming attributes pass the block schema and island allow-list
- the signature matches the normalized block name and attributes

Signatures are generated at render time on the serving WordPress site from that
site's salts and the island payload. They are not exported or baked into a
theme.

Endpoint responses are marked non-cacheable. If you want fragment caching, use
`island.cache`.

## Fragment Caching

Dynamic fragments are uncached by default.

```json title="block.json"
{
  "blockstudio": {
    "island": {
      "mode": "dynamic",
      "cache": true
    }
  }
}
```

`true` uses a short global cache. Use the object form for explicit control:

```json
{
  "cache": {
    "ttl": 120,
    "per": "user"
  }
}
```

`per: "global"` shares one cached fragment for the same block and attributes.
`per: "user"` varies the cache by the current WordPress user ID.

## Programmatic Rendering

Islands work wherever Blockstudio blocks render:

```php
bs_render_block([
  'name' => 'acme/cart-count',
  'data' => ['productId' => 42],
]);

echo bs_block([
  'name' => 'acme/cart-count',
  'data' => ['productId' => 42],
]);
```

They also work through block tags:

```html
<bs:acme-cart-count product-id="42" />
<block name="acme/cart-count" productId="42" />
```

The initial output is still the island marker and placeholder, and the runtime
is injected when the rendered response contains that marker.

## PHP API

```php
Blockstudio\Islands::is_island('acme/cart-count');
Blockstudio\Islands::mode('acme/cart-count');
Blockstudio\Islands::registered();
Blockstudio\Islands::marker('acme/cart-count', $attributes, $html);
```

`marker()` validates block metadata. Dynamic markers are only built for
registered dynamic Blockstudio blocks with `island.mode` set to `dynamic`.

## Hooks

Detection and markup:

```php
blockstudio/islands/is_island
blockstudio/islands/mode
blockstudio/islands/attributes
blockstudio/islands/marker_tag
blockstudio/islands/marker_attributes
blockstudio/islands/placeholder
```

Endpoint and rendering:

```php
blockstudio/islands/allowed
blockstudio/islands/max_per_request
blockstudio/islands/request_attributes
blockstudio/islands/fragment
```

`blockstudio/islands/max_per_request` changes the maximum number of dynamic
islands accepted by one batched endpoint request. The default is `50`.

Runtime configuration:

```php
blockstudio/islands/endpoint_url
blockstudio/islands/loading
```

Signatures:

```php
blockstudio/islands/signature_payload
blockstudio/islands/signature
blockstudio/islands/verify_signature
```

Lifecycle actions:

```php
blockstudio/islands/registered
blockstudio/islands/rendered
```

## When Not To Use Islands

Do not use a dynamic island for SEO-critical content. Dynamic island fragments
are not present in the initial HTML and are not meant to be indexed.

Use normal rendering or a hydrated island when the content is cache-safe and
should be visible to crawlers. Use a dynamic island only for content that can
arrive after the page shell, such as account UI, cart state, personalized
recommendations, or permission-gated controls.

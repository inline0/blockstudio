# 007: Block Islands for Hydration and Deferred Rendering

## Summary

Blockstudio blocks do not precompile to static HTML. They are dynamic WordPress
blocks: WordPress calls `Blockstudio\Block::render` on each request, and
Blockstudio renders the block's PHP, Twig, or Blade template in that request.
That works well for normal server rendering, but it means a block that reads
per-user or per-request state can contaminate the whole server response. A page
with a live cart count, personalized greeting, "last viewed" strip, or countdown
either has to opt out of shared full-page caching or risk caching stale or
visitor-specific markup.

This PRD adds block islands to Blockstudio 7.5.0. An island is a single block
that is marked in `block.json` as either hydrated or dynamic:

- the initial server response stays cache-safe and fully cacheable
- hydrated islands render their normal cache-safe server output, then attach
  their own frontend JavaScript on the client
- dynamic islands render only a cache-safe placeholder in the initial response,
  then fetch their real per-request output from a frontend-safe render endpoint
  and swap it in
- a small client runtime discovers islands, batches one request for all dynamic
  islands on the page, and swaps results in place
- everything else about the page, including caching, routing, and SEO, is
  unchanged

The user-facing result: a developer adds `"island": "dynamic"` to a block's
`blockstudio` config, provides a cache-safe placeholder for the initial render,
and the block now shows fresh per-visitor content on a page that is still safe
for a shared full-page cache. This is the Blockstudio-native version of partial
prerendering with islands, adapted to Blockstudio's server-rendered block model.

Testing constraint for this PRD: do not run local unit tests, local E2E tests,
`npm run test:*`, or `npm run wp-env:start` unless the user explicitly asks for
local execution. Verification happens through pushed GitHub Actions commits
using `[all]`, with follow-up fixes driven from CI logs.

## Product Context

Modern frameworks solve the "mostly cacheable, a little dynamic" problem with
partial prerendering and islands. A cache-safe shell is served instantly, and
small interactive or personalized regions are hydrated or streamed in on the
client. The shell caches perfectly. The dynamic holes stay fresh.

Blockstudio is already a strong file-first render system. Pages, patterns, and
site templates compile source files into WordPress block markup, while
Blockstudio blocks are registered as dynamic WordPress blocks and render their
PHP/Twig/Blade templates per request. The gap is that one per-user block can make
the whole server response unsafe for a shared full-page cache. If any block on
the page reflects the current user, session, request, or time, the cached page
can become wrong for the next visitor.

Islands close that gap by moving the dynamic part out of the initial server response
and onto the client:

- the server response contains only cache-safe markup
- per-request and per-user output is produced by a separate client-triggered
  render that runs in the real request context of the visitor
- the two concerns compose cleanly, so one page can be cached forever and still
  show live data

This matters directly for static caching layers. A host, a plugin, or an
external runtime that caches the full server response now gets a page that never
needs per-user invalidation, because nothing per-user is in the server response.
The dynamic content lives entirely in the island fetch.

## Current State

### Frontend block rendering

Blockstudio registers blocks with `Blockstudio\Block::render` as their
`render_callback` (`includes/classes/block-registrar.php` around line 223, plus
the `build.php` registration paths). On every frontend request, WordPress calls
that callback and the block renders its PHP/Twig/Blade template with the current
request context. There is no built-in way to keep the block on the page while
excluding selected per-request output from the cached server response.

### Programmatic block rendering

Blockstudio also renders blocks programmatically through the same core render
method:

- `bs_render_block()` and `blockstudio_render_block()` call `Render::block()`,
  which calls `Blockstudio\Block::render()`.
- `bs_block()` buffers the same `Render::block()` output and returns it as a
  string.
- Block tags such as `<bs:acme-card />` resolve through `Block_Tags::render()`
  and then call `Blockstudio\Block::render()` for Blockstudio blocks.

Therefore islands must be implemented at the `Blockstudio\Block::render` layer,
not only in the WordPress `render_callback` path. A dynamic island rendered via
`bs_render_block()`, `bs_block()`, or a block tag should produce the same
placeholder marker in the initial page response as the same block inserted in
post content. If that output is included in a normal frontend page response, the
runtime injection pass should discover the marker and print the island runtime.

If a developer uses `bs_block()` or `bs_render_block()` in a non-page context
such as an email, CLI command, custom REST response, or server-to-server render,
Blockstudio can still return the island marker and placeholder, but the browser
runtime may not be present unless that context explicitly includes it. The v1
contract is normal frontend responses.

### The render REST endpoints

Blockstudio already renders blocks over REST for the editor
(`includes/classes/rest.php`):

- `POST /blockstudio/v1/gutenberg/block/render/{namespace}/{name}` renders one
  block. Handler: `Rest::gutenberg_block_render` (line 556). It requires a
  registered dynamic block, accepts `attributes`, `context`, and optional
  `post_id`, injects context through the `_BLOCKSTUDIO_CONTEXT` attribute, calls
  `render_block()`, and returns `{ "rendered": "<html>" }`.
- `POST /blockstudio/v1/gutenberg/block/render/all` batch renders many blocks.
  Handler: `Rest::gutenberg_block_render_all` (line 609). It accepts a `data`
  array of `{ name, attributes, context, post, clientId }` entries and returns a
  map of `{ [clientId]: "<html>" }`.

These endpoints are editor-oriented. They are not designed as a public,
allow-listed, frontend-safe fragment API. The island runtime needs a sibling
endpoint that reuses the same render pipeline but is safe to call from anonymous
frontend visitors.

### Per-block frontend assets

Block CSS and JS are discovered and processed by `includes/classes/assets.php`
and the ES module classes (`esmodules.php`, `abstract-esmodule.php`,
`esmodulescss.php`). Assets are classified by filename prefix in
`block-registry.php`:

- `admin-` assets load only in wp-admin
- `block-editor-` assets load only in the block editor
- `global-` assets load on every page
- other block CSS/JS loads with the block

A block's frontend JavaScript is already how a Blockstudio block becomes
interactive on the frontend. Hydrated islands build on this: the block ships its
normal cache-safe server output plus its own frontend JS, and the island runtime
guarantees a reliable mount signal even when markup is swapped in later.

### Isomorphic editor rendering

PRD 002 added `"isomorphic": true`, which renders Twig client-side in the editor
to avoid a REST round-trip per attribute change. Islands are the frontend and
runtime analog of that idea: keep the expensive or context-bound render off the
critical path, and do the smallest possible client-side work to produce the final
output.

### refreshOn

The `blockstudio` config already supports `refreshOn`
(`block-registrar.php` line 303), which re-renders a block in the editor when a
named event fires. Islands introduce the frontend equivalent: a runtime that can
(re)render a block on the live site in response to load or to an event.

## Goals

- Add an `island` flag to the `blockstudio` block config.
- Support two island modes: hydrated and dynamic.
- Keep the initial server page response cache-safe for both modes.
- For hydrated islands, render normal cache-safe server output and attach the
  block's existing frontend JavaScript on the client.
- For dynamic islands, render only a cache-safe placeholder in the initial
  response, then fetch the real per-request output on the client and swap it in.
- Add a frontend-safe, allow-listed render endpoint that reuses the existing
  render pipeline.
- Batch all dynamic island fetches on a page into a single request.
- Provide a small, dependency-free client runtime that discovers, hydrates, and
  swaps islands.
- Provide a reliable hydration lifecycle signal for block frontend JS.
- Support all existing frontend render entry points: normal block rendering,
  `bs_render_block()`, `bs_block()`, and block tags.
- Add public PHP helpers and filters for island detection, markup, and fragment
  caching policy.
- Preserve existing block rendering, editor rendering, and asset behavior.
- Add unit and E2E coverage for markers, the endpoint, the runtime, caching,
  security, SEO fallbacks, and composition with a static cache.
- Update docs, generated docs/LLM output, readme changelog, and the 7.5 blog
  post draft.

## Non Goals

- Do not implement server-side streaming, chunked transfer, or a BigPipe-style
  flush pipeline. Islands are client-completed, not server-streamed.
- Do not implement Drupal-style cacheability metadata, auto-placeholdering, or a
  lazy-builder graph. Islands are an explicit, per-block opt-in, not automatic
  render analysis.
- Do not make dynamic island content part of the initial server HTML. It is not
  present for crawlers by design. SEO-critical content must use a hydrated island
  or normal server rendering.
- Do not add a general client-side data-fetching framework. The island runtime
  only renders Blockstudio blocks through the Blockstudio endpoint.
- Do not add arbitrary dynamic template regions, named dynamic slots, or
  callback-capturing helpers in v1. Only registered blocks marked as dynamic
  islands can be deferred.
- Do not change how non-island blocks render.
- Do not require the isomorphic Twig path. Islands work with PHP, Twig, and Blade
  because the fragment render runs on the server.
- Do not add cron pre-warming of fragment caches in v1. It can be a follow-up.

## Guiding Principle

The initial server response must be cache-safe. Anything per-request or per-user
moves to the island fetch.

Concretely:

1. A page with islands renders to markup that depends only on cache-safe inputs.
2. The cache-safe markup is safe to cache at the full-page level, including by an
   external runtime with no knowledge of Blockstudio.
3. Dynamic output is produced only when the client asks for it, in the real
   request context of the visitor.
4. Removing all island markup and JavaScript from a page must still leave a valid
   cache-safe page. Skeletons and hydrated server output are the fallback.

If a block cannot express a meaningful cache-safe fallback, it should not be a
dynamic island.

## Island Modes

An island is declared per block. There are two modes.

### Hydrated Island

- Server renders the block normally using its PHP/Twig/Blade template. The
  output must be cache-safe: it must not depend on the current user, session, or
  other request-specific state unless the page cache varies by that state.
- The output is wrapped in an island marker so the runtime can find it.
- On the client, the runtime dispatches a hydration signal and the block's own
  frontend JavaScript attaches interactivity.
- No network request is made. Hydrated islands are for interactive but static
  content such as sliders, tabs, accordions, and toggles.
- SEO is unaffected because the real content is in the initial server HTML.

### Dynamic Island

- Server renders only a cache-safe placeholder, skeleton, or safe default in a
  dedicated placeholder phase.
- The placeholder is wrapped in an island marker that carries the block name and
  the attributes needed to render the real block.
- On the client, the runtime collects every dynamic island, sends one batched
  request to the frontend render endpoint, and swaps each returned fragment into
  its marker.
- The fragment render runs on the server in the visitor's real request context,
  so it can read the current user, session, cart, time, or any request state.
- Dynamic island content is not in the initial server HTML and is not indexed.

Mode selection is explicit. A block is one or the other, never both.

### No Arbitrary Dynamic Regions In V1

Template-level capture regions are attractive because they would let a single
block contain several deferred regions. They are also dangerous as the v1
primitive. In PHP, Twig, and Blade, arbitrary code inside a captured region can
execute before Blockstudio sees the final output string. By the time an output
parser finds the region marker, the per-user markup may already have been
rendered into the cacheable page response.

The v1 island boundary is therefore only a registered Blockstudio block marked
as `"island": "dynamic"`. Arbitrary template capture regions, named dynamic
slots, inline callback regions, and PHP helpers that capture arbitrary template
output are out of scope for v1.

A parent block that needs one small dynamic region should define a small child
block, mark that child block as `"island": "dynamic"`, and render it with
existing block tag syntax:

```html
<bs:acme-cart-count product-id="42" />
```

That keeps the deferred unit explicit, allow-listed, testable, and renderable
through the same endpoint as every other island. It also avoids inventing a
cross-engine capture API for PHP, Twig, and Blade.

Any future syntax sugar should compile to this same registered-block mechanism,
not introduce a second dynamic rendering primitive. V1 should make block-level
islands solid first.

## Block Manifest

Add an `island` key to the `blockstudio` object in `block.json`.

Shorthand:

```json title="blocks/cart-count/block.json"
{
  "name": "acme/cart-count",
  "blockstudio": {
    "island": "dynamic"
  }
}
```

Accepted shorthand values:

| Value | Meaning |
| --- | --- |
| `"hydrate"` | Hydrated island. |
| `true` | Alias for `"hydrate"`. |
| `"dynamic"` | Dynamic island. |
| `false` or absent | Not an island. Normal rendering. |

Object form for configuration:

```json title="blocks/cart-count/block.json"
{
  "name": "acme/cart-count",
  "blockstudio": {
    "island": {
      "mode": "dynamic",
      "tag": "div",
      "attributes": ["productId", "variant"],
      "placeholder": "placeholder.php",
      "cache": false,
      "loading": "eager"
    }
  }
}
```

Properties:

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `mode` | `string` | `"hydrate"` | `"hydrate"` or `"dynamic"`. |
| `tag` | `string` | `"div"` | Wrapper element for the island marker. |
| `attributes` | `string[]` | All block attributes | Allow-list of attribute keys sent to the fragment render. Narrow this for dynamic islands. |
| `placeholder` | `string` | Auto-detected placeholder source | Optional placeholder source path relative to the block folder. If omitted, Blockstudio tries `placeholder.php`, `placeholder.blade.php`, `placeholder.twig`, and `placeholder.html`, then falls back to the normal template with placeholder-phase flags. |
| `cache` | `boolean` or `object` | `false` | Fragment cache policy for dynamic islands. See Caching and Performance. |
| `loading` | `string` | `"eager"` | `"eager"` fetches on load. `"visible"` fetches when the marker enters the viewport. |
| `event` | `string` | `null` | Optional event name that triggers a re-fetch, mirroring `refreshOn` on the frontend. |

The parsed value is added to block metadata in `build_blockstudio_metadata`
alongside `conditions`, `editor`, `extend`, `group`, `icon`, `refreshOn`, and
`transforms` (`includes/classes/block-registrar.php` around lines 295 to 304).

## Server Rendering, Placeholders, and Markers

Island wrapping happens in the block render path (`Blockstudio\Block::render` in
`includes/classes/block.php`), gated on the block's `island` metadata. Non-island
blocks are untouched.

Important: the initial render is still a WordPress server render. Blockstudio is
not turning a block into a precompiled static artifact. The difference is which
branch runs:

- normal blocks render exactly as they do today
- hydrated islands render the normal block template and add a marker
- dynamic islands render a placeholder source or placeholder branch and add a
  marker
- fragment requests render the real block template and return only that
  fragment

Dynamic islands should prefer a dedicated placeholder source when possible:

```text
blocks/cart-count/
  block.json
  index.php
  placeholder.php
```

If no placeholder source exists, Blockstudio may fall back to rendering the
normal template with placeholder-phase flags. That fallback is convenient, but
the template must branch before reading per-user or per-request state.

The wrapper is a single element carrying stable data attributes:

```html
<div
  data-bs-island="acme/cart-count"
  data-bs-island-mode="dynamic"
  data-bs-island-props='{"productId":42,"variant":"blue"}'
  data-bs-island-signature="..."
  data-bs-island-loading="eager"
>
  <!-- hydrated: the normal cache-safe block output -->
  <!-- dynamic: the cache-safe placeholder / skeleton -->
</div>
```

Rules:

- `data-bs-island` is the block name. It is the allow-list key on the server
  endpoint. Only names present in this attribute and registered as islands can be
  rendered through the frontend endpoint.
- `data-bs-island-props` is the JSON-encoded, allow-listed attribute subset. It
  must contain only attributes listed in `island.attributes` (or all attributes
  when unset). It must be escaped for an HTML attribute.
- `data-bs-island-signature` is an HMAC-like signature generated by Blockstudio
  from the block name, mode, allow-listed attributes, optional public post
  context, and a site secret. The endpoint verifies it before rendering. This
  avoids printing a time-limited nonce into a page that may be cached for longer
  than the nonce lifetime.
- Signatures are generated at render time on the serving WordPress site, using
  that site's salts/secret plus the normalized island payload. They are never
  baked into exported theme files. If the same theme is exported or deployed to
  another host, that host signs its own island markers with its own secret.
- For dynamic islands, Blockstudio renders a dedicated placeholder source or a
  placeholder branch of the block template. Blockstudio exposes template flags
  (for example `isIsland`, `isIslandPlaceholder`, `isIslandFragment`, and
  `islandPhase`) so a template can output the placeholder during the initial
  render and the real content during the fragment render.
- For hydrated islands, the template renders normally and the wrapper is added
  around the real output.
- The wrapper never contains per-user or per-request data for dynamic islands.
  That is the entire point.

The placeholder phase must not call functions that leak per-user state into the
cached response. Templates decide what is cache-safe. The PRD provides the
placeholder source lookup, flags, and wrapper, not automatic purity analysis.

## Client Island Runtime

Ship a small, framework-free runtime as a Blockstudio asset that is printed only
when the rendered response contains at least one island marker. It is plain ES
module JavaScript with no dependencies. This can use the same output-buffer
injection model Blockstudio already uses for block assets.

Responsibilities:

1. Query `document.querySelectorAll('[data-bs-island]')`.
2. For hydrated islands, dispatch a `blockstudio:island:hydrate` CustomEvent on
   the marker and let the block's frontend JS attach. Provide an idempotent mount
   guard so a marker is hydrated once.
3. For dynamic islands, group markers by `loading`:
   - `eager` markers are collected immediately
   - `visible` markers are observed with `IntersectionObserver` and collected
     when they intersect
4. Batch all ready dynamic markers into one request to the frontend render
   endpoint, keyed by a generated client id per marker.
5. Replace each marker's inner HTML with the returned fragment, then dispatch
   `blockstudio:island:rendered` so any frontend JS in the fragment can attach.
6. Re-run the block's frontend JS against swapped-in markup. Swapped fragments
   must receive the same mount signal as initial hydration.
7. If a marker declares an `event`, re-fetch and re-swap when that event fires.
8. On fetch failure, keep the placeholder, mark the island as errored via
   a data attribute, and dispatch `blockstudio:island:error`. Never blank the
   region.

The runtime must be resilient to partial batches, must de-duplicate identical
`(name, props)` pairs into one render, and must not block page interactivity
while fetching.

Batching is required behavior, not an optional optimization. On initial load,
every `loading: "eager"` dynamic island on the page is collected into one
`POST /blockstudio/v1/island/render` request. `loading: "visible"` islands are
batched by viewport entry tick, so several islands entering the viewport together
still produce one request. Event-driven refreshes are batched per event tick.
The runtime must not fire one REST request per island unless only one island is
ready in that batch.

## Fragment Endpoint

Add a frontend-safe render endpoint rather than exposing the editor endpoints to
anonymous visitors.

Proposed route:

```text
POST /blockstudio/v1/island/render
```

Request body:

```json
{
  "islands": [
    {
      "clientId": "i1",
      "name": "acme/cart-count",
      "attributes": { "productId": 42 },
      "signature": "..."
    }
  ]
}
```

Response:

```json
{
  "i1": "<div class=\"cart-count\">3 items</div>"
}
```

Behavior and constraints:

- Reuse the existing render pipeline. Internally this mirrors
  `Rest::gutenberg_block_render_all`: build a block array, inject context through
  `_BLOCKSTUDIO_CONTEXT`, and call `render_block()`.
- Render runs in the visitor's real request context. Logged-in state, cookies,
  and session are the visitor's own. This is what makes per-user output correct.
- Allow-list enforcement is the primary gate. The endpoint renders a block only
  if:
  - the block is registered
  - the block is a dynamic block
  - the block's `blockstudio.island.mode` is `"dynamic"`
  A request for any other block name returns an error for that entry. The
  endpoint is not a general "render any block" API.
- Attribute validation is mandatory. Incoming attributes are filtered to the
  block's registered attribute schema and to the block's `island.attributes`
  allow-list. Unknown keys are dropped.
- The endpoint does not rely on a rotating nonce printed into the page shell.
  That would break when a shared full-page cache serves the shell beyond the
  nonce lifetime. Instead, each marker carries a stable server-generated
  signature for its block name and allow-listed attributes. The signature is a
  secondary check that the exact normalized `(name, attributes, context)` payload
  was emitted by this WordPress site. The endpoint is publicly reachable but
  renders only entries that pass both the allow-list gate and marker-signature
  verification.
- The fragment render sets an island render flag so templates output the real
  content branch, not the placeholder branch.
- Responses are marked non-cacheable at the HTTP level. Per-request output must
  not be stored by shared caches.
- Default WordPress REST same-origin behavior should remain intact. The endpoint
  must not add permissive CORS headers.

Keep the editor endpoints unchanged. The island endpoint is a separate,
narrower, public-safe surface.

## Composition With Static Caching

This is the reason the feature exists.

- The initial server response for a page with islands is cache-safe. Hydrated
  islands contain normal cache-safe server output. Dynamic islands contain only
  placeholders. Nothing in the initial server response depends on the current
  user or time unless the site deliberately varies its full-page cache by that
  state.
- Because the initial response is cache-safe, any full-page cache can store it
  and serve it to every visitor, including a cache that knows nothing about
  Blockstudio.
- Per-user and per-request output is produced only by the island fetch, which is
  never part of the cached page.
- This holds even for logged-in visitors: the cached shell is shared, and the
  per-user data arrives through the island fetch in the visitor's own context.
- It also holds in environments where Blockstudio is not the caching layer. The
  island contract is "cache-safe server response plus client fetch," which is
  portable to any host or external static runtime.

The practical outcome: dynamic content stops forcing per-user page caches. A site
can cache pages aggressively and still show live, personalized regions.

## Public API

Expose a small PHP surface:

```php
Blockstudio\Islands::is_island( 'acme/cart-count' );
Blockstudio\Islands::mode( 'acme/cart-count' );
Blockstudio\Islands::marker( 'acme/cart-count', $attributes, $inner_html );
Blockstudio\Islands::registered();
```

`Blockstudio\Islands::marker()` is not a bypass around island registration. It
must validate the normalized block metadata before building a marker. For dynamic
markers, it must refuse to build or sign a marker unless the block is registered,
dynamic, and has `island.mode === "dynamic"`. Hydrated markers may wrap hydrated
island output, but they must not include dynamic render props/signatures. The
endpoint still enforces the same checks independently.

Template flags available during render:

- `isIsland` is true while rendering an island block
- `isIslandPlaceholder` is true during the initial placeholder render of a
  dynamic island
- `isIslandFragment` is true during the client-triggered fragment render
- `islandPhase` is `"normal"`, `"hydrate"`, `"placeholder"`, or `"fragment"`

These flags let a single template branch between skeleton and real output without
duplicating the block.

## Filters and Hooks

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
blockstudio/islands/request_attributes
blockstudio/islands/fragment
```

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

Client events dispatched on markers:

```text
blockstudio:island:hydrate
blockstudio:island:rendered
blockstudio:island:error
```

All filters and events must be documented and tested.

## Caching and Performance

Dynamic islands are per-request by default, but a block can opt into fragment
caching when its output is shared across visitors for a window of time.

`island.cache` accepts:

| Value | Meaning |
| --- | --- |
| `false` | No fragment cache. Render on every request. Default. |
| `true` | Cache with a default short TTL, keyed by name plus allow-listed attributes. |
| `{ "ttl": 60, "per": "user" }` | Cache for 60 seconds. `per` can be `"global"` or `"user"`. |

Requirements:

- Fragment cache keys include block name, allow-listed attributes, the `per`
  scope, and the Blockstudio version.
- `per: "user"` must key on the current user id so one visitor never sees
  another visitor's fragment.
- `per: "global"` is only valid when output does not depend on the visitor.
- Fragment caches use the object cache or transients, never a mechanism that
  could leak into the shared full-page cache.
- The client runtime batches and de-duplicates island requests so one page makes
  one request regardless of island count.
- The runtime should support `loading: "visible"` so below-the-fold islands do
  not fetch until needed.
- Endpoint responses set no shared-cache HTTP headers.

Fragment caching is an optimization, not the default. Correctness first, then
opt-in caching.

## SEO and Accessibility

- Dynamic island content is not present in the initial server HTML and is not
  indexed.
  This is documented as a hard constraint. SEO-critical content must use a
  hydrated island or normal server rendering.
- Hydrated islands are fully indexed because their real content is in the server
  response.
- Dynamic placeholders must reserve layout space to avoid layout shift. Docs must
  show how to size skeletons.
- Skeletons should use appropriate ARIA (for example `aria-busy="true"`) and
  swapped content should update it. Live regions are recommended for content that
  changes after load.
- With JavaScript disabled, dynamic islands show their placeholder and the
  page remains valid. Hydrated islands show real content and lose only added
  interactivity.

## Security

- The frontend endpoint renders only registered dynamic blocks whose
  `island.mode` is `"dynamic"`. It is not a general block render API.
- Attributes are filtered to the block's registered schema and to the block's
  `island.attributes` allow-list. Extra keys are dropped before render.
- Markers carry only allow-listed attributes. Do not serialize secrets, tokens,
  or full attribute sets into `data-bs-island-props`.
- Requests are marker-signature checked. A fragment request must prove that the
  block name and allow-listed attributes came from a Blockstudio-rendered island
  marker. Do not rely on a rotating nonce embedded in the cached page shell.
- The endpoint runs in the visitor's own context. It must not accept a caller
  supplied user id or post author override. `post_id` context, if allowed, is
  validated and limited to public, readable content.
- Fragment output is escaped by the block template exactly as normal block
  output is. Islands add no new unescaped sink.
- Rate limiting and a maximum islands-per-request cap protect the endpoint from
  abuse.
- Placeholder renders must not embed per-user data into the cached page. Any
  template that leaks user data into the placeholder branch is a bug the docs
  must warn against.

## Backward Compatibility

- Blocks without an `island` flag render exactly as before.
- Existing editor render endpoints are unchanged.
- Existing per-block frontend JS is unchanged. Hydrated islands reuse it and add
  a reliable mount signal.
- Existing `refreshOn` behavior in the editor is unchanged. The frontend `event`
  option is additive.
- Pages, patterns, isomorphic blocks, content sync, and site templates are
  unaffected.
- The island runtime is printed only when the rendered response contains an
  island marker, so pages without islands ship no extra JavaScript.

## Relationship to Divine

The island mechanism is a Blockstudio feature and is fully described by this PRD.
It works on any WordPress site, including sites that use Blockstudio without any
external runtime.

Divine's static prerender benefits from islands automatically: because island
pages produce a cache-safe initial response, Divine's full-page cache can store
and serve them without per-user invalidation, and background prerender warming
keeps them fresh. Divine's optional side of this, such as signaling a
placeholder render context during prerender warming or adding a fragment cache
policy in exported themes, is tracked separately in the Divine repo and is out of
scope here. This PRD does not depend on Divine.

## Implementation Plan

1. Parse and normalize the `island` config in `block-registrar.php` and add it to
   block metadata next to `conditions`, `editor`, and `refreshOn`.
2. Add island wrapping in `Blockstudio\Block::render`, gated on island metadata,
   with hydrate, placeholder, and fragment phases plus the `isIsland`,
   `isIslandPlaceholder`, `isIslandFragment`, and `islandPhase` template flags.
3. Add placeholder source detection for `placeholder.php`,
   `placeholder.blade.php`, `placeholder.twig`, and `placeholder.html`, plus the
   explicit `island.placeholder` path.
4. Add `Blockstudio\Islands` with detection, marker building, allow-list,
   placeholder source lookup, and fragment cache policy.
5. Register the `POST /blockstudio/v1/island/render` endpoint in `rest.php`,
   reusing the render pipeline, enforcing the island allow-list, filtering
   attributes, and verifying the marker signature.
6. Add the client island runtime as a conditionally injected asset, with
   discovery, hydration signals, batched dynamic fetch, viewport loading,
   event-driven refresh, de-duplication, and error handling.
7. Print the runtime and endpoint URL only when the rendered response
   contains an island marker. This can use the existing output-buffer asset pass;
   it should not enqueue runtime JavaScript on pages without islands.
8. Add fragment caching with object cache/transient storage and correct per-user
   and per-global keys.
9. Add public helpers, filters, actions, and client events.
10. Add unit tests for config parsing, markers, allow-list enforcement, attribute
   filtering, programmatic rendering entry points, the endpoint, and caching.
11. Add E2E tests for hydration, dynamic swap, batching, viewport loading, error
    fallback, JS-disabled fallback, and composition with a full-page cache.
12. Update docs, generated docs/LLM output, readme changelog, and the 7.5 blog
    draft.
13. Do not run local unit, E2E, or `npm run test:*` commands for this PRD unless
    the user explicitly requests it.
14. Push a final commit with `[all]` and keep fixing from GitHub Actions logs
    until CI is green.

## Test Plan

### Unit Tests

Add `tests/unit/islands/IslandsTest.php` or equivalent.

Config and metadata:

- `"island": "hydrate"`, `true`, `"dynamic"`, and `false` parse to the correct
  mode
- object form parses `mode`, `tag`, `attributes`, `placeholder`, `cache`,
  `loading`, and `event`
- island metadata appears in the block metadata next to `refreshOn`
- an unknown mode value falls back safely and records a warning

Markers and placeholder rendering:

- a hydrated island wraps real output in a marker
- a dynamic island uses `placeholder.php` when present
- a dynamic island uses the explicit `island.placeholder` source when set
- a dynamic island falls back to the normal template with placeholder flags when
  no placeholder source exists
- a dynamic island wraps the placeholder in a marker
- `data-bs-island-props` contains only allow-listed attributes
- `data-bs-island-signature` is generated from the block name, mode, attributes,
  and optional public post context
- marker attributes are escaped for HTML output
- non-island blocks are not wrapped
- the initial placeholder phase does not execute the fragment branch in test
  fixtures that would otherwise emit per-user data
- `bs_render_block()` renders a dynamic island as the same placeholder marker as
  normal block rendering
- `bs_block()` returns a dynamic island placeholder marker string
- block tags render dynamic islands as the same placeholder marker

Endpoint:

- rendering a registered dynamic island block returns its fragment
- rendering a non-island block name is rejected
- rendering a non-dynamic block is rejected
- attributes are filtered to the schema and to `island.attributes`
- a missing or invalid marker signature is rejected
- a caller-supplied user id or author override is ignored
- batched requests return a map keyed by client id
- an oversized islands array is rejected by the per-request cap

Caching:

- `cache: false` renders every request
- `cache: true` reuses a fragment within the TTL
- `per: "user"` keys separate fragments per user
- `per: "global"` shares a fragment across users
- a fragment cache never appears in the initial server response

### E2E Tests

Add island fixtures under the E2E theme, for example an `acme/cart-count`
dynamic island and an `acme/tabs` hydrated island.

Browser coverage:

- the initial server HTML for a dynamic island contains only the placeholder,
  not the live value
- the client runtime fetches and swaps the real value after load
- a full-page cached shell can be served after WordPress nonce rotation and
  dynamic islands still render because marker signatures do not expire on the
  nonce tick
- a page template that calls `bs_render_block()` for a dynamic island gets the
  runtime, batched request, and swapped fragment
- a page template that echoes `bs_block()` for a dynamic island gets the runtime,
  batched request, and swapped fragment
- a page or template that uses a block tag for a dynamic island gets the runtime,
  batched request, and swapped fragment
- multiple eager dynamic islands on one page produce exactly one network request
- a `visible` island does not fetch until scrolled into view
- multiple visible islands entering the viewport together produce exactly one
  network request for that viewport batch
- multiple islands refreshed by the same event produce exactly one network
  request for that event tick
- a hydrated island receives its mount signal and becomes interactive
- swapped-in fragment markup receives the same mount signal as initial hydration
- a failed fetch keeps the placeholder and sets the error attribute
- with JavaScript disabled, dynamic islands show the placeholder and hydrated
  islands show real content
- an island page served from a full-page cache still shows fresh per-visitor
  content after the fetch

Composition coverage:

- render the page for user A and user B from the same cached server response and
  confirm each sees their own island output
- confirm the initial server response bytes are identical for both users

### Regression Tests

- non-island blocks render unchanged
- `bs_render_block()`, `bs_block()`, and block-tag rendering for non-island
  blocks are unchanged
- editor render endpoints work unchanged
- isomorphic blocks work unchanged
- pages, patterns, content sync, and site templates tests still pass
- full `[all]` GitHub Actions run is green

### Test Execution

- Add the unit and E2E coverage above, but do not run the local test suite on the
  development machine by default.
- Do not run local unit tests, local E2E tests, `npm run test:unit`,
  `npm run test:e2e`, or `npm run wp-env:start` unless the user explicitly asks
  for local execution.
- Use the pushed `[all]` commit as the test gate. Debug failures from GitHub
  Actions logs and push follow-up fixes until CI is green.

## Docs

Add a new docs page:

- `docs/content/docs/blocks/islands.mdx`

Update:

- `docs/content/docs/blocks/meta.json`
- `docs/content/docs/blocks/rendering.mdx`
- `docs/src/schemas/schema.ts` for the `island` property
- `includes/llm/blockstudio-llm.txt`
- `readme.txt` changelog
- the 7.5 blog draft at `docs/content/blog/blockstudio-7-5.mdx`

Docs must explain:

- what an island is and the two modes
- when to use a hydrated island vs a dynamic island vs normal rendering
- the `island` config, shorthand, and object form
- placeholder render vs fragment render and the template flags
- support for normal block rendering, `bs_render_block()`, `bs_block()`, and
  block tags
- the client runtime, events, and loading strategies
- the frontend endpoint, marker signatures, and why the cached shell does not
  contain a rotating nonce
- fragment caching and per-user vs per-global scope
- SEO and accessibility constraints, especially that dynamic content is not
  indexed
- composition with full-page static caching
- filters, hooks, and client events
- testing and release caveats

## Open Decisions

- Whether the wrapper tag defaults to `div` or is inferred from the block's root
  element to reduce extra nesting.
- Whether the boolean flags are enough or whether all template examples should
  prefer the single `islandPhase` value.
- Whether to expose a global `blockstudio_island()` template helper in v1 or keep
  only the `Blockstudio\Islands` class and template flags.
- Whether `visible` loading should also support a distance/rootMargin option in
  v1.
- Whether to allow a `post_id` context on the endpoint in v1 or defer it until
  the security model for it is fully tested.
- Whether to add cron pre-warming of `per: "global"` fragment caches in a
  follow-up PRD.

## Definition of Done

A block can declare:

```json title="blocks/cart-count/block.json"
{
  "name": "acme/cart-count",
  "blockstudio": { "island": "dynamic" }
}
```

and get all of this with no consumer-side glue:

1. the initial page server response is cache-safe and contains only the
   placeholder for dynamic islands
2. the client runtime fetches and swaps the real per-visitor value
3. multiple dynamic islands on a page make one batched request
4. a hydrated island renders normal cache-safe server output and attaches its
   frontend JS
5. swapped and hydrated markup receive the same mount signal
6. dynamic islands work through normal block rendering, `bs_render_block()`,
   `bs_block()`, and block tags
7. the endpoint renders only allow-listed dynamic island blocks with filtered
   attributes and a valid marker signature
8. per-user output is correct for logged-in visitors on a shared cached page
9. dynamic content is absent from the initial server HTML and documented as not
   indexed
10. fragment caching works with correct per-user and per-global keys
11. JS-disabled and fetch-error paths keep a valid cache-safe page
12. non-island blocks and existing features are unchanged
13. docs, LLM output, changelog, and the 7.5 blog draft are updated
14. no local test suite is run unless the user explicitly asks for local
    execution
15. the final GitHub Actions run triggered by `[all]` is green

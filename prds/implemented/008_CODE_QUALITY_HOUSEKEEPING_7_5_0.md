# 008: Code Quality Housekeeping for 7.5.0

## Summary

7.5.0 is a housekeeping release. After a long run of feature work (content sync,
page sync write-back, custom field types, file-backed site templates, block
islands) the codebase accrued real bugs, security gaps, duplication, and dead
code that no feature PRD owned. This PRD is the remediation backlog.

It is the output of a full-codebase audit (PHP in `includes/`, TypeScript in
`src/`) cross-checked with an objective duplication scan. Every finding below was
verified against the actual code, not inferred. Findings are grouped by priority.
P0/P1 are described in full; P2 and the duplication/dead-code tail are compact
tables so a fixing agent can work top-down.

### Scope and constraints

- **No UX or feature changes.** Fix behavior to match documented/intended
  behavior; do not redesign anything. Bug fixes that restore intended behavior
  are in scope. Security fixes are in scope.
- **New 7.5.0 code may be adjusted.** Islands, site templates, and custom field
  types are new this release; correcting their defects is explicitly allowed.
- **Coding standards already pass.** PHPCS, `tsc --noEmit`, and ESLint are all
  green on this branch. Do not report or "fix" style. Keep them green.
- **Testing workflow.**
  - **Unit tests may (and should) run locally**: `npm run wp-env:start` then
    `npm run test:unit` (or a single file with PHPUnit inside wp-env) while
    iterating. Add/extend unit tests with each fix and confirm them locally.
  - **Do not run E2E tests locally.** E2E runs in GitHub CI only. Add or update
    E2E specs under `tests/e2e/` as findings require, but exercise them through CI,
    not `npm run test:e2e` locally.
  - **Final pass is a green CI run.** Land each batch with an `[all]` commit
    message (which runs unit + E2E) and confirm every CI job passes before
    considering the work done. `[all]` (or at minimum `[e2e]` for E2E-affecting
    changes) is required whenever a fix touches plugin functionality (PHP, TS,
    tests); omit only for docs/comment-only edits.
- **Changelog.** Make `readme.txt` changelog adjustments for the 7.5.0 release: a
  single release-boundary `= 7.5.0 =` entry summarizing the user-visible fixes
  (security fixes, the content-sync/page data-loss fixes, the islands and canvas
  corrections). Follow repo policy — one consolidated entry at the release
  boundary, not a line per iterative commit; do not log internal-only
  DRY/dead-code cleanup.
- Verify each fix by exercising the affected path (or its unit test) before moving
  on. Several findings note a missing test that should be added with the fix.

### Baseline (verified clean, do not touch)

PHPCS `composer cs` exit 0 · `tsc --noEmit` exit 0 · `eslint src` exit 0 · zero
TODO/FIXME/HACK markers · version constants at 7.4.2 across `blockstudio.php` /
`readme.txt` / `package.json` (bump at release, not a bug).

### Priority index

| # | Priority | Area | One-line |
|---|----------|------|----------|
| P0-1 | P0 sec | admin-page.php | Path traversal → arbitrary file write (RCE) in registry import |
| P0-2 | P0 sec | islands + block.php | Unsigned `$_GET` context: fragment cache poisoning + private-post disclosure (unauth) |
| P0-3 | P0 data | content-sync.php | `push --prune` with a type/taxonomy selector deletes out-of-selection entities |
| P0-4 | P0 data | content-sync.php | Malformed content JSON + `--prune` silently deletes the DB entity |
| P0-5 | P0 data | content-sync.php | `apply_meta` deletes attachment-reference meta when `media: 'none'` |
| P0-6 | P0 sec | pages.php | `serve_markdown` serves draft/private/password page sources publicly |
| P1-1 | P1 | build-cache / tailwind | Runtime + asset + tailwind caches grow without bound; no pruning anywhere |
| P1-2 | P1 sec | utils.php | `Utils::attributes()` emits scalar values unescaped (stored XSS); phpcs comments claim otherwise |
| P1-3 | P1 | islands.php + rest.php | Logged-in visitors render as anonymous in every fragment (feature broken) |
| P1-4 | P1 | islands.php | `request_attributes` filter applied before signature check → breaks all signatures |
| P1-5 | P1 | canvas.php + page-sync | Canvas REST sync resets `post_parent` to 0 permanently for draft-parent hierarchies |
| P1-6 | P1 sec | canvas.php | Canvas REST endpoints live when canvas disabled; `edit_posts` can drive sync + unbounded SSE |
| P1-7 | P1 | database.php | Meta storage strict comparison → `userScoped` meta lists always empty; typed filters never match |
| P1-8 | P1 | block-tags.php | Static builders shadow trait renderers and have drifted; pullquote builder emits save-invalid markup |
| P1-9 | P1 | field-type-registry.php | Custom field type `rest_schema` breaks repeater/array storage saves (new 7.5.0) |
| P2 | P2 | PHP only | ~20 lower-impact PHP correctness/robustness bugs (table). React P2 rows are deferred. |
| P3 | P3 | PHP + safe TS | Duplication clusters + dead code (tables). TS limited to dead-code + type/pure DRY. |
| — | **Deferred** | React client (`src/`) | Behavioral React/rendering fixes are **out of scope** this release. See "Deferred". |

---

## TypeScript / client-side scope (read before touching `src/`)

A prior attempt at the React behavioral fixes in this PRD **broke the entire E2E
suite**. The editor's block-rendering pipeline (server-side-render fetch loop,
richtext flush, repeater instance reuse, effect/ref timing) is more subtle than
the individual findings suggest, and small "correct-looking" changes to hooks,
effects, refs, or event handlers have large, non-obvious blast radius. For 7.5.0
we do **not** fix client-side behavior.

**In scope for `src/` (safe, non-behavioral only):**

- Dead-code removal — deleting verified-unused files, exports, props, branches,
  types, and empty directories (P3d TypeScript list). Deleting truly dead code
  cannot change runtime behavior.
- Pure DRY that is provably behavior-preserving by inspection: **type-only**
  dedup (shared interfaces) and extraction of **pure, side-effect-free helper
  functions** that are byte-for-byte equivalent to their call sites.
- Cosmetic cleanup (imports, obvious no-ops) that does not alter control flow.

**Explicitly OUT of scope for `src/` (do not touch in 7.5.0):**

- `useEffect`/`useMemo`/`useCallback` — adding, removing, or changing deps.
- Refs — introducing refs or "moving a function into a ref."
- `useState` init/sync, `useSelect`/`useDispatch` selectors, memoization.
- Event handlers, MutationObservers, debounce/throttle timing, async ordering.
- Anything in the block render / SSR-fetch / richtext / repeater data path.
- Merging or restructuring React components (even thin wrappers).

**Hard rules:** Every `src/` change must leave the E2E suite green in CI **without
any logical edit to E2E tests** (renaming/formatting only, if anything). E2E is
the guardrail, not something to adjust so a refactor "passes." If a TS change
cannot be shown safe by reading alone, or if it turns E2E red, revert it — it was
not in scope. When unsure whether a `src/` change is behavioral, treat it as
behavioral and skip it.

The known client-side bugs are still documented (see "Deferred") so they are not
lost; they need a dedicated effort with deep rendering-system knowledge and full
E2E validation, not this housekeeping pass.

---

## P0 — Security and data loss

### P0-1. Path traversal → arbitrary file write (RCE) in registry import

**File:** `includes/classes/admin-page.php:686-709` (`handle_import`).

The `block` request param is validated against `..` (`admin-page.php:574-576`),
but the individual file names in the remote registry response
(`$block['files']`) are not. Each entry is concatenated straight into the write
path:

```php
$file_path = $target . '/' . $file;   // $file from remote registry JSON, unchecked
$file_dir  = dirname( $file_path );
if ( ! is_dir( $file_dir ) ) { wp_mkdir_p( $file_dir ); }
file_put_contents( $file_path, wp_remote_retrieve_body( $file_response ) );
```

A registry entry like `"../../../../wp-content/mu-plugins/x.php"` writes attacker
content anywhere the web server can write → remote code execution. Registries are
third-party by design (the registry browser references external URLs from
`blocks.json`), so this crosses a real trust boundary. Trigger: a `manage_options`
user imports a block from a malicious or compromised registry.

**Fix:** Validate each `$file` the same way `$block_name` is validated — reject
`..` and absolute paths, then confirm the resolved real path stays under
`$target` (`wp_normalize_path` + `str_starts_with($resolved, $target)`). Add a
unit test with a traversal filename.

### P0-2. Islands: unsigned `$_GET` context poisons the fragment cache and discloses private posts

**Files:** `includes/classes/block.php:1248-1260`,
`includes/classes/islands.php:766-808` (`render_endpoint`, no `$_GET`
neutralization), `islands.php:915-929` (cache key).

`Block::render()` reads `$_GET['blockstudioMode']` and `$_GET['postId']` on every
render, including renders driven by the new unauthenticated
`POST /blockstudio/v1/island/render`. Neither value is in the HMAC-signed payload
(name/mode/attributes/context) nor in the fragment cache key
(name/attributes/scope/version). Two concrete attacks, both by an anonymous
visitor replaying a valid marker signature that is present in the page HTML:

- **Cache poisoning:** `?blockstudioMode=editor` makes `render_phase()` return
  `normal` with `$isEditor = true`; the editor-variant output (with
  interactivity directives processed) is stored under the normal cache key and
  served to all visitors for the TTL. `?postId=N` caches wrong-post output the
  same way.
- **Private content disclosure:** `?postId=<draft/private id>` renders block/Twig
  templates against an attacker-chosen non-public post with no readability check.

PRD 007's Security section explicitly forbids caller-supplied post overrides. The
islands unit test already unsets these globals
(`tests/unit/islands/IslandsTest.php:14,27`), showing the render path depends on
them — but the endpoint has no guard.

**Fix:** In `render_endpoint()`, save and unset `$_GET['blockstudioMode']` and
`$_GET['postId']` around the render (restore in a `finally`). If post context is
ever supported, add it to the signed payload and the cache key and gate it to
public, readable posts. Add an E2E/unit test that replays a signature with
`?blockstudioMode=editor` and asserts the cached output is unchanged.

### P0-3. `push --prune` with a selector deletes entities outside the selection

**File:** `includes/classes/content-sync.php` — plan built by
`read_post_files:1264` / `read_term_files:1303` (honor `--post-type` /
`--taxonomy`); `prune_missing:2251` compares against `query_prunable_posts:2311`
(ALL configured post types) and `query_content_terms:2336` (ALL taxonomies).
Reachable via `cli.php:258-273`, which forwards `$assoc_args` unchanged.

Trigger: config with `postTypes: ['page','post']`; pull; then
`wp bs content push --post-type=page --prune --yes`. Every synced `post`-type
entity is trashed even though its file is on disk. With `--taxonomy=category
--prune`, terms of the other configured taxonomies are hard-deleted
(`wp_delete_term`, no trash). `status --post-type=page` mislabels them
`orphaned` via the same bug in `preview_prune_missing:498`.

**Fix:** Restrict `query_prunable_posts` / `query_content_terms` to the selected
types/taxonomies (pass the selection args through), or refuse `--prune` when a
selector is present. Fixing `prune_missing` and `preview_prune_missing` in one
shared "collect orphans" helper (see P3 duplication) closes both. Add a CLI test.

### P0-4. Malformed content JSON is silently dropped; `--prune` then deletes the entity

**File:** `includes/classes/content-sync.php:1276-1279` (`read_post_files`) and
`1313-1316` (`read_term_files`):

```php
$data = $this->read_json_file( $file );
if ( ! is_array( $data ) ) { continue; }   // no error row emitted
```

Trigger: one content JSON file gets a syntax error (merge-conflict marker, bad
edit). `push --prune --yes` then omits its UID from the plan, validation passes
(no error rows), and `prune_missing` trashes the post / hard-deletes the term.
Plain `push` silently skips it too, so the user believes it synced.

**Fix:** When `json_decode` fails on an existing file, emit an `error` row.
`has_error_rows` already aborts apply and blocks prune, so this both surfaces the
problem and prevents the deletion.

### P0-5. `apply_meta` deletes attachment-reference meta when `media: 'none'`

**File:** `includes/classes/content-sync.php:1169-1178`.

The deletion loop removes any allowlisted meta key not present in the incoming
`$meta`, checking only `meta_key_allowed`:

```php
foreach ( $existing as $key => $_values ) {
    if ( $this->meta_key_allowed( $key ) && ! array_key_exists( $key, $meta ) ) {
        delete_metadata( $type, $id, $key );
    }
}
```

But `should_drop_meta_key` (`content-sync.php:1221-1224`) intentionally strips
attachment-reference keys from files when `media === 'none'` (pull omits them,
push nulls them). So an allowlisted attachment-reference key that was correctly
dropped from `$meta` gets **deleted from the DB** on any push that applies. Data
loss for keys like `thumbnail_id` (featured image).

**Fix:** Skip keys where `should_drop_meta_key( $key )` is true in the deletion
loop.

### P0-6. `serve_markdown` serves draft/private/password-protected page sources publicly

**File:** `includes/classes/pages.php:1175-1216` (`serve_markdown`), post looked
up via `find_collection_post_by_relative_path` (status-blind `post_status =>
'any'`, `pages.php:749-757`) with a `get_page_by_path` fallback that is also
status-blind.

`serve_markdown()` never checks post status or `post_password` before echoing the
file. File-backed pages default to `draft` (`page-discovery.php:1337`), so any
anonymous visitor can read the full markdown of an unpublished page via
`/collection/path.md`.

**Fix:** Bail unless the post is publicly viewable
(`is_post_publicly_viewable( $post )`) or the current user can `read_post`. Add a
test hitting `.md` for a draft page as an anonymous request.

---

## P1 — High-impact correctness, security, and new-feature defects

### P1-1. Caches grow without bound (runtime, editor assets, site templates, tailwind)

**Files:** `includes/classes/build-cache.php:56-85` (populate version), `:124-143`
(runtime key), `:311-338` (`write`); `includes/classes/tailwind.php:126-150`,
`:214-235`; `includes/classes/site-templates.php:643`.

`write()` creates `uploads/blockstudio/cache/<scope>/<key>.php` and never deletes
siblings. There is no prune/cleanup code anywhere in the plugin (the only cache
clear is a manual Tailwind WP-CLI command, `cli.php:527-546`). The runtime cache
key includes a `populate` version that is regenerated as a fresh UUID on
`save_post` and on **every** post/term/user meta add/update/delete
(`build-cache.php:58-84`). So every content change — every WooCommerce order,
every meta write — orphans a full serialized runtime cache file forever. The
editor-assets scope (keyed on per-asset mtimes), the `site-templates` scope, and
the Tailwind cache (one file per unique candidate set) all accumulate the same
way. On a busy site this is unbounded disk growth.

**Fix (no behavior change):** On `write()`, derive a stable per-scope+instance
filename prefix and delete stale siblings, or keep-last-N / prune-older-than-N
inside `write()`. Apply the same to the Tailwind cache.

### P1-2. `Utils::attributes()` emits scalar values unescaped (stored XSS)

**File:** `includes/classes/utils.php:76-82`.

```php
$value = $value['value'] ?? ( is_array( $value ) ? esc_attr( wp_json_encode( $value ) ) : $value );
if ( ! $variables ) {
    $attributes .= 'data-' . $key . '="' . $value . '" ';   // scalar $value NOT escaped
} elseif ( ! is_array( $value ) ) {
    $attributes .= '--' . $key . ': ' . $value . ';';        // scalar $value NOT escaped
}
```

Only the array branch is escaped. A scalar string (or a `['value' => ...]` field)
is written raw into an HTML attribute or style. The sibling
`Utils::data_attributes()` (`utils.php:107`) escapes both sides, so this is an
inconsistency, not a deliberate choice. The public wrappers
`bs_render_attributes` / `bs_render_variables` / `bs_attributes` / `bs_variables`
(`includes/functions/functions.php:194-233`) carry `phpcs:ignore` comments
asserting "Utils::attributes handles escaping" — **false** for the scalar case.
Stored XSS is reachable when a template passes user-editable attribute values to
these documented helpers (exactly what `tests/theme/.../native-twig/index.twig`
does).

**Fix:** `esc_attr( $value )` in both scalar branches; correct the false
`phpcs:ignore` comments on the wrappers.

### P1-3. Islands: logged-in visitors render as anonymous in every fragment

**Files:** `includes/classes/islands.php:687-694` (runtime `fetch`),
`includes/classes/rest.php:394` (`permission_callback => '__return_true'`).

The island runtime sends `credentials: 'same-origin'` but **no `X-WP-Nonce`
header**. WordPress core demotes any cookie-authenticated REST request without a
nonce to user 0 (`rest_cookie_check_errors` → `wp_set_current_user(0)`). So inside
every fragment render `is_user_logged_in()` is false and
`get_current_user_id()` is 0. The shipped fixture
`tests/theme/blockstudio/islands/dynamic/index.php` always shows "guest" for a
logged-in user after the swap. This defeats PRD 007's central promise (per-user
output correct for logged-in visitors) and collapses the `per: "user"` cache
scope to `user:0`. Every other REST fetch in the plugin sends a nonce
(`rpc.php:70`, `database.php:76`, `code.tsx:161`); the island runtime is the only
one that does not. E2E only covers anonymous visitors, so this path is untested.

**Fix:** The cached shell cannot embed a nonce (correct per PRD). Bootstrap one at
runtime load via a tiny no-store GET endpoint and send it as `X-WP-Nonce`; or add
route-scoped authentication that restores the cookie identity, using the HMAC
marker signature plus `Origin`/`Sec-Fetch-Site` as the CSRF gate. Add a
logged-in E2E case.

### P1-4. Islands: `request_attributes` filter breaks all signatures when used

**File:** `includes/classes/islands.php:824-834` (`render_endpoint_item`).

Marker signatures are computed from `filter_attributes()` output only
(`marker()`, `islands.php:459-461`). But the endpoint applies the
`blockstudio/islands/request_attributes` filter **before** building the payload it
verifies. Any callback that changes anything makes every island on the site fail
with `blockstudio_island_invalid_signature`, so this documented extension point
(`docs/.../islands.mdx:287`) cannot be used for its stated purpose.

**Fix:** Verify the signature against the `filter_attributes()` result first, then
apply `request_attributes` to derive the attributes actually passed to render.

### P1-5. Canvas REST sync permanently resets `post_parent` to 0 for draft-parent hierarchies

**Files:** `includes/classes/page-registry.php:135-146` (`hydrate_from_posts`
loads `post_status => 'publish'` only), `includes/classes/page-sync.php:783-791`
(`resolve_parent_id` via registry → 0 when parent not hydrated), `page-sync.php:513-517`
(writes the 0).

Canvas `refresh()` / `stream()` call `$sync->sync()` in a REST request where
`is_admin()` is false, so the registry lazily hydrates from **published** posts
only. Default page `postStatus` is `draft`, so a draft parent is never hydrated,
`resolve_parent_id()` returns 0, and the child's existing `post_parent` is
overwritten with 0. The new fingerprint is then stamped, so the next admin
`Pages::init` short-circuits on the fingerprint and never repairs the hierarchy.
Trigger: a hierarchical collection with draft pages + a canvas live edit of a
child page file.

**Fix:** Resolve the parent by meta query (`_blockstudio_page_key` = parent_key)
when the registry has no entry, or hydrate all statuses in `hydrate_from_posts()`
and filter statuses in the read APIs instead.

### P1-6. Canvas REST endpoints are live even when canvas is disabled

**File:** `includes/classes/canvas.php:37-41` (constructor always hooks
`rest_api_init`), `:368-404` (`register_endpoints`, no enabled check — the
`dev/canvas/enabled` gate exists only for the admin page/assets at `:49`, `:96`),
`:530-560` (`stream` infinite 1s loop, `set_time_limit(0)`).

A contributor-level user (`current_user_can('edit_posts')`) can, on any site
including production with canvas off: trigger a full page discovery + sync
(creates/updates/publishes/trashes posts) via `/canvas/refresh`, and hold
PHP-FPM workers open with the `/canvas/stream` poll loop.

**Fix:** Bail in both callbacks (or skip registration) when `dev/canvas/enabled`
is false; consider requiring `manage_options` for a dev-only tool.

### P1-7. Meta storage strict comparison: `userScoped` lists always empty, typed filters never match

**File:** `includes/classes/database.php` — `meta_list:1333`,
`meta_paginate:1366`, `meta_count:1406` use strict `!==`; the jsonc twins
(`:1628`, `:1658`, `:1695`) deliberately use loose `!=` with a
"Intentional loose comparison for query param strings" phpcs comment. The meta
copies diverged from the intended behavior.

Two concrete failures: (a) `storage_list` injects
`$filters['user_id'] = (string) get_current_user_id()` (`database.php:873`) while
`storage_create` stores it as int (`:994`), so any `userScoped: true` +
`storage: 'meta'` schema returns **zero rows** from REST list, `bs.db().list()`,
and the admin data viewer. (b) A REST filter on an integer/number/boolean meta
field (`?count=5`) never matches because stored values are typed.

**Fix:** Use the same loose (or normalized-to-string) comparison in the three
meta closures, matching jsonc.

### P1-8. Static builders shadow trait renderers, have drifted, and emit save-invalid pullquote markup

**File:** `includes/classes/block-tags.php:1756-1767` (`build_block_array` checks
the builders registry at `:1806-1829` before the trait renderers at `:52-87`).

18 block types are defined in both registries; because builders win,
those 18 `render_*` trait methods are unreachable through every internal path,
and the two copies have already diverged:

- **`build_pullquote` (`:2212`) is a correctness bug:** it emits
  `<figure class="wp-block-pullquote"><blockquote>` + raw unescaped body with no
  `<p>` wrapper, while `trait-pullquote-renderer.php:34-35` wraps plain-text body
  in `<p>` and escapes it. Core's canonical save markup is
  `<blockquote><p>…</p>…</blockquote>`. A `<bs:core-pullquote>` in a synced
  page/pattern produces stored markup that fails editor save-validation.
- `build_more` (`:2085`) ignores the `customtext`/`noteaser` remap that
  `trait-more-renderer.php` performs → the custom text is silently dropped.
- `build_embed` uses `esc_html($url)` vs `render_embed`'s `esc_url($url)`;
  `build_button` defaults href to `''` vs `'#'`; `build_heading` clamps level vs
  `render_heading` resetting to 2.

**Fix:** Pick one source of truth — have the builders delegate to the trait
renderers (or drop the leaf entries from the builders registry and keep the
traits). Fix `build_pullquote` to match the trait. This also removes the drift
class permanently.

### P1-9. Custom field type `rest_schema` breaks repeater/array storage saves (new 7.5.0)

**File:** `includes/classes/field-type-registry.php:296-316`
(`get_storage_rest_schema`), consumed by
`storage-handlers/post-meta-storage.php:46-55` and `option-storage.php:46-55`.

`get_storage_rest_schema()` returns the type definition's `storage.rest_schema`
whenever `is_custom_type()` is true, ignoring the resolved `$value_type`. A
custom field with a definition `rest_schema` (the PRD's canonical
`object` + `additionalProperties: string`) used inside a repeater with
`postMeta`/`option` storage registers the meta/setting as `type => 'array'` but
with an object REST schema. The stored value (array of per-row objects) then
fails `rest_validate_value_from_schema` and the save errors out. Separately, the
generic array fallback (`:311-316`) forces `items => { type: object }`, so a
custom `array` type storing scalars also fails validation.

**Fix:** When `$value_type === 'array'` and the definition schema is not
array-typed, wrap it (`['type' => 'array', 'items' => $definition_schema]`); for a
custom array type with no explicit schema, default to `['type' => 'array']` (no
`items`). Add unit coverage for custom-type + repeater + storage.

Related (new 7.5.0), **`build.php:926-944`:** the legacy `Build::build_attributes`
guard `if ( ! isset( $attributes[ $field_id ] ) ) { continue; }` (`:942`) sits
*below* the `returnFormat`/`populate`/`_blockField` assignments, so a
`produces_attribute => false` custom type that also declares one of those keys
auto-vivifies a phantom attribute, while `Attribute_Builder` produces nothing.
Move the guard above `:926`. (Best resolved together with P3's legacy-path
consolidation.)

The four highest-impact client-side (React) bugs found in the audit —
`refreshOn` stale-attribute refetch, WYSIWYG stale-after-reorder, `fetchSingle`
out-of-order/no-catch, and the module-global `repeaters` sort corruption — are
real but **deferred**. Their fixes all live in the render/effect/ref path that a
prior attempt broke. See the "Deferred" section; do not attempt them in 7.5.0.

---

## P2 — Lower-impact bugs and robustness (PHP)

Real, verified, concrete trigger, smaller blast radius. Fix opportunistically.
These are all PHP; the React P2 items are moved to "Deferred".

| Area | File:line | Issue |
|------|-----------|-------|
| Assets | `includes/classes/assets.php:1525-1530`, `:1605-1614` | Toggling `assets/minify/*` never recompiles existing `_dist` files (compiled filename hash excludes settings). Mix setting flags into `get_asset_version()`. |
| Assets | `includes/classes/assets.php:1084-1091` | `get_assets_html` drops any asset whose filename merely *contains* `view` (e.g. `preview.css`, `overview.js`). Match `/[-.]view\./`. |
| Assets | `includes/classes/assets.php:960-975` | `get_preview_assets` buckets by `strpos($k,'style')` not `is_css()` → `custom.css` treated as script, `style-switcher.js` as style. |
| Assets | `includes/classes/build.php:2045,2055,2065` (+`:1466,1476,1486`) | Category prefixes are loose `str_starts_with` → `globals.css` becomes a site-wide global, `administration.css` becomes admin-only. Require a `.`/`-` boundary. |
| Build | `includes/classes/build.php:902-911` | `?? $v['options']` right operand unguarded → "Undefined array key 'options'" for a `token` field with a default on every uncached build. Add `?? array()`. |
| Build | `includes/classes/build.php:1100-1104` | `Build::get_instance_name` `explode(...)[1]` → undefined offset for `Build::init` paths outside wp-content (symlinks). |
| ESModules | `includes/classes/esmodules.php:166-171` | `reset($matches[1])` can be `false` → fetches `https://esm.sh` homepage; modules that only re-export a default never resolve. Bail when empty. |
| Robustness | `assets.php:1556,1626`, `esmodules.php:231`, `esmodulescss.php:171`, `tailwind.php:148,232`, `content-sync.php:2600`, `database.php:1607` | Unchecked `file_put_contents` → phantom compiled assets / sync state stamped after a failed write. Also `assets.php:1772` treats `filesize(false)` as renderable; `database.php:1607` jsonc write lacks `LOCK_EX`. Check the return, surface/skip on failure. |
| DB | `includes/classes/database.php:762,787` | `handle_create`/`handle_update` fatal (500) on non-JSON body (`get_json_params()` null → `TypeError`). Coerce `(array) (... ?? [])`. |
| DB | `includes/classes/database.php:532-551` | `text` (longtext) fields run through `sanitize_text_field` → newlines stripped. Add `case 'text': return sanitize_textarea_field(...)`. |
| DB | `includes/classes/database.php:711-718` | Unknown query params (e.g. `_locale=user`, cache-busters) empty meta/jsonc/cpt lists (table/sqlite whitelist; these don't). Whitelist filter keys against schema fields once. |
| DB | `includes/classes/database.php:297,2642` | `ensure_storage` runs `dbDelta`/SQLite DDL on every REST request and every `Db::*` call (per block render). Memoize per schema key. |
| DB | `includes/classes/database.php:712`, `db.php:88` | `?limit=-1` bypasses the 100 cap (SQLite dumps all, MySQL errors). Clamp `max(1, min(..., 100))`. |
| Content sync | `content-sync.php:439` vs `:1005` | `status`/`push --dry-run` report a permanent false `conflict` for page-sync-managed posts (live fingerprint hashes `post_content`; file fingerprint uses `''`). Use `fingerprint_post_for_file` in `status_post_file`. |
| Islands | `includes/classes/islands.php:916` | `per: "user"` fragment cache shares one `user:0` entry across all anonymous visitors → guest A's session-dependent fragment served to guest B. Skip caching when user id is 0. |
| Islands | `includes/classes/islands.php` runtime `flush()` | Fragment swapped via `innerHTML` → template-authored `<script>` never executes. Document, or use `createContextualFragment`. |
| Pages | `page-sync.php:682-699` | `mark_stale_missing` ignores its `$post_types` param and queries `post_type => 'any'` (excludes `exclude_from_search` CPTs) → orphans never pruned, frontend hydrate misses. |
| Pages | `pages.php:1350`, `page-registry.php:187` | `Pages::pages()` (no collection) doesn't hydrate on the frontend → `blockstudio_pages()` empty while `blockstudio_pages('slug')` works. |
| Pages | `page-sync.php:202-204` | Blade page templates always render view `'index'` → fatal/wrong file for a loader page with an explicit `file`. Derive the view name like `site-templates.php:396`. |
| Pages | `pages.php:1202` | Frontmatter strip regex diverges from `Page_Markdown::split_frontmatter` (no BOM handling) → BOM'd file leaks frontmatter into the `.md` response. Reuse the shared splitter. |
| Site templates | `site-templates.php:66` (+ core `has_theme_file`) | Customized file-backed templates lose the Site Editor "Reset" affordance (reported as `custom`/`user`). Filter results to set `has_theme_file = true`/`origin = 'theme'` for registered slugs. |
| Site templates | `site-template-discovery.php:244` | `title_from_slug` is applied to explicit manifest titles → "Two-Column" becomes "Two Column". Use the title verbatim when present; transform only the slug fallback. |
| Site templates | `site-templates.php:632` | `array_merge` of slug-keyed `templates`+`parts` drops a same-slug template from the cache watch list (e.g. `header` part + `header` template) → stale cache. Iterate the two arrays separately. |
| Block cache | `block-tags.php:2374-2418` | Self-closing `<bs:foo/>` render cache reuses the first instance's `index`/`indexTotal`/unique id for repeated identical tags. Exclude index/id-reading blocks, or re-stamp per instance. |

Lower-priority pages/canvas performance (real but require care, keep behavior
identical): `Patterns::init` re-reads + DOM-parses every pattern on every request
including frontend (`class-plugin.php:278`, `patterns.php:120-136`, no cache);
`Canvas::stream()` runs full discovery + `Build::refresh_blocks()` every second
unconditionally before the fingerprint compare (`canvas.php:540-548`);
`Pages::init` does full discovery + per-page fingerprint + up to 3 meta queries
per page on every admin request.

---

## P3 — Duplication and dead code (housekeeping)

Objective duplication scan (jscpd, min-tokens 70, excluding generated
`src/tailwind/data` and `src/types`): **280 exact clones, ~2,546 duplicated lines
(4.4% of source, 7.3% of tokens).** The clusters below are the ones worth
consolidating; each is verified, not just a scanner hit.

### P3a. Legacy `Build::build_attributes` parallel to `Attribute_Builder`

The modern attribute path is `Attribute_Builder` (used by `Block_Registrar`); the
legacy inline implementation `Build::build_attributes` (`build.php:463`) still
runs and has already drifted (the phantom-attribute bug P1-9; the missing
`$has_dynamic_args` guard in the populate transform). A parity test
(`tests/unit/attribute-builder/AttributeBuilderTest.php:1744-1785`) exists
*because* the team already fears this drift. Its only non-test callers are two
REST handlers (`rest.php:544`, `:560`) that themselves belong to routes with no
first-party caller (see P3d), plus internal `build.php:2486,2642`.

**Fix:** Point the remaining internal/REST callers at `Attribute_Builder`, delete
`Build::build_attributes` and the parity test, and remove the now-unused
`Attribute_Builder::is_tailwind_active()`/`reset_tailwind_active()`
(`attribute-builder.php:274,283`, no production callers — the live flag lives in
`Block_Registry`).

### P3b. Duplicated PHP helpers with 3-5 copies each (extract to shared utils)

| Helper | Locations | Note |
|--------|-----------|------|
| `read_json_file` | `block-discovery.php:426`, `page-discovery.php:1554`, `content-sync.php:2569`, `site-template-discovery.php:276`, inline in `pattern-discovery.php:82` | 5 copies, slightly divergent (return type, `file_exists` vs `is_file`). One `Utils::read_json_file(): ?array`. |
| Theme `get_paths` | `pages.php:892`, `patterns.php:92`, `site-templates.php:227` | template-dir + child-theme pattern; site-templates uses a different idiom. `Utils::theme_subdir_paths($folder)`. |
| Blade/Twig source compile | `page-sync.php:202-209`, `patterns.php:128-133`, `site-templates.php:395-403` | 3 copies; only site-templates derives the view name correctly (fixes P2 Blade bug). Shared `Template_Compiler::compile()`. |
| Index-source candidate list | `page-discovery.php:1224`, `pattern-discovery.php:133`, `site-template-discovery.php:308` | `index.php > .blade.php > .twig` + `is_twig`/`is_blade` flags. Shared resolver. |
| Registry singleton/`instance`/`reset`/`get_paths` boilerplate | `block-registry.php:207`, `field-registry.php:41`, `page-registry.php:83`, `pattern-registry.php:47`, `site-template-registry.php` | ~20 lines each; abstract base registry. |
| Settings-loader tail | `filter-loader.php:73`, `options-loader.php:86`, `json-loader.php:67` | (See P3d — these classes are dead; deletion supersedes de-dup.) |

### P3c. Large intra/inter-file clone clusters

| Cluster | Locations | Fix |
|---------|-----------|-----|
| `block-tags.php` leaf/container block arrays (1,064 cloned lines total) | leaf 5-key return ×32, container open/null/close assembly ×~23 across `block-tags.php` and the 25 renderer traits | `Block_Tags::leaf_block($name,$attrs,$html)` + `container_block(...)` helpers; `rebuild_container_inner_content()` for the ×4 snippet at `block-tags.php:1331/1363/1415/1546`. Folds into the P1-8 single-source-of-truth fix. |
| RPC/Cron/DB subsystem machinery (~200 lines) | `rpc.php:355-479` ≈ `cron.php:105-227` (block-file loader + attribute reflection + name normalize); `rpc.php:75-127` ≈ `database.php:195-247` (client injection: `inject_frontend_client`/`inject_editor_client`/`client_script`/`has_any`) | Shared `Block_File_Loader` trait (filename + filter + normalizer) and `Client_Injector` trait (bs.fn vs bs.db). |
| `database.php` backend boilerplate (708 cloned lines) | filter closure ×6 (`meta_*`/`jsonc_*`), WHERE builder ×4 (`table_*`/`sqlite_*`), `meta_query` builder ×2 (`cpt_*`), `meta_*`/`jsonc_*` CRUD same read-modify-write algo | `records_*` helper parameterized by load/save closures (~120 line reduction). Fixing P1-7 in one shared closure prevents future divergence. |
| `content-sync.php` orphan collection | `preview_prune_missing:498` ≈ `prune_missing:2251`; `status_post_file` ≈ `status_term_file` | Shared "collect orphans" iterator — also the single-point fix for P0-3. |
| `Block::get_option_value` vs `Option_Value_Resolver::get_option_value` | `block.php:118-186` ≈ `option-value-resolver.php:30-146` (~70 lines, both live, callers split) | Make `Block::get_option_value` delegate to the resolver. |
| Populate options transform | `build.php:726-790` ≈ `select-field-handler.php:176-260` (~70 lines, already diverged) | Keep the resolver's copy, delete the two duplicates. |
| ESModules fetch-and-write | `esmodules.php:197-234` ≈ `esmodulescss.php:142-174` | Wire the unused `Abstract_ESModule` base (P3d) which was written for exactly this, or one shared helper. |
| `Build::process_block_assets` vs `register_cached_assets` | `build.php:1993-2125` ≈ `:1440-1505` (~55 lines) | `register_asset_with_registry($asset,$data,$registry)`. |
| option-storage vs post-meta-storage | `option-storage.php:90` ≈ `post-meta-storage.php:91` (31-line clone) | Shared base/trait for the common tail. |
| React **types** (safe) | `PreloadEntry` interface ×4 (`render-cache.ts:4`, `canvas.tsx:39`, `update-queue.ts:14`, inline in `blocks/index.tsx:302`), `Page`/`BlockItem` ×3 (`canvas.tsx:14`, `update-queue.ts:1`, `artboard.tsx:10`) | Type-only dedup into a shared types module. No runtime code changes — safe. This is the only React DRY item in scope. |

> **Deferred React DRY:** the `use-get-css-*` hook merge, `waitForMedia`, the
> repeater/panel group-renderer merge, the `blockVisibility` stripping helper, and
> `text.tsx`/`textarea.tsx` all touch hooks, components, or the render/hash path.
> They are **out of scope** — see "Deferred". Do not extract them in 7.5.0.

### P3d. Dead code (verified zero callers across `includes/`, `src/`, `tests/`, built bundles, `docs/`; accounts for hook/REST/CLI/dynamic usage)

**PHP — whole classes/files:**
- `includes/classes/asset-discovery.php` (388 lines) — only a `require_once`; stale
  parallel of `Build::process_block_assets`, already drifted. Delete + the require
  at `class-plugin.php:107`.
- `includes/classes/abstract-esmodule.php` (226 lines) — required but nothing
  extends it. Delete, or wire it (see P3c ESModules).
- `includes/classes/settings-loaders/{options,json,filter}-loader.php` +
  `interfaces/settings-loader-interface.php` (~380 lines) — required at
  `class-plugin.php:77,111-113`, never instantiated; `Settings` has its own copies
  and the docblocks describe a "loader chain" that doesn't exist. Delete + the
  requires.

**PHP — methods/branches:**
- Dead REST routes (no first-party caller; only registration-assertion tests):
  `/data`, `/blocks`, `/blocks-sorted`, `/files`, `/attributes/build`,
  `/gutenberg/block/update`, and — notably — `/editor/options/save`
  (`rest.php:502`), which writes `blockstudio_settings` and the theme
  `blockstudio.json` gated only by `Admin::is_allowed()`. Remove the routes + their
  test asserts (this also removes the last non-internal `Build::build_attributes`
  callers, unblocking P3a).
- `Block_Registry`: `has_block:272`, `get_override_configs:350`,
  `get_blade_for_instance:370`, `update_block_data:737`, `set_files:490`,
  `set_assets:652` (superseded by `merge_files`/`add_asset`).
- `Html_Parser::$block_renderers` (`html-parser.php:51,57`) — written on every
  `new Html_Parser()` (running `get_renderers` for nothing), never read.
- `Attribute_Builder::build_attributes_recursive` `$is_override`/`$is_extend`
  params (`attribute-builder.php:179`) — threaded through but never used.
- `Option_Value_Resolver::resolve_nested_value` / `build_options_from_populate` /
  `transform_query_options` (`option-value-resolver.php:156-264`) — zero callers.
- `Storage_Registry::has_storage_type` / `get_handlers` — tests only.
- `Build::build_attribute_ids` (`build.php:989-1013`) — only self-recursive.
- `hydrate_cached_runtime_build` no-op dependency-filter branch (`build.php:1355-1367`)
  — operates on payload keys `write_runtime` never writes.
- `Assets::get_interactivity_api_import_map` (`:493`); `ESModules::get_module_matches`
  string-rewrite mode; `assets.php:1536` always-true `if` after an early return.
- Page/pattern getters unused externally: `page-registry.php` `has_page`,
  `get_synced_post`, `get_collections`, `get_errors` (page errors are collected but
  never read — write-only error reporting); `page-sync.php:884` `delete_synced_post`;
  `page-discovery.php:198` `get_pages`; `pattern-discovery.php:153` `get_patterns`;
  `page-markdown.php:51` `has_frontmatter`. (Caveat: `Page_Registry` is passed to the
  public `blockstudio/pages/synced` action, so treat its getters as maybe-public.)

**TypeScript — Tier 1, safe standalone deletions (verified against webpack entry
points and dynamic-import strings). These are the primary in-scope TS work:**
- `src/utils/`: `capitalize-first-letter.ts`, `download-file.ts`, `is-css.ts`,
  `log.ts`, `unique-object-array-by.ts`, `get-filename.ts`.
- `src/blocks/utils/get-asset-id.ts`, `src/blocks/utils/replace-placeholders.ts`.
- `src/const/spacings.ts`, `src/components/error/index.tsx`, `src/types/builder.ts`.
- `src/types/blockstudio.ts` (1,020 lines, generated by `scripts/generate-types.ts`,
  never imported — stop tracking or exclude from build).
- `src/tools/` — two empty directories, untracked. Delete.

**TypeScript — Tier 2, deletions inside render-path files (only if you can prove
the deadness by reading, and E2E stays green; otherwise skip):** These live in
hot editor components, which is exactly where the prior attempt caused breakage.
Removing genuinely dead code here is safe, but the deadness analysis must be
certain and re-checked against E2E. When in doubt, leave them.
- The `<Fields config>` prop path (`fields/index.tsx` early return `:125`,
  seeding effect `:147-163`, `files.tsx` branch) — `config` is never passed.
- `block/index.tsx` `response` prop (`:99`, never passed) + empty `if (event) {}`
  (`:373`); `select.tsx:210-216` no-op `onFilterChange` filter.

### P3e. Consistency notes (PHP only — fix while nearby, not standalone work)

- `error_log()` used raw in new code (`field-type-registry.php:597`) while the
  plugin has `Error_Handler` gating on `WP_DEBUG_LOG`.
- camelCase attribute remapping is applied by some block-tag renderers
  (media-text, accordion, more) but not others (cover, group); unify when
  introducing the container-renderer helper (P3c).

(The `code.tsx` raw-fetch-vs-`apiFetch` inconsistency and the missing
`react-hooks` ESLint plugin are client-side and moved to "Deferred".)

---

## Deferred — client-side (React) behavior (out of scope for 7.5.0)

These are **real, verified** bugs, but every fix lives in the editor's
render/effect/ref/state path. A prior attempt at them broke the whole E2E suite.
They are recorded here so they are not lost, and are **not to be fixed in 7.5.0**.
They need a dedicated effort by someone with deep knowledge of the block
render/SSR-fetch pipeline, changed one at a time, each validated against a full
green E2E run. Do not touch these as part of this housekeeping pass.

**High-impact (were P1):**

| Was | File | Bug | Eventual direction (not now) |
|-----|------|-----|------------------------------|
| P1-10 | `block/index.tsx:499-510,408` | `refreshOn` listener keeps the first-render debounced `fetchSingle` → refetches with stale mount-time `attributes`/context and clobbers correct markup | ref-forward the handler or correct effect deps |
| P1-11 | `wysiwyg.tsx:57,92-101` + `repeater.tsx:415` | WYSIWYG reads `value` once; position-keyed repeater rows reuse instances → stale content after reorder/remove/duplicate, next edit writes wrong row | stable row identity keys, or sync on `value` change |
| P1-12 | `block/index.tsx:389-406` | `fetchSingle` has no out-of-order guard and no `.catch` → older response clobbers newer markup; failed first render = permanent spinner | monotonic request id + catch (pattern exists in `select.tsx`) |
| P1-13 | `repeater.tsx:24,484-488` + `list/index.tsx:33-37` | Module-global `repeaters` registry keyed by field id, not clientId → same-block instances corrupt each other's drag-sort | scope the key by clientId |

**Lower-impact (were React P2):**

| Area | File:line | Bug |
|------|-----------|-----|
| Conditions | `is-allowed-to-render.ts:54-56` | `empty` operator can never be true → documented operator permanently hides the field |
| Fields | `fields/index.tsx:57,301` | Module-global `isDeleting` (only `add()` resets) suppresses repeater `min`/default seeding across all blocks after any delete |
| Editor | `blocks/index.tsx:154-162` | `clickSave` runs on any Cmd/Ctrl chord (no `e.key==='s'`) → spurious writes + re-render on Cmd+C/B |
| Checkbox | `checkbox.tsx:31-36` | "Toggle all" stores `{value,label,disabled,innerBlocks}` vs `{value,label}` → hash/payload divergence, keys leak into saved content |
| Classes field | `classes/index.tsx:75` | Observer spreads stale first-render `attributes` (should use `attributesRef.current`) |
| Perf (editor) | `blocks/index.tsx:103`, `fields/index.tsx:133`, `block/rich-text.tsx:30` | Three `useSelect` subscribers read the whole richtext map → every keystroke re-renders every block's edit component |
| Code field | `code.tsx:180-200` | Injected `<style>`/`<script>` never removed on unmount (sibling `extensions.tsx:243` has cleanup) |
| Select | `select.tsx:181-183` | `debounce` memoized `[]` pins mount-time `fetcher` (stale `{attributes.x}` populate); missing `.cancel()` |

**Deferred React DRY / tooling:** `use-get-css-classes.ts` ≈ `use-get-css-variables.ts`
(hook merge + shared cache-pollution bug); `waitForMedia` (`canvas.tsx:220-292` ≈
`wait-for-iframe.ts:33-70`); group renderer (`repeater.tsx:131-236` ≈
`panel/index.tsx:32-80`); `blockVisibility` stripping ×3 (`render-cache.ts:175`,
`block/index.tsx:377`, `batch-fetcher.ts:34`, must stay in sync with hashing);
`text.tsx` == `textarea.tsx`; `code.tsx:153-165` raw `window.fetch` vs `apiFetch`;
and adding the `react-hooks` ESLint plugin (would flag the conditional-hook
patterns at `rich-text.tsx:37` / `inner-blocks.tsx:53` / `fields/index.tsx:125`).
All touch hooks/components/render paths — defer with the bugs above.

---

## Suggested execution order

1. **P0 security + data loss first** (P0-1..P0-6). Small, high-value, independent.
   Add a regression test with each.
2. **P1 by subsystem** so a fixer stays in one area: islands (P0-2, P1-3, P1-4,
   plus islands P2 items), canvas/pages (P1-5, P1-6, pages P2), database (P1-7,
   P1-9 storage, DB P2), block-tags (P1-8 + P3c leaf/container helpers together).
   All P1 work is PHP — the React P1 items are deferred (see "Deferred").
3. **P1-1 cache pruning** on its own (touches build-cache + tailwind).
4. **P3 consolidation**, largest wins first: the legacy `build_attributes`
   deletion (P3a, unblocked once the dead REST routes in P3d go), then the
   block-tags helper extraction (folds P1-8), then database.php backend helpers
   (folds P1-7), then the shared `read_json_file`/`get_paths`/`Template_Compiler`
   utils. Delete the three dead whole-classes (P3d) early — pure removal, no risk.
5. **TS housekeeping last and standalone** (Tier-1 dead-code deletions + the
   type-only dedup). No behavioral React changes. Run E2E in CI after this batch
   and confirm it is still green before considering the TS work done.

## Definition of done

- Every P0 and P1 has a code fix and a test that fails before / passes after.
  Unit tests verified locally; E2E specs verified in CI.
- PHPCS, `tsc --noEmit`, ESLint remain green.
- A final `[all]` commit is green across every GitHub CI job (unit + E2E). CI, not
  local runs, is the acceptance gate.
- The islands logged-in path (P1-3) and the prune-selector path (P0-3) have new
  automated coverage, since both were entirely untested.
- Duplication scan re-run shows the **in-scope** P3c clusters gone; `Asset_Discovery`,
  `Abstract_ESModule`, and the settings-loaders are deleted.
- `readme.txt` has a consolidated `= 7.5.0 =` changelog entry for the user-visible
  fixes.
- **No React behavioral changes were made.** `src/` work is limited to Tier-1
  dead-code deletion and type-only dedup; the editor E2E suite is green in CI
  after the TS batch, with no logical edits to E2E tests. Everything in "Deferred"
  is untouched.
- No behavior change beyond restoring documented/intended behavior; the 7.5.0
  feature set is unchanged from a user's perspective.

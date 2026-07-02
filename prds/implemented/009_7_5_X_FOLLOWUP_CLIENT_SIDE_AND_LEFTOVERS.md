# 009: 7.5.x Follow-up — Deferred Client-Side Fixes and 008 Leftovers

## Summary

PRD 008 (code quality housekeeping) has been implemented and independently
verified. Every PHP P0 and P1 item was fixed correctly with matching unit tests
and no regressions; the E2E suite is green in CI; no E2E test logic was changed;
and none of the deliberately-deferred React files were touched. See "Status of
008" below for the audit result.

This PRD covers what 008 deliberately left out or the fixing agent skipped:

- **Part A — Client-side (React) behavioral fixes.** The bugs 008 moved to
  "Deferred" because a prior attempt broke the whole E2E suite. These are the
  real remaining work. They need a careful, isolated methodology, not another
  batch pass.
- **Part B — PHP leftovers and hardening.** A short list of opportunistic P2
  items, optional P3 consolidations, and small hardening items surfaced during
  the 008 verification. All low priority; none are correctness-critical.

Scope and testing rules from 008 still apply (no UX/feature changes; keep PHPCS /
`tsc` / ESLint green; unit tests run locally; a green CI `[all]` run is the
acceptance gate) — with one important exception proposed for Part A, see
"Methodology".

---

## Status of 008 (context — do not re-do)

Verified by a five-cluster independent review of commit `3e1c3da`:

- **All 10 P0/P1 items: correct and unit-tested.** Path-traversal containment,
  `Utils::attributes` escaping, islands `$_GET` neutralization + logged-in
  identity + signature-before-filter, content-sync prune-selector /
  malformed-JSON / attachment-meta data-loss fixes, meta-storage comparison,
  `serve_markdown` visibility (implemented *better* than the PRD's literal spec —
  it also covers password-protected published pages), canvas parent resolution +
  endpoint gating, pullquote/builder shadowing, custom-field `rest_schema`, cache
  pruning, and the legacy `Build::build_attributes` deletion (parity preserved by
  wiring the previously-dead override/extend params into the field handlers).
- **No regressions found. No E2E tests changed. Zero deferred-React files
  touched.** All `src/` changes were type-only dedup + dead-code deletion.
- **Dead-code deletions done:** `Asset_Discovery`, `Abstract_ESModule`, the three
  settings-loaders + interface, and ~15 dead methods/branches; ~4,100 lines
  removed overall.

The items below are the remainder. Everything here is optional-to-important
cleanup and deferred behavior work — nothing here is a live P0/P1 defect.

---

## Part A — Client-side (React) behavioral fixes

These are the exact bugs from 008's "Deferred" section. They are real (verified in
the original audit) but every fix lives in the editor's render / effect / ref /
state path — the code that broke the entire E2E suite on the first fix attempt.

### Methodology (mandatory — this is why the first attempt failed)

The first attempt at these fixes ("Harden 7.5.0 systems", later reset and
abandoned) batched client-side changes and broke every E2E test. The final 008
pass succeeded precisely *because* it did not touch this code. Do not repeat the
batch approach.

1. **One bug per commit/PR.** Never batch client-side fixes. Each change must be
   isolated so a breakage is attributable to exactly one fix and revertable
   without losing the others.
2. **Read the pipeline before touching it.** Understand, end to end: the
   server-side-render fetch loop (`block/index.tsx` `fetchSingle` →
   `render-cache.ts` → `updateRender`), the richtext flush + the whole-map
   `useSelect` subscribers, and the repeater's position-based row keying that
   makes instances reused across sort/remove/duplicate. Most of these bugs
   interact; a "fix" to one can move the failure.
3. **Prefer the smallest correct change.** If a fix seems to require restructuring
   hooks or moving functions into refs across a component, stop — that is the
   pattern that backfired. Reach for the minimal, local change (a stable key, a
   guard, a single dep) and validate it in isolation.
4. **Validate each fix against E2E before the next.** A green unit run is not
   sufficient for this code — the bugs are interaction/timing bugs that only the
   browser E2E catches.
5. **RECOMMENDED / OPEN QUESTION FOR THE MAINTAINER:** allow **local** E2E for
   this Part-A work (at least a targeted subset, e.g.
   `npx playwright test <editor spec> --config=playwright.wp-env.config.ts`).
   The 008 rule was "E2E in CI only," and for that PHP work it was fine. But the
   `[all]` CI E2E suite takes **~42 minutes per run**; iterating on the most
   fragile subsystem in the codebase through 42-minute round trips is exactly
   what turned the first attempt into a multi-hour thrash that still shipped
   nothing. For Part A specifically, local E2E turns a 42-minute feedback loop
   into a sub-minute one and makes "one bug at a time, validated" actually
   practical. Please decide before starting Part A. If local E2E stays
   disallowed, expect Part A to be slow and budget for it.

### The bugs (fix in this rough order — least entangled first)

| # | File | Bug | Smallest-change direction |
|---|------|-----|---------------------------|
| A1 | `src/blocks/utils/is-allowed-to-render.ts:54-56` | `empty` operator can never be true (`v && v === ''` is always falsy) → the documented operator permanently hides the field | Return `val === '' || val === undefined || val === false`. Self-contained pure function — the safest one to start with. |
| A2 | `src/blocks/components/fields/components/checkbox.tsx:31-36` | "Toggle all" stores `{value,label,disabled,innerBlocks}` while individual toggles store `{value,label}` → hash/payload divergence, extra keys leak into saved content | Map to `{value,label}` before `change(..., true)`. Localized. |
| A3 | `src/blocks/index.tsx:154-162` | `clickSave` runs on any Cmd/Ctrl chord (no `e.key === 's'` check) → spurious attribute writes + re-render on Cmd+C/B | Add the `e.key === 's'` guard. Localized. |
| A4 | `src/blocks/components/fields/components/code.tsx:180-200` | Injected `<style>`/`<script>` never removed on unmount (sibling `extensions.tsx:243` already has cleanup) | Add the same `return () => element?.remove()` cleanup. |
| A5 | `src/blocks/components/fields/components/classes/index.tsx:75` | MutationObserver spreads stale first-render `attributes` | Spread `attributesRef.current` instead of `attributes`. |
| A6 | `src/blocks/components/fields/components/select.tsx:181-183` | `debounce` memoized `[]` pins mount-time `fetcher` (stale `{attributes.x}` populate); missing `.cancel()` | Ref-forward the fetcher; add `.cancel()` cleanup. Touches refs — validate carefully. |
| A7 | `src/blocks/components/block/index.tsx:499-510,408` (P1-10) | `refreshOn` listener keeps first-render debounced `fetchSingle` → refetches with stale attributes and clobbers correct markup | Ref-forward the handler, or add `debouncedFetchSingle` to deps. Render path — high care. |
| A8 | `src/blocks/components/block/index.tsx:389-406` (P1-12) | `fetchSingle` has no out-of-order guard and no `.catch` → older response clobbers newer markup; failed first render = permanent spinner | Monotonic request id checked before `updateRender`; add `.catch`. `select.tsx` has the pattern. Render path — high care. |
| A9 | `src/blocks/components/fields/components/repeater.tsx:24,484-488` + `list/index.tsx:33-37` (P1-13) | Module-global `repeaters` registry keyed by field id, not clientId → same-block instances corrupt each other's drag-sort | Scope the key by clientId. Sort path — high care. |
| A10 | `src/blocks/components/fields/components/wysiwyg.tsx:57,92-101` + `repeater.tsx:415` (P1-11) | WYSIWYG reads `value` once; position-keyed repeater rows reuse instances → stale content after reorder | Stable row-identity keys, or sync `val` on `value` change. Most entangled (touches repeater keying) — do last. |
| A11 | `blocks/index.tsx:103`, `fields/index.tsx:133`, `block/rich-text.tsx:30` | Three `useSelect` subscribers read the whole richtext map → every keystroke re-renders every block's edit component (perf) | Select `getRichText()?.[clientId]` per client. Perf, not correctness — lowest priority. |
| A12 | `fields/index.tsx:57,301` | Module-global `isDeleting` (only `add()` resets) suppresses repeater `min`/default seeding across all blocks after any delete | Make it a per-instance ref, or reset after the delete commit. |

**Deferred React DRY (only after the bugs, and only with local E2E):**
`use-get-css-classes.ts` ≈ `use-get-css-variables.ts` (hook merge + the shared
cache-pollution bug where the accumulator is stored under each key); `waitForMedia`
(`canvas.tsx` ≈ `wait-for-iframe.ts`); the repeater/panel group-renderer;
`blockVisibility` stripping ×3 (must stay in sync with hashing); `text.tsx` ==
`textarea.tsx`; `code.tsx:153-165` raw `window.fetch` → `apiFetch`. Optional:
adding the `react-hooks` ESLint plugin would catch the latent conditional-hook
patterns (`rich-text.tsx:37`, `inner-blocks.tsx:53`, `fields/index.tsx:125`) — but
turn it on only when you intend to resolve what it flags, or it will just fail CI
lint.

---

## Part B — PHP leftovers and hardening (low priority)

None of these are correctness-critical; all are safe, incremental PHP work. Group
into one or two small commits.

### B1. Opportunistic P2 items not implemented in 008

- **Site Editor "Reset" affordance** (`site-templates.php` `filter_block_templates`,
  the customized-slug `continue` at ~`:98`). A customized file-backed template
  still reports `custom`/`user` and loses the "Reset" button. Augment the
  DB-customized (`wp_id`) entry to set `has_theme_file = true` / `origin = 'theme'`
  for slugs that match a registered file-backed item.
- **Islands fragment `<script>` execution** (`islands.php` runtime `flush()`,
  `el.innerHTML = rendered`). A `<script>` authored in a dynamic island's template
  never executes after the swap. Either document the limitation or swap via
  `Range.createContextualFragment`.
- **`pull()` failed-write still stamps state** (`content-sync.php:104-113`).
  `write_json_file` now returns `false` on a failed write, but `update_entity_state`
  is still called unconditionally, so a disk-full/permission failure is recorded as
  `unchanged`. Gate the state stamp on the write result; surface an error row on
  failure.

### B2. Hardening surfaced during 008 verification (islands + admin)

- **Islands same-origin check is host-only** (`islands.php` `is_same_origin_browser_request`,
  ~`:966,971` use `PHP_URL_HOST`). It is scheme- and port-blind, so a same-host
  different-port/scheme origin is treated as same-origin. Not exploitable beyond
  what WP's host-scoped `logged_in` cookie already allows, but tighten to compare
  scheme+host+port against `home_url()`.
- **Islands "absent headers ⇒ trusted"** (`islands.php` ~`:962-968`): identity is
  restored when both `Origin` and `Sec-Fetch-Site` are absent (non-browser
  clients). Only benefits a caller that already holds a valid `logged_in` cookie,
  so no new capability, but confirm the default is intentional or require at least
  one same-origin signal.
- **`handle_import` partial-write-then-abort** (`admin-page.php:686-713`). A
  malicious file mid-list aborts the loop after earlier legitimate files were
  already written (the malicious one is never written — safe). Prefer validating
  all `$block['files']` entries before writing any.

### B3. Verify one P1-9 deviation

The custom-field-type generic array fallback emits `items => array()` rather than
omitting `items` as 008 prescribed (`field-type-registry.php:337-350`). Only the
schema *shape* is unit-tested. Do a live REST save of a custom scalar-value-type
field with `array`/repeater + `postMeta`/`option` storage and no explicit array
`rest_schema`; if WP's `rest_validate_value_from_schema` rejects it (missing item
`type`), switch to omitting `items`.

### B4. P3 consolidations the fixing agent skipped (optional, no regressions)

008 completed the low-risk consolidations (`Template_Compiler`,
`Utils::read_json_file`, `Utils::theme_subdir_paths`, `index_source_candidates`)
and correctly avoided the ones that touch hot paths. These remain as duplication
and can be closed if desired, but weigh each against regression risk:

- **Delete the now-dead leaf builders.** After the P1-8 reorder, the ~18 leaf
  `build_*` methods in `block-tags.php` plus `get_block_builders` are fully
  shadowed dead code (traits win). Deleting them is the clean completion of P1-8 —
  a pure removal, low risk — and is preferable to leaving dead code behind.
- `Block::get_option_value` (`block.php:118-186`) still duplicates
  `Option_Value_Resolver::get_option_value` (~70 lines). block.php was not touched
  in 008. Make `Block::get_option_value` delegate. Touches the render path — treat
  with the same care as Part A.
- `database.php` backend boilerplate (6 filter closures, WHERE builder ×4,
  `meta_query` ×2) — a shared `records_*` helper. Careful: it must not reintroduce
  the P1-7 strict-comparison bug (keep loose comparison).
- `content-sync.php` shared orphan-collection iterator (`prune_missing` /
  `preview_prune_missing`).
- `esmodules.php` / `esmodulescss.php` `fetch_module_and_write_to_file` dedup, and
  `Build::process_block_assets` vs `register_cached_assets`
  (`register_asset_with_registry`).

### B5. Test-coverage backfill (nice to have)

Add unit coverage that 008 left as gaps: canvas `stream()` / `register_endpoints`
skip when disabled (only `refresh` is tested); the site-templates same-slug
`array_merge` watch-list fix; the page-sync-managed false-`conflict` status
scenario; non-JSON body → 400 on `handle_create`/`handle_update`; `ensure_storage`
memoization.

---

## Definition of done

- **Part A:** each bug fixed in its own isolated commit; the editor E2E suite is
  green (ideally validated locally per fix, then confirmed in a final `[all]` CI
  run); no behavior changed beyond the targeted bug; no batching. If a fix can't be
  made without restructuring hooks, it is escalated, not forced.
- **Part B:** items done in one or two small PHP commits; PHPCS / `tsc` / ESLint
  green; a final `[all]` CI run green. B3 confirmed with a live save. Any dead-code
  deletion (leaf builders) re-verified as unreachable first.
- `readme.txt`: no new changelog entries for Part-B internal cleanup; a single
  user-facing line only if a Part-A fix changes visible editor behavior at the
  7.5.x release boundary.

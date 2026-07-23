# 011: Additive bsui/Parser Capabilities

## Summary

Five additive, non-breaking capabilities surfaced by a downstream-theme audit.
Each lets consuming themes drop hand-rolled boilerplate by moving a generic
mechanic into Blockstudio itself, or improves Blockstudio's own bundled `bsui/*`
components. The bundled components carry the same boilerplate the themes do, so
most of these pay off twice.

**Hard scoping rule (applies to every task):** additive generic mechanics and
improvements to Blockstudio's own bundled `bsui/*` components only. No
theme-specific content. Existing behavior for existing tags, blocks, and
templates must not change. Every task ships with tests and lands only when CI is
green with `[all]`.

**Model to follow:** the recently landed `Block_Tags::resolve_prefix_tag_name()`
nested resolution + `Block_Tags::output_has_tags()` gate (`includes/classes/
block-tags.php`) and the two `block.php` template-render gates that call it. Small,
guarded, mirrored by unit tests under `tests/unit/block-tags/`.

**None of these are already implemented in 7.5.0.** The 7.5.0 range (55 commits)
adds nested prefix resolution, the block-tags builders filter, islands, and
housekeeping; it does not add any of the five below. Task 3 is explicitly
*confirmed unfixed* (see that task). Verified against the code on branch 7.5.0.

### Sequencing and dependencies

- **Task 3 (nested render parity) is a precondition for Task 5** (and for any
  bundled or theme work that renders `bsui/button` nested via `bs_block()` /
  `bs_render_block()`, e.g. `dialog/close`, `alert-dialog/close`, `drawer/close`).
  Do Task 3 first. Its guarantee is what makes an icon slot (Task 5) render
  identically in editor preview and on the frontend.
- **Tasks 1, 2, 4 are independent** of each other and of 3/5.
- **Task 4 (button `@layer` + variants filter) and Task 5 (button icon slot)**
  both touch `bsui/button` but do not overlap (4 = CSS emission + variants; 5 =
  additive attributes + template). Either order; both are additive.

### Backward compatibility

All five are backward compatible in the sense that a site that opts into nothing
keeps identical behavior. Two carry a scoped, intentional behavior change worth
calling out:

- **Tasks 2 and 5: fully additive.** No existing input can change behavior. Task 2
  only expands new token strings (`*`, `category:`, `@theme`); every literal
  `namespace/block` list is byte-identical, and block names cannot contain `*`,
  `:`, or `@`, so no literal can be mistaken for a token. Task 5 renders the icon
  span only when an `icon` attribute is set, so a button with no icon emits today's
  markup exactly; existing saved content is unaffected.
- **Task 3: fixes broken output, mirrors shipped fixes.** It changes nested
  `bs_render_block`/`bs_block` in editor mode from the current *broken* result
  (literal unresolved `<RichText>`/`<InnerBlocks>` tags) to resolved frontend
  markup. Nothing correct is being replaced, and the mechanism is identical to the
  already-shipped `Block_Tags::render_bs_block()` and Islands fixes. Top-level
  editor preview of a block is untouched.
- **Task 1: additive except for the exact configuration it targets.** Default
  `p → core/paragraph` and any non-core block *without* a `content` richtext
  attribute are unchanged. The only behavior change is for a block that maps an
  inline tag to a registered non-core block declaring a `content` richtext
  attribute: today its inner text is parsed into a nested block; after this it is
  routed into `content`. That current behavior is the bug being fixed, and the
  `blockstudio/block_tags/builders` escape hatch still lets a site opt back to
  custom handling.
- **Task 4: intentional cascade change (the point of the task).** Wrapping bundled
  `bsui/*` CSS in `@layer bsui` *lowers* its cascade priority so unlayered theme
  CSS wins without `!important`. Default component appearance with no theme
  override is unchanged (proven by the visual-regression test), but a site that
  authored unlayered CSS which currently *loses* to a bsui rule (because the bsui
  selector is more specific) will find that CSS now *wins*. That is the desired
  outcome, but it is a real visual change for such sites, so it should ship behind
  a clear changelog note. `@layer` is supported in all evergreen browsers.

---

## Task 1: Route mapped `<p>` (and inline text tags) inner text into `content`

### Boilerplate / limitation removed

`Block_Tags::parse_all_elements()` maps raw HTML elements to blocks via the
`blockstudio/parser/element_mapping` filter. Today only the **heading** branch
routes an element's inner text into a `content` attribute for a non-core mapped
target:

- `includes/classes/block-tags.php:1524-1531`:
  ```php
  if ( preg_match( '/^h[1-6]$/', $html_tag ) ) {
      $attrs['level'] = (int) substr( $html_tag, 1 );
      if ( 'core/heading' !== $block_name && '' !== trim( $inner ) && ! isset( $attrs['content'] ) ) {
          $attrs['content'] = trim( $inner );
      }
  }
  ```
- For a mapped `<p>` the non-container path falls through to
  `build_block_array( $block_name, $attrs, $inner )` (`block-tags.php:1594`), whose
  generic fallback (`block-tags.php:1789-1815`) parses `$inner` as *inner blocks*
  and never populates a `content` attribute.

So a theme that maps `<p>` to its own richtext block must register a
`blockstudio/block_tags/builders` closure purely to copy inner text into
`content` (exactly the pattern exercised in
`tests/unit/block-tags/BlockTagsExtensionPointsTest.php:32-58`). That per-theme
paragraph-builder filter is the boilerplate this removes.

Plugin-side, the same limitation means no bundled richtext leaf block can be
targeted by a mapped inline element without a builder.

### Exact mechanism

In `parse_all_elements()`, after `resolve_html_block_name()`
(`block-tags.php:1519`) and alongside the heading branch, add a generalized
content-routing branch. `$registered_blocks = Build::blocks()` is already in
scope (`block-tags.php:1329`), and block attribute definitions are stored at
`$registered_blocks[$block_name]['blockstudio']['attributes']` (see
`build.php:1660`), each `{ id, type, ... }`.

Route inner text into `content` when **all** hold:
1. `$html_tag` is a text/inline element in a configured allowlist (start with
   `p`; consider `span`, `strong`, `em`, `a`, `blockquote`-less inline set as the
   implementer sees fit). Not a container tag.
2. `$block_name` is a registered **non-core** block
   (`isset($registered_blocks[$block_name]) && ! str_starts_with($block_name,'core/')`).
3. That block declares an attribute `['id'] === 'content'` with
   `['type'] === 'richtext'`.
4. `! isset($attrs['content'])` (never overwrite an explicit attribute).
5. `$inner` contains **no nested block/element tags**: no `<p>`, `<div>`, `<bs:`,
   `<block `, no registered prefix/alias tag, no other mapped HTML tag. Inline
   formatting (`<strong>`, `<em>`, `<a>`, `<code>`, `<br>`) may be preserved as
   literal richtext content (implementer chooses the exact inline whitelist).

When routing, set `$attrs['content'] = trim($inner)` **and pass `''` as the inner
argument** to the subsequent `build_block_array()` call so the same text is not
also re-parsed into a duplicate inner paragraph block. (This also tightens the
existing heading branch's latent double-parse; keep heading behavior observably
identical for its current assertions.)

### Why non-breaking (the guard)

- `blockstudio/block_tags/builders` remains the escape hatch: `build_block_array()`
  resolves `get_renderers()` (which includes that filter) **before** the generic
  fallback (`block-tags.php:1784-1787`), and the documented builder pattern only
  sets `content` when it is not already set. A pre-set `content` composes with,
  and is overridable by, a registered builder.
- Guard conditions 2 + 3 mean the branch only fires for a registered non-core
  block that *opted in* by declaring a `content` richtext attribute. `core/*`
  targets (the default `p → core/paragraph` mapping) are untouched, so existing
  paragraph parsing is unchanged.
- Guard condition 5 means anything with real nested structure still parses as
  inner blocks exactly as today.

### Tests (under `tests/unit/block-tags/`)

Extend `BlockTagsExtensionPointsTest` (or a new `BlockTagsMappedContentTest`):
- `<p>` mapped to a registered non-core block with a `content` richtext attribute:
  inner text lands in `content`; **no** duplicate inner paragraph block is created.
- Same block, inner containing a nested `<p>`/`<bs:>`/mapped tag: falls back to
  inner-block parsing (content not routed).
- `core/paragraph` mapping: unchanged (content not force-set by this branch).
- Non-core block **without** a `content` richtext attribute: unchanged (inner
  parsed as blocks).
- Regression: a registered `blockstudio/block_tags/builders` closure still
  overrides/decorates the routed content (escape hatch intact); reuse the
  existing paragraph-builder fixture.
- Existing heading assertions in
  `test_mapped_paragraph_and_heading_both_preserve_inner_text` still pass.

### Acceptance criteria

- Themes can map `<p>` to a richtext leaf block and get `content` populated with
  no per-theme builder filter.
- Existing tag/element parsing behavior is byte-identical for all current cases
  (headings, core targets, nested structures).
- CI green with `[all]`, no regressions.

### Effort: M  ·  Dependencies: none

---

## Task 2: `allowedBlocks` wildcard / category / `@theme` token expansion

### Boilerplate / limitation removed

`allowedBlocks` is authored as a literal JSON array on the `<InnerBlocks>` custom
tag inside block templates, and the bundled `bsui/*` blocks carry long drifting
lists, for example:
- `includes/ui/blocks/field/root/index.php:20` (11 items),
- `includes/ui/blocks/field-group/root/index.php:11` (13 items),
- `includes/ui/blocks/card/root/index.php:4`, `card/header/index.php:4`,
  `navigation-menu/root/index.php:5`, `toolbar/root/index.php:12` (multi-line
  arrays).

Theme templates carry the same shape. Every new sibling block means editing every
list by hand.

### Exact mechanism

Add a server-side token expander for `allowedBlocks` values and run it where the
`<InnerBlocks>` tag is handed to the editor. In editor/preview mode
`replace_components()` returns the content with the `<InnerBlocks allowedBlocks=
'[...]'>` tag **intact** for the client to hydrate
(`includes/classes/block.php:495-515`); the frontend path strips `allowedBlocks`
(`block.php:571`) and never uses it. So expansion belongs on the **editor** side,
before the tag reaches the client parser (`src/blocks/components/block/
inner-blocks.tsx:29-69`, which passes `allowedBlocks` straight to WordPress).

Add a helper, e.g. `Block::expand_allowed_blocks_tokens( array $list ): array`,
and invoke it during the editor-mode handoff (a scan of the returned content in
the `$is_editor_or_preview` branch at `block.php:495-515`, rewriting each
`<InnerBlocks>` tag's `allowedBlocks` JSON). Supported tokens:
- `elements/*` (and any `namespace/*`): expand to every registered block in that
  namespace, from `Build::blocks()` + `WP_Block_Type_Registry::get_all_registered()`.
- `category:content` (any `category:<slug>`): expand to every registered block
  whose `category` equals the slug (block type registry `category` field).
- `@theme`: expand to every Blockstudio block registered from the active theme
  directory (resolve via the block's source path / registration origin already
  tracked in the build data).

Literal `namespace/block` entries pass through untouched; expanded results are
merged and de-duplicated preserving author order.

Optionally rewrite the bundled `bsui/*` templates to use tokens (for example the
`field`/`field-group` lists become `category:content` + the specific `bsui/*`
parts), which is where the "kills the drifting arrays" payoff lands for the
plugin itself. Do that only after the expander and its tests are green.

### Why non-breaking (the guard)

- Only strings matching a token grammar (`*` suffix wildcard, `category:` prefix,
  or the literal `@theme`) are expanded. Any exact `namespace/block` string is
  returned unchanged, so every existing literal list produces an identical result.
- Expansion runs only in the editor/preview handoff; the frontend path
  (`block.php:571` strip) is untouched, so rendered output is unaffected.
- If a token resolves to nothing (namespace/category empty), it contributes no
  entries rather than erroring.

### Tests

- Unit (`tests/unit/block/` or `tests/unit/block-tags/`): a
  `expand_allowed_blocks_tokens()` unit covering `elements/*`, `namespace/*`,
  `category:content`, `@theme`, mixed literal + token, unknown token (passes
  through or drops per decision), and de-dup/order preservation. Seed a couple of
  registered blocks/categories in the test theme so expansion has targets.
- E2E (`tests/e2e/`): an editor test that inserts a block whose template uses a
  token in `allowedBlocks` and asserts the editor inserter for its InnerBlocks
  offers exactly the expanded set (and a literal-list control block still behaves
  as before).

### Acceptance criteria

- A template can write `allowedBlocks='["category:content","bsui/field"]'` and the
  editor restricts insertion to the expanded set.
- Every existing literal `allowedBlocks` list yields identical editor behavior.
- Frontend output unchanged.
- CI green with `[all]`, no regressions.

### Effort: M  ·  Dependencies: none

---

## Task 3: Certify nested `bs_render_block` editor/frontend parity (precondition)

### Status: real gap, NOT fixed in 7.5.0

A nested `bs_render_block('bsui/...', [...])` (or `bs_block(...)`) called inside a
parent block template does **not** render identically in editor preview vs. the
frontend. Root cause and evidence:

- `bs_render_block()` / `bs_block()` both delegate to `Render::block()`
  (`includes/functions/functions.php:40-64`), which calls `Block::render()`
  (`includes/classes/render.php:61-123`) **without scoping** `$_GET['blockstudioMode']`.
- `Block::render()` derives `$is_editor`/`$is_preview` from that **global**
  (`block.php:1248-1253`), so a nested call inherits the parent request's editor
  mode.
- In editor mode `replace_components()` early-returns after only swapping
  `useBlockProps` and appending editor wrapper classes
  (`block.php:495-515`, esp. line 505); it does **not** resolve `<RichText>` /
  `<InnerBlocks>` / `<MediaPlaceholder>`. The frontend branch (`block.php:517-676`)
  does. Result: a nested `bsui/button` (`button/root/index.php` emits
  `<RichText attribute="label"/>`) or `bsui/card` (`card/root/index.php` emits
  `<InnerBlocks/>`) leaves literal `<RichText>`/`<InnerBlocks>` tags in editor
  preview, which the parent's client parser then mis-binds.
- The fix pattern already exists for the sibling nested-render entry points and is
  simply missing here:
  - `Block_Tags::render_bs_block()` unsets `$_GET['blockstudioMode']` around
    `Block::render()` (`block-tags.php:1975-1994`, comment: "Force frontend mode
    so `<InnerBlocks />` gets replaced with actual content").
  - `Islands::render_endpoint()` does the same (`islands.php:771-780`, restore
    `824-832`).
  - `Render::block()` does neither. `render.php` is byte-unchanged across the
    entire 7.5.0 range; 7.5.0 added this scoping only to Islands
    (`e05fc817` / `3e1c3da3`).

### Exact mechanism

In `Render::block()` (`render.php:61-123`), mirror `Block_Tags::render_bs_block()`:
save the current `$_GET['blockstudioMode']`, `unset()` it around the
`Block::render()` delegation, then restore it in a `finally`. This forces embedded
blocks to render as resolved frontend HTML regardless of the ambient request mode,
matching the two existing siblings. Preserve the existing
`_BLOCKSTUDIO_EDITOR_STRING` branch (`render.php:82-105`) behavior; only the
`Block::render()` call is wrapped.

### Why non-breaking (the guard)

- `Render::block()` is only the programmatic embed path (`bs_block` /
  `bs_render_block`), never the top-level editor preview path (that is the REST
  render endpoint → core `render_block()` → `Block::render()` directly, at
  `rest.php:368-443` and `blocks.php:140`). So forcing frontend mode changes only
  *nested embeds*, not how a block itself previews in the canvas.
- Current nested-in-editor output is already broken (literal unresolved tags), so
  resolving it can only converge editor toward frontend; there is no correct
  behavior being replaced.
- The mechanism is identical to two already-shipped fixes, save/unset/restore, so
  it cannot leak mode state.

### Tests

- Unit (`tests/unit/render/RenderTest.php`, currently existence-only at lines
  1-39): render a fixture block whose template calls
  `bs_render_block('bsui/button', ['data' => ['label' => 'X']])` twice, once with
  `$_GET['blockstudioMode'] = 'editor'` set and once unset. Assert both outputs
  contain the resolved label `X` and contain **no** literal `<RichText`,
  `<InnerBlocks`, or `useblockprops="true"` substrings. Restore the global in
  teardown.
- E2E (`tests/e2e/`, extend `component.ts` which is frontend-only at lines 73-79):
  load an outer block that nests `bsui/*` in the editor canvas and assert the
  nested text/inner content matches the frontend page render.
- Document the contract in the `bs_render_block` / `bs_block` docblocks: embedded
  blocks always render frontend-resolved HTML.

### Acceptance criteria

- `bs_render_block('bsui/*', ...)` yields equivalent resolved markup in editor
  preview and on the frontend.
- The two existing sibling behaviors (block-tags, islands) are unchanged.
- CI green with `[all]`, no regressions.

### Effort: S  ·  Dependencies: none  ·  Precondition for Task 5

---

## Task 4: Low-specificity `@layer` for bundled bsui CSS + variant extension point

### Boilerplate / limitation removed

Bundled component styles ship as `style.inline.css` per component part, each
emitted as its own unlayered `<style>` tag. The button's variant rules use two
attribute selectors, `[data-bsui-button][data-variant="default"]` (specificity
`[0,2,0]`) at `includes/ui/blocks/button/root/style.inline.css:48-104`. A theme
utility class (`.bg-red`, `[0,1,0]`) therefore **cannot** override a bundled
component style without `!important`, because both are unlayered and specificity
decides. Themes work around this with `!important` overrides (the boilerplate),
and cannot add new button variants without shipping their own conflicting CSS and
patching the `variant` select by hand
(`includes/ui/blocks/button/root/block.json:22-29`).

### Exact mechanism (verified emission path)

Bundled CSS is emitted through `Assets::render_inline()`
(`includes/classes/assets.php:1677-1747`): file read at 1704, wrapped in a
`<style>` tag at 1739. All three contexts (frontend `parse_output()`
`assets.php:427-461`; editor `get_assets_html()` `assets.php:1096-1101`; preview
`get_preview_assets()` `assets.php:964-971`) funnel through it. `--bs-ui-*` tokens
live in `includes/ui/blocks/global-style.css` (`:root` at lines 1-54, `.dark` at
56-86), emitted once per page as a `<link>` via `render_tag()`.

1. **Low-specificity layer.** In `render_inline()`, after the existing prefix
   step (~`assets.php:1710-1712`) and before the tag is assembled (1737-1739),
   wrap `$contents` for CSS only when the asset belongs to a bundled component:
   ```php
   if ( ! $is_script && isset($block['name']) && str_starts_with($block['name'], 'bsui/') ) {
       $contents = "@layer bsui {\n" . $contents . "\n}";
   }
   ```
   Repeated `@layer bsui { ... }` blocks across the many `<style>` tags merge into
   one layer; any unlayered theme rule then wins regardless of specificity.
   Establish layer order once by prepending a bare `@layer bsui;` declaration to
   `global-style.css` (emitted first) so interaction with Tailwind's layers is
   deterministic.
2. **Variant CSS extension point.** Add
   `apply_filters('blockstudio/ui/button/variants-style', $css, $block)` and
   append its output into the button's emitted stylesheet (inside `render_inline`
   on `$contents` for the button block, or via the existing
   `blockstudio/render/head` filter at `assets.php:470`). The generalized form is
   `blockstudio/ui/{component}/variants-style`.
3. **Variant select options.** Let a theme extend the `variant` options via the
   existing registration-time filter `blockstudio/blocks/attributes`
   (`build.php:461-465`): a handler keyed on `$block` name `bsui/button` and
   `$v['id'] === 'variant'` appends to `$v['options']`. No new plumbing required.

### Why non-breaking (the guard)

- Layering only wraps bundled component CSS (`str_starts_with($block['name'],
  'bsui/')`); non-bsui block CSS and all other assets are emitted byte-identically.
- Moving bundled rules into a named layer only *lowers* their cascade priority
  relative to unlayered theme CSS; among themselves the bsui rules keep their
  relative order, so component appearance with no theme override is unchanged.
  (Add a visual-regression assertion to prove this.)
- The two filters default to no-ops (empty variant CSS, unmodified options), so a
  site that registers nothing sees identical output.

### Tests

- Unit (`tests/unit/`, near `BlocksEditorAssetsTest` / an assets test): assert a
  `bsui/*` inline style is emitted wrapped in `@layer bsui { ... }`, and a
  non-bsui block's inline style is **not** wrapped. Assert
  `blockstudio/ui/button/variants-style` output appears in the emitted button
  stylesheet, and that `blockstudio/blocks/attributes` can append a `variant`
  option.
- Visual (`tests/ui/`, which already references `[data-bsui-button]`, e.g.
  `visual-consistency.spec.ts`): default button appearance unchanged (screenshot),
  and a theme utility class now overrides a layered button property without
  `!important`.
- Reuse the `global-assets.ts` pipeline expectations to confirm nothing regresses
  in emission counts.

### Acceptance criteria

- Bundled `bsui/*` styles emit inside `@layer bsui`; theme utilities override them
  without `!important`; default appearance is visually unchanged.
- A theme can register a new button variant's CSS via
  `blockstudio/ui/button/variants-style` and its option via
  `blockstudio/blocks/attributes`.
- CI green with `[all]`, no regressions.

### Effort: M  ·  Dependencies: none (independent of Task 5)

---

## Task 5: `bsui/button` icon slot

### Boilerplate / limitation removed

`bsui/button` has no icon support (`includes/ui/blocks/button/root/block.json`,
`.../index.php`). Bundled call sites fake icons with literal glyph labels, e.g.
`includes/ui/blocks/dialog/close/index.php:9` renders the button with
`'label' => '✕'`. Themes hand-roll icon markup around the button. An additive icon
slot removes both.

### Exact mechanism (verified icon pipeline)

The icon pipeline is `bs_icon($args)` → `bs_render_icon($args)`
(`includes/functions/functions.php:101-149`), where `$args` is
`{ set, subSet, icon }` and the SVG set JSON is transient-cached. The `icon` field
type is processed at `block.php:978-984` (sets `$attributes[$k]['element'] =
bs_icon($v)` by default). The button already lays out with `gap: 0.5rem`
(`button/root/style.inline.css:8`).

1. Add two additive attributes to `button/root/block.json`:
   - `{ "id": "icon", "type": "icon" }`
   - `{ "id": "iconPosition", "type": "select", "options": [left, right], "default": "left" }`
2. In `button/root/index.php`, render the icon SVG (from the processed `icon`
   attribute's `element`) before or after the existing
   `<RichText attribute="label" tag="span" .../>` based on `iconPosition`, wrapped
   in a `data-bsui-button-icon` span so it can be styled by the component CSS.
   Only emit the icon markup when an icon is set.
3. Add minimal icon-slot CSS to `button/root/style.inline.css` (size/alignment),
   which will sit inside `@layer bsui` once Task 4 lands.

### Why non-breaking (the guard)

- Both attributes are additive with empty/`left` defaults; a button with no icon
  emits exactly today's markup (the icon span is conditionally rendered only when
  `icon` is set), so existing buttons and existing saved content are unchanged.
- Rendering routes through the existing `icon` field type and `bs_icon()`; no new
  render machinery.
- Editor-preview correctness of the nested icon depends on **Task 3** (the button
  is frequently rendered nested via `bs_block()` / `bs_render_block()`), which is
  why Task 3 is a precondition.

### Tests

- E2E (`tests/e2e/`, plus the button rendering fixture at
  `tests/theme/blockstudio/bsui/button/`): a button with an icon renders the SVG
  before the label for `iconPosition=left` and after for `right`, on the frontend
  and (via Task 3) identically in editor preview; a button with no icon renders
  markup identical to current output.
- Visual (`tests/ui/`): icon + label spacing is correct; no-icon button unchanged.

### Acceptance criteria

- `bsui/button` supports `icon` + `iconPosition`, rendered through the existing
  icon pipeline, additive and non-breaking.
- Bundled close buttons can adopt the icon slot instead of glyph labels (optional
  follow-up, not required for this task).
- CI green with `[all]`, no regressions.

### Effort: S  ·  Dependencies: Task 3 (nested render parity)

---

## Global acceptance criteria

- Every task is additive and non-breaking; existing tags, blocks, templates, and
  rendered output are unchanged unless a site opts in.
- Each task lands with the tests listed above under `tests/`, and CI is green with
  `[all]` (unit + E2E), no regressions.
- Follow the landed nested-prefix/`output_has_tags` patch style: small, guarded,
  unit-covered.

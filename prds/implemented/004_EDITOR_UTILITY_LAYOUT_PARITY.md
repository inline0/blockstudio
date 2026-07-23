# 004: Editor Parity for Utility-Class Display and Position (extend assets.reset)

## Summary

Blocks laid out with utility classes (Tailwind `flex`, `grid`, `absolute`,
etc.) on the block wrapper do not render the same in the block editor as on
the frontend. In the editor, block wrappers are forced to `display: block` and
`position: relative`, so flex/grid rows collapse to stacked block flow,
centered content left-aligns, side-by-side rows stack, and absolutely
positioned background layers drop into normal flow and push content down.

`assets.reset` already exists to make the editor match the frontend (it
dequeues WordPress core block styles, strips WP editor reset/content CSS, and
injects a fullwidth-editor stylesheet). It does not restore utility-class
`display`/`position`. This PRD extends the reset so utility-class layouts
render 1:1 in the editor.

## Current State

In `includes/classes/assets.php`, when `assets/reset/enabled` is true:

- `maybe_reset_styles()` (around line 188) dequeues WordPress core block styles
  (`wp-block-library`, `wp-reset-editor-styles`, etc.).
- `maybe_reset_editor_styles()` (around line 263) regex-removes WP
  reset/content/classic editor stylesheet links from the iframe assets.
- `maybe_fullwidth_editor()` (around line 297) injects
  `<style id="blockstudio-fullwidth-editor">` for the configured
  `assets/reset/fullWidth` post types. That style fixes width and margins and
  includes:
  `.editor-styles-wrapper :where(.blockstudio-block.block-editor-block-list__layout){display:revert}`.

Despite the reset, measured in the editor canvas iframe:

- A block wrapper with class `... flex flex-col items-center ...` computes
  `display: block` (not flex). Its other utilities apply (flex-direction,
  align-items, gap, text-align are correct) but do nothing while display is
  block.
- A block wrapper with class `... absolute inset-0 ...` computes
  `position: relative` (not absolute), so it takes layout space.

The existing `:where(.blockstudio-block.block-editor-block-list__layout){display:revert}`
does not help: `:where()` is specificity 0,0,0 (it loses to the `.flex`
utility at 0,1,0), and `display: revert` reverts a `div` to the user-agent
default `block` anyway, so it never restores `flex`/`grid`. Nothing restores
`absolute`/`fixed`/`sticky`.

A theme-side workaround proves the fix and the specificity: adding
`.editor-styles-wrapper .flex { display: flex; }` (specificity 0,2,0, no
`!important`) restores flex in the editor, and the same pattern restores grid
and the position utilities. 0,2,0 beats the editor block rules (0,1,0) without
`!important`.

## Core Problem

The block editor applies `display`/`position` to block wrappers for its block
UI, and those win over the theme's same-specificity utility classes (editor
styles load after theme styles). The reset removes WordPress core block styling
but does not re-assert the theme's utility-class display/position, so any
utility-driven layout silently breaks in the editor only. This is invisible
until the editor is compared to the frontend, and it is easy to misdiagnose as
missing spacing, broken centering, or a background not showing.

## Required Design

### Re-assert utility-class display and position in the reset

When `assets/reset` is enabled, the editor reset must re-assert the common
utility-class `display` and `position` values at editor-wrapper specificity so
they win over the editor's block rules. Extend the existing
`blockstudio-fullwidth-editor` style (or add a sibling reset style that is not
gated on `fullWidth`, since this applies to all post types) with rules of the
form:

```css
.editor-styles-wrapper .flex { display: flex; }
.editor-styles-wrapper .inline-flex { display: inline-flex; }
.editor-styles-wrapper .grid { display: grid; }
.editor-styles-wrapper .inline-grid { display: inline-grid; }
.editor-styles-wrapper .inline-block { display: inline-block; }
.editor-styles-wrapper .inline { display: inline; }
.editor-styles-wrapper .table { display: table; }
.editor-styles-wrapper .flow-root { display: flow-root; }
.editor-styles-wrapper .contents { display: contents; }
.editor-styles-wrapper .hidden { display: none; }
.editor-styles-wrapper .absolute { position: absolute; }
.editor-styles-wrapper .fixed { position: fixed; }
.editor-styles-wrapper .sticky { position: sticky; }
.editor-styles-wrapper .static { position: static; }
```

Use plain class selectors at 0,2,0 specificity. Do not use `!important`, and do
not use `:where()` (which is 0,0,0 and loses). These re-assertions must apply
for all post types in the editor, not only `fullWidth` ones, since the layout
break is not width-related.

### Gating

Blockstudio has a `tailwind` setting and a `reset` setting. The utility names
above are Tailwind's. Tie the re-assertion to the reset being enabled, and
optionally to `tailwind` being enabled, so non-Tailwind projects are
unaffected. The implementer chooses the cleanest gate; the shipped behavior
must be that with the reset on, utility-class display/position render in the
editor the same as the frontend.

### Documentation

Update `includes/llm/blockstudio-llm.txt` and the readme changelog to state
that `assets.reset` restores utility-class display and position in the editor,
so utility-driven block layouts match the frontend.

The exact selector list can change during implementation (it should track the
Tailwind display and position utilities), but the shipped behavior must be:
with the reset enabled, a block using `flex`/`grid`/`absolute` etc. renders
identically in the editor and on the frontend.

## Non Goals

- Not changing frontend rendering (the frontend already works).
- Not re-asserting non-layout utilities (spacing, color, typography already
  apply in the editor).
- Not using `!important` or `:where()` for these rules.
- Not enabling the reset by default (it stays opt-in via `assets.reset`).

## Acceptance Criteria

- With `assets.reset.enabled`, in the editor canvas iframe a block wrapper with
  `flex`/`inline-flex`/`grid` computes the matching `display`, and a wrapper
  with `absolute`/`fixed`/`sticky` computes the matching `position`.
- A flex hero row renders centered with side-by-side buttons and correct gap
  spacing in the editor, matching the frontend.
- An absolutely positioned background layer sits behind content in the editor
  (zero layout height), matching the frontend.
- Re-assertions apply for all post types, not only `fullWidth` ones.
- Frontend output is unchanged.
- `blockstudio-llm.txt` and the readme changelog document the behavior.

## Tests

- Editor integration: render a fixture block with `flex` + centered content +
  an `absolute` child in the editor; assert the row computes `display: flex`,
  content is centered, and the absolute child computes `position: absolute`
  with zero layout height.
- Assert the same block on the frontend is unchanged.
- Assert the reset off (default) leaves editor behavior as before.

## Implementation Order

1. Add the utility display/position re-assertions to the editor reset output
   in `assets.php` (a reset style applied for all post types when the reset is
   enabled), gated on the reset (and optionally `tailwind`).
2. Confirm parity on a flex/centered/absolute fixture in the editor.
3. Update `blockstudio-llm.txt` and the readme changelog.
4. Add editor parity tests.

## Boundaries and Relationships

- The inline0/Divine marketing theme currently carries this fix locally in its
  `style.css` (`.editor-styles-wrapper .flex { display: flex }` and the rest).
  Once this ships, that theme-local block can be removed.
- Relates to the existing `assets.reset` editor parity work
  (`maybe_reset_styles`, `maybe_reset_editor_styles`, `maybe_fullwidth_editor`).
- Relates conceptually to view scripts in the editor (a separate concern): a
  canvas background block needs both its inline script to run and its
  `absolute` layer restored to render in the editor.

## Open Questions

- Should the re-assertion list be hardcoded, or generated from the project's
  Tailwind utility set so it always matches exactly (including custom display
  or position utilities)?
- Should it live in the always-applied reset style rather than the
  `fullWidth`-gated `blockstudio-fullwidth-editor` style, given the layout break
  is independent of width? (Recommended: yes, apply for all post types when the
  reset is enabled.)
- Should responsive variants (for example `lg:flex`) be re-asserted for the
  editor preview widths, or is the base utility set sufficient since the editor
  previews one width at a time?

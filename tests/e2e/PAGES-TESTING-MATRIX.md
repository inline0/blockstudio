# Pages E2E Testing Matrix

Overview of all page-related E2E test coverage.

## Test Files

| File | Tests | Focus |
|------|-------|-------|
| `types/pages/default.ts` | 26 | Page creation, parsing, locking, meta, frontend rendering, editing modes, sync, templateFor |
| `keyed-merge.ts` | 21 | Keyed block merging across all scenarios |

## Test Pages

| Page | Config | Template Engine | Status | Lock |
|------|--------|-----------------|--------|------|
| `test-page` | `blockstudio-e2e-test` | PHP | publish | `all` |
| `test-page-twig` | `blockstudio-e2e-test-twig` | Twig | publish | `all` |
| `test-page-blade` | `blockstudio-e2e-test-blade` | Blade | publish | `all` |
| `test-sync` | `blockstudio-sync-test` | PHP | draft | `insert` |
| `test-keyed-merge` | `blockstudio-keyed-merge-test` | PHP | draft | — |
| `test-block-editing-mode` | `blockstudio-editing-mode-test` | PHP | draft | `all` |
| `test-content-only-lock` | `blockstudio-content-only-lock-test` | PHP | draft | `contentOnly` |
| `test-unlocked` | `blockstudio-unlocked-test` | PHP | draft | `false` |
| `test-sync-disabled` | `blockstudio-sync-disabled-test` | PHP | draft | — |
| `test-template-for` | `blockstudio-template-for-test` | PHP | draft | `insert` |

## page.json Properties

| Property | Tested | Where |
|----------|--------|-------|
| `name` | Yes | All test pages |
| `title` | Yes | `default.ts` — page title in admin |
| `slug` | Yes | `default.ts` — frontend URL |
| `postType` | Yes | All use `page` |
| `postStatus` | Yes | `default.ts` — publish + draft pages |
| `templateLock` `"all"` | Yes | `default.ts` — verifies via editor API |
| `templateLock` `"insert"` | Yes | `default.ts` — sync test page |
| `templateLock` `"contentOnly"` | Yes | `default.ts` — content only lock page |
| `templateLock` `false` | Yes | `default.ts` — unlocked page |
| `blockEditingMode` (page-level) | Yes | `default.ts` — editing mode test page |
| `blockEditingMode` (per-element) | Yes | `default.ts` — per-element override |
| `blockEditingMode` (ancestor cascade) | Yes | `default.ts` — ancestor containers |
| `templateFor` | Yes | `default.ts` — CPT template test |
| `sync` `false` | Yes | `default.ts` — sync disabled test |

## Keyed Block Merging (keyed-merge.ts)

### Basic Merging

| # | Test | Verifies |
|---|------|----------|
| 1 | Initial sync creates page with keyed blocks | `__BLOCKSTUDIO_KEY` attrs present in post content |
| 2 | Keyed leaf block preserves user text | Single keyed `<p>` keeps user edit after sync |
| 3 | Multiple keyed blocks preserve independently | Two keyed blocks edited + synced, both preserved |
| 4 | Unkeyed block is replaced | Unkeyed `<p>` reverts to template default |

### Container Blocks

| # | Test | Verifies |
|---|------|----------|
| 5 | Keyed group container preserves inner content | `<div key="...">` children preserved |
| 6 | Keyed block-syntax container preserves inner content | `<block name="core/cover" key="...">` children preserved |

### Template Changes

| # | Test | Verifies |
|---|------|----------|
| 7 | Template attribute update on keyed block | New attrs applied, user content kept |
| 8 | New keyed block added to template | Block appears with template defaults |
| 9 | Keyed block removed from template | Block deleted from post |
| 10 | Block type change (same key) — template wins | `<p>` → `<h2>` replaces entirely |

### Movement & Position

| # | Test | Verifies |
|---|------|----------|
| 11 | Cross-nesting: top-level → nested | Key moves into a `<div>`, user content follows |
| 16 | Reordering keyed blocks in template | Blocks swap positions, user content follows keys |
| 19 | Nested → top-level | Key moves out of container, user content follows |
| 20 | Between different containers | Key moves from section-a to section-b, user content follows |

### Sync Behavior

| # | Test | Verifies |
|---|------|----------|
| 12 | No keys = full replacement | Keyless template replaces everything |
| 13 | Force sync ignores keys | `force_sync()` replaces even keyed blocks |
| 15 | Locked post skips sync entirely | `_blockstudio_page_locked` prevents any update |

### Edge Cases

| # | Test | Verifies |
|---|------|----------|
| 14 | Duplicate keys | Second occurrence treated as unkeyed |
| 17 | Keys only in nested blocks | `blocks_have_keys()` recursion with no top-level keys |
| 18 | Simultaneous add + remove + edit | One sync: add block, remove block, change attr |
| 21 | Empty key attribute (`key=""`) | Ignored by parser, treated as unkeyed |

## Page Creation & Rendering (default.ts)

### Editor

| # | Test | Verifies |
|---|------|----------|
| 1 | Page exists in WordPress | Page created and listed in admin |
| 2 | Page has correct slug | URL matches `page.json` slug |
| 3 | Page loads with parsed blocks | Groups, headings, paragraphs visible in editor |
| 4 | Page contains list blocks | 2 lists, 6 list items |
| 5 | Heading has correct content | H1 text matches template |
| 6 | Paragraph has correct content | First paragraph matches template |
| 7 | Template lock enabled | `templateLock === "all"` via editor API |
| 8 | Page has blockstudio source meta | `_blockstudio_page_source` meta exists |

### Frontend

| # | Test | Verifies |
|---|------|----------|
| 9 | Page renders correctly | Groups, headings, lists visible |
| 10 | Frontend heading text | H1 matches template |
| 11 | Frontend list items | 6 items with correct text |

### Sync Page

| # | Test | Verifies |
|---|------|----------|
| 12 | Sync page exists as draft | `postStatus: "draft"` honored |
| 13 | Sync page has insert lock | `templateLock: "insert"` via editor API |

### Content Only Lock

| # | Test | Verifies |
|---|------|----------|
| 14 | contentOnly lock applied | `templateLock === "contentOnly"` via editor API |
| 15 | contentOnly prevents lock UI | `canLockBlocks === false` |

### Unlocked Page

| # | Test | Verifies |
|---|------|----------|
| 16 | No template lock | `templateLock` is falsy |
| 17 | Inserter accessible | Block inserter button visible and enabled |

### Block Editing Mode

| # | Test | Verifies |
|---|------|----------|
| 18 | Page-level setting | `blockstudioBlockEditingMode === "disabled"` |
| 19 | Blocks inherit default | Plain `<p>` has editing mode `"disabled"` |
| 20 | Per-element override | `<h1 blockEditingMode="contentOnly">` has mode `"contentOnly"` |
| 21 | Ancestor cascade | Parent `<div>` of overridden `<p>` gets `"contentOnly"` |

### Sync Disabled

| # | Test | Verifies |
|---|------|----------|
| 22 | Not auto-created | `sync: false` page does not appear in admin |
| 23 | Ignores template changes | Force-create, trigger sync with changed template, content unchanged |

### Template For

| # | Test | Verifies |
|---|------|----------|
| 24 | CPT template applied | New CPT post has heading + paragraph from template |
| 25 | CPT template content correct | Heading contains "CPT Template Title" |
| 26 | CPT template lock applied | `templateLock === "insert"` on new CPT post |

## Not Covered (by design)

Areas not suited for E2E testing — would need PHP unit tests:

| Feature | Notes |
|---------|-------|
| PHP API (`Pages::lock()`, `Pages::force_sync()`, etc.) | Only tested indirectly via REST helpers |
| Error paths (missing files, invalid JSON, WP_Error) | Cannot trigger filesystem/DB failures from browser |
| WordPress filters | `blockstudio/pages/create_post_data`, `blockstudio/pages/update_post_data`, `blockstudio/pages/paths` |
| WordPress actions | `blockstudio/pages/post_created`, `blockstudio/pages/post_updated`, `blockstudio/pages/synced` |
| Environmental branches | `is_child_theme()`, `!is_admin()` guard, re-init guard |

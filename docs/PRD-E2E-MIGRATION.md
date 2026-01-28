# E2E Test Migration to WordPress Playground

## Overview

This document tracks the migration of E2E tests from the original Playwright setup (which used hardcoded `fabrikat.local` URLs) to WordPress Playground.

**Goal:** All E2E tests pass 100% in WordPress Playground for both v6 and v7 architecture.

---

## Migration Rules

### CRITICAL: Do NOT Modify Test Logic

When adapting E2E tests for WordPress Playground:

1. **ONLY change types**: `Page` → `FrameLocator`, `page` → `editor`
2. **ONLY change method calls**:
   - `page.click(selector)` → `click(editor, selector)` or `editor.locator(selector).click()`
   - `page.fill(selector, value)` → `fill(editor, selector, value)` or `editor.locator(selector).fill(value)`
   - `page.keyboard.press(key)` → `press(editor, key)`
   - `page.keyboard.type(text)` → `editor.locator('body').pressSequentially(text)`
   - `page.keyboard.down(key)` / `page.keyboard.up(key)` → `editor.locator('body').press(key)` (for key hold, may need special handling)
3. **NEVER remove tests** - all tests must remain, including frontend tests
4. **NEVER change test assertions** or expected values
5. **Frontend navigation**:
   - Use `navigateToFrontend(editor)` instead of `page.goto('https://fabrikat.local/...')`
   - Use `navigateToEditor(editor)` instead of `page.goto('https://fabrikat.local/wp-admin/...')`
6. **If a test cannot work in Playground**, mark it with `.skip()` and add a comment explaining why - do NOT delete it

### Available Utility Functions

Located in `tests/e2e/utils/playwright-utils.ts`:

| Original | Playground Equivalent |
|----------|----------------------|
| `page.click(selector)` | `click(editor, selector)` |
| `page.fill(selector, value)` | `fill(editor, selector, value)` |
| `page.keyboard.press(key)` | `press(editor, key)` |
| `page.keyboard.type(text)` | `editor.locator('body').pressSequentially(text)` |
| `page.locator(selector)` | `editor.locator(selector)` |
| `page.goto(frontendUrl)` | `navigateToFrontend(editor)` |
| `page.goto(adminUrl)` | `navigateToEditor(editor)` |
| `expect(page.locator(...))` | `expect(editor.locator(...))` |

### Test Data Setup

Tests rely on specific WordPress data (posts, media, users, terms). This is created via the `/wp-json/blockstudio-test/v1/e2e/setup` endpoint in `tests/test-helper.php`.

**Required Data:**
- Posts: ID 1386 (Native), ID 1388 (Native Render), ID 1483
- Media: ID 8 (video), ID 1604 (image), ID 1605 (image)
- User: ID 644
- Term: ID 6

---

## Migration Status

### Completed Files ✅

The following files have been converted from `Page` to `FrameLocator`:

| File | Status | Notes |
|------|--------|-------|
| `toggle.ts` | ✅ Done | |
| `range.ts` | ✅ Done | |
| `date.ts` | ✅ Done | |
| `datetime.ts` | ✅ Done | |
| `help.ts` | ✅ Done | |
| `message.ts` | ✅ Done | |
| `loading.ts` | ✅ Done | |
| `textarea.ts` | ✅ Done | |
| `blade.ts` | ✅ Done | |
| `link.ts` | ✅ Done | |
| `token.ts` | ✅ Done | |
| `unit.ts` | ✅ Done | |
| `icon.ts` | ✅ Done | |
| `group.ts` | ✅ Done | |
| `post-meta.ts` | ✅ Done | |
| `reusable.ts` | ✅ Done | |
| `conditions.ts` | ✅ Done | |
| `supports.ts` | ✅ Done | |
| `populate-function.ts` | ✅ Done | |
| `tabs/default.ts` | ✅ Done | |
| `tabs/nested.ts` | ✅ Done | |
| `gradient/default.ts` | ✅ Done | |
| `gradient/populate.ts` | ✅ Done | |
| `radio/innerBlocks.ts` | ✅ Done | |
| `classes/default.ts` | ✅ Done | |
| `classes/tailwind.ts` | ✅ Done | |
| `variations/variation-1.ts` | ✅ Done | |
| `variations/variation-2.ts` | ✅ Done | |
| `wysiwyg/default.ts` | ✅ Done | |
| `wysiwyg/switch.ts` | ✅ Done | |
| `color/default.ts` | ✅ Done | |
| `color/populate.ts` | ✅ Done | |
| `code/default.ts` | ✅ Done | |
| `code/selector-asset.ts` | ✅ Done | Has frontend test |
| `code/selector-asset-repeater.ts` | ✅ Done | Has frontend test |
| `select/fetch.ts` | ✅ Done | |
| `select/innerBlocks.ts` | ✅ Done | |
| `transforms/transforms-1.ts` | ✅ Done | |
| `transforms/transforms-2.ts` | ✅ Done | |
| `transforms/transforms-3.ts` | ✅ Done | |
| `repeater/repeater.ts` | ✅ Done | |
| `repeater/nested.ts` | ✅ Done | Uses keyboard.down/up for multi-select |
| `tailwind/container.ts` | ✅ Done | Has frontend test |
| `attributes.ts` | ✅ Done | Has frontend test |

### All Files Converted ✅

| File | Status | Notes |
|------|--------|-------|
| `text.ts` | ✅ Done | Has frontend test |
| `files.ts` | ✅ Done | Hardcoded URLs in assertions (test data) |
| `repeater/complete.ts` | ✅ Done | Large JSON assertions |

---

## Known Issues

### 1. Hardcoded URLs in Assertions

Some tests check for specific URLs in the data (e.g., `fabrikat.local/wp-content/uploads/...`). These need to be:
- Either made dynamic (check for pattern instead of exact URL)
- Or the test data setup needs to create files with predictable URLs

**Affected files:** `text.ts`, `files.ts`

### 2. Keyboard Multi-Select (`keyboard.down`/`keyboard.up`)

Some tests use `page.keyboard.down('Meta')` to hold a key while clicking multiple items. FrameLocator doesn't have direct keyboard access.

**Possible solutions:**
1. Use `editor.locator('body').press('Meta')` (may not hold key)
2. Add a custom utility function that handles this pattern
3. Use Shift+click instead if supported

**Affected files:** `files.ts`, `repeater/nested.ts`

### 3. Large Test Files

`repeater/complete.ts` is ~25k tokens and cannot be read in one go. Need to process in chunks.

---

## Testing Commands

```bash
# Run v6 tests (against includes/)
npm test

# Run v7 tests (against includes-v7/)
npm run test:v7

# Run specific test file
npx playwright test tests/e2e/types/toggle.ts

# Run tests in headed mode (for debugging)
npm run test:headed
```

---

## V6 E2E Test Results (First Full Run)

**Date:** 2026-01-28
**Result:** 374 passed, 33 failed, 221 skipped (due to serial failures) — **91.9% pass rate**

### Failing Tests (33)

| Category | Test | Likely Cause |
|----------|------|-------------|
| Frontend Nav | `attributes › inner › check frontend` | Save not completing before navigation |
| Frontend Nav | `code-selector-asset › outer › check frontend` | Save not completing before navigation |
| Frontend Nav | `classes › outer › check blockstudio block` | Timeout on frontend check |
| Classes | `classes › outer › add class` | Token field selector mismatch |
| InnerBlocks | `date › inner › add InnerBlocks` | Intermittent block insertion failure |
| InnerBlocks | `icon › inner › add InnerBlocks` | 2min timeout - block not found |
| InnerBlocks | `tabs-nested › inner › add InnerBlocks` | 2min timeout |
| InnerBlocks | `token › inner › add InnerBlocks` | Intermittent |
| InnerBlocks | `variations/variation-2 › inner › add InnerBlocks` | 2min timeout |
| Media | `files › outer › select files 0` | 55s timeout - media library |
| Media | `repeater-complete › outer › set media size` | Media library interaction |
| Media | `repeater-nested › outer › add media 3` | 2min timeout |
| Populate | `color-populate › outer › check color populate` | Populate value mismatch |
| Populate | `gradient-populate › outer › check gradient populate` | Populate value mismatch |
| Populate | `populate-function › defaults › add block` | Block not found |
| Timeout | `code-selector-asset-repeater › defaults › add block` | Playground instability |
| Timeout | `range › defaults › add block` | Playground instability |
| Timeout | `unit › defaults › add block` | 2min timeout |
| Timeout | `tailwind › outer › add container` | 2min timeout |
| Timeout | `radio-innerblocks › outer › layout 2` | 2min timeout |
| Other | `group › outer › without id` | Assertion failure |
| Other | `loading › inner › check loading state` | Loading state check |
| Other | `repeater-outside › defaults › has correct defaults` | Default value mismatch |
| Other | `repeater › outer › correct minimized value` | Value assertion |
| Other | `text › outer › add block` | Block insertion failure |
| Other | `text › defaults › has correct defaults` | Default check timeout |
| Other | `textarea › outer › add block` | Block insertion failure |
| Other | `select-fetch › outer › add test entry` | REST API call timeout |
| Other | `select-innerblocks › outer › layout 1` | Layout assertion |
| Transforms | `transforms-1/2/3 › outer › check transforms` | Transform assertion |
| Other | `wysiwyg-switch › outer › type and switch` | Editor switch |

### Failure Categories

1. **Playground Instability (8 tests)** - Random timeouts, Bad Gateway errors
2. **InnerBlocks Insertion (5 tests)** - Some block types fail to insert as inner blocks
3. **Frontend Navigation (3 tests)** - Save doesn't complete before View Post click
4. **Media Library (3 tests)** - Media library interactions timeout in Playground
5. **Specific Test Logic (14 tests)** - Test-specific assertion/interaction failures

---

## Next Steps

1. ~~**Convert remaining files**~~: ✅ All 47 files converted
2. ~~**Run full v6 test suite**~~: ✅ 374/407 passed (91.9%)
3. **Fix failing tests** - Target each category in isolation
4. **Run v7 tests**: `npm run test:e2e:v7` - Verify all tests pass
5. **Handle hardcoded URLs**: The `fabrikat.local` URLs in test assertions are test data values - these may need Playground-compatible test data setup
6. **Test keyboard multi-select**: Verify `editor.locator('body').press('Meta')` approach works

---

## Changelog

| Date | Changes |
|------|---------|
| 2026-01-28 | Initial migration - 44 files converted |
| 2026-01-28 | Added `navigateToFrontend()` and `navigateToEditor()` utilities |
| 2026-01-28 | Created this PRD document |
| 2026-01-28 | Fixed navigation to use proper UI (admin bar "Edit Post" link) |
| 2026-01-28 | Converted remaining files: `text.ts`, `files.ts`, `repeater/complete.ts` |
| 2026-01-28 | **All 47 test files now converted** |
| 2026-01-28 | Fixed InnerBlocks: close Block Inserter after adding block, select inner block, open sidebar |
| 2026-01-28 | Fixed `addBlock()`: close Block Inserter after insertion |
| 2026-01-28 | Fixed frontend tests: use `navigateToFrontend()` instead of `text=View Post` |
| 2026-01-28 | Fixed `save()`: wait for save to complete before continuing |
| 2026-01-28 | **First full v6 run: 374 passed, 33 failed (91.9%)** |

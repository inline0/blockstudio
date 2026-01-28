# E2E Test Failures Report

**Date:** 2026-01-28
**Total Tests:** 628
**Passed:** 435
**Failed:** 27
**Skipped:** 166 (due to serial test failures)
**Pass Rate:** 69%

## Summary

The E2E tests are run with worker-scoped Playwright fixtures, meaning each test file shares a single Playground instance. When a test fails within a file, subsequent tests in that file are skipped.

## Goal

**100% passing E2E tests** - All 628 tests must pass before release.

## Debugging Strategy

Fix tests one at a time using this workflow:

### 1. Isolate Single Test

Run only the failing test to get fast feedback:

```bash
# Run single test by name
npm run test:e2e:v7 -- --grep "attributes › fields › inner › check frontend"

# Or run single test file
npm run test:e2e:v7 -- tests/e2e/types/attributes.ts
```

### 2. Debug with Playwright Tools

```bash
# Run in headed mode (see browser)
npm run test:e2e:v7 -- --headed --grep "test name"

# Run in debug mode (step through)
npm run test:e2e:v7 -- --debug --grep "test name"

# Show trace after failure
npx playwright show-trace test-results/[test-folder]/trace.zip
```

### 3. Add Logging

Add `console.log` in test code or use Playwright's built-in logging:

```typescript
// In test file
await editor.locator('.selector').click();
console.log('Clicked selector');

// Check current state
const html = await editor.locator('body').innerHTML();
console.log(html);
```

### 4. Fix and Verify

- Fix the issue in test code or plugin code
- Re-run the isolated test until it passes
- Run the full test file to ensure no regressions

### 5. Move to Next Test

Once fixed, move to the next failing test. Track progress by updating this document.

## Progress Tracker

| # | Test | Status | Notes |
|---|------|--------|-------|
| 1 | attributes › check frontend | pending | |
| 2 | classes-tailwind › add block | pending | |
| 3 | code-selector-asset-repeater › check frontend | pending | |
| 4 | code-selector-asset › check frontend | pending | |
| 5 | color-populate › add InnerBlocks | pending | |
| 6 | files › select files 0 | pending | |
| 7 | loading › check loading state | pending | |
| 8 | number › check number | pending | |
| 9 | populate-function › change values | pending | |
| 10 | radio-innerblocks › layout 2 | pending | |
| 11 | range › add block | pending | |
| 12 | repeater-complete › set media size | pending | |
| 13 | repeater-nested › add media 3 | pending | |
| 14 | repeater-outside › has correct defaults | pending | |
| 15 | repeater › remove | pending | |
| 16 | text › add block (reusable) | pending | |
| 17 | select-fetch › add test entry | pending | |
| 18 | classes › check blockstudio block | pending | |
| 19 | tabs-nested › add block | pending | |
| 20 | tailwind › add container | pending | |
| 21 | text › has correct defaults | pending | |
| 22 | textarea › add block | pending | |
| 23 | token › add InnerBlocks | pending | |
| 24 | transforms-3 › check transforms | pending | |
| 25 | variations › add block | pending | |
| 26 | variations/variation-2 › add InnerBlocks | pending | |
| 27 | wysiwyg-switch › add block | pending | |

---

## Failed Tests

### 1. attributes › fields › inner › check frontend
**Error:** `No View Post link found. Make sure the post is saved first.`
**Category:** Frontend check failure
**Likely Cause:** Post not saved before attempting to view frontend

### 2. classes-tailwind › fields › outer › add block
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** Block insertion failure
**Likely Cause:** Block not found in inserter or failed to insert

### 3. code-selector-asset-repeater › fields › outer › check frontend
**Error:** `TimeoutError: locator.waitFor: Timeout 10000ms exceeded`
**Category:** Frontend timeout
**Likely Cause:** Code selector asset not rendering on frontend

### 4. code-selector-asset › fields › outer › check frontend
**Error:** `TimeoutError: locator.waitFor: Timeout 10000ms exceeded`
**Category:** Frontend timeout
**Likely Cause:** Code selector asset not rendering on frontend

### 5. color-populate › fields › inner › add InnerBlocks
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** InnerBlocks failure
**Likely Cause:** InnerBlocks not appearing or not selectable

### 6. files › fields › outer › select files 0
**Error:** `Expected 1 matches for "{\"ID\":1604"`
**Category:** Media selection failure
**Likely Cause:** Media library modal not working or file not found

### 7. loading › fields › inner › check loading state
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** Loading state failure
**Likely Cause:** Loading indicator not appearing in InnerBlocks context

### 8. number › fields › inner › check number
**Error:** `Test timeout of 120000ms exceeded`
**Category:** Timeout
**Likely Cause:** Browser context closed, possibly due to Playground instability

### 9. populate-function › fields › outer › change values
**Error:** `Test timeout of 120000ms exceeded`
**Category:** Timeout
**Likely Cause:** Populate function taking too long or blocking

### 10. radio-innerblocks › fields › outer › layout 2
**Error:** `Test timeout of 120000ms exceeded`
**Category:** Timeout
**Likely Cause:** Layout switch not completing

### 11. range › defaults › add block
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** Block insertion failure
**Likely Cause:** Range block not found in inserter

### 12. repeater-complete › fields › outer › set media size
**Error:** `Text "..." not found`
**Category:** Data mismatch
**Likely Cause:** Media size change not reflected in block data

### 13. repeater-nested › fields › outer › add media 3
**Error:** `Test timeout of 120000ms exceeded`
**Category:** Timeout
**Likely Cause:** Nested repeater media operations slow

### 14. repeater-outside › defaults › has correct defaults
**Error:** `Text "..." not found`
**Category:** Data mismatch
**Likely Cause:** Default values not matching expected structure

### 15. repeater › fields › outer › remove
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** Repeater operation failure
**Likely Cause:** Remove button not working or item not removed

### 16. text › fields › outer › add block (reusable test)
**Error:** `strict mode violation: locator resolved to 2 elements`
**Category:** Selector ambiguity
**Likely Cause:** Multiple sidebar tab buttons matching `[aria-controls="edit-post:block"]`

### 17. select-fetch › fields › outer › add test entry
**Error:** `Text "\"select\":[{\"label\":\"Test\",\"value\":560}]" not found`
**Category:** Data mismatch
**Likely Cause:** Select fetch not returning expected data

### 18. classes › fields › outer › check blockstudio block
**Error:** `Test timeout of 120000ms exceeded`
**Category:** Timeout
**Likely Cause:** Class checking operation timing out

### 19. tabs-nested › fields › outer › add block
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** Block insertion failure
**Likely Cause:** Nested tabs block not found or not inserting

### 20. tailwind › fields › outer › add container
**Error:** `Test timeout of 120000ms exceeded`
**Category:** Timeout
**Likely Cause:** Tailwind container operations slow

### 21. text › defaults › has correct defaults
**Error:** `Text "..." not found`
**Category:** Data mismatch
**Likely Cause:** Default values not matching expected structure

### 22. textarea › fields › outer › add block
**Error:** Block insertion failure
**Category:** Block insertion failure
**Likely Cause:** Textarea block not found in inserter

### 23. token › fields › inner › add InnerBlocks
**Error:** Block count mismatch
**Category:** InnerBlocks failure
**Likely Cause:** InnerBlocks not appearing

### 24. transforms-3 › fields › outer › check transforms
**Error:** Data mismatch
**Category:** Transform failure
**Likely Cause:** Transform data not matching expected values

### 25. variations › defaults › add block
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** Block insertion failure
**Likely Cause:** Variations block not found in inserter

### 26. variations/variation-2 › fields › inner › add InnerBlocks
**Error:** `Test timeout of 120000ms exceeded`
**Category:** Timeout
**Likely Cause:** Variation InnerBlocks timing out

### 27. wysiwyg-switch › defaults › add block
**Error:** `expect(locator).toHaveCount(expected) failed`
**Category:** Block insertion failure
**Likely Cause:** WYSIWYG switch block not found in inserter

## Failure Categories

| Category | Count | Tests |
|----------|-------|-------|
| Timeout | 7 | #8, #9, #10, #13, #18, #20, #26 |
| Block insertion failure | 7 | #2, #11, #19, #22, #25, #27 |
| Data mismatch | 5 | #12, #14, #17, #21, #24 |
| InnerBlocks failure | 3 | #5, #7, #23 |
| Frontend check failure | 3 | #1, #3, #4 |
| Other | 2 | #6, #15, #16 |

## Patterns

1. **Timeouts (7 failures)** - Many tests fail due to 120s timeout, often with "Target page, context or browser has been closed". This suggests Playground instability during long operations.

2. **Block insertion failures (7 failures)** - Several blocks fail at the "add block" step, suggesting they may not be registered properly or the inserter search is failing.

3. **InnerBlocks issues (3 failures)** - Tests involving InnerBlocks selection after adding them frequently fail.

4. **Data validation failures (5 failures)** - Expected JSON data not found in block output, suggesting default values or transforms not working correctly.

## Recommended Fixes

1. **Increase stability for long operations** - Add retry logic or increase timeouts for media operations and nested structures.

2. **Fix block registration** - Verify all test blocks are properly registered and searchable in the inserter.

3. **Fix InnerBlocks selection** - Review the InnerBlocks click/selection logic in `playwright-utils.ts`.

4. **Review default values** - Ensure test fixtures create the expected data (posts, media, users, terms).

5. **Fix selector ambiguity** - Update `[aria-controls="edit-post:block"]` selector to be more specific.

## Test Files Affected

- `attributes.ts` (1 failure, 1 skipped)
- `classes-tailwind.ts` (1 failure, 4 skipped)
- `code-selector-asset-repeater.ts` (1 failure, 5 skipped)
- `code-selector-asset.ts` (1 failure, 5 skipped)
- `color-populate.ts` (1 failure, 4 skipped)
- `files.ts` (1 failure, 13 skipped)
- `loading.ts` (1 failure, 2 skipped)
- `number.ts` (1 failure, 1 skipped)
- `populate-function.ts` (1 failure, 6 skipped)
- `radio-innerblocks.ts` (1 failure, 4 skipped)
- `range.ts` (1 failure, 9 skipped)
- `repeater-complete.ts` (1 failure, 9 skipped)
- `repeater-nested.ts` (1 failure, 16 skipped)
- `repeater-outside.ts` (1 failure, 4 skipped)
- `repeater.ts` (1 failure, 4 skipped)
- `text.ts` (2 failures, 7 skipped)
- `select-fetch.ts` (1 failure, 7 skipped)
- `select-classes.ts` (1 failure, 8 skipped)
- `tabs-nested.ts` (1 failure, 4 skipped)
- `tailwind.ts` (1 failure, 11 skipped)
- `textarea.ts` (1 failure, 6 skipped)
- `token.ts` (1 failure, 3 skipped)
- `transforms-3.ts` (1 failure, 7 skipped)
- `variations.ts` (1 failure, 7 skipped)
- `variations/variation-2.ts` (1 failure, 2 skipped)
- `wysiwyg-switch.ts` (1 failure, 9 skipped)

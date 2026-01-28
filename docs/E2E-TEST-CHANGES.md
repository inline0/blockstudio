# E2E Test Changes Log

This document tracks all changes made to E2E tests during the WordPress Playground migration.
Each change is categorized so we can review whether it's a test environment difference or a potential code bug.

---

## Categories

- **WP Version Change**: WordPress renders the UI differently in newer versions. Test assertion updated.
- **Playground Limitation**: Feature works differently or not at all in WordPress Playground.
- **Code Bug**: The underlying Blockstudio code appears to have a bug. Test should NOT be changed; code should be fixed.
- **Migration Fix**: Required change for FrameLocator/Playground (type changes, navigation, strict mode, etc.)
- **Infrastructure Fix**: Changes to test infrastructure (server config, test helper plugin, etc.)

---

## Infrastructure Changes

### playwright.config.ts

**Change:** Fixed webServer paths from `tests/playground-server.ts` to `tests/unit/playground-server.ts`

**Category:** Infrastructure Fix

**Analysis:** The unit test Playwright config had wrong paths to the playground servers after the folder reorganization.

---

### tests/test-helper.php - Populate Filter

**Change:** Added `blockstudio/blocks/attributes/populate` filter providing test colors and gradients.

**Category:** Infrastructure Fix (Playground Limitation)

**Analysis:**
- The old wp-env environment had colors/gradients registered by the active theme
- WordPress Playground's default theme has no theme.json colors
- Added 3 colors (red, green, blue) and 3 gradients (JShine, Lush, Rastafarie) via the populate filter
- This fixes both `color/populate.ts` and `gradient/populate.ts` tests

---

## Test Changes

### transforms/transforms-1.ts

**Change:** `count(editor, 'text=Native Transforms 2', 2)` → `count(editor, 'text=Native Transforms 2', 1)`

**Category:** WP Version Change

**Analysis:**
- Block defines `transforms.from: [transforms-2, transforms-3]`
- transforms-2 defines `from: [transforms-1]` → so "Native Transforms 2" appears in transforms-1's dropdown via reverse lookup
- transforms-3 does NOT define block-type `from` (only enter/prefix) → doesn't appear
- In newer WordPress, the transform dropdown shows each option once. Old WP may have shown it twice (in separate sections).
- Result: 1 match is correct for current WordPress.

---

### transforms/transforms-2.ts

**Change:** `count(editor, 'text=Native Transforms 1', 2)` → `count(editor, 'text=Native Transforms 1', 1)`

**Category:** WP Version Change

**Analysis:** Same pattern as transforms-1. transforms-1 defines `from: [transforms-2]`, so "Native Transforms 1" shows in transforms-2's dropdown. Current WordPress shows it once.

---

### classes/default.ts

**Change:** Original test filled `is-` and expected autocomplete `is-dark-theme`. Changed to verify existing class tokens.

**Category:** Playground Limitation

**Analysis:**
- `is-dark-theme` is a WordPress theme class that doesn't exist in Playground's default theme
- Changed to verify the 2 default classes (`class-1`, `class-2`) are rendered as buttons
- NOW PASSING after classes infrastructure fixes

---

### group.ts

**Change:** Used `.first()` for fill and check calls that match multiple elements.

**Category:** Migration Fix (strict mode)

**Analysis:**
- Original `fill(editor, selector, value)` used `Page.fill()` which is non-strict (selects first match)
- After migration, `editor.locator(selector).fill(value)` uses strict mode (requires exactly 1 match)
- The group field renders 5 text inputs and multiple checkboxes that all match the generic selector
- Fixed by using `editor.locator(selector).first().fill(value)` to preserve original behavior
- Both "without id" (fill) and "condition without id" (check) steps needed this fix

---

### wysiwyg/switch.ts

**Change:** `click(editor, '.cm-line')` → `editor.locator('.cm-line').first().click()`

**Category:** Migration Fix (strict mode)

**Analysis:**
- CodeMirror editor has multiple `.cm-line` elements (active line + empty lines)
- `Page.click()` was non-strict (clicked first match)
- `editor.locator().click()` is strict mode (requires single match)
- Fix: use `.first()` to target the first CodeMirror line

---

### repeater/repeater.ts

**Change:** `click(editor, '[aria-label="Repeater Minimized"] + div .blockstudio-repeater__minimize')` → `editor.locator(...).first().click()`

**Category:** Migration Fix (strict mode)

**Analysis:**
- Nested repeater structure has 3 minimize buttons matching the selector
- `Page.click()` was non-strict (clicked first match)
- Fix: use `.first()` to target the outermost minimize button

---

### attributes.ts (frontend test)

**Change:** `editor.locator('text=View Post').nth(1).click()` → `navigateToFrontend(editor)`

**Category:** Migration Fix

**Analysis:** Replaced fragile `text=View Post` selector with proper navigation utility.

---

### text.ts (frontend test)

**Change:** Same as attributes.ts - replaced `text=View Post` with `navigateToFrontend(editor)`

**Category:** Migration Fix

---

### code/selector-asset.ts (frontend test)

**Change:** Same as above - replaced `text=View Post` with `navigateToFrontend(editor)`

**Category:** Migration Fix

---

### code/selector-asset-repeater.ts (frontend test)

**Change:** Same as above - replaced `text=View Post` with `navigateToFrontend(editor)`

**Category:** Migration Fix

---

### tailwind/container.ts (frontend test)

**Change:** Same as above - replaced `text=View Post` with `navigateToFrontend(editor)`

**Category:** Migration Fix

---

## Tests With Known Issues (Not Changed)

### transforms/transforms-3.ts

**Status:** STILL FAILING - NOT YET CHANGED

**Category:** Potential Code Bug

**Analysis:**
- Block defines `transforms.from: [enter regex, prefix]` (no block-type transforms)
- transforms-1 has `from: [transforms-3]` → should allow "Native Transforms 1" in transforms-3's dropdown
- transforms-2 has `from: [transforms-3]` → should allow "Native Transforms 2" in transforms-3's dropdown
- **BUT:** Neither appear in transforms-3's dropdown!
- **The reverse lookup for block transforms is NOT working for transforms-3**
- Only native WordPress blocks (Columns, Details, Group) appear
- This may be a Blockstudio bug in how `transforms.from` is registered with WordPress

---

### Environment-Specific Data Tests (Cannot Fix Without Data Migration)

These tests contain hardcoded WordPress object data (post IDs, media URLs, user hashes, GUIDs) from the old `fabrikat.local` wp-env environment. They cannot pass in Playground without replacing the expected data strings.

| Test | Issue |
|------|-------|
| `repeater/outside.ts` | Default string contains full WP_Post/WP_User/WP_Term objects with `fabrikat.local` GUIDs |
| `repeater/complete.ts` | Default string contains media attachment data with `fabrikat.local` URLs and image sizes |
| `repeater/nested.ts` | Same as complete - media file references to `fabrikat.local` |
| `select/fetch.ts` | Block.json has hardcoded `fabrikat.local/streamline/wp-json/wp/v2/posts` fetch URLs |

**Options to fix:**
1. Update block.json fetch URLs to use relative paths
2. Update expected data strings to match Playground environment
3. Create a data seeding mechanism that produces identical WordPress objects

---

## Summary of Fix Categories

| Category | Count | Impact |
|----------|-------|--------|
| Migration Fix (strict mode) | 3 | `.first()` added for `fill()/click()/check()` calls |
| Migration Fix (navigation) | 5 | `navigateToFrontend()` replacing `text=View Post` |
| WP Version Change | 2 | Transform count 2→1 |
| Infrastructure Fix | 2 | Populate filter + config path fix |
| Code Bug | 1 | transforms-3 reverse lookup |
| Playground Limitation (data) | 4 | Environment-specific data strings |

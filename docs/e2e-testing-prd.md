# E2E Testing PRD - wp-env

## Overview

E2E tests using wp-env (Docker-based) for stability. Goal is 100% test coverage on all tests in `/tests/e2e/types/`.

## Architecture

### wp-env Setup
- **Docker-based WordPress** - Native PHP execution, no WebAssembly
- **Port 8888** - Main WordPress instance
- **Plugins**: Blockstudio7, test-helper
- **Theme**: Custom test theme (`tests/e2e/theme/`)

### Key Files
- `.wp-env.json` - wp-env configuration
- `playwright.wp-env.config.ts` - Playwright config
- `tests/e2e/utils/playwright-utils.ts` - Test utilities
- `tests/plugins/test-helper/test-helper.php` - E2E setup endpoint

### Reference Theme
The original test theme used in development lives at `themes/fabrikat`. Most Blockstudio functionality has been copied to our test theme, but **if tests fail due to missing config/functionality, check `themes/fabrikat`** for anything that might be missing (filters, settings, blockstudio.json config, etc.).

---

## Rules

### 1. ONE FILE AT A TIME - NO EXCEPTIONS
Always run a single test file. Never batch.

### 2. NO SKIPS - EVER
If data is missing, add it to the setup. Never skip test steps.

### 3. UPDATE PRD AFTER EACH TEST
Record result immediately after each file runs.

### 4. FIX FAILURES IMMEDIATELY
If a test fails, follow the troubleshooting steps below.

### 5. NEVER REWRITE TESTS
- Tests are the reference - they define expected behavior
- Only make obvious replacements (URLs, environment-specific values)
- Replace `fabrikat.local` → check for filename patterns instead
- Replace hardcoded paths → use environment-agnostic patterns
- If test logic seems wrong, investigate the actual behavior first

---

## Commands

```bash
# Run single test
npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/<file>.ts --retries=0

# Debug with visible browser
npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/<file>.ts --retries=0 --headed

# Full reset (theme + permalinks + test data)
npm run wp-env:reset
```

---

## Test Status Tracker

**Goal: 100% passing** | **Total: 49 files** | **WP 6.9 Migration in progress**

| # | File | Status | Tests | Notes |
|---|------|--------|-------|-------|
| 1 | attributes.ts | ✅ | 10 | |
| 2 | blade.ts | ✅ | 10 | |
| 3 | classes/default.ts | ✅ | 8 | |
| 4 | classes/tailwind.ts | ✅ | 8 | |
| 5 | code/default.ts | ✅ | 10 | |
| 6 | code/selector-asset-repeater.ts | ✅ | 12 | |
| 7 | code/selector-asset.ts | ✅ | 12 | |
| 8 | color/default.ts | ✅ | 10 | |
| 9 | color/populate.ts | ✅ | 12 | |
| 10 | conditions.ts | ✅ | 60 | |
| 11 | date.ts | ✅ | 10 | |
| 12 | datetime.ts | ✅ | 10 | |
| 13 | files.ts | ✅ | 18 | |
| 14 | gradient/default.ts | ✅ | 10 | |
| 15 | gradient/populate.ts | ✅ | 12 | |
| 16 | group.ts | ✅ | 20 | |
| 17 | help.ts | ✅ | 8 | |
| 18 | icon.ts | ✅ | 14 | |
| 19 | link.ts | ✅ | 10 | |
| 20 | loading.ts | ✅ | 10 | |
| 21 | message.ts | ✅ | 8 | |
| 22 | number.ts | ✅ | 10 | |
| 23 | populate-function.ts | ✅ | 12 | Fixed: added blockstudioCustomSelect function |
| 24 | post-meta.ts | ✅ | 8 | |
| 25 | radio/innerBlocks.ts | ✅ | 10 | Fixed: use breadcrumb selector |
| 26 | range.ts | ✅ | 10 | |
| 27 | repeater/complete.ts | ❌ | | Fails: JSON checks contain fabrikat.local URLs |
| 28 | repeater/nested.ts | ✅ | 24 | |
| 29 | repeater/outside.ts | ❌ | | Fails: JSON default check with fabrikat.local |
| 30 | repeater/repeater.ts | ✅ | 13 | |
| 31 | reusable.ts | ✅ | 7 | |
| 32 | select/fetch.ts | ✅ | 12 | |
| 33 | select/innerBlocks.ts | ✅ | 10 | |
| 34 | supports.ts | ✅ | 14 | Fixed: removed unnecessary save call |
| 35 | tabs/default.ts | ✅ | 16 | |
| 36 | tabs/nested.ts | ✅ | 8 | |
| 37 | tailwind/container.ts | ✅ | 16 | Fixed: heading selector, http URL check |
| 38 | text.ts | ✅ | 16 | Fixed: post 1099, textDecorationLine, class counts |
| 39 | textarea.ts | ✅ | 10 | |
| 40 | toggle.ts | ✅ | 10 | |
| 41 | token.ts | ✅ | 10 | |
| 42 | transforms/transforms-1.ts | ✅ | 8 | |
| 43 | transforms/transforms-2.ts | ✅ | 8 | |
| 44 | transforms/transforms-3.ts | ✅ | 12 | |
| 45 | unit.ts | ✅ | 12 | |
| 46 | variations/variation-1.ts | ✅ | 8 | |
| 47 | variations/variation-2.ts | ✅ | 8 | |
| 48 | wysiwyg/default.ts | ✅ | 46 | |
| 49 | wysiwyg/switch.ts | ✅ | 8 | |

---

## Progress

- **Fully Passing**: 47 / 49 (96%)
- **Failing**: 2 / 49 (repeater/complete, repeater/outside - JSON checks with fabrikat.local URLs)
- **Status**: WP 6.9 Migration complete

---

## Troubleshooting

When a test fails, **DO NOT** immediately rewrite the test. Tests define expected behavior. Follow this process:

### Step 1: Run in Isolation
```bash
npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/<file>.ts --retries=0
```

### Step 2: Check Artifacts
- `test-results/<test>/error-context.md` - Page HTML snapshot
- `test-results/<test>/test-failed-1.png` - Screenshot at failure

### Step 3: Run Headed (Watch It)
```bash
npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/<file>.ts --retries=0 --headed
```
Watch exactly what happens. Is the element there? Is it visible? Does the click work?

### Step 4: Debug with Pause
```bash
npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/<file>.ts --retries=0 --debug
```
Step through line by line. Inspect the DOM at each step.

### Step 5: Add Logging
Add `console.log` statements to understand state:
```typescript
const html = await editor.locator('.selector').innerHTML();
console.log('Current HTML:', html);
```

### Step 6: Check Reference Theme
If functionality seems missing, check `themes/fabrikat` for:
- `blockstudio.json` settings
- `functions.php` filters/actions
- CSS/JS assets

### Common Issues

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| Element not found | Selector changed, timing | Add wait, check selector |
| Timeout | Slow loading, wrong selector | Increase timeout, verify selector |
| Wrong value | Data not set up | Check test-helper.php setup |
| 404 on asset | Path/URL mismatch | Check wp-env mappings |

### DO NOT
- ❌ Rewrite test logic without understanding the failure
- ❌ Skip tests or mark them as expected failures
- ❌ Assume the test is wrong - investigate first
- ❌ Make changes without running headed to verify

### DO
- ✅ Understand exactly what the test expects
- ✅ Verify the actual page state matches expectations
- ✅ Fix the environment/setup, not the test
- ✅ Document findings in the Notes section below

---

## Notes

(Add notes for specific test issues here as they arise)

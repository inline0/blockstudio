---
name: bug-fix
description: Fix bugs using test-driven development - first write a failing test, then implement the fix and verify it passes
---

# Bug Fix Skill

Fix bugs in Blockstudio 7.0 using a test-driven approach.

## Workflow

### 1. Understand the Bug

- Get a clear description of the bug from the user
- Identify expected vs actual behavior
- Research the relevant code in `includes/` and `package/`

### 2. Write a Failing Test First

Check if a matching folder already exists in `tests/blocks/types/` and `tests/e2e/types/`. If so, add your test to the existing folder/file instead of creating a new one.

Otherwise, create a **minimal reproduction** test block in `tests/blocks/types/{bug-name}/`. Don't copy the user's full configuration - include only the fields necessary to trigger the bug:

```
tests/blocks/types/{bug-name}/
├── block.json      # Block configuration that triggers the bug
└── index.twig      # Template that demonstrates the bug
```

Create E2E test in `tests/e2e/types/{bug-name}.ts`:

```typescript
import { Page, expect } from '@playwright/test';
import { testType } from '../utils/playwright-utils';

testType(
  '{bug-name}',
  false,
  () => {
    return [
      {
        description: 'should [expected behavior]',
        testFunction: async (page: Page) => {
          // Test that verifies the EXPECTED behavior
          // This test should FAIL initially because the bug exists
        },
      },
    ];
  }
);
```

### 3. Verify Test Fails

Run the test to confirm it fails with the current buggy behavior:

```bash
npm run playground:v7   # Start playground server (port 9701)
npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/{bug-name}.ts
```

The test MUST fail at this point. If it passes, the test is not correctly capturing the bug.

### 4. Implement the Fix

- Fix the bug in `includes/` (PHP) or `package/` (frontend)
- Keep the fix minimal and focused
- Run `composer cs` to check coding standards
- Run `composer cs:fix` to auto-fix issues

### 5. Verify Test Passes

Run the test again to confirm the fix works:

```bash
npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/{bug-name}.ts
```

The test should now PASS.

### 6. Run Full Test Suite

Ensure the fix doesn't break anything else:

```bash
npm run test:v7         # Run unit tests
npm run test:e2e        # Run all E2E tests
```

### 7. Update Changelog

Add entry to `readme.txt` under the `== Changelog ==` section:

```
= 7.0.0 (next) =
* Fix: description of what was fixed
```

## Checklist

Before marking complete, verify:

- [ ] Bug understood and documented
- [ ] Failing test created that demonstrates the bug
- [ ] Test confirmed to FAIL before fix
- [ ] Fix implemented
- [ ] Test confirmed to PASS after fix
- [ ] WordPress Coding Standards pass (`composer cs`)
- [ ] Full test suite passes
- [ ] Changelog updated in `readme.txt`

## Commands Reference

| Command | Description |
|---------|-------------|
| `npm run playground:v7` | Start test server (port 9701) |
| `npm run test:v7` | Run unit/snapshot tests |
| `npm run test:e2e` | Run all E2E tests |
| `npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/{name}.ts` | Run specific E2E test |
| `composer cs` | Check PHP coding standards |
| `composer cs:fix` | Auto-fix coding standards |

## Tips

- Name the test block/file descriptively (e.g., `repeater-nested-default` for a bug with nested repeater defaults)
- The test should be minimal - only include what's needed to reproduce the bug
- If the bug is in frontend code, the test may need to interact with the editor
- Keep the fix as small as possible to minimize risk of regressions

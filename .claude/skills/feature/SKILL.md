---
name: feature
description: Build and ship complete features for Blockstudio 7.0 including implementation, E2E tests, documentation, and changelog updates
---

# Feature Build Skill

Build and ship complete features for Blockstudio 7.0.

## Workflow

### 1. Research (if needed)

If you don't already understand how the feature should work:

- Explore the codebase to understand existing patterns
- Check `includes/` for PHP classes
- Check `package/` for frontend code
- Check `_reference/` for v6 implementation (read-only)
- Review similar features for patterns

### 2. Implement

- Write PHP code in `includes/` following WordPress Coding Standards
- Write frontend code in `package/` if needed
- Run `composer cs` to check coding standards
- Run `composer cs:fix` to auto-fix issues

### 3. Add E2E Test

Create test block in `tests/blocks/types/{feature-name}/`:

```
tests/blocks/types/{feature-name}/
├── block.json      # Block configuration with blockstudio attributes
└── index.twig      # Template rendering the feature
```

Example block.json:
```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/type-{feature-name}",
  "title": "Feature Name",
  "category": "blockstudio-test-native",
  "icon": "star-filled",
  "description": "Description of the feature.",
  "blockstudio": {
    "attributes": [
      {
        "id": "fieldId",
        "type": "text",
        "label": "Field Label"
      }
    ]
  }
}
```

Create E2E test in `tests/e2e/types/{feature-name}.ts`:

```typescript
import { Page, expect } from '@playwright/test';
import { testType } from '../utils/playwright-utils';

testType(
  '{feature-name}',
  false,
  () => {
    return [
      {
        description: 'test description',
        testFunction: async (page: Page) => {
          // Test implementation
        },
      },
    ];
  }
);
```

### 4. Run Tests

```bash
npm run playground:v7   # Start playground server (port 9701)
npm run test:v7         # Run unit tests
npm run test:e2e        # Run E2E tests (requires wp-env)
```

### 5. Add Documentation

Add or update documentation in `docs/content/docs/`:

- Create new `.mdx` file if it's a new feature
- Update existing docs if extending a feature
- Follow the existing documentation style
- Include code examples

### 6. Update Changelog

Add entry to `readme.txt` under the `== Changelog ==` section. Add to the current version at the top:

```
= 7.0.0 (next) =
* New: feature description
* Enhancement: improvement description
* Fix: bug fix description
* Breaking: breaking change description
```

## Checklist

Before marking complete, verify:

- [ ] Feature implemented and working
- [ ] WordPress Coding Standards pass (`composer cs`)
- [ ] Test block created in `tests/blocks/types/`
- [ ] E2E test created in `tests/e2e/types/`
- [ ] Unit tests pass (`npm run test:v7`)
- [ ] E2E tests pass (`npm run test:e2e`)
- [ ] Documentation added/updated
- [ ] Changelog updated in `readme.txt`

## Commands Reference

| Command | Description |
|---------|-------------|
| `npm run playground:v7` | Start v7 test server (port 9701) |
| `npm run test:v7` | Run unit/snapshot tests |
| `npm run test:e2e` | Run E2E tests |
| `composer cs` | Check PHP coding standards |
| `composer cs:fix` | Auto-fix coding standards |

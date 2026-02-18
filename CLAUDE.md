# Blockstudio 7

WordPress block framework plugin. v7 modernization with 100% WordPress Coding Standards.

## Quick Reference

```bash
# Run E2E tests (browser automation)
npm run wp-env:start && npm run test:e2e

# Check coding standards
composer cs
```

## Testing - IMPORTANT

There is one active test system:

### E2E Tests (wp-env)

**Purpose:** Browser automation tests for UI interactions.

**Technology:** wp-env (full WordPress with Docker)

**Commands:**
```bash
npm run wp-env:start        # Start wp-env (port 8888)
npm run test:e2e            # Run all E2E tests
npm run catalog:blocks      # Run catalog block generation test
npx playwright test tests/e2e/types/text.ts --config=playwright.wp-env.config.ts  # Run a specific test
```

**Test files:** `tests/e2e/**/*.ts`

## Project Structure

```
blockstudio7/
├── blockstudio.php      # Entry point
├── includes/            # PHP classes (primary codebase)
├── src/                 # Frontend code (TypeScript/React)
│   ├── blocks/          # Block editor components
│   │   ├── components/  # React components (fields, editor, etc.)
│   │   └── hooks/       # Custom hooks (usePopout, useMedia, etc.)
│   └── types/           # TypeScript types (block.ts, types.ts)
├── docs/                # Documentation (OneDocs/Next.js)
│   └── src/schemas/     # JSON Schema definitions (block, blockstudio, extend)
├── .claude/skills/      # Claude Code skills
├── readme.txt           # WordPress plugin readme with changelog
├── _reference/          # Legacy reference code (read-only)
└── tests/
    ├── e2e/             # E2E test files
    └── theme/           # Test theme (blocks, pages, test-helper)
        ├── blockstudio/ # Test blocks
        ├── pages/       # Test pages
        └── test-helper.php
```

## Skills

Use `/feature` when implementing new features. This skill guides the complete workflow:

1. Research codebase (if needed)
2. Implement the feature
3. Update schema if adding new field properties (`docs/src/schemas/`)
4. Update TypeScript types (`src/types/block.ts`)
5. Add E2E test in `tests/e2e/types/`
6. Add test block in `tests/theme/blockstudio/types/`
7. Update documentation in `docs/content/docs/`
8. Update changelog in `readme.txt`

## Schemas

JSON Schemas are defined in `docs/src/schemas/` and served via Next.js routes:

- `/schema/block` - Block definition schema (extends WordPress block.json)
- `/schema/blockstudio` - Blockstudio settings schema
- `/schema/extend` - Block extension schema

When adding new field properties:
1. Add to `docs/src/schemas/schema.ts` in the appropriate field definition
2. Add TypeScript type to `src/types/block.ts`

## Documentation

The `docs/` folder contains the documentation site built with OneDocs (Fumadocs + Next.js).

```bash
cd docs
npm install
npm run dev              # Start dev server on port 9700
npm run generate         # Generate docs from local schemas
npm run build            # Generate + build
```

**Schema-driven docs:** Field types and settings filters are auto-generated from local schemas in `docs/src/schemas/`. Generated content is injected between `{/* GENERATED_*_START */}` and `{/* GENERATED_*_END */}` markers in MDX files.

## Comment Policy

- Internal code: no JSDoc. Comments only for why, not what.
- Public APIs: JSDoc required (description + params/returns/examples).
- Tests: no redundant comments that restate test names. Comment only when setup/assertion is non-obvious.
- **No banner comments**: never use decorative separator lines like `// ==========`, `// -----`, `// ===== SECTION =====`, etc. Exception: in large test files with many assertions, a single `// Section Name` line is fine to separate groups.
- **No em dashes**: never use em dashes in code, docs, or copy. Use periods, commas, colons, or rewrite the sentence.

## Key Rules for Claude

1. **NEVER COMMIT WITHOUT TESTING** - Always run and verify tests pass before committing, unless explicitly instructed otherwise.
2. **DEBUG UNTIL SOLVED** - When a test fails, debug with temporary logging, screenshots, and other debugging tools until the problem is resolved.
3. **Run tests** after changes: `npm run test:e2e`
4. **Never modify `_reference/`** - read-only legacy baseline
5. **100% WordPress Coding Standards** - no exceptions
6. **One class at a time** - migrate and test incrementally
7. **Avoid direct `npx` for routine flows** - prefer `npm run` scripts from package.json

## Commands

| Command | Description |
|---------|-------------|
| `npm run wp-env:start` | Start wp-env for E2E tests (port 8888) |
| `npm run wp-env:stop` | Stop wp-env |
| `npm run wp-env:reset` | Reset wp-env + seed test data |
| `npm run test:e2e` | Run all E2E tests |
| `npm run catalog:blocks` | Run catalog block test |
| `composer cs` | Check PHPCS |
| `composer cs:fix` | Auto-fix PHPCS issues |

### Docs Commands (run from `docs/`)

| Command | Description |
|---------|-------------|
| `npm run dev` | Start docs dev server (port 9700) |
| `npm run generate` | Generate MDX from local schemas |
| `npm run build` | Full build (generate + next build) |

## Ports

| Server | Port |
|--------|------|
| Docs | 9700 |
| wp-env (E2E) | 8888 |

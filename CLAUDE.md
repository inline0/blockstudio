# Blockstudio 7

WordPress block framework plugin. v7 modernization with 100% WordPress Coding Standards.

## Quick Reference

```bash
# Run unit tests (PHPUnit inside wp-env)
npm run wp-env:start && npm run test:unit

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
├── docs/                # Portable Markdown collections
│   ├── docs/            # Product documentation
│   ├── guides/          # Guides
│   ├── registry/        # Registry documentation
│   └── blog/            # Release posts
├── schemas/             # Canonical static JSON Schemas
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
3. Update schema if adding new field properties (`schemas/`)
4. Update TypeScript types (`src/types/block.ts`)
5. Add E2E test in `tests/e2e/types/`
6. Add test block in `tests/theme/blockstudio/types/`
7. Update documentation in `docs/docs/`
8. Update changelog in `readme.txt`

## Schemas

JSON Schemas are stored as deterministic JSON files in `schemas/`:

- `/schema/block` - Block definition schema (extends WordPress block.json)
- `/schema/blockstudio` - Blockstudio settings schema
- `/schema/extend` - Block extension schema

When adding new field properties:
1. Update the appropriate JSON file in `schemas/` and its checksum in `schemas/manifest.json`
2. Add TypeScript type to `src/types/block.ts`
3. Run `npm run schemas:check` and `npm run types`

## Documentation

The `docs/` folder contains four portable Markdown collections consumed by the
Blockstudio site and release tooling. It contains no application runtime.

```bash
npm run docs:check       # Validate content, frontmatter, and navigation
npm run docs:generate    # Refresh schema-generated documentation sections
npm run build:llm        # Rebuild the bundled LLM context
```

**Schema-driven docs:** Field types and settings filters are generated from
`schemas/`. Generated content is injected between the existing generated
section markers in Markdown files.

## Changelog Policy

- `readme.txt` changelog entries are release-facing. Do not add entries for iterative changes to unreleased 7.3 behavior.
- Only update the changelog when documenting a release boundary or a change that should be visible to users upgrading from a previous published version.

## Comment Policy

- Internal code: no JSDoc. Comments only for why, not what.
- Public APIs: JSDoc required (description + params/returns/examples).
- Tests: no redundant comments that restate test names. Comment only when setup/assertion is non-obvious.
- **No banner comments**: never use decorative separator lines like `// ==========`, `// -----`, `// ===== SECTION =====`, etc. Exception: in large test files with many assertions, a single `// Section Name` line is fine to separate groups.
- **No em dashes**: never use em dashes in code, docs, or copy. Use periods, commas, colons, or rewrite the sentence.

## CI

- Lint (TSC + PHPCS) runs on every push to `main`.
- E2E tests only run when the commit message contains `[e2e]`. Include `[e2e]` in the commit message when changes affect plugin functionality (PHP, TypeScript, tests). Skip it for docs-only, UI copy, or config changes.

## Key Rules for Claude

1. **NEVER COMMIT WITHOUT TESTING** - Always run and verify tests pass before committing, unless explicitly instructed otherwise.
2. **DEBUG UNTIL SOLVED** - When a test fails, debug with temporary logging, screenshots, and other debugging tools until the problem is resolved.
3. **Run tests** after changes: `npm run test:e2e`
4. **Never modify `_reference/`** - read-only legacy baseline
5. **100% WordPress Coding Standards** - no exceptions
6. **One class at a time** - migrate and test incrementally
7. **Avoid direct `npx` for routine flows** - prefer `npm run` scripts from package.json
8. **E2E CI gate** - Add `[e2e]` to commit messages when changes affect plugin functionality. Omit for docs/UI-only changes.
9. **Durable guidance only** - Add instructions here only when they are general, valid, and repeatedly useful for the repo. Do not add one-off preferences, temporary decisions, or task-specific snippets.

## Commands

| Command | Description |
|---------|-------------|
| `npm run wp-env:start` | Start wp-env for E2E tests (port 8888) |
| `npm run wp-env:stop` | Stop wp-env |
| `npm run wp-env:reset` | Reset wp-env + seed test data |
| `npm run test:unit` | Run PHPUnit tests inside wp-env |
| `npm run test:e2e` | Run all E2E tests |
| `npm run catalog:blocks` | Run catalog block test |
| `composer cs` | Check PHPCS |
| `composer cs:fix` | Auto-fix PHPCS issues |

### Docs Commands

| Command | Description |
|---------|-------------|
| `npm run docs:check` | Validate all portable Markdown collections |
| `npm run docs:generate` | Refresh schema-generated documentation |
| `npm run schemas:check` | Validate and checksum the static schemas |
| `npm run build:llm` | Rebuild the bundled LLM context |

## Ports

| Server | Port |
|--------|------|
| wp-env (E2E) | 8888 |

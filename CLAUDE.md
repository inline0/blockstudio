# Blockstudio 7

WordPress block framework plugin - v7 modernization with 100% WordPress Coding Standards.

## Quick Reference

```bash
# Run tests
npm run playground:v7 && npm run test:v7

# Check coding standards
composer cs
```

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
├── _reference/          # v6 reference (gitignored, for snapshots)
└── tests/               # Test infrastructure
```

## Skills

Use `/feature` when implementing new features. This skill guides the complete workflow:

1. Research codebase (if needed)
2. Implement the feature
3. Update schema if adding new field properties (`docs/src/schemas/`)
4. Update TypeScript types (`src/types/block.ts`)
5. Add E2E test in `tests/e2e/types/`
6. Add test block in `tests/blocks/types/`
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

- Internal code: no JSDoc; comments only for **why**, not **what**.
- Public APIs: JSDoc required (description + params/returns/examples).
- Tests: no redundant comments that restate test names; comment only when setup/assertion is non-obvious.

## Key Rules for Claude

1. **NEVER COMMIT WITHOUT TESTING** - Always run and verify tests pass before committing. No exceptions.
2. **DEBUG UNTIL SOLVED** - When a test fails, debug with temporary logging, screenshots, and other debugging tools until the problem is resolved. Do not give up or move on.
3. **Run tests** after changes: `npm run test:v7`
4. **Never modify `_reference/`** - read-only v6 baseline
5. **100% WordPress Coding Standards** - no exceptions
6. **One class at a time** - migrate and test incrementally
7. **Never use `npx`** - always use `npm run` scripts from package.json

## Commands

| Command | Description |
|---------|-------------|
| `npm run playground:v7` | Start v7 server (port 9701) |
| `npm run test:v7` | Run v7 unit/snapshot tests (not E2E) |
| `npm run playground` | Start v6 reference server (port 9706) |
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
| v7 Unit Tests | 9701 |
| v6 Reference | 9706 |
| v7 E2E | 9711 |
| v6 E2E | 9710 |

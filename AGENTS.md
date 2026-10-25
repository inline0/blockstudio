# Blockstudio 7

WordPress block framework plugin. v7 modernization with 100% WordPress Coding Standards.

## Quick Reference

```bash
# Local static checks (no containers or browsers)
npm run lint
npm run typecheck
npm run runtime:check

# Check coding standards
composer cs
```

## Testing - IMPORTANT

Full suites and all E2E tests run in GitHub CI only. Do not run `npm run check`,
the full client/PHP/registry suites, Playwright, or screenshot scripts locally.
Focused non-E2E tests are allowed, for example
`node --test scripts/tests/build.test.ts`. Builds, formatting, lint, types,
and documentation/schema checks can run locally.

### E2E Tests (wp-env)

**Purpose:** Browser automation tests for UI interactions.

**Technology:** wp-env (full WordPress with Docker)

**CI commands (not local):**

```bash
npm run wp-env:start        # Start wp-env (port 8888)
npm run test:e2e            # Run all E2E tests
npm run catalog:blocks      # Run catalog block generation test
npx playwright test tests/e2e/types/text.ts --config=playwright.wp-env.config.ts  # Run a specific test
```

**Test files:** `tests/e2e/**/*.ts`

**Optional contributor tooling:** `npm run test:e2e:playground -- tests/e2e/types/text.ts`
runs explicit specs against WordPress Playground instead of wp-env (see README,
"Browser tests without Docker"). Agents must still use CI for all browser tests
and must not start Playground locally. The optional workflow has a CI smoke job.

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

## Tooling

- Use Node from `.nvmrc`; install with `npm ci` and `npm ci --prefix packages/registry`.
- Repository scripts/configs are TypeScript, run directly with Node. Keep syntax erasable and imports explicit (`.ts`, `import type`).
- Use the Effect runtime in `scripts/lib/` for CLI boundaries, scoped processes and temporary files. Keep pure parsers ordinary TypeScript.
- Oxfmt owns formatting; Oxlint owns lint. PHP remains PHPCS/PHPStan. Prettier remains a browser formatting dependency, not the repository formatter.
- Edit UI behavior in `src/ui/` and frontend helpers in `src/runtime/`; run `npm run build:runtime` and commit the generated JS at its original `includes/` path.
- Do not convert JavaScript test fixtures or vendor code. `audit:tooling` enforces the explicit exceptions and checked TS project coverage.
- `npm run build` retains the WordPress webpack pipeline, externals and `.asset.php` manifests.
- Translation generation is explicit: `npm run i18n:translate` uses Codex CLI to fill missing forms only. Preserve existing translations. `i18n:check` and `i18n:coverage` are offline CI gates; never call AI from tests or normal builds.

## CI

- The complete CI suite runs on every push to `main`, `7.*`, and `tooling-upgrade`.
- Main CI uses the existing self-hosted Blockstudio runners: four main-suite shards, empty-theme coverage, and UI regressions. Distribution and release packaging retain their existing runners.
- PHP unit tests, main E2E and live-filesystem coverage use native wp-env. Loading-mode probes and the optional contributor smoke use Playground; do not replace native process, locking or OPcache coverage with WASM.
- Main E2E shards balance whole files using recorded durations. New matching test files are included automatically; keep the default test selection intact.
- CI setup actions reuse a suitable host PHP toolchain, share Composer dependencies within the same run and use a fresh bounded npm download cache. Do not add large archive restores without measuring their complete cost.
- Commit-message tags such as `[all]`, `[e2e]`, and `[unit]` do not gate jobs. `[all]` remains a release-validation convention only.

## Key Rules for Agents

1. **NEVER COMMIT WITHOUT TESTING** - Always run and verify tests pass before committing, unless explicitly instructed otherwise.
2. **DEBUG UNTIL SOLVED** - When a test fails, debug with temporary logging, screenshots, and other debugging tools until the problem is resolved.
3. **CI owns full testing** - No local E2E or full-suite runs. Use focused non-E2E checks locally, then push and inspect every CI job.
4. **Never modify `_reference/`** - read-only legacy baseline
5. **100% WordPress Coding Standards** - no exceptions
6. **One class at a time** - migrate and test incrementally
7. **Avoid direct `npx` for routine flows** - prefer `npm run` scripts from package.json
8. **E2E CI gate** - Every push to `main` and `7.*` runs the complete E2E matrix; do not add commit-message conditions that bypass behavioral coverage.
9. **Durable guidance only** - Add instructions here only when they are general, valid, and repeatedly useful for the repo. Do not add one-off preferences, temporary decisions, or task-specific snippets.

## Commands

| Command                  | Description                                             |
| ------------------------ | ------------------------------------------------------- |
| `npm run wp-env:start`   | Start wp-env for E2E tests (port 8888)                  |
| `npm run wp-env:stop`    | Stop wp-env                                             |
| `npm run wp-env:reset`   | Reset wp-env + seed test data                           |
| `npm run test:unit`      | Run PHPUnit tests inside wp-env                         |
| `npm run test:e2e`       | Run all E2E tests                                       |
| `npm run test:e2e:playground` | Run given E2E specs on Playground, no Docker       |
| `npm run playground:start` | Start a seeded Playground site (port 8888)            |
| `npm run catalog:blocks` | Run catalog block test                                  |
| `composer cs`            | Check PHPCS                                             |
| `composer cs:fix`        | Auto-fix PHPCS issues                                   |
| `npm run i18n`           | Update `languages/` after changing translatable strings |

### Docs Commands

| Command                 | Description                                |
| ----------------------- | ------------------------------------------ |
| `npm run docs:check`     | Validate all portable Markdown collections |
| `npm run docs:generate`  | Refresh schema-generated documentation     |
| `npm run schemas:check`  | Validate and checksum the static schemas   |
| `npm run build:llm`      | Rebuild the bundled LLM context            |

## Ports

| Server       | Port |
| ------------ | ---- |
| wp-env (E2E) | 8888 |

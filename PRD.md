# Frontend Modernization PRD

## Overview

Modernize the frontend tooling while keeping webpack as the build system.

## Goals

1. **Consolidate to root** - Move JS/package infrastructure to project root
2. **Rename package → src** - Standard naming for source code
3. **Remove gulp** - Keep type generation scripts standalone
4. **Stricter TypeScript** - Enable strict mode, fix all errors
5. **Keep ESLint + Prettier** - Keep existing linting/formatting setup
6. **Single pipeline** - One command for format, lint, and type-check

## Principles

- **Use newest versions** - All new packages must use latest stable versions
- **Verify after each change** - After each refactor step, run build and tests:
  ```bash
  npm run build && npx playwright test --config=playwright.wp-env.config.ts tests/e2e/types/text.ts
  ```

## Non-Goals

- Migrating to Vite (keeping webpack)
- Changing test infrastructure
- Replacing ESLint/Prettier with Biome

## Tasks

### Phase 1: Directory Restructure ✅

- [x] Rename `package/` to `src/`
- [x] Move `package/package.json` dependencies to root `package.json`
- [x] Move `package/tsconfig.json` to root
- [x] Move `package/webpack.config.js` to root as `webpack.config.cjs`
- [x] Update all import paths and aliases
- [x] Move `package/.babelrc` to root
- [x] Delete `package/` directory artifacts (gulp, etc.)

### Phase 2: Remove Gulp ✅

- [x] Extract type generation from gulp to standalone script
- [x] Remove gulpfile.cjs
- [x] Remove gulp dependencies from package.json
- [x] Update npm scripts

### Phase 3: ESLint + Prettier Setup ✅

- [x] Move ESLint config to root
- [x] Move Prettier config to root
- [x] Update npm scripts: `lint`, `format`

### Phase 4: Single Pipeline ✅

- [x] Create unified `npm run check` command
- [x] Runs: eslint + tsc --noEmit
- [x] Create `npm run fix` for auto-fixing
- [ ] Update CI/pre-commit if applicable

### Phase 5: Purge Unused Packages ✅

- [x] Run `npx depcheck` to identify unused dependencies
- [x] Remove unused packages from package.json
- [x] Verify build still works

### Phase 6: Strict TypeScript ✅

- [x] Enable strict mode in tsconfig.json
- [x] Fix all TypeScript errors (176 → 0)
- [x] Add proper types where `any` is used
- [x] Ensure no implicit any
- [x] Fix prettier.format() sync type (returns string, not Promise)

### Phase 7: ESLint Strictening ✅

- [x] Enable stricter rules (no-unused-vars, array-callback-return, dot-notation)
- [x] Refactor code to fix new lint errors
- [x] Remove any unnecessary eslint-disable comments (none found)

### Phase 8: Babel → SWC (Optional) - SKIPPED

- [x] Verified Emotion CSS transforms work with SWC (~25% faster builds)
- [x] Decided to keep Babel for stability (SWC Emotion plugin less mature)

### Phase 9: Update Dependencies

- [ ] Update all packages except react/react-dom, tailwindcss (waiting for WP React 19 support)
- [ ] Update prettier v2 → v3 and fix breaking changes
- [ ] Update node-fetch v2 → v3 (ESM-only) and fix imports
- [ ] Fix any other breaking changes
- [ ] Run full test suite to verify

## File Structure (After)

```
blockstudio7/
├── src/                    # Frontend source (was package/src)
│   ├── blocks/
│   ├── components/
│   ├── tailwind/
│   ├── types/
│   └── utils/
├── scripts/                # Build scripts (type generation, etc.)
├── tests/                  # Test infrastructure (unchanged)
├── includes/               # PHP (unchanged)
├── package.json            # Root package.json with all deps
├── tsconfig.json           # Root TypeScript config
├── webpack.config.cjs      # Root webpack config
├── eslint.config.mjs       # ESLint config
└── .prettierrc             # Prettier config
```

## Success Criteria

1. ✅ `npm run build` produces working bundle
2. ✅ `npm run check` validates formatting, linting, and types
3. ✅ All E2E tests pass (2714 tests)
4. ✅ No gulp dependencies remain
5. ✅ TypeScript strict mode enabled with zero errors

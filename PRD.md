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

### Phase 1: Directory Restructure

- [ ] Rename `package/` to `src/`
- [ ] Move `package/package.json` dependencies to root `package.json`
- [ ] Move `package/tsconfig.json` to root
- [ ] Move `package/webpack.config.js` to root as `webpack.config.ts`
- [ ] Update all import paths and aliases
- [ ] Delete `package/` directory artifacts (gulp, babel, etc.)

### Phase 2: Remove Gulp

- [ ] Extract type generation from gulp to standalone script
- [ ] Remove gulpfile.cjs
- [ ] Remove gulp dependencies from package.json
- [ ] Update npm scripts

### Phase 3: ESLint + Prettier Setup

- [ ] Move ESLint config to root
- [ ] Move Prettier config to root
- [ ] Update npm scripts: `lint`, `format`

### Phase 4: Single Pipeline

- [ ] Create unified `npm run check` command
- [ ] Runs: eslint + prettier --check + tsc --noEmit
- [ ] Create `npm run fix` for auto-fixing
- [ ] Update CI/pre-commit if applicable

### Phase 5: Strict TypeScript

- [ ] Enable strict mode in tsconfig.json
- [ ] Fix all TypeScript errors
- [ ] Add proper types where `any` is used
- [ ] Ensure no implicit any

### Phase 6: ESLint Strictening

- [ ] Move ESLint config to `eslint.config.ts` if supported
- [ ] Enable stricter rules
- [ ] Refactor code to fix new lint errors
- [ ] Remove any unnecessary eslint-disable comments

### Phase 7: Babel → SWC (Optional)

- [ ] Verify Emotion CSS transforms work with SWC
- [ ] If compatible, replace Babel with SWC
- [ ] Update webpack config to use swc-loader
- [ ] Remove Babel dependencies

### Phase 8: Update Dependencies

- [ ] Update all npm packages to latest versions
- [ ] Fix any breaking changes
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
├── webpack.config.ts       # Root webpack config (TypeScript)
├── eslint.config.mjs       # ESLint config
└── .prettierrc             # Prettier config
```

## Success Criteria

1. `npm run build` produces working bundle
2. `npm run check` validates formatting, linting, and types
3. All 17 E2E tests pass
4. No gulp dependencies remain
5. TypeScript strict mode enabled with zero errors

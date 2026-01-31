# Frontend Modernization PRD

## Overview

Modernize the frontend tooling while keeping webpack as the build system.

## Goals

1. **Consolidate to root** - Move JS/package infrastructure to project root
2. **Rename package → src** - Standard naming for source code
3. **Remove gulp** - Keep type generation scripts standalone
4. **Stricter TypeScript** - Enable strict mode, fix all errors
5. **Biome for linting/formatting** - Replace ESLint + Prettier
6. **Single pipeline** - One command for format, lint, and type-check

## Principles

- **Use newest versions** - All new packages must use latest stable versions

## Non-Goals

- Migrating to Vite (keeping webpack)
- Changing test infrastructure

## Tasks

### Phase 1: Directory Restructure

- [ ] Rename `package/` to `src/`
- [ ] Move `package/package.json` dependencies to root `package.json`
- [ ] Move `package/tsconfig.json` to root
- [ ] Move `package/webpack.config.js` to root
- [ ] Update all import paths and aliases
- [ ] Delete `package/` directory artifacts (gulp, babel, etc.)

### Phase 2: Remove Gulp

- [ ] Extract type generation from gulp to standalone script
- [ ] Remove gulpfile.cjs
- [ ] Remove gulp dependencies from package.json
- [ ] Update npm scripts

### Phase 3: Biome Setup

- [ ] Install biome
- [ ] Create biome.json config
- [ ] Remove ESLint config and dependencies
- [ ] Remove Prettier config and dependencies
- [ ] Add npm scripts: `check`, `fix`, `format`, `lint`

### Phase 4: Strict TypeScript

- [ ] Enable strict mode in tsconfig.json
- [ ] Fix all TypeScript errors
- [ ] Add proper types where `any` is used
- [ ] Ensure no implicit any

### Phase 5: Single Pipeline

- [ ] Create unified `npm run check` command
- [ ] Runs: biome check + tsc --noEmit
- [ ] Create `npm run fix` for auto-fixing
- [ ] Update CI/pre-commit if applicable

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
├── webpack.config.js       # Root webpack config
└── biome.json              # Biome config
```

## Success Criteria

1. `npm run build` produces working bundle
2. `npm run check` validates formatting, linting, and types
3. All 17 E2E tests pass
4. No gulp dependencies remain
5. TypeScript strict mode enabled with zero errors

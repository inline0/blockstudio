# Testing

## Philosophy

**Hybrid Approach:** Unit tests use WordPress Playground (fast, in-memory). E2E tests use wp-env (Docker, real MySQL).

| Test Type | Environment | Why |
|-----------|-------------|-----|
| **Unit Tests** | WordPress Playground | Fast startup, snapshot testing |
| **E2E Tests** | wp-env (Docker) | Stable UI testing, real database |

## Current Status

| Test Suite | Status | Focus |
|------------|--------|-------|
| **Unit Tests** | **All passing** (14/14) | Build class snapshot verification |
| **E2E Tests** | 35/49 passing (71%) | Field type interactions |

### Unit Tests

Unit tests verify that the Build class produces correct output by comparing against snapshots. These test the PHP backend without UI interaction.

**All 14 unit tests pass.** They verify:
- Block discovery and registration
- Attribute building from fields
- Asset compilation (SCSS → CSS)
- All Build class getter methods

### E2E Tests

E2E tests interact with the Gutenberg editor to test field types (text, number, select, repeater, etc.). They use wp-env for stability.

**Goal: 100% passing for field type tests in `tests/e2e/types/`.**

Current E2E tests focus on:
- Adding blocks to the editor
- Editing field values in the inspector
- Saving and verifying persistence
- Testing default values

See `docs/e2e-testing-prd.md` for detailed progress tracking.

## Test Types

| Type | Focus | Location | Environment |
|------|-------|----------|-------------|
| **Unit Tests** | Build class snapshots | `tests/unit/` | Playground (port 9401) |
| **E2E Tests** | Field type UI interactions | `tests/e2e/types/` | wp-env (port 8888) |

## Snapshot Testing

The primary strategy is **snapshot testing**. We capture exact output from Build class methods and verify future runs produce identical data.

### Build Class Methods Tested

- `blocks()` - All discovered blocks with configurations
- `data()` - Block data indexed by path
- `extensions()` - Extension configurations
- `files()` - All discovered files
- `assetsAdmin()` - Admin-specific assets
- `assetsBlockEditor()` - Block editor assets
- `assetsGlobal()` - Global assets
- `paths()` - Block discovery paths
- `overrides()` - Block overrides
- `assets()` - All assets
- `blade()` - Blade template configurations

### Dynamic Value Normalization

Before comparison, these dynamic values are normalized:

| Pattern | Normalized To |
|---------|---------------|
| `scope:0.1234567890` | `scope:NORMALIZED` |
| `test-1769516162.js` | `test-TIMESTAMP.js` |
| `style-10af90f2...css` | `style-CONTENTHASH.css` |
| `blockstudio-49a9c898bab4` | `blockstudio-HASHID` |
| `bs-43849caa438e...` | `bs-NORMALIZED_HASH` |
| `mtime` fields | `NORMALIZED_MTIME` |
| `key` timestamp fields | `NORMALIZED_KEY` |

### Snapshot Files

```
tests/unit/snapshots/
├── build-snapshot.json      # ~4MB, all Build class output
└── compiled-assets.json     # Compiled CSS/JS from _dist/
```

## Capturing Snapshots

When Build class output changes intentionally:

```bash
# Start the v7 playground server
npm run playground:v7

# In another terminal, capture new snapshots
npx tsx tests/unit/capture-snapshot.ts
npx tsx tests/unit/capture-compiled-assets.ts

# Run tests to verify
npm run test:v7
```

The capture scripts use `PLAYGROUND_PORT` environment variable (defaults to 9401).

## Test Infrastructure

```
tests/
├── wordpress-playground/     # Playground infrastructure (unit tests)
│   ├── fixtures.ts           # Playwright fixtures with WP frame
│   ├── global-setup.ts       # One-time Playground initialization
│   ├── playground-init.ts    # Express server factory
│   └── playwright-wordpress.config.ts
├── unit/
│   ├── playground-server.ts      # v6 server (port 9400, serves _reference/)
│   ├── playground-server-v7.ts   # v7 server (port 9401, serves root)
│   ├── build-snapshot.test.ts    # Snapshot tests
│   ├── compiled-assets.test.ts   # Compiled assets tests
│   ├── capture-snapshot.ts       # Snapshot capture script
│   └── capture-compiled-assets.ts
├── e2e/
│   ├── theme/                    # Custom theme with Timber
│   ├── types/                    # E2E test files (49 tests)
│   └── utils/                    # Test helpers (playwright-utils.ts)
├── plugins/
│   └── test-helper/              # E2E setup plugin
│       └── test-helper.php       # REST endpoints, test data setup
├── blocks/                       # 100+ test blocks
│   ├── components/               # InnerBlocks, RichText, etc.
│   ├── functions/                # assets, context, events
│   ├── overrides/                # group, tabs, repeater
│   ├── single/                   # native blocks, elements
│   └── types/                    # All field types
└── snapshots/                    # Snapshot data
```

## Test Helper Plugin

The `test-helper.php` plugin:

1. **Configures block paths** - Tells Blockstudio where test blocks are
2. **Provides populate data** - Registers colors/gradients for populate fields
3. **Exposes REST endpoints** - For testing PHP from Playwright

```php
// Configure test blocks location
add_filter('blockstudio/settings/blocks/paths', function ($paths) {
    $paths[] = get_stylesheet_directory() . '/blockstudio';
    return $paths;
});

// Provide populate data
add_filter('blockstudio/blocks/attributes/populate', function ($options) {
    $options['colors'] = [
        ['value' => '#f00', 'label' => 'red'],
        ['value' => '#0f0', 'label' => 'green'],
        ['value' => '#00f', 'label' => 'blue'],
    ];
    return $options;
});

// REST endpoints
register_rest_route('blockstudio-test/v1', '/snapshot', [...]);
register_rest_route('blockstudio-test/v1', '/compiled-assets', [...]);
```

## Running Tests

### Unit Tests (Playground)

```bash
# Start v7 Playground server
npm run playground:v7

# Run unit tests (14 tests)
npm run test:v7

# Headed mode (debugging)
npm run test:headed

# Specific test file
npm run test:v7 -- tests/unit/build-snapshot.test.ts
```

### E2E Tests (wp-env)

```bash
# Start wp-env (first time or after reset)
npm run wp-env:start

# Run all E2E tests
npm run test:e2e

# Run single test file
npm run test:e2e -- tests/e2e/types/text.ts --retries=0

# Headed mode (watch browser)
npm run test:e2e:headed -- tests/e2e/types/text.ts --retries=0

# Reset wp-env completely (wipe DB + media + reset data)
npm run wp-env:reset

# Stop wp-env
npm run wp-env:stop
```

## E2E Testing

E2E tests interact with the WordPress block editor through Playwright, running against wp-env (Docker).

### Worker-Scoped Fixtures

Tests use worker-scoped fixtures for efficiency - the browser stays logged in across all tests in a worker:

```typescript
type WorkerFixtures = {
  workerPage: Page;  // Shared across all tests
};

type TestFixtures = {
  editor: Page;  // Per-test, uses workerPage
};
```

### Test Helper Functions

| Function | Purpose |
|----------|---------|
| `addBlock(editor, type)` | Opens inserter, searches, inserts block |
| `openBlockInserter(editor)` | Opens inserter |
| `openSidebar(editor)` | Opens block inspector sidebar |
| `save(editor)` | Publishes/updates, handles pre-publish |
| `saveAndReload(editor)` | Saves and reloads page |
| `removeBlocks(editor)` | Clears all blocks via data store |
| `testType(field, default, tests)` | Creates standard field type test suite |

### Test Data Setup

The test-helper plugin provides a REST endpoint that creates test data:

```bash
curl -X POST http://localhost:8888/wp-json/blockstudio-test/v1/e2e/setup
```

This creates posts, media, users, and terms needed by E2E tests. It's called automatically when the worker initializes.

## Compiled Assets Testing

In addition to Build metadata, we snapshot actual compiled output from `_dist/` directories:

- Compiled CSS from SCSS
- Generated JS files
- ES module bundles
- Scoped CSS output

This ensures the SCSS compilation pipeline doesn't change unexpectedly.

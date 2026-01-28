# Testing

## Philosophy

**WordPress Playground Only.** All tests run in a real WordPress environment using WordPress Playground (WebAssembly). This provides true integration testing without mocking.

### Why WordPress Playground?

| Aspect | wp-env (Docker) | WordPress Playground |
|--------|-----------------|---------------------|
| **CI Setup** | Docker-in-Docker complexity | Just Node.js |
| **Startup** | ~30 seconds | ~2-3 seconds |
| **Dependencies** | Docker daemon required | None (WebAssembly) |
| **Database** | MySQL container | SQLite in-memory |
| **Isolation** | Container-based | Complete (browser sandbox) |

## Current Status

| Test Suite | Status | Focus |
|------------|--------|-------|
| **Unit Tests** | **All passing** (14/14) | Build class snapshot verification |
| **E2E Tests** | Work in progress | Field type interactions |

### Unit Tests

Unit tests verify that the Build class produces correct output by comparing against snapshots. These test the PHP backend without UI interaction.

**All 14 unit tests pass.** They verify:
- Block discovery and registration
- Attribute building from fields
- Asset compilation (SCSS → CSS)
- All Build class getter methods

### E2E Tests

E2E tests interact with the Gutenberg editor to test field types (text, number, select, repeater, etc.). They were migrated from the original test suite.

**Goal: 100% passing for field type tests in `tests/e2e/types/`.**

Current E2E tests focus on:
- Adding blocks to the editor
- Editing field values in the inspector
- Saving and verifying persistence
- Testing default values

The E2E tests are not all passing yet - this is active work.

## Test Types

| Type | Focus | Location |
|------|-------|----------|
| **Unit Tests** | Build class snapshots | `tests/unit/` |
| **E2E Tests** | Field type UI interactions | `tests/e2e/types/` |

Both use WordPress Playground with Playwright.

## Ports

| Environment | v6 Reference | v7 Primary |
|-------------|--------------|------------|
| **Unit Tests** | 9400 | 9401 |
| **E2E Tests** | 9410 | 9411 |

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
├── wordpress-playground/     # Shared infrastructure
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
│   ├── playground-server.ts      # v6 E2E server (port 9410)
│   ├── playground-server-v7.ts   # v7 E2E server (port 9411)
│   ├── theme/                    # Custom theme with Timber
│   └── utils/                    # Test helpers
├── blocks/                       # 100+ test blocks
│   ├── components/               # InnerBlocks, RichText, etc.
│   ├── functions/                # assets, context, events
│   ├── overrides/                # group, tabs, repeater
│   ├── single/                   # native blocks, elements
│   └── types/                    # All field types
├── snapshots/                    # Snapshot data
└── test-helper.php               # PHP plugin for REST endpoints
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

```bash
# v7 Primary Tests
npm run playground:v7    # Start server
npm run test:v7          # Run tests (14 tests)

# v6 Reference Tests (baseline)
npm run playground       # Start server
npm test                 # Run tests

# Headed mode (debugging)
npm run test:headed

# Specific test file
npm run test:v7 -- tests/unit/build-snapshot.test.ts
```

## E2E Testing

E2E tests interact with the WordPress block editor through Playwright.

### Key Fixtures

```typescript
type E2EFixtures = {
  editor: FrameLocator;              // WordPress iframe
  resetBlocks: () => Promise<void>;  // Clear all blocks
};
```

### Important Playground Behaviors

1. **No Persistence on Reload** - Playground uses in-memory SQLite. Use `saveAndReload()` instead of page reload.

2. **Nested Iframe Structure** - WordPress runs inside `iframe#playground > iframe#wp`. Use `FrameLocator`, not `Page`.

3. **Button State Sync** - After `resetBlocks()`, UI buttons may show incorrect state. Helper functions include retry logic.

4. **Two-Step Publish** - WordPress shows pre-publish panel. The `save()` helper handles both clicks.

### Test Helper Functions

| Function | Purpose |
|----------|---------|
| `addBlock(editor, type)` | Opens inserter, searches, inserts block |
| `openBlockInserter(editor)` | Opens inserter with retry logic |
| `openSidebar(editor)` | Opens block inspector sidebar |
| `save(editor)` | Publishes/updates, handles pre-publish |
| `saveAndReload(editor)` | Saves, navigates away and back |
| `resetBlocks()` | Clears all blocks via data store |

## Compiled Assets Testing

In addition to Build metadata, we snapshot actual compiled output from `_dist/` directories:

- Compiled CSS from SCSS
- Generated JS files
- ES module bundles
- Scoped CSS output

This ensures the SCSS compilation pipeline doesn't change unexpectedly.

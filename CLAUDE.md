# Blockstudio 7 - Open Source Migration PRD

## Project Overview

**Blockstudio** is a WordPress block framework plugin that enables developers to create custom Gutenberg blocks using a filesystem-based approach. This document outlines the migration from a proprietary, legacy architecture to an open-source, modern singleton-based architecture following the sleek-* plugin patterns.

### Goals

1. **Open Source Release** - Make Blockstudio freely available under GPL2
2. **Architecture Modernization** - Migrate to singleton pattern (sleek-* style)
3. **Test-Driven Refactoring** - WordPress Playground integration tests
4. **Backend Focus** - Phase 1 focuses on PHP backend only (UI/packages later)
5. **Maintainability** - Clean, documented, testable codebase

### Non-Goals (Phase 1)

- UI/React component migration (packages/)
- New feature development
- Breaking API changes to public functions

---

## Current Architecture (v6.x)

```
blockstudio7/
├── blockstudio.php              # Entry point - direct class instantiation
├── includes/
│   ├── classes/                 # 23 PHP classes (no namespaces, no singletons)
│   │   ├── admin.php            # Admin → new \Jetstudio\Blockstudio\Admin()
│   │   ├── assets.php           # Assets pipeline
│   │   ├── block.php            # Block rendering
│   │   ├── blocks.php           # Gutenberg integration
│   │   ├── build.php            # Block discovery engine
│   │   └── ...
│   ├── functions/functions.php  # Public API
│   └── admin/                   # Assets and templates
├── vendor/                      # Scoped PHP dependencies
└── lib/                         # PHP Scoper output
```

### Current Issues

1. **No PSR-4 Autoloading** - Manual requires in blockstudio.php
2. **Legacy Namespace** - `Jetstudio\Blockstudio\` (company rename)
3. **No Singleton Pattern** - Classes instantiated directly
4. **Tight Coupling** - Classes depend on each other directly
5. **Hard to Test** - No dependency injection, global state
6. **No Unit Tests** - Only E2E tests exist

---

## Target Architecture (v7.x)

```
blockstudio/
├── blockstudio.php              # Entry point with PSR-4 autoloader
├── includes/
│   ├── Plugin.php               # Main singleton orchestrator
│   ├── classes/                 # Namespaced service classes
│   │   ├── Admin.php            # Blockstudio\Admin
│   │   ├── Assets.php           # Blockstudio\Assets
│   │   ├── Block.php            # Blockstudio\Block
│   │   ├── Blocks.php           # Blockstudio\Blocks
│   │   ├── Build.php            # Blockstudio\Build
│   │   ├── Register.php         # Blockstudio\Register
│   │   ├── Rest/                # Blockstudio\Rest\*
│   │   │   ├── Controller.php
│   │   │   ├── Blocks.php
│   │   │   └── ...
│   │   └── ...
│   ├── functions/
│   │   └── functions.php        # Public API (backwards compatible)
│   └── shared/                  # Shared utilities
├── tests/
│   ├── bootstrap.php            # Test bootstrap with WP stubs
│   ├── Unit/                    # Fast unit tests (no WP)
│   │   ├── BuildTest.php
│   │   ├── BlockTest.php
│   │   ├── AssetsTest.php
│   │   └── ...
│   ├── Integration/             # Tests with WP (slower)
│   │   ├── RegisterTest.php
│   │   ├── RestTest.php
│   │   └── ...
│   └── Fixtures/                # Test blocks and data
├── vendor/                      # Composer dependencies
├── composer.json                # With autoload + dev dependencies
└── phpunit.xml                  # PHPUnit configuration
```

### Singleton Pattern (sleek-* style)

```php
<?php
// blockstudio.php
const BLOCKSTUDIO_VERSION = '7.0.0';
const BLOCKSTUDIO_FILE = __FILE__;
const BLOCKSTUDIO_DIR = __DIR__;

if (!defined('ABSPATH')) {
    exit();
}

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Blockstudio\\';
    $baseDir = __DIR__ . '/includes/classes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/includes/Plugin.php';
require_once __DIR__ . '/includes/functions/functions.php';

use Blockstudio\Plugin;

function blockstudio(): Plugin {
    return Plugin::getInstance();
}

// Initialize
blockstudio();
```

```php
<?php
// includes/Plugin.php
namespace Blockstudio;

class Plugin {
    private static ?self $instance = null;

    private function __construct() {
        $this->init();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init(): void {
        $this->loadClasses();
        do_action('blockstudio/init', $this);
    }

    private function loadClasses(): void {
        new Settings();
        new Build();
        new Register();
        new Block();
        new Blocks();
        new Assets();
        new Admin();
        new Rest\Controller();
        new Tailwind();
        new Extensions();
        new Library();
        // ... other services
    }
}
```

---

## Testing Strategy

### Philosophy

**WordPress Playground Only.** All tests run in a real WordPress environment using WordPress Playground (WebAssembly). This provides true integration testing without mocking, ensuring tests reflect actual WordPress behavior.

### Design Principles

1. **CI-First** - Tests must run in GitHub Actions without local dependencies
2. **No Docker Required** - Use WordPress Playground (WebAssembly) instead of wp-env
3. **No Mocking** - Test against real WordPress, not mocked functions
4. **Test All Blocks** - Use the extensive test blocks in `tests/blocks/`

### Test Architecture

```
┌─────────────────────────────────────────────────────────┐
│              Express Server (localhost:9400)             │
│                          ↓                               │
│  ┌───────────────────────────────────────────────────┐  │
│  │           WordPress Playground (WebAssembly)       │  │
│  │                                                    │  │
│  │  • Plugin files loaded via /api/plugin-files      │  │
│  │  • Test blocks from tests/blocks/          │  │
│  │  • Plugin activated automatically                 │  │
│  │  • PHP assertions run via Playwright              │  │
│  └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### What to Test

| Area | Test Focus |
|------|------------|
| **Build Class** | All `get*()` methods - verify data returned for each test block |
| **Block Registration** | `WP_Block_Type_Registry::is_registered()` for all test blocks |
| **Block Rendering** | `render_block()` output for all template types (PHP, Twig, Blade) |
| **Assets Generation** | SCSS compilation, CSS/JS file generation |
| **REST API** | All REST endpoints respond correctly |

### Test Blocks

Over 100 test blocks available in `tests/blocks/`:

```
tests/blocks/
├── components/           # InnerBlocks, RichText, useBlockProps, MediaPlaceholder
├── functions/            # assets, context, events, init
├── overrides/            # group, tabs, repeater
├── single/               # native blocks, element blocks
└── types/                # All field types (text, number, repeater, select, etc.)
```

---

### Two Test Types

| Type | Focus | Status |
|------|-------|--------|
| **Unit Tests** | PHP code - Build class, block registration, rendering | Now |
| **E2E Tests** | UI - Block editor, inspector controls | Later |

Both use WordPress Playground. Unit tests call PHP functions via REST API. E2E tests use Playwright to interact with the UI.

---

### Snapshot Testing

**The primary testing strategy is snapshot testing.** We capture the exact output from all Build class methods and test that future runs produce identical data. This ensures the migration doesn't break anything.

**Build Class Methods Tested:**
- `blocks()` - All discovered blocks with their configurations
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

**How It Works:**

1. **Capture Snapshot** - Run `npx tsx tests/capture-snapshot.ts` with Playground running
2. **Snapshot Saved** - Data saved to `tests/snapshots/build-snapshot.json` (~4MB)
3. **Run Tests** - Each test compares fresh data against the snapshot
4. **Normalization** - Dynamic values (timestamps, hashes, scope URLs) are normalized before comparison

**Dynamic Values Normalized:**
- Playground scope URLs: `scope:0.1234567890` → `scope:NORMALIZED`
- Timestamps in filenames: `test-1769516162.js` → `test-TIMESTAMP.js`
- Content hashes: `style-10af90f280e9944d28a32c07649e0628.css` → `style-CONTENTHASH.css`
- Generated hash IDs: `blockstudio-49a9c898bab4` → `blockstudio-HASHID`
- Scoped class hashes: `bs-43849caa438e2447ef552c25a075ff08` → `bs-NORMALIZED_HASH`
- `mtime` fields → `NORMALIZED_MTIME`
- `key` timestamp fields → `NORMALIZED_KEY`

**Updating the Snapshot:**

When you intentionally change Build class output:
```bash
# Start Playground in one terminal
npm run playground

# Capture new snapshot in another terminal
npx tsx tests/capture-snapshot.ts

# Run tests to verify
npm test
```

---

### Unit Tests (WordPress Playground + REST API)

Test PHP functions directly by calling them through a test helper plugin that exposes REST endpoints.

**Why WordPress Playground?**
- No Docker required (runs in WebAssembly)
- No MySQL (uses SQLite in-memory)
- Fast startup (~2-3 seconds vs ~30s for wp-env)
- Works identically local and CI
- Same approach as sleek-* plugins
- Tests real WordPress, not mocked functions

**Test Infrastructure:**

```
tests/
├── wordpress-playground/         # Shared infrastructure (from sleek-*)
│   ├── fixtures.ts               # Playwright fixtures with WP frame
│   ├── global-setup.ts           # One-time Playground initialization
│   ├── playground-init.ts        # Express server factory
│   └── playwright-wordpress.config.ts
├── playground-server.ts          # Blockstudio server config
├── test-helper.php               # PHP plugin exposing test endpoints
├── unit/                         # Unit tests (PHP via REST)
│   ├── build.test.ts             # Build class tests
│   ├── register.test.ts          # Block registration tests
│   └── render.test.ts            # Block rendering tests
└── e2e/                          # E2E tests (later)
    └── editor.test.ts
```

**Test Helper Plugin (PHP):**

The test-helper.php plugin does two things:
1. **Configures Blockstudio** to find test blocks in the theme's `blockstudio/` directory
2. **Exposes REST endpoints** for testing PHP functions from Playwright

```php
<?php
// tests/test-helper.php
/**
 * Plugin Name: Blockstudio Test Helper
 * Description: Exposes test endpoints and configures test blocks
 */

// 1. Configure Blockstudio to find test blocks in theme directory
// Test blocks from tests/blocks/ are copied to:
// /wp-content/themes/{active-theme}/blockstudio/
add_filter('blockstudio/settings/blocks/paths', function ($paths) {
    $theme_blockstudio_path = get_stylesheet_directory() . '/blockstudio';
    if (is_dir($theme_blockstudio_path)) {
        $paths[] = $theme_blockstudio_path;
    }
    return $paths;
});

// 2. REST API endpoints for testing
add_action('rest_api_init', function() {
    // Get all blocks from Build class
    register_rest_route('blockstudio-test/v1', '/build/all', [
        'methods' => 'GET',
        'callback' => fn() => \Blockstudio\Build::getBlocks(),
        'permission_callback' => '__return_true',
    ]);

    // Get registered blocks from WP_Block_Type_Registry
    register_rest_route('blockstudio-test/v1', '/registered', [
        'methods' => 'GET',
        'callback' => function() {
            $registry = \WP_Block_Type_Registry::get_instance();
            $blocks = [];
            foreach ($registry->get_all_registered() as $name => $block) {
                if (str_starts_with($name, 'blockstudio/')) {
                    $blocks[$name] = [
                        'attributes' => $block->attributes ?? [],
                        'supports' => $block->supports ?? [],
                    ];
                }
            }
            return $blocks;
        },
        'permission_callback' => '__return_true',
    ]);

    // Render a block
    register_rest_route('blockstudio-test/v1', '/render', [
        'methods' => 'POST',
        'callback' => function($request) {
            return [
                'html' => render_block([
                    'blockName' => $request->get_param('blockName'),
                    'attrs' => $request->get_param('attrs') ?? [],
                    'innerHTML' => $request->get_param('innerHTML') ?? '',
                    'innerBlocks' => [],
                ]),
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    // Health check
    register_rest_route('blockstudio-test/v1', '/health', [
        'methods' => 'GET',
        'callback' => fn() => [
            'status' => 'ok',
            'blockstudio_loaded' => class_exists('Blockstudio\\Build'),
        ],
        'permission_callback' => '__return_true',
    ]);
});
```

**Unit Test Example (TypeScript):**

```typescript
// tests/unit/build.test.ts
import { test, expect } from '../wordpress-playground/fixtures';

test.describe('Build Class', () => {
    test('discovers all test blocks', async ({ wp }) => {
        const blocks = await wp.locator('body').evaluate(async () => {
            const res = await fetch('/wp-json/blockstudio-test/v1/build/all');
            return res.json();
        });

        // Should have many blocks from test-blocks/
        expect(Object.keys(blocks).length).toBeGreaterThan(50);
    });

    test('returns correct data for InnerBlocks block', async ({ wp }) => {
        const block = await wp.locator('body').evaluate(async () => {
            const res = await fetch('/wp-json/blockstudio-test/v1/build/blockstudio/components-innerblocks-default');
            return res.json();
        });

        expect(block.name).toBe('blockstudio/components-innerblocks-default');
        expect(block.fields).toBeDefined();
        expect(block.attributes).toBeDefined();
    });

    test('builds attributes from fields', async ({ wp }) => {
        const block = await wp.locator('body').evaluate(async () => {
            const res = await fetch('/wp-json/blockstudio-test/v1/build/blockstudio/types-text');
            return res.json();
        });

        // Check that text field was converted to attribute
        expect(block.attributes).toHaveProperty('blockstudio');
    });
});

test.describe('Block Registration', () => {
    test('all test blocks are registered', async ({ wp }) => {
        const blocks = await wp.locator('body').evaluate(async () => {
            const res = await fetch('/wp-json/blockstudio-test/v1/registered');
            return res.json();
        });

        const names = Object.keys(blocks);
        expect(names.length).toBeGreaterThan(50);
        expect(names).toContain('blockstudio/components-innerblocks-default');
        expect(names).toContain('blockstudio/types-repeater-default');
    });
});

test.describe('Block Rendering', () => {
    test('renders PHP template', async ({ wp }) => {
        const result = await wp.locator('body').evaluate(async () => {
            const res = await fetch('/wp-json/blockstudio-test/v1/render', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    blockName: 'blockstudio/single-native',
                    attrs: {},
                }),
            });
            return res.json();
        });

        expect(result.html).toBeDefined();
        expect(typeof result.html).toBe('string');
    });
});
```

**npm Scripts:**

```json
{
  "scripts": {
    "playground": "tsx tests/playground-server.ts",
    "test": "npx playwright test tests/unit/",
    "test:e2e": "npx playwright test tests/e2e/",
    "test:headed": "npx playwright test tests/unit/ --headed"
  }
}
```

---

## Migration Steps

### Phase 1: Test Infrastructure (Week 1)

1. **Setup Testing Environment**
   - [ ] Setup WordPress Playground infrastructure (from sleek-*)
   - [ ] Create test-helper.php plugin for REST endpoints
   - [ ] Configure Playwright for WordPress Playground
   - [ ] Use existing test blocks from `tests/blocks/`

2. **Write Unit Tests (Playwright + REST API)**
   - [ ] `build.test.ts` - Build class get methods for all test blocks
   - [ ] `register.test.ts` - Block registration verification
   - [ ] `render.test.ts` - Block rendering (PHP, Twig, Blade templates)
   - [ ] `assets.test.ts` - SCSS/CSS file generation
   - [ ] `rest.test.ts` - REST API endpoints

3. **Ensure All Tests Pass** with current codebase

### Phase 2: Architecture Migration (Week 2)

4. **Create New Structure**
   - [ ] Update composer.json with PSR-4 autoload
   - [ ] Create includes/Plugin.php singleton
   - [ ] Update blockstudio.php entry point

5. **Migrate Classes One by One**
   - [ ] Rename namespace `Jetstudio\Blockstudio` → `Blockstudio`
   - [ ] Add proper namespace declarations
   - [ ] Update class references
   - [ ] Run tests after each class migration

6. **Update Public API**
   - [ ] Ensure functions.php maintains backwards compatibility
   - [ ] Add `blockstudio()` helper function
   - [ ] Deprecate old function names (if any)

### Phase 3: Cleanup & Documentation (Week 3)

7. **Remove Legacy Code**
   - [ ] Remove old autoloader
   - [ ] Clean up unused files
   - [ ] Remove licensing/update code (open source)

8. **Documentation**
   - [ ] Update README.md for open source
   - [ ] Add CONTRIBUTING.md
   - [ ] Add LICENSE (GPL2)
   - [ ] Document public API

9. **Final Testing**
   - [ ] Run full test suite
   - [ ] Manual testing in WordPress
   - [ ] Test backwards compatibility

---

## Class Migration Checklist

| Current Class | New Class | Namespace | Tests |
|--------------|-----------|-----------|-------|
| `admin.php` | `Admin.php` | `Blockstudio\Admin` | [ ] |
| `assets.php` | `Assets.php` | `Blockstudio\Assets` | [ ] |
| `block.php` | `Block.php` | `Blockstudio\Block` | [ ] |
| `blocks.php` | `Blocks.php` | `Blockstudio\Blocks` | [ ] |
| `build.php` | `Build.php` | `Blockstudio\Build` | [ ] |
| `builder.php` | ~~Remove~~ | Deprecated | N/A |
| `configurator.php` | `Configurator.php` | `Blockstudio\Configurator` | [ ] |
| `esmodules.php` | `ESModules.php` | `Blockstudio\ESModules` | [ ] |
| `esmodulescss.php` | `ESModulesCss.php` | `Blockstudio\ESModulesCss` | [ ] |
| `examples.php` | `Examples.php` | `Blockstudio\Examples` | [ ] |
| `extensions.php` | `Extensions.php` | `Blockstudio\Extensions` | [ ] |
| `field.php` | `Field.php` | `Blockstudio\Field` | [ ] |
| `files.php` | `Files.php` | `Blockstudio\Files` | [ ] |
| `library.php` | `Library.php` | `Blockstudio\Library` | [ ] |
| `llm.php` | `LLM.php` | `Blockstudio\LLM` | [ ] |
| `migrate.php` | `Migrate.php` | `Blockstudio\Migrate` | [ ] |
| `populate.php` | `Populate.php` | `Blockstudio\Populate` | [ ] |
| `register.php` | `Register.php` | `Blockstudio\Register` | [ ] |
| `render.php` | `Render.php` | `Blockstudio\Render` | [ ] |
| `rest.php` | `Rest/Controller.php` | `Blockstudio\Rest\Controller` | [ ] |
| `settings.php` | `Settings.php` | `Blockstudio\Settings` | [ ] |
| `tailwind.php` | `Tailwind.php` | `Blockstudio\Tailwind` | [ ] |
| `utils.php` | `Utils.php` | `Blockstudio\Utils` | [ ] |

---

## Dependencies

### Production (PHP)
- PHP 8.2+
- WordPress 6.0+
- `scssphp/scssphp` ^2.0 (SCSS compilation)
- `matthiasmullie/minify` (JS/CSS minification)

### Development (PHP - composer.json)
- `squizlabs/php_codesniffer` ^3.7 (coding standards)

### Development (Node.js - package.json)
- `express` ^5.0 (test server)
- `tsx` ^4.0 (TypeScript execution)
- `@playwright/test` ^1.40 (testing framework)
- `typescript` ^5.0

---

## Commands

```bash
# Start Playground server (for development)
npm run playground

# Run unit tests (Playwright + WordPress Playground)
npm test

# Run tests in headed mode (for debugging)
npm run test:headed

# Check coding standards
composer cs

# Fix coding standards
composer cs:fix
```

---

## CI/CD Configuration

### GitHub Actions Workflow

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
    name: Unit Tests (WordPress Playground)
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install dependencies
        run: npm ci

      - name: Install Playwright browsers
        run: npx playwright install --with-deps chromium

      - name: Run Unit Tests
        run: npm test

      - name: Upload test results
        uses: actions/upload-artifact@v4
        if: always()
        with:
          name: playwright-report
          path: playwright-report/

  coding-standards:
    name: Coding Standards
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Check coding standards
        run: composer cs
```

### Test Speed Targets

| Test Suite | Target Time | Environment |
|------------|-------------|-------------|
| Unit Tests | < 30 seconds | Playwright + WP Playground |
| E2E Tests | < 60 seconds | Playwright + WP Playground (later) |

### Why WordPress Playground over wp-env?

| Aspect | wp-env (Docker) | WordPress Playground |
|--------|-----------------|---------------------|
| **CI Setup** | Docker-in-Docker complexity | Just Node.js |
| **Startup** | ~30 seconds | ~2-3 seconds |
| **Dependencies** | Docker daemon required | None (WebAssembly) |
| **Database** | MySQL container | SQLite in-memory |
| **Isolation** | Container-based | Complete (browser sandbox) |
| **Same as sleek-*** | No | Yes ✓ |

---

## Git Repository Setup

**Repository:** `github.com/inline0/blockstudio` (private initially)

```bash
# Initial setup
git init
git remote add origin git@github.com:inline0/blockstudio.git

# Branches
main        # Stable releases
develop     # Development branch
feature/*   # Feature branches
test/*      # Test development branches
```

**Initial Commit Structure:**
1. Current codebase with CLAUDE.md
2. Testing infrastructure
3. Unit tests (one commit per test file)
4. Architecture migration (one commit per class)
5. Final cleanup

---

## Success Criteria

### Phase 1 Complete When:
- [ ] All test blocks discovered and registered
- [ ] All unit tests pass (Build, Register, Render)
- [ ] Tests run in < 30 seconds

### Phase 2 Complete When:
- [ ] All classes migrated to new namespace
- [ ] Singleton pattern implemented
- [ ] PSR-4 autoloading working
- [ ] All tests still pass

### Phase 3 Complete When:
- [ ] Documentation complete
- [ ] LICENSE file added
- [ ] No proprietary code remaining
- [ ] Ready for public release

---

## Notes for Claude

When working on this project:

1. **Always run tests** after making changes
2. **One class at a time** - migrate and test incrementally
3. **Preserve backwards compatibility** - public functions must work
4. **Follow sleek-* patterns** - consistent with existing plugins
5. **Document decisions** - update this file as needed

### Key Files to Reference

- `/includes/classes/build.php` - Core block engine (most complex)
- `/includes/classes/block.php` - Rendering logic
- `/includes/classes/settings.php` - Already has singleton-like pattern
- `/includes/functions/functions.php` - Public API to preserve

### Testing Commands

```bash
# Quick test run
./vendor/bin/phpunit --testsuite unit

# Specific test file
./vendor/bin/phpunit tests/Unit/BuildTest.php

# With verbose output
./vendor/bin/phpunit --testsuite unit -v
```

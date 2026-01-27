# Blockstudio 7 - Open Source Migration PRD

## Project Overview

**Blockstudio** is a WordPress block framework plugin that enables developers to create custom Gutenberg blocks using a filesystem-based approach. This document outlines the migration from a proprietary, legacy architecture to an open-source, modern singleton-based architecture following the sleek-* plugin patterns.

### Goals

1. **Open Source Release** - Make Blockstudio freely available under GPL2
2. **Architecture Modernization** - Migrate to singleton pattern (sleek-* style)
3. **Test-Driven Refactoring** - Write comprehensive unit tests FIRST, then refactor
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

**Write tests FIRST before any refactoring.** The test suite becomes the safety net that ensures functionality is preserved during migration.

### Design Principles

1. **CI-First** - Tests must run in GitHub Actions without local dependencies
2. **No Docker Required** - Use WordPress Playground (WebAssembly) instead of wp-env
3. **Fast Feedback** - Unit tests run in seconds, integration in under a minute
4. **No E2E for Now** - Focus on PHP testing, UI testing comes later

### Test Pyramid

```
        /\
       /  \     E2E Tests (later - not in Phase 1)
      /----\
     /      \   Integration Tests (WordPress Playground, ~40%)
    /--------\
   /          \ Unit Tests (Brain Monkey, ~60%)
  /------------\
```

### Two-Tier Testing Approach

| Tier | Tool | Tests | Speed |
|------|------|-------|-------|
| **Unit** | Brain Monkey | Pure PHP logic, no WP classes needed | ~5ms/test |
| **Integration** | WordPress Playground | WP classes (`WP_Block_Type_Registry`, REST API) | ~50ms/test |

---

### Unit Tests (Brain Monkey)

Fast tests that mock WordPress **functions**. For pure PHP logic that doesn't need real WP classes.

**What to Unit Test:**

| Class | Test Focus |
|-------|------------|
| `Build` | JSON parsing, attribute building from fields |
| `Assets` | CSS/JS string parsing, SCSS compilation |
| `Settings` | Configuration get/set, defaults |
| `Utils` | Utility functions, path helpers |
| `Field` | Field extraction, grouping logic |
| `ESModules` | URL transformation, package resolution |
| `Tailwind` | Class extraction |

**Example Unit Test:**

```php
<?php
namespace Blockstudio\Tests\Unit;

use Blockstudio\Build;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BuildTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_builds_attributes_from_fields(): void {
        $fields = [
            ['name' => 'title', 'type' => 'text', 'default' => 'Hello'],
            ['name' => 'count', 'type' => 'number', 'default' => 5],
        ];

        $build = new Build();
        $attributes = $build->buildAttributes($fields);

        $this->assertEquals('string', $attributes['title']['type']);
        $this->assertEquals('Hello', $attributes['title']['default']);
    }
}
```

---

### Integration Tests (WordPress Playground)

Tests that need real WordPress classes like `WP_Block_Type_Registry`, `WP_REST_Server`, etc.

**Why WordPress Playground?**
- No Docker required (runs in WebAssembly)
- No MySQL (uses SQLite in-memory)
- Fast startup (~2-3 seconds vs ~30s for wp-env)
- Works identically local and CI
- Same approach as sleek-* plugins

**What to Integration Test:**

| Class | Test Focus |
|-------|------------|
| `Register` | `WP_Block_Type_Registry::is_registered()` |
| `Block` | `render_block()` output |
| `Rest` | REST API endpoints, responses |
| `Blocks` | Block editor script enqueuing |

**Architecture (based on sleek-* plugins):**

```
┌─────────────────────────────────────────────────────────┐
│              Express Server (localhost:9400)             │
│                          ↓                               │
│  ┌───────────────────────────────────────────────────┐  │
│  │           WordPress Playground (WebAssembly)       │  │
│  │                                                    │  │
│  │  • Plugin files loaded via /api/plugin-files      │  │
│  │  • Plugin activated automatically                 │  │
│  │  • PHP assertions run via client.run()           │  │
│  └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

**Test Infrastructure:**

```
tests/
├── bootstrap.php                 # Brain Monkey setup for unit tests
├── Unit/                         # Unit tests (PHPUnit + Brain Monkey)
│   ├── BuildTest.php
│   ├── AssetsTest.php
│   └── ...
├── Integration/                  # Integration tests
│   ├── playground-server.ts      # Express server for WP Playground
│   ├── run-php-tests.ts          # Runs PHP assertions in Playground
│   ├── RegisterTest.php          # PHP test file (loaded into Playground)
│   ├── RestTest.php
│   └── ...
└── Fixtures/                     # Test blocks and data
    └── blocks/
```

**Playground Server (TypeScript):**

```typescript
// tests/Integration/playground-server.ts
import { createPlaygroundServer } from './wordpress-playground';
import { join } from 'path';

createPlaygroundServer({
  port: 9400,
  pluginPath: join(__dirname, '../..'),
  pluginSlug: 'blockstudio',
  pluginMainFile: 'blockstudio.php',
  title: 'Blockstudio - Test Environment',
});
```

**Running PHP Tests in Playground:**

```typescript
// tests/Integration/run-php-tests.ts
import { startPlaygroundWeb } from '@wp-playground/client';

const client = await startPlaygroundWeb({ /* config */ });

// Run PHP assertion
const result = await client.run({
  code: `<?php
    require_once '/wordpress/wp-load.php';

    // Test block registration
    $registered = WP_Block_Type_Registry::get_instance()
      ->is_registered('blockstudio/test-block');

    echo json_encode([
      'test' => 'block_registration',
      'passed' => $registered === true,
      'message' => $registered ? 'Block registered' : 'Block NOT registered'
    ]);
  `
});

console.log(JSON.parse(result.text));
```

**Example Integration Test (PHP file loaded into Playground):**

```php
<?php
// tests/Integration/RegisterTest.php
// This file is loaded and executed inside WordPress Playground

require_once '/wordpress/wp-load.php';

$results = [];

// Test 1: Block is registered
$results[] = [
    'test' => 'block_is_registered',
    'passed' => WP_Block_Type_Registry::get_instance()->is_registered('blockstudio/test-block'),
];

// Test 2: Block has correct attributes
$block = WP_Block_Type_Registry::get_instance()->get_registered('blockstudio/test-block');
$results[] = [
    'test' => 'block_has_title_attribute',
    'passed' => isset($block->attributes['title']),
];

// Test 3: Block renders correctly
$html = render_block([
    'blockName' => 'blockstudio/test-block',
    'attrs' => ['title' => 'Hello World'],
]);
$results[] = [
    'test' => 'block_renders_title',
    'passed' => strpos($html, 'Hello World') !== false,
];

// Output results as JSON
echo json_encode(['results' => $results]);
```

**npm Scripts:**

```json
{
  "scripts": {
    "test:unit": "composer test:unit",
    "test:integration": "tsx tests/Integration/run-tests.ts",
    "test:integration:server": "tsx tests/Integration/playground-server.ts",
    "test": "npm run test:unit && npm run test:integration"
  }
}
```

### Test Fixtures

```
tests/Fixtures/
├── blocks/
│   ├── test-block/
│   │   ├── block.json
│   │   ├── index.php
│   │   ├── style.scss
│   │   └── script.js
│   ├── nested-fields/
│   │   ├── block.json         # Block with repeaters, groups
│   │   └── index.php
│   └── blade-block/
│       ├── block.json
│       └── index.blade.php
├── config/
│   └── blockstudio.json       # Test configuration
└── data/
    ├── attributes.json        # Sample attribute data
    └── field-definitions.json # Sample field definitions
```

---

## Migration Steps

### Phase 1: Test Infrastructure (Week 1)

1. **Setup Testing Environment**
   - [ ] Add PHPUnit, Brain Monkey, Mockery to composer.json
   - [ ] Create phpunit.xml configuration
   - [ ] Create tests/bootstrap.php with WP function stubs
   - [ ] Create test fixtures (sample blocks, configs)

2. **Write Unit Tests for Core Classes**
   - [ ] `BuildTest.php` - Block discovery and attribute building
   - [ ] `BlockTest.php` - Rendering and transformation
   - [ ] `AssetsTest.php` - Asset pipeline
   - [ ] `SettingsTest.php` - Configuration management
   - [ ] `UtilsTest.php` - Utility functions
   - [ ] `FieldTest.php` - Field extraction
   - [ ] `PopulateTest.php` - Option population
   - [ ] `ESModulesTest.php` - Module URL transformation
   - [ ] `TailwindTest.php` - CSS class handling
   - [ ] `ExtensionsTest.php` - Extension system

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
- `phpunit/phpunit` ^10.0
- `brain/monkey` ^2.6 (WordPress function mocking)
- `mockery/mockery` ^1.6
- `squizlabs/php_codesniffer` ^3.7 (coding standards)

### Development (Node.js - package.json)
- `express` ^5.0 (test server)
- `tsx` ^4.0 (TypeScript execution)
- `@wp-playground/client` (WordPress Playground API)
- `typescript` ^5.0

---

## Commands

```bash
# Run all tests
npm test

# Run unit tests only (fast, PHP + Brain Monkey)
composer test:unit

# Run integration tests (Node.js + WordPress Playground)
npm run test:integration

# Start Playground server manually (for debugging)
npm run test:integration:server

# Run tests with coverage
composer test:coverage

# Check coding standards
composer cs

# Fix coding standards
composer cs:fix

# Static analysis
composer analyse
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
  unit:
    name: Unit Tests (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ['8.2', '8.3']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: xdebug

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run Unit Tests
        run: composer test:unit

      - name: Upload coverage
        uses: codecov/codecov-action@v4
        if: matrix.php == '8.2'
        with:
          files: ./coverage.xml

  integration:
    name: Integration Tests (WordPress Playground)
    runs-on: ubuntu-latest
    needs: unit  # Only run if unit tests pass

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: |
          composer install --prefer-dist --no-progress
          npm ci

      - name: Run Integration Tests
        run: npm run test:integration

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
| Unit | < 10 seconds | PHP + Brain Monkey |
| Integration | < 30 seconds | Node.js + WP Playground |
| Full Suite | < 60 seconds | CI |

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
- [ ] 80%+ code coverage on core classes
- [ ] All unit tests pass
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

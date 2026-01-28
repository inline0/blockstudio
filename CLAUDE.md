# Blockstudio 7 - Open Source Migration PRD

## Project Overview

**Blockstudio** is a WordPress block framework plugin that enables developers to create custom Gutenberg blocks using a filesystem-based approach. This document outlines the migration from a proprietary, legacy architecture to an open-source, modern singleton-based architecture following **100% WordPress Coding Standards** (for potential WordPress.org submission).

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
├── blockstudio.php              # v6 entry point (unchanged)
├── blockstudio-v7.php           # v7 entry point (WP coding standards)
├── includes/                    # v6 code (unchanged during migration)
│   └── classes/                 # Original classes
├── includes-v7/                 # v7 code (WordPress coding standards)
│   ├── class-plugin.php         # Main singleton orchestrator
│   ├── classes/                 # Classes being migrated one-by-one
│   │   ├── admin.php            # Blockstudio\Admin (to migrate)
│   │   ├── build.php            # Blockstudio\Build (to migrate)
│   │   └── ...
│   ├── functions/
│   │   └── functions.php        # Public API (backwards compatible)
│   └── admin/                   # Admin assets and templates
├── tests/
│   ├── wordpress-playground/    # Shared test infrastructure
│   ├── playground-server.ts     # v6 test server (port 9400)
│   ├── playground-server-v7.ts  # v7 test server (port 9401)
│   ├── test-helper.php          # PHP plugin exposing test endpoints
│   ├── unit/                    # Snapshot tests
│   │   ├── build-snapshot.test.ts
│   │   └── compiled-assets.test.ts
│   └── snapshots/               # Snapshot data
│       ├── build-snapshot.json
│       └── compiled-assets.json
├── vendor/                      # Composer dependencies
├── composer.json                # With WPCS dev dependencies
└── phpcs.xml                    # WordPress coding standards config
```

### Singleton Pattern (WordPress Coding Standards)

**All v7 code must follow 100% WordPress Coding Standards** - no exclusions. This ensures potential submission to WordPress.org.

```php
<?php
/**
 * Plugin Name: Blockstudio
 * Version: 7.0.0
 *
 * @package Blockstudio
 */

// blockstudio-v7.php - Loading guard prevents conflicts with v6.
if ( defined( 'BLOCKSTUDIO_VERSION' ) ) {
	return;
}

define( 'BLOCKSTUDIO_VERSION', '7.0.0' );
define( 'BLOCKSTUDIO_FILE', __FILE__ );
define( 'BLOCKSTUDIO_DIR', __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// PSR-4 Autoloader (with WP spacing).
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'Blockstudio\\';
		$base_dir = __DIR__ . '/includes-v7/classes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

require_once BLOCKSTUDIO_DIR . '/vendor/autoload.php';
require_once __DIR__ . '/includes-v7/class-plugin.php';
require_once __DIR__ . '/includes-v7/functions/functions.php';

/**
 * Get the Plugin instance.
 *
 * @return \Blockstudio\Plugin
 */
function blockstudio(): \Blockstudio\Plugin {
	return \Blockstudio\Plugin::get_instance();
}

blockstudio();
```

```php
<?php
/**
 * Main Plugin class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Plugin singleton class.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {  // Yoda condition.
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 */
	private function init(): void {
		$this->load_classes();
		do_action( 'blockstudio_init', $this );  // Underscore hooks, not slashes.
	}

	/**
	 * Load all plugin classes.
	 */
	private function load_classes(): void {
		new Settings();
		new Build();
		// ... other services
	}
}
```

**WordPress Coding Standards Requirements:**
- Tabs for indentation (not spaces)
- Spaces inside parentheses: `if ( $condition )`
- Yoda conditions: `null === self::$instance`
- Snake_case for methods: `get_instance()` not `getInstance()`
- Snake_case for variables: `$class_name` not `$className`
- File naming: `class-plugin.php` not `Plugin.php`
- Hook naming: `blockstudio_init` not `blockstudio/init`
- Proper docblocks with `@package`, `@var`, `@param`, `@return`

---

## V7 Class Architecture

### Key Architectural Changes from V6

| Aspect | V6 (includes/) | V7 (includes-v7/) |
|--------|----------------|-------------------|
| **State Storage** | Static properties on Build class | Block_Registry singleton |
| **Build::init()** | ~700 line monolithic function | Orchestrator delegating to focused classes |
| **File Discovery** | Inline in Build::init() | Block_Discovery class |
| **Method Names** | camelCase (`getData()`) | snake_case (`get_data()`) |
| **String Functions** | Custom helpers (`Files::endsWith()`) | PHP 8.0+ built-ins (`str_ends_with()`) |

### New V7 Classes

```
includes-v7/classes/
├── block-registry.php      # Singleton state store (replaces static properties)
├── block-discovery.php     # File discovery and classification
├── asset-discovery.php     # Asset discovery and processing
├── block-registrar.php     # WordPress block registration
├── file-classifier.php     # File type classification
├── attribute-builder.php   # Builds block attributes from fields
├── constants.php           # Centralized configuration constants
├── error-handler.php       # Centralized error handling
├── field-handlers/         # Strategy pattern for field types
│   ├── field-handler-interface.php
│   ├── text-field-handler.php
│   ├── number-field-handler.php
│   ├── boolean-field-handler.php
│   ├── select-field-handler.php
│   ├── media-field-handler.php
│   └── container-field-handler.php
└── settings/               # Settings loaders
    ├── options-loader.php
    ├── json-loader.php
    └── filter-loader.php
```

### Block_Registry Singleton

Replaces all static properties from v6 Build class:

```php
// V6 - Static properties on Build class
Build::$blocks[]
Build::$data[]
Build::$extensions[]
Build::$files[]
Build::$assetsAdmin[]
// ... etc

// V7 - Block_Registry singleton
Block_Registry::instance()->get_blocks()
Block_Registry::instance()->get_data()
Block_Registry::instance()->get_extensions()
Block_Registry::instance()->get_files()
Block_Registry::instance()->get_assets_admin()
// ... etc
```

Public API is preserved - Build class getter methods delegate to Block_Registry:

```php
// Build::blocks() now delegates
public static function blocks(): array {
    return Block_Registry::instance()->get_blocks();
}
```

### Build::init() Orchestration

V6 had everything inline (~700 lines). V7 decomposes into phases:

```php
public static function init( $args = false ) {
    $registry = Block_Registry::instance();

    // Phase 1: Discover blocks using Block_Discovery
    $discovery = new Block_Discovery();
    $results   = $discovery->discover( $path, $instance, $library, $editor );

    // Phase 2: Process assets for each discovered item
    foreach ( $store as $name => &$data ) {
        self::process_block_assets( $data, $name, $instance, $editor, $registry );
    }

    // Phase 3: Register blocks with WordPress
    foreach ( $registerable as $name => $item ) {
        self::register_block_type( $item['data'], $item['block_json'], ... );
    }

    // Phase 4: Apply overrides
    self::apply_overrides( $registry );
}
```

### Block_Discovery Class

Handles all file iteration and classification:

```php
$discovery = new Block_Discovery();
$results = $discovery->discover( $path, $instance, $library, $editor );

// Returns:
[
    'store'           => [...],  // All discovered items
    'registerable'    => [...],  // Items needing WP registration
    'blade_templates' => [...],  // Blade template mappings
    'overrides'       => [...],  // Override blocks
]
```

### Method Name Mapping (V6 → V7)

All public methods renamed to snake_case for WordPress Coding Standards:

| V6 Method | V7 Method |
|-----------|-----------|
| `Build::getBlocks()` | `Build::blocks()` |
| `Build::getData()` | `Build::data()` |
| `Build::dataSorted()` | `Build::data_sorted()` |
| `Build::assetsAdmin()` | `Build::assets_admin()` |
| `Build::assetsBlockEditor()` | `Build::assets_block_editor()` |
| `Build::assetsGlobal()` | `Build::assets_global()` |
| `Build::isTailwindActive()` | `Build::is_tailwind_active()` |
| `Build::buildAttributes()` | `Build::build_attributes()` |
| `Build::filterAttributes()` | `Build::filter_attributes()` |
| `Build::mergeAttributes()` | `Build::merge_attributes()` |
| `Build::getInstanceName()` | `Build::get_instance_name()` |
| `Build::getBuildDir()` | `Build::get_build_dir()` |

### Verification

All 14 snapshot tests verify v7 produces identical output to v6:

```bash
npm run test:v7  # Runs against includes-v7/
npm test         # Runs against includes/ (v6 reference)
```

Both must pass with identical snapshots, ensuring no functional regressions.

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
| **Unit Tests** | PHP code - Build class, block registration, rendering | Active |
| **E2E Tests** | UI - Block editor, inspector controls, field interactions | Active |

Both use WordPress Playground. Unit tests call PHP functions via REST API. E2E tests use Playwright to interact with the Gutenberg editor UI.

---

### E2E Testing Infrastructure

E2E tests interact with the WordPress block editor through Playwright, testing actual user workflows like adding blocks, editing fields, and saving posts.

#### Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Playwright Test Runner                            │
│                            ↓                                         │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │              Express Server (localhost:9410)                    │ │
│  │  • Serves plugin files via /api/plugin-files                   │ │
│  │  • Serves test blocks via /api/test-blocks                     │ │
│  │  • Serves test theme via /api/test-theme                       │ │
│  │  • Serves test helper plugin via /api/test-helper-plugin       │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                            ↓                                         │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │           WordPress Playground (WebAssembly)                    │ │
│  │                                                                 │ │
│  │  ┌──────────────────────────────────────────────────────────┐  │ │
│  │  │  iframe#playground → iframe#wp (nested iframe structure)  │  │ │
│  │  │                                                           │  │ │
│  │  │  • Custom theme with Timber/Twig loaded                  │  │ │
│  │  │  • Test blocks in theme's blockstudio/ directory         │  │ │
│  │  │  • Welcome guide disabled via user preferences           │  │ │
│  │  │  • Shared page instance across serial tests              │  │ │
│  │  └──────────────────────────────────────────────────────────┘  │ │
│  └────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

#### Key Files

```
tests/e2e/
├── playground-server.ts      # Express server config for E2E (port 9410)
├── playground-server-v7.ts   # v7 server config (port 9411)
├── theme/                    # Custom WordPress theme with Timber
│   ├── style.css             # Theme header
│   ├── functions.php         # Loads Timber autoloader
│   ├── composer.json         # Timber dependency
│   └── vendor/               # Timber/Twig (installed via composer)
├── utils/
│   ├── fixtures.ts           # Playwright fixtures (editor, resetBlocks)
│   └── playwright-utils.ts   # Test helper functions
└── types/                    # Field type tests
    └── number.ts             # Example: number field tests
```

#### Playwright Fixtures

The `fixtures.ts` file provides two key fixtures:

```typescript
type E2EFixtures = {
  editor: FrameLocator;        // WordPress iframe - already in block editor
  resetBlocks: () => Promise<void>;  // Clear all blocks between tests
};
```

**`editor`** - The WordPress iframe inside Playground, navigated to the block editor. Tests run serially sharing a single page instance to avoid repeated Playground initialization (~40s startup).

**`resetBlocks`** - Clears all blocks using `wp.data.dispatch("core/block-editor").resetBlocks([])`. Use between test groups to start fresh.

#### Important Playground Behaviors

1. **No Persistence on Reload** - Playground runs in WebAssembly with in-memory SQLite. Page reloads reset the entire WordPress state. Use `saveAndReload()` which navigates to the posts list and back instead of reloading.

2. **Nested Iframe Structure** - WordPress runs inside `iframe#playground > iframe#wp`. All locators must use `FrameLocator`, not `Page`.

3. **Button State Synchronization** - After `resetBlocks()`, UI buttons (like the block inserter toggle) may show incorrect `aria-pressed` state. The `openBlockInserter()` function handles this with retries.

4. **Welcome Guide Modal** - Disabled via WordPress user preferences in `playground-init.ts`. The `closeModals()` function handles any modals that appear.

5. **Two-Step Publish Flow** - WordPress shows a pre-publish panel before publishing. The `save()` function handles both the initial click and the confirmation.

#### Test Helper Functions

| Function | Purpose |
|----------|---------|
| `addBlock(editor, type)` | Opens inserter, searches, and inserts a block |
| `openBlockInserter(editor)` | Opens the block inserter with retry logic |
| `openSidebar(editor)` | Opens the block inspector sidebar |
| `save(editor)` | Publishes/updates post, handles pre-publish panel |
| `saveAndReload(editor)` | Saves, navigates to posts list, returns to post |
| `resetBlocks()` | Clears all blocks via WordPress data store |
| `click(editor, selector)` | Clicks an element |
| `fill(editor, selector, value)` | Fills an input |
| `text(editor, value)` | Waits for text to appear in the editor |
| `count(editor, selector, n)` | Asserts element count |
| `delay(ms)` | Waits for specified milliseconds |

#### Writing Field Type Tests

Use the `testType()` helper to create standardized test suites:

```typescript
// tests/e2e/types/number.ts
import { testType, click, saveAndReload, expect, locator } from "../utils/playwright-utils";

testType("number", '"number":10,"numberZero":0', () => {
  return [
    {
      description: "change number",
      testFunction: async (editor) => {
        await click(editor, '[data-type="blockstudio/type-number"]');
        await editor.locator(".blockstudio-fields__field--number input").first().fill("100");
        await saveAndReload(editor);
      },
    },
    {
      description: "check number",
      testFunction: async (editor) => {
        await click(editor, '[data-type="blockstudio/type-number"]');
        await expect(
          locator(editor, ".blockstudio-fields__field--number input").nth(0)
        ).toHaveValue("100");
      },
    },
  ];
});
```

The `testType()` helper automatically creates:
- **defaults** test group: add block, check defaults, remove block
- **fields/outer** test group: block in root container
- **fields/inner** test group: block inside InnerBlocks

#### Running E2E Tests

```bash
# Run all E2E tests
npm run test:e2e

# Run specific test file
npm run test:e2e -- tests/e2e/types/number.ts

# Run with headed browser (for debugging)
npm run test:e2e:headed

# Run v7 E2E tests
npm run test:e2e:v7
```

#### Ports

| Test Type | v6 Port | v7 Port |
|-----------|---------|---------|
| Unit Tests | 9400 | 9401 |
| E2E Tests | 9410 | 9411 |

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

### Compiled Assets Testing

In addition to Build class metadata, we also snapshot the **actual compiled output** from `_dist/` directories. This tests the SCSS compilation pipeline and ensures generated CSS/JS doesn't change unexpectedly.

**What's Tested:**
- Compiled CSS from SCSS source files
- Generated JS files
- ES module bundles in `_dist/modules/`
- Scoped CSS output

**Snapshot Location:** `tests/snapshots/compiled-assets.json`

**Updating the Compiled Assets Snapshot:**

```bash
# Start Playground
npm run playground

# Capture compiled assets
npx tsx tests/capture-compiled-assets.ts

# Run tests
npm test
```

**Why This Matters:**
- SCSS compilation depends on scssphp library
- Migration could break compilation if library versions change
- Ensures CSS scoping works correctly
- Catches any changes to minification/bundling

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

## v7 Class Migration Workflow

Classes are migrated **one at a time** from `includes-v7/classes/` to WordPress Coding Standards. The workflow:

### 1. Current State

- `includes-v7/classes/` contains copied v6 classes (not yet WP compliant)
- `phpcs.xml` only checks migrated files (currently `blockstudio-v7.php` and `class-plugin.php`)
- v7 tests pass using the copied classes

### 2. Migration Process (Per Class)

```bash
# 1. Pick a class to migrate (e.g., build.php)
# 2. Add it to phpcs.xml
<file>./includes-v7/classes/build.php</file>

# 3. Run PHPCS to see all issues
composer phpcs

# 4. Fix issues (use phpcbf for auto-fixable ones)
composer phpcbf

# 5. Manually fix remaining issues:
#    - Snake_case methods: getData() → get_data()
#    - Yoda conditions: $x === null → null === $x
#    - Proper docblocks

# 6. Run v7 tests to ensure nothing broke
npm run test:v7

# 7. Commit the migrated class
git add includes-v7/classes/build.php phpcs.xml
git commit -m "Migrate Build class to WordPress coding standards"
```

### 3. phpcs.xml Configuration

```xml
<?xml version="1.0"?>
<ruleset name="Blockstudio">
    <description>WordPress Coding Standards for Blockstudio v7</description>

    <!-- Only check migrated v7 code -->
    <file>./blockstudio-v7.php</file>
    <file>./includes-v7/class-plugin.php</file>
    <!-- Add each class as it's migrated -->

    <!-- Exclude vendor and node_modules -->
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>

    <!-- Use WordPress coding standards -->
    <rule ref="WordPress"/>

    <!-- PHP 8.2+ -->
    <config name="testVersion" value="8.2-"/>

    <!-- Prefix for hooks, functions, classes -->
    <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
        <properties>
            <property name="prefixes" type="array">
                <element value="blockstudio"/>
                <element value="Blockstudio"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

### 4. Migration Priority

Start with classes that have fewer dependencies:

1. **Settings** - Configuration, minimal dependencies
2. **Utils** - Utility functions
3. **Build** - Core engine, many methods
4. **Block** - Block rendering
5. **Register** - Block registration
6. **Assets** - Asset pipeline
7. **Admin** - Admin interface
8. **Rest** - REST API endpoints

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
# Start v6 Playground server (port 9400)
npm run playground

# Start v7 Playground server (port 9401)
npm run playground:v7

# Run v6 tests (against includes/)
npm test

# Run v7 tests (against includes-v7/)
npm run test:v7

# Run tests in headed mode (for debugging)
npm run test:headed

# Capture new snapshots (when Build output changes intentionally)
npm run playground          # Start server first
npx tsx tests/capture-snapshot.ts
npx tsx tests/capture-compiled-assets.ts

# Check WordPress coding standards (v7 code only)
composer phpcs

# Fix coding standards automatically
composer phpcbf
```

### Dual Test Environment

Both v6 and v7 code can be tested independently:

| Version | Entry Point | Directory | Port | Command |
|---------|-------------|-----------|------|---------|
| v6 | `blockstudio.php` | `includes/` | 9400 | `npm test` |
| v7 | `blockstudio-v7.php` | `includes-v7/` | 9401 | `npm run test:v7` |

This allows side-by-side comparison during migration. Both test suites run the same 14 snapshot tests.

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

1. **Always run tests** after making changes (`npm test` for v6, `npm run test:v7` for v7)
2. **One class at a time** - migrate and test incrementally
3. **100% WordPress Coding Standards** - no exclusions, no exceptions
4. **Preserve backwards compatibility** - public functions must work
5. **Document decisions** - update this file as needed
6. **NEVER touch `includes/` directory** - This is the v6 reference code. Only modify files in `includes-v7/`. The `includes/` directory exists as a reference implementation and must remain unchanged.

### WordPress Coding Standards Checklist

When migrating a class:
- [ ] Tabs for indentation
- [ ] Spaces inside parentheses: `if ( $x )` not `if ($x)`
- [ ] Yoda conditions: `null === $var` not `$var === null`
- [ ] Snake_case for methods and variables
- [ ] Proper docblocks with `@param`, `@return`, `@var`
- [ ] File header with `@package Blockstudio`
- [ ] Add file to `phpcs.xml` and verify `composer phpcs` passes
- [ ] Run `npm run test:v7` to verify tests pass

### Key Files to Reference

- `/includes-v7/class-plugin.php` - Main singleton (WP compliant example)
- `/blockstudio-v7.php` - Entry point (WP compliant example)
- `/includes-v7/classes/build.php` - Core block engine (to migrate)
- `/phpcs.xml` - Add files here as they're migrated

### Testing Commands

```bash
# Run v6 tests (original code)
npm test

# Run v7 tests (migrated code)
npm run test:v7

# Check coding standards
composer phpcs

# Auto-fix coding standards
composer phpcbf
```

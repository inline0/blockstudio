# Architecture

## Entry Point

The plugin loads via `blockstudio.php`:

```php
<?php
/**
 * Plugin Name: Blockstudio
 * Version: 7.0.0
 *
 * @package Blockstudio
 */

// Prevent double-loading
if ( defined( 'BLOCKSTUDIO_VERSION' ) ) {
    return;
}

define( 'BLOCKSTUDIO_VERSION', '7.0.0' );
define( 'BLOCKSTUDIO_FILE', __FILE__ );
define( 'BLOCKSTUDIO_DIR', __DIR__ );

// PSR-4 style autoloader
spl_autoload_register( function ( $class_name ) {
    $prefix   = 'Blockstudio\\';
    $base_dir = __DIR__ . '/includes/classes/';
    // ... load class files
});

require_once BLOCKSTUDIO_DIR . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/functions/functions.php';

function blockstudio(): \Blockstudio\Plugin {
    return \Blockstudio\Plugin::get_instance();
}

blockstudio();
```

## Singleton Pattern

The main `Plugin` class uses the singleton pattern:

```php
namespace Blockstudio;

class Plugin {
    private static ?Plugin $instance = null;

    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_classes();
        $this->init();
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}
```

## Class Organization

```
includes/
├── class-plugin.php              # Main singleton orchestrator
├── classes/
│   ├── build.php                 # Block discovery engine (core)
│   ├── block.php                 # Block rendering
│   ├── block-registry.php        # Singleton state store
│   ├── block-discovery.php       # File iteration and classification
│   ├── asset-discovery.php       # Asset processing
│   ├── block-registrar.php       # WordPress block registration
│   ├── attribute-builder.php     # Builds block attributes from fields
│   ├── file-classifier.php       # File type classification
│   ├── constants.php             # Configuration constants
│   ├── error-handler.php         # Error handling
│   ├── settings.php              # Plugin settings
│   ├── admin.php                 # Admin interface
│   ├── assets.php                # Asset pipeline
│   ├── rest.php                  # REST API endpoints
│   ├── field-handlers/           # Strategy pattern for field types
│   │   ├── abstract-field-handler.php
│   │   ├── text-field-handler.php
│   │   ├── number-field-handler.php
│   │   └── ...
│   └── settings-loaders/         # Settings loading strategies
│       ├── options-loader.php
│       ├── json-loader.php
│       └── filter-loader.php
├── interfaces/
│   ├── field-handler-interface.php
│   └── settings-loader-interface.php
└── functions/
    └── functions.php             # Public API (backwards compatible)
```

## Block_Registry Singleton

Replaces static properties from v6's Build class:

```php
// v6 - Static properties scattered across Build class
Build::$blocks[]
Build::$data[]
Build::$extensions[]
Build::$files[]

// v7 - Centralized in Block_Registry singleton
Block_Registry::instance()->get_blocks()
Block_Registry::instance()->get_data()
Block_Registry::instance()->get_extensions()
Block_Registry::instance()->get_files()
```

The public API is preserved - Build class methods delegate to Block_Registry:

```php
class Build {
    public static function blocks(): array {
        return Block_Registry::instance()->get_blocks();
    }
}
```

## Build::init() Decomposition

v6 had a ~700 line monolithic `init()` function. v7 decomposes it into phases:

```php
public static function init( $args = false ) {
    $registry = Block_Registry::instance();

    // Phase 1: Discover blocks
    $discovery = new Block_Discovery();
    $results = $discovery->discover( $path, $instance, $library, $editor );

    // Phase 2: Process assets
    foreach ( $store as $name => &$data ) {
        self::process_block_assets( $data, $name, $instance, $editor, $registry );
    }

    // Phase 3: Register with WordPress
    foreach ( $registerable as $name => $item ) {
        self::register_block_type( $item['data'], $item['block_json'], ... );
    }

    // Phase 4: Apply overrides
    self::apply_overrides( $registry );
}
```

## Method Name Mapping (v6 → v7)

All public methods renamed to snake_case for WordPress Coding Standards:

| v6 Method | v7 Method |
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

## Key Architectural Changes

| Aspect | v6 (`_reference/`) | v7 (`includes/`) |
|--------|---------------------|------------------|
| **State Storage** | Static properties on Build | Block_Registry singleton |
| **Build::init()** | ~700 line monolithic | Orchestrator with focused classes |
| **File Discovery** | Inline in Build::init() | Block_Discovery class |
| **Method Names** | camelCase | snake_case |
| **String Functions** | Custom helpers | PHP 8.0+ built-ins |
| **Namespace** | `Jetstudio\Blockstudio` | `Blockstudio` |

## Field Handler Strategy Pattern

Field types are handled by individual handler classes:

```php
interface Field_Handler_Interface {
    public function get_type(): string;
    public function get_attribute_type(): string;
    public function get_default_value(): mixed;
    public function build_attribute( array $field ): array;
}

class Text_Field_Handler extends Abstract_Field_Handler {
    public function get_type(): string {
        return 'text';
    }

    public function get_attribute_type(): string {
        return 'string';
    }
}
```

## Settings Loader Strategy Pattern

Settings can be loaded from multiple sources:

```php
interface Settings_Loader_Interface {
    public function load(): array;
    public function get_priority(): int;
}

// Implementations:
// - Options_Loader: WordPress options table
// - JSON_Loader: blockstudio.json files
// - Filter_Loader: WordPress filters
```

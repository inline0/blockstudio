# Blockstudio PHPStan Extension PRD

A PHPStan extension package that gives Blockstudio users **TypeScript-level type safety** in PHP. Install it as a dev dependency, run PHPStan, get the same kind of guarantees a TypeScript/Zod project provides.

## Why

Blockstudio is built on PHP and JSON conventions: `block.json` declares attributes, `db.php` declares schemas, `rpc.php` declares functions. Templates access these through loosely-typed PHP arrays (`$a['title']`) where typos and shape mismatches go undetected until runtime.

The TypeScript ecosystem solves this with generated types from JSON schemas. PHP can do the same via PHPStan extensions: read the project's `block.json`, `db.php`, `rpc.php` and `blockstudio.json` files at analysis time, infer the exact types, and feed them back into PHPStan as concrete `array{...}` shape types.

## Who it's for

Theme and plugin developers building with Blockstudio who want:

- Autocomplete on `$a['field']` based on the actual `block.json` attributes
- Errors when accessing fields that don't exist
- Errors when the wrong shape is used (e.g. treating a `link` field as a string)
- Errors when `Db::get()` is called with a schema that doesn't exist
- Errors when `bs.fn()` (PHP equivalent) is called with the wrong RPC name
- Validation of `block.json`, `db.php`, `rpc.php`, `blockstudio.json` shapes themselves

## Distribution

A standalone Composer package published to Packagist:

```bash
composer require --dev blockstudio/phpstan
```

PHPStan auto-discovers the extension via the package's `extension.neon`. Zero user configuration required.

## Where it lives

In `phpstan/` subdirectory of the main repo, similar to how `registry/` is structured. The directory is a complete Composer package with its own `composer.json`, can be developed alongside core, and can be split into a separate repo later if needed.

## Stack

- **Language**: PHP 8.2+ (matches Blockstudio minimum)
- **PHPStan**: 1.11+ (uses stable extension API)
- **Test framework**: PHPUnit (PHPStan ships test helpers)
- **Schema parsing**: PHP native (json_decode for `block.json`, PHP token parser for `db.php`/`rpc.php` array returns)

## Architecture

```
phpstan/
├── composer.json
├── extension.neon              # PHPStan auto-discovery entry point
├── README.md
├── PRD.md                      # This file
├── phpstan.neon.dist           # Self-test config
├── src/
│   ├── Stubs/                  # Type definitions for Blockstudio public API
│   │   ├── functions.stub      # Global helpers: bs_render_block, bs_get_group, etc.
│   │   ├── Db.stub             # Db class signatures
│   │   ├── Field_Registry.stub
│   │   ├── Settings.stub
│   │   ├── Build.stub
│   │   └── hooks.stub          # Filter/action callback signatures
│   ├── Schema/                 # Reads project schemas at analysis time
│   │   ├── BlockJsonReader.php
│   │   ├── DbSchemaReader.php
│   │   ├── RpcSchemaReader.php
│   │   ├── BlockstudioJsonReader.php
│   │   └── ProjectScanner.php  # Walks the user's project, caches results
│   ├── Type/                   # PHPStan type extensions
│   │   ├── BlockTemplateAttributeType.php   # Types $a in block templates
│   │   ├── DbGetReturnType.php              # Types Db::get() return based on schema
│   │   ├── BsRenderBlockReturnType.php
│   │   └── SettingsGetReturnType.php
│   ├── Rules/                  # Custom analysis rules
│   │   ├── BlockJsonShapeRule.php           # Validates block.json structure
│   │   ├── DbSchemaShapeRule.php            # Validates db.php structure
│   │   ├── RpcSchemaShapeRule.php           # Validates rpc.php structure
│   │   ├── BlockstudioJsonShapeRule.php     # Validates blockstudio.json
│   │   ├── HookCallbackRule.php             # Validates filter/action callbacks
│   │   ├── SettingsPathRule.php             # Validates Settings::get() paths
│   │   ├── FieldTypeAccessRule.php          # Catches wrong field shape access
│   │   └── DeprecatedApiRule.php
│   └── Reflection/             # Helpers for inspecting Blockstudio code in user projects
│       ├── BlockTemplateDetector.php        # Determines if a file is a Blockstudio template
│       └── FieldTypeRegistry.php            # Maps field type names to data shapes
├── tests/
│   ├── Rules/
│   │   ├── BlockJsonShapeRuleTest.php
│   │   ├── DbSchemaShapeRuleTest.php
│   │   └── ...
│   ├── Type/
│   │   └── BlockTemplateAttributeTypeTest.php
│   ├── data/                   # Fixture projects for testing
│   │   ├── valid-block/
│   │   │   ├── block.json
│   │   │   └── index.php
│   │   ├── invalid-block-typo/
│   │   │   └── ...
│   │   └── ...
│   └── bootstrap.php
└── _references/                # External references (gitignored)
    ├── phpstan-wordpress/      # Reference for stub patterns
    └── phpstan-doctrine/       # Reference for dynamic type extension patterns
```

## What it does (in detail)

### 1. Stubs for Blockstudio public API

Stubs are PHP files with rich PHPDoc that describe Blockstudio's API to PHPStan. They are not executed; they are parsed by PHPStan as type information.

**Example: `Db.stub`**

```php
<?php
namespace Blockstudio;

class Db {
    /**
     * @template T of array<string, mixed>
     * @phpstan-return self<T>|null
     */
    public static function get(string $block, string $schema = 'default'): ?self {}

    /**
     * @phpstan-param T $data
     * @phpstan-return T|\WP_Error
     */
    public function create(array $data) {}

    /**
     * @phpstan-param array<string, mixed> $filters
     * @phpstan-return list<T>
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0): array {}

    /** @phpstan-return T|null */
    public function get_record(int $id) {}

    /**
     * @phpstan-param array<string, mixed> $data
     * @phpstan-return T|\WP_Error|null
     */
    public function update(int $id, array $data) {}

    public function delete(int $id): bool {}

    public function form(): string {}
}
```

The `T` template parameter gets concrete types via the `DbGetReturnType` extension below.

**Stubs to ship in v1**:

| File | Covers |
|---|---|
| `functions.stub` | `bs_render_block`, `bs_get_group`, `bs_get_scoped_class`, all `bs_*` helpers |
| `Db.stub` | `Db::get`, `Db::form`, all CRUD methods |
| `Settings.stub` | `Settings::get`, `Settings::get_all`, `Settings::get_instance` |
| `Field_Registry.stub` | `Field_Registry::instance`, `all`, `get` |
| `Build.stub` | `Build::blocks`, `Build::extensions`, `Build::get_build_dir` |
| `hooks.stub` | All `apply_filters('blockstudio/...')` and `do_action('blockstudio/...')` signatures |

### 2. Project schema readers

These classes scan the user's project at analysis time to understand its Blockstudio structure. PHPStan caches the results across analysis runs.

**`BlockJsonReader`**: For each `block.json` in the project, parses the file, extracts the `blockstudio.attributes` array, computes the resulting shape that gets passed to templates. Handles `group`, `repeater`, `tabs`, `custom/` fields recursively.

**`DbSchemaReader`**: For each `db.php` in the project, evaluates the returned array via PHP token parsing (NOT eval, for safety), extracts the `fields` shape and storage configuration.

**`RpcSchemaReader`**: For each `rpc.php`, extracts the function names and their parameter shapes. Inferred via PHPDoc on the closures or static analysis of the closure body.

**`BlockstudioJsonReader`**: Reads the theme's `blockstudio.json` and provides typed access to settings.

**`ProjectScanner`**: Coordinates the readers. Discovers all Blockstudio files in the project root by walking the filesystem looking for `block.json` files (and the `db.php`/`rpc.php` siblings). Result is cached in memory per analysis run.

### 3. Dynamic type extensions

These hook into PHPStan's type inference to provide concrete types in specific contexts.

**`BlockTemplateAttributeType`**: This is the killer feature. When PHPStan analyzes a file that lives next to a `block.json` (the file matches `*/block.json`'s sibling templates: `index.php`, `index.twig`, `index.blade.php`), the extension intercepts variable accesses to `$a` and returns an `array{...}` shape type built from the block's declared attributes.

```php
// blockstudio/hero/block.json declares:
//   attributes: [
//     { id: "title", type: "text" },
//     { id: "cta", type: "link" },
//     { id: "items", type: "repeater", attributes: [{ id: "label", type: "text" }] }
//   ]

// blockstudio/hero/index.php
echo $a['title'];                     // PHPStan: string
echo $a['cta']['href'];               // PHPStan: string
echo $a['cta']['target'];             // PHPStan: string|null
foreach ($a['items'] as $item) {
    echo $item['label'];              // PHPStan: string
}
echo $a['typo'];                      // PHPStan ERROR: Offset 'typo' does not exist on array{title: string, cta: array{href: string, ...}, items: list<array{label: string}>}
```

This requires understanding every Blockstudio field type and how it gets serialized into the attributes array. The `FieldTypeRegistry` class maps field type names to their data shape templates.

**`DbGetReturnType`**: When the user calls `Db::get('mytheme/widget', 'subscribers')`, this extension reads the matching `db.php`, extracts the `fields` config, and returns a `Db<array{email: string, name: string|null, ...}>` typed instance. Subsequent `->create()`, `->list()`, `->get_record()` calls use the templated type from the stub.

```php
$db = Db::get('mytheme/app', 'subscribers');
$record = $db->create(['email' => 'a@b.com', 'name' => 'Alice']);
echo $record['email'];  // PHPStan: string
echo $record['typo'];   // PHPStan ERROR: Offset 'typo' does not exist
```

**`BsRenderBlockReturnType`**: Always returns `string`, but validates the `name` argument matches a registered block in the project, and the `data` argument matches that block's attribute shape.

**`SettingsGetReturnType`**: Returns the right type for each known settings path. `Settings::get('tailwind/enabled')` returns `bool`, `Settings::get('users/ids')` returns `int[]`, etc. Unknown paths warn (covered by the `SettingsPathRule`).

### 4. Custom rules

Rules report errors for invalid patterns. Each rule targets a specific class of mistakes.

| Rule | What it catches |
|---|---|
| `BlockJsonShapeRule` | Invalid `block.json` files: missing required fields, unknown field types, malformed `attributes`, conditions referencing non-existent fields, etc. |
| `DbSchemaShapeRule` | Invalid `db.php`: missing `storage`, unknown storage type, fields without `type`, capability config wrong shape, custom-table without `table` name, etc. |
| `RpcSchemaShapeRule` | Invalid `rpc.php`: function not callable, `methods` not in valid HTTP methods, `public` value not bool/`'open'`, capability not string/array, etc. |
| `BlockstudioJsonShapeRule` | Invalid `blockstudio.json`: unknown setting paths, wrong types for known paths, deprecated shorthand booleans (`"tailwind": true` instead of `{enabled: true}`), etc. |
| `HookCallbackRule` | When `add_filter('blockstudio/render', $cb)` is used with a `$cb` whose signature doesn't match the hook's documented signature. |
| `SettingsPathRule` | When `Settings::get('typo/path')` references a path that doesn't exist in the settings schema. Suggests the closest valid path. |
| `FieldTypeAccessRule` | When a template accesses a field with the wrong shape: e.g. `$a['cta']` (where `cta` is a link field) being concatenated to a string instead of accessed as `$a['cta']['href']`. |
| `DeprecatedApiRule` | Warns when calling deprecated Blockstudio functions/methods. Reads a hardcoded list of removals per version. |

### 5. Schema validation philosophy

Schema files are validated **at analysis time, not runtime**. PHPStan reads `block.json`, `db.php`, etc. and reports errors as analysis errors, exactly like syntax errors. This means:

- Users see schema errors in their IDE immediately (via PHPStan IDE integration)
- CI catches malformed schemas before they hit production
- No runtime overhead in Blockstudio core for validation that PHPStan can do at dev time
- Errors point at the exact line in the JSON file (PHPStan supports this)

Schema validation uses a port of the existing JSON Schema files in `docs/src/schemas/`. We extract the type information from those schemas (which are TypeScript modules generating JSON Schema) and translate them into PHP validation logic in the rules.

## Field type → data shape map

Critical reference for the `BlockTemplateAttributeType` extension. Every field type maps to a specific shape that ends up in the attributes array.

| Field type | PHP type expression |
|---|---|
| `text`, `textarea`, `richtext` | `string` |
| `wysiwyg`, `code` | `string` |
| `number`, `range` | `int\|float` |
| `toggle` | `bool` |
| `select` (single) | `string\|int` |
| `select` (multiple) | `list<string\|int>` |
| `radio` | `string\|int` |
| `checkbox` (single) | `string\|int` |
| `checkbox` (multiple) | `list<string\|int>` |
| `color` | `array{value: string, opacity?: float}` |
| `gradient` | `string` |
| `link` | `array{href: string, title?: string, target?: string, opensInNewTab?: bool}` |
| `files` (single) | `array{id: int, url: string, alt?: string, sizes?: array<string, mixed>, mime_type?: string}` |
| `files` (multiple) | `list<array{id: int, url: string, ...}>` |
| `icon` | `array{set: string, subSet: string, icon: string}` |
| `date` | `string` (YYYY-MM-DD) |
| `datetime` | `string` (ISO 8601) |
| `classes` | `string` |
| `html-tag` | `string` |
| `unit` | `string` |
| `attributes` | `array<string, string>` |
| `block` | `string` (rendered) or `array<string, mixed>` (data mode) |
| `group` | Flattened: each child field becomes `{groupId}_{childId}` in the parent shape |
| `repeater` | `list<array{...child shape...}>` |
| `tabs` | Flattened: each tab's fields become top-level entries |
| `custom/{name}` | Resolved from `field.json` definition |
| `message` | Not stored in attributes (display only) |

The `group` and `tabs` flattening is the trickiest part: a field with id `cta` of type `group` containing children `text` and `url` becomes `cta_text` and `cta_url` in the resulting shape, NOT `cta.text` and `cta.url`.

## Phased delivery

### Phase 1: Foundation + core stubs

Goal: package boots, PHPStan discovers it, basic API stubs work.

- [ ] Set up Composer package: `composer.json`, `extension.neon`, autoloader
- [ ] Write stubs for `bs_render_block`, `bs_get_group`, `bs_get_scoped_class`
- [ ] Write stubs for `Db`, `Settings`, `Field_Registry`, `Build`
- [ ] Write stubs for the 10 most important hooks
- [ ] Set up self-test PHPStan config to validate the stubs themselves
- [ ] Set up PHPUnit + PHPStan testing utilities
- [ ] Smoke test: install in a fresh test project and verify autocomplete + basic type checking works

### Phase 2: Schema readers + project scanner

Goal: read user's Blockstudio project structure into memory.

- [ ] `ProjectScanner`: walk filesystem from PHPStan's analyzed paths, find all `block.json` files
- [ ] `BlockJsonReader`: parse one `block.json`, extract attributes shape (without types yet)
- [ ] `FieldTypeRegistry`: map every field type to its data shape template
- [ ] Recursive resolution of `group`, `repeater`, `tabs`, `custom/` fields
- [ ] `DbSchemaReader`: parse `db.php` array returns via PHP-Parser (nikic/php-parser), extract fields shape
- [ ] `RpcSchemaReader`: parse `rpc.php` array returns, extract function signatures
- [ ] `BlockstudioJsonReader`: parse the theme's `blockstudio.json`
- [ ] In-memory cache keyed by file path + mtime

### Phase 3: Dynamic type extensions

Goal: `$a` and `Db::get()` get concrete types.

- [ ] `BlockTemplateDetector`: identify whether a file is a Blockstudio template (lives next to a `block.json`)
- [ ] `BlockTemplateAttributeType`: provide `array{...}` shape for `$a` in templates
- [ ] `DbGetReturnType`: provide concrete `Db<T>` type from `Db::get()` calls
- [ ] `SettingsGetReturnType`: provide typed return for `Settings::get()` calls
- [ ] `BsRenderBlockReturnType`: validate `bs_render_block()` arguments

### Phase 4: Schema shape rules

Goal: catch invalid schema files.

- [ ] `BlockJsonShapeRule`: validate `block.json` against the Blockstudio schema
- [ ] `DbSchemaShapeRule`: validate `db.php` structure
- [ ] `RpcSchemaShapeRule`: validate `rpc.php` structure
- [ ] `BlockstudioJsonShapeRule`: validate `blockstudio.json` structure (catch the shorthand boolean trap)
- [ ] All rules report at the exact line in the source file

### Phase 5: Behavioral rules

Goal: catch wrong usage of valid schemas.

- [ ] `HookCallbackRule`: validate filter/action callback signatures
- [ ] `SettingsPathRule`: typo detection on `Settings::get()` paths
- [ ] `FieldTypeAccessRule`: catch wrong shape access (using a link field as a string)
- [ ] `DeprecatedApiRule`: warn on deprecated APIs

### Phase 6: Polish and ship

- [ ] README with installation, configuration, examples
- [ ] Documentation page in `docs/content/registry/...` style under `docs/content/dev/phpstan.mdx`
- [ ] Compatibility test against Drift theme (real-world example)
- [ ] Compatibility test against the test theme in `tests/theme/`
- [ ] Publish to Packagist
- [ ] Blog post announcing the extension

## Out of scope (for v1)

- Twig template analysis (templates use `{{ a.field }}` syntax, would need a separate parser)
- Blade template analysis (similar concern)
- IDE integration beyond what PHPStan natively provides
- Auto-fix for schema errors
- Generation of TypeScript types from PHP Blockstudio code

These can be added later as separate features once the core PHP analysis is solid.

## Technical risks

**Reading `db.php`/`rpc.php` safely**: These are PHP files that return arrays. We can't `require` them at analysis time (side effects, autoload issues, security). We use `nikic/php-parser` to parse the file as an AST and extract the returned array literal. Limitation: only static array literals are supported. Dynamic schemas (computed at runtime) cannot be analyzed. Document this limitation.

**Project scanning performance**: PHPStan analyzes individual files, not projects. The scanner needs to discover the project root and walk the filesystem for `block.json` files. We cache the result in memory per analysis run, which is what PHPStan extensions like `phpstan-doctrine` do for similar problems.

**Field type resolution complexity**: `group`, `repeater`, `tabs`, `custom/` recursion can theoretically cycle (custom field referencing itself). Detect cycles and emit a clear error.

**Stub maintenance**: Stubs must stay in sync with Blockstudio's actual API. Solution: write a CI check in the main repo that compares stub signatures against the actual class signatures and fails if they drift.

## Reference

Look at these existing PHPStan extensions for patterns:

- `szepeviktor/phpstan-wordpress` - WordPress stubs and basic rules
- `phpstan/phpstan-doctrine` - Dynamic type extensions reading project metadata
- `phpstan/phpstan-symfony` - Container service type inference

We will copy these into `phpstan/_references/` (gitignored) for local reference.

## Open questions

1. **Package name**: `blockstudio/phpstan` or `blockstudio/phpstan-blockstudio`? The latter follows the `szepeviktor/phpstan-wordpress` convention but is verbose. The former is cleaner but might conflict with future packages.

2. **PHPStan version baseline**: Target 1.11+ (most stable extension API) or 2.x (latest)? Going 2.x means a smaller user base initially but cleaner code.

3. **`phpstan-wordpress` as a hard dependency**: Should we require it (so users get WP types automatically) or just recommend it (smaller install, more setup)?

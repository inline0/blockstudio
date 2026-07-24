# Blockstudio PHPStan Extension

PHPStan extension for [Blockstudio](https://blockstudio.dev). It adds
type-safe template access, schema validation, hook checking, and stubs for the
Blockstudio public PHP API.

## Install

```bash
composer require --dev blockstudio/phpstan
```

If you have
[phpstan/extension-installer](https://github.com/phpstan/extension-installer)
installed, the extension is auto-discovered. Otherwise, include it manually in
your `phpstan.neon`:

```yaml
includes:
  - vendor/blockstudio/phpstan/extension.neon
```

## What it checks

### Template field access

When a PHP file lives next to a `block.json`, the extension validates every
`$a['key']` access against the block's declared attributes.

```php
// blockstudio/hero/index.php
<?php
/** @var array<string, mixed> $a */

echo $a['title'];     // OK
echo $a['subtitle'];  // OK
echo $a['typo'];      // Error: Field "typo" does not exist in block.json
```

Field keys follow Blockstudio's runtime flattening rules. `tabs` and anonymous
`group` containers without an `id` keep their children in the parent scope.
Named groups prefix child keys, so a `text` field inside a `cta` group is
available as `$a['cta_text']`.

File-backed reusable fields are expanded too. A
`{"type":"custom/mytheme/hero","idStructure":"hero_{id}"}` reference contributes
the same transformed keys as it does at runtime, including nested custom
references inside groups, tabs, and repeaters. Per-instance `overrides` are
applied before PHP, Twig, Blade, block-tag, and inferred-shape checks.

Missing, ambiguous, invalid, and cyclic definitions report focused
`blockstudio.customField.*` errors without producing secondary unknown-key
noise. Definitions registered only through the runtime `blockstudio/fields`
filter cannot be inferred; use `field.json` for statically checked fields.

Twig and Blade templates are checked too:

```twig
<h1>{{ a.title }}</h1>
<p>{{ a.typo }}</p> {# Error #}
```

```blade
<h1>{{ $a['title'] }}</h1>
<p>{{ $a['typo'] }}</p> {{-- Error --}}
```

### Block tag validation

Both `<block>` and `<bs:>` tag syntaxes are validated across PHP, Twig, and
Blade templates.

```html
<bs:mytheme-hero title="Hello" />          <!-- OK -->
<bs:mytheme-nonexistent />                 <!-- Error: unknown block -->
<bs:mytheme-hero badattr="" />             <!-- Error: unknown attribute -->
<block name="core/separator" />            <!-- OK -->
```

`data-*` and `html-*` attributes are treated as pass-through and are not
validated.

### Typed `Db::get()` records

The extension reads your `db.php` schema and uses it to type record arrays
returned by `Db::get()`.

```php
// blockstudio/subscribers/db.php
return [
    'storage' => 'table',
    'fields' => [
        'email' => ['type' => 'string', 'required' => true],
        'name'  => ['type' => 'string'],
    ],
];

$db = Db::get('mytheme/subscribers');
$record = $db->create(['email' => 'a@b.com']);

echo $record['email']; // string
echo $record['name'];  // string|null
echo $record['typo'];  // Error
```

This also works with the PHP-native builder syntax:

```php
use Blockstudio\Db\Field;
use Blockstudio\Db\Schema;
use Blockstudio\Db\Storage;

return Schema::make(
    storage: Storage::Table,
    fields: [
        'email' => Field::string(required: true),
        'active' => Field::boolean(default: false),
    ],
);
```

### Settings path validation

`Settings::get()` paths are checked against the known Blockstudio settings
schema.

```php
Settings::get('tailwind/enabled');  // OK
Settings::get('tailwind/enabld');   // Error: Did you mean "tailwind/enabled"?
```

### Hook name validation

Blockstudio action and filter hook names are validated.

```php
add_filter('blockstudio/render', $cb); // OK
add_filter('blockstudio/rendrr', $cb); // Error
```

Dynamic settings hooks such as `blockstudio/settings/tailwind/enabled` are
always allowed. Non-Blockstudio hooks are ignored.

### Schema validation

The extension validates Blockstudio schema files across the project:

- `block.json`
- `field.json`
- extension JSON files in `extensions/`
- `page.json`
- `db.php`
- `rpc.php`
- `cron.php`
- `blockstudio.json`

That covers missing required keys, invalid field types, malformed schema
shapes, bad RPC method values, invalid cron schedules, and deprecated settings
shorthand such as `"tailwind": true`.

`db.php`, `rpc.php`, and `cron.php` support both legacy arrays and the optional
PHP-native forms:

- `Blockstudio\Db\Schema` / `Blockstudio\Db\Field`
- `#[Blockstudio\Attributes\Rpc]`
- `#[Blockstudio\Attributes\Cron]`

## API stubs

The package ships stubs for the Blockstudio public API, including:

- `Db`, `Settings`, `Build`, `Field_Registry`
- `Blockstudio\Db\Schema`, `Blockstudio\Db\Field`, `Blockstudio\Db\Storage`
- `Blockstudio\Rpc\Method`, `Blockstudio\Rpc\Access`
- `Blockstudio\Cron\Schedule`
- `Blockstudio\Attributes\Rpc`, `Blockstudio\Attributes\Cron`
- global helpers like `bs_render_block()`

Legacy compatibility aliases are stubbed too, so older codebases still analyze
cleanly while migrating.

## Convention: typing `$a` in PHP templates

Add a `@var` annotation at the top of each PHP block template so PHPStan knows
`$a` exists:

```php
<?php
/** @var array<string, mixed> $a */
```

Twig and Blade templates do not need this annotation.

## Configuration

The extension requires no manual configuration. It auto-discovers
`block.json`, `db.php`, `rpc.php`, `cron.php`, `page.json`, `field.json`, and
`blockstudio.json` files in your project.

If a project references blocks or reusable fields from a library outside the
project root, add the library directory to `blockstudioScanRoots`. Both
`block.json` and `field.json` files are indexed:

```yaml
parameters:
  blockstudioScanRoots:
    - vendor/acme/block-library/blockstudio
```

If you need to exclude specific paths, use PHPStan's standard `excludePaths`:

```yaml
parameters:
  excludePaths:
    - some/path/to/exclude
```

## Requirements

- PHP 8.2+
- PHPStan 2.0+
- [phpstan/phpstan-wordpress](https://github.com/szepeviktor/phpstan-wordpress)

## License

MIT

# Blockstudio PHPStan Extension

PHPStan extension for [Blockstudio](https://blockstudio.dev). Adds type-safe block templates, schema validation, and Blockstudio API stubs.

## Install

```bash
composer require --dev blockstudio/phpstan
```

If you have [phpstan/extension-installer](https://github.com/phpstan/extension-installer) installed, the extension is auto-discovered. Otherwise, include it in your `phpstan.neon`:

```yaml
includes:
  - vendor/blockstudio/phpstan/extension.neon
```

## What it does

### 1. Validates `$a['key']` accesses in block templates

When a PHP file lives next to a `block.json`, the extension reads that block's declared attributes and reports any accesses to fields that don't exist.

```php
// blockstudio/hero/block.json
{
  "blockstudio": {
    "attributes": [
      { "id": "title", "type": "text" },
      { "id": "subtitle", "type": "text" }
    ]
  }
}

// blockstudio/hero/index.php
<?php
/** @var array<string, mixed> $a */

echo $a['title'];     // OK
echo $a['subtitle'];  // OK
echo $a['typo'];      // Error: Field "typo" does not exist in block.json (block: hero). Did you mean "title"?
```

### 2. Types `Db::get()` return values from `db.php`

When you call `Db::get('mytheme/widget', 'subscribers')`, the extension reads the matching `db.php`, extracts its `fields` shape, and types the resulting record arrays.

```php
// blockstudio/subscribers/db.php
return [
    'storage' => 'table',
    'fields' => [
        'email' => ['type' => 'string', 'required' => true],
        'name'  => ['type' => 'string'],
    ],
];

// somewhere in your code
$db = Db::get('mytheme/subscribers');
$record = $db->create(['email' => 'a@b.com']);

echo $record['email']; // PHPStan: string
echo $record['name'];  // PHPStan: string|null (optional)
echo $record['typo'];  // Error: Offset 'typo' does not exist on array{id: int, email: string, name: string|null}
```

### 3. Validates `block.json` schemas

The extension reads every `block.json` in your project and reports invalid configurations:

- Missing `name` field
- Missing field `id` or `type`
- Unknown field types
- `select`/`radio`/`checkbox` fields without `options` or `populate`
- Invalid nested `group`, `repeater`, `tabs` structures

### 4. Validates `db.php` schemas

- Missing `fields` array
- Invalid field types (must be `string`, `number`, `boolean`, `array`, or `object`)
- Missing field `type` declarations

### 5. Validates `rpc.php` schemas

- Invalid HTTP methods
- Wrong `public` value (must be bool or `'open'`)

### 6. Validates `Settings::get()` paths

```php
Settings::get('tailwind/enabled');  // OK, returns bool
Settings::get('tailwind/enabld');   // Error: Settings path "tailwind/enabld" is not a known Blockstudio setting. Did you mean "tailwind/enabled"?
```

### 7. Validates Blockstudio hook names

```php
add_filter('blockstudio/render', $cb);          // OK
add_filter('blockstudio/rendrr', $cb);          // Error: Unknown Blockstudio hook "blockstudio/rendrr". Did you mean "blockstudio/render"?
```

### 8. Provides stubs for the Blockstudio public API

`Db`, `Settings`, `Field_Registry`, `Build`, and global helpers like `bs_render_block()` are stubbed with proper PHPDoc, giving you autocomplete and type checking for all Blockstudio APIs without needing the plugin source on your dev machine.

## Convention: typing `$a` in templates

Add a `@var` annotation at the top of every template so PHPStan knows `$a` exists:

```php
<?php
/** @var array<string, mixed> $a */

// your template here
```

The extension's rule will then validate accesses against your `block.json`. Without this annotation, PHPStan reports `$a` as undefined (which is also useful — it tells you when you're not in a block template).

## Configuration

The extension requires no configuration. It auto-discovers `block.json`, `db.php`, `rpc.php`, and `blockstudio.json` files in your project.

If you need to exclude specific paths from analysis, use PHPStan's standard `excludePaths`:

```yaml
parameters:
  excludePaths:
    - some/path/to/exclude
```

## Requirements

- PHP 8.2+
- PHPStan 2.0+
- [phpstan/phpstan-wordpress](https://github.com/szepeviktor/phpstan-wordpress) (installed automatically)

## License

MIT

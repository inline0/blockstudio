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

## Opt-in analysis presets

The auto-discovered `extension.neon` remains the compatibility-safe base
extension. Version 7.6 adds separate presets; installing the package does not
enable them automatically.

| Preset | Includes |
| --- | --- |
| `base.neon` | The unchanged auto-discovered schema, template, hook, and API checks. |
| `theme.neon` | Base plus WordPress theme structure, Blockstudio asset references, scoped block styles, field defaults, and repeater bounds. |
| `extreme-theme.neon` | Theme plus PHPStan `max`, unsafe PHP checks, output escaping, Tailwind validation, JavaScript syntax and browser hygiene, and Interactivity API contracts. |
| `wordpress-render.neon` | Extreme theme plus an explicit live WordPress render probe. It never starts WordPress by itself. |

Include a preset directly when an existing PHPStan command owns the rest of
the configuration:

```yaml
includes:
  - vendor/blockstudio/phpstan/extreme-theme.neon

parameters:
  blockstudioThemeRoots:
    - .
  blockstudioThemeExcludePaths:
    - fixtures/**
  blockstudioThemeMaxFiles: 10000
  blockstudioExtremeJavaScript: true
  blockstudioExtremeTailwind: true
```

`blockstudioThemeExcludePaths` limits the project scanner. PHPStan's own
`excludePaths` still controls which PHP files PHPStan analyzes.

## Canonical command

The package installs `vendor/bin/blockstudio-phpstan`. It defaults to the
extreme-theme preset:

```bash
vendor/bin/blockstudio-phpstan --root . -- --no-progress
```

Projects can keep the canonical command's defaults in `blockstudio.json`:

```json
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "phpstan": {
    "preset": "extreme-theme",
    "roots": ["."],
    "excludePaths": ["fixtures/**"],
    "maxFiles": 10000
  }
}
```

Relative roots resolve from the configuration file. Explicit command-line
values replace their corresponding JSON value, so automation can make a
one-off selection without rewriting project configuration. Use
`--blockstudio-json <path>` when a project supplies the settings from an
alternate source. Invalid JSON, unknown `phpstan` keys, invalid types, and a
missing explicit source fail with exit code `2`.

Use another preset, compose a project configuration, or emit PHPStan's JSON
format:

```bash
vendor/bin/blockstudio-phpstan \
  --preset theme \
  --configuration phpstan.neon \
  --root . \
  --exclude 'fixtures/**' \
  --error-format json \
  -- --no-progress
```

The command writes its composed NEON file only to the system temporary
directory and removes it on exit. It does not create a project cache,
configuration, baseline, or hook. PHPStan's normal cache policy still applies
when a caller explicitly configures one.

Exit codes are stable:

- `0`: analysis passed
- `1`: PHPStan reported diagnostics
- `2`: invalid usage, configuration, or process execution

## Managed commit hook

Blockstudio can own an analysis-only pre-commit hook. Enable it in the project
`blockstudio.json`:

```json
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "phpstan": {
    "preset": "extreme-theme",
    "roots": ["."],
    "excludePaths": [],
    "maxFiles": 10000
  },
  "githooks": {
    "commit": true
  }
}
```

Then synchronize the repository:

```bash
vendor/bin/blockstudio-githooks sync
```

The command installs a generated hook inside Git's common directory and points
`core.hooksPath` at its managed directory. It records the prior hooks path and
chains an existing pre-commit hook before running
`vendor/bin/blockstudio-phpstan` from the configured project root. The
canonical command reads the same `phpstan` object, so the generated hook needs
no duplicated arguments. Re-running the command refreshes an owned hook,
including after package upgrades.

Set `commit` to `false`, remove the setting, or remove `blockstudio.json`, then
run `sync` again to remove only Blockstudio-owned files and restore the recorded
hooks path. `vendor/bin/blockstudio-githooks remove` performs the same safe
cleanup explicitly.

Blockstudio refuses to overwrite or remove files without its generated marker.
If `core.hooksPath` is changed after installation, removal leaves that newer
user setting intact. Linked Git checkouts share the managed directory, while
the generated hook resolves the active checkout and project root at commit
time. Paths containing spaces are supported.

The hook performs PHPStan analysis only. It never formats or rewrites project
files. A missing Composer installation or failed analysis blocks the commit
with a direct error; bypass behavior remains Git's standard `--no-verify`.

## Optional live WordPress render

Live rendering is a separate, explicit preset because it is slower and needs a
caller-owned WordPress environment:

```bash
vendor/bin/blockstudio-phpstan \
  --preset wordpress-render \
  --render-command='["wp","eval-file","tools/render-probe.php"]' \
  --render-working-directory=. \
  --render-timeout=60 \
  --root .
```

The command is an argv JSON array and is executed without a shell. The probe
must print exactly one JSON object:

```json
{"ok":true}
```

To report a failure:

```json
{
  "ok": false,
  "message": "Rendered block failed.",
  "file": "/absolute/path/to/block.json",
  "line": 12
}
```

Non-zero exits, timeouts, malformed JSON, and an `ok: false` response use the
`blockstudio.wordpress.render` diagnostic.

## Preset diagnostics

Every new diagnostic has a stable `blockstudio.*` identifier:

- Theme roots and structure:
  `blockstudio.theme.root.missing`, `blockstudio.theme.style.missing`,
  `blockstudio.theme.style.header`, and `blockstudio.theme.scanLimit`
- Block and field contracts:
  `blockstudio.theme.asset.manualEnqueue`,
  `blockstudio.theme.asset.selectorScope`,
  `blockstudio.theme.asset.missing`, `blockstudio.field.default`, and
  `blockstudio.field.repeaterBounds`
- PHP:
  `blockstudio.php.forbiddenFunction`,
  `blockstudio.wordpress.rawDatabaseWrite`, and
  `blockstudio.output.unescaped`
- Tailwind:
  `blockstudio.tailwind.compilerMissing`,
  `blockstudio.tailwind.compile`, `blockstudio.tailwind.unknownUtility`, and
  `blockstudio.tailwind.semanticToken`
- JavaScript:
  `blockstudio.javascript.syntax`, `blockstudio.javascript.debugOutput`,
  `blockstudio.javascript.bannedApi`, `blockstudio.javascript.leakedGlobal`,
  `blockstudio.javascript.importSpecifier`,
  `blockstudio.javascript.rootGuard`, `blockstudio.javascript.initShape`,
  `blockstudio.javascript.domContract`,
  `blockstudio.javascript.listenerCleanup`, and
  `blockstudio.javascript.reducedMotion`
- Interactivity API:
  `blockstudio.interactivity.import`,
  `blockstudio.interactivity.moduleImport`,
  `blockstudio.interactivity.namespace`,
  `blockstudio.interactivity.scopedDom`,
  `blockstudio.interactivity.derivedState`,
  `blockstudio.interactivity.handler`, `blockstudio.interactivity.binding`,
  `blockstudio.interactivity.context`, and
  `blockstudio.interactivity.orphan`

## Performance

The theme scanner uses ordinary materialized filesystem roots, deduplicates
them, skips dependency/build/cache trees by default, reads each file once, and
sorts diagnostics deterministically. Use narrow
`blockstudioThemeRoots`/`--root` values, exclusions, and
`blockstudioThemeMaxFiles`/`--max-files` for large repositories. JavaScript and
Tailwind checks can be disabled independently. The live-render layer runs only
when explicitly selected.

## Requirements

- PHP 8.2+
- PHPStan 2.0+
- [phpstan/phpstan-wordpress](https://github.com/szepeviktor/phpstan-wordpress)
- [Phasis](https://github.com/phasis/phasis) for JavaScript parsing
- [TailwindPHP](https://github.com/tailwindphp/tailwindphp) for Tailwind analysis

## License

MIT

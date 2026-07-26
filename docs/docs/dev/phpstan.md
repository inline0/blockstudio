---
title: PHPStan
description: Static analysis for Blockstudio projects with type-safe templates, schema validation, and hook checking.
path: "dev/phpstan"
order: 66
section: "Dev"
meta_title: "PHPStan"
meta_description: "Static analysis for Blockstudio projects with type-safe templates, schema validation, and hook checking."
---

# PHPStan

The `blockstudio/phpstan` extension brings static analysis to Blockstudio
projects. It validates block templates, schema files, settings paths, and hook
names at analysis time, catching bugs before they reach runtime.

## Installation

```bash
composer require --dev blockstudio/phpstan
```

The extension auto-discovers via PHPStan's extension installer. No manual
configuration needed.

## What it checks

### Template field access

When a PHP file lives next to a `block.json`, the extension validates every
`$a['key']` access against the block's declared attributes.

```php title="blockstudio/hero/index.php"
<?php
/** @var array<string, mixed> $a */

echo $a['title'];     // OK
echo $a['subtitle'];  // OK
echo $a['typo'];      // Error: Field "typo" does not exist in block.json
```

Add `/** @var array<string, mixed> $a */` at the top of each PHP template so
PHPStan knows `$a` exists. The extension handles the rest.

Field keys follow Blockstudio's runtime flattening rules. `tabs` and anonymous
`group` containers without an `id` are presentation-only, so their child fields
remain in the parent scope. A named group prefixes its child keys with the group
ID:

```php
echo $a['heading'];  // Anonymous group child.
echo $a['cta_text']; // "text" inside the named "cta" group.
```

### Reusable custom fields

File-backed `custom/*` references are expanded before template keys and array
shapes are checked. The extension reads the matching `field.json` and applies
the same `idStructure`, `overrides`, nested reference, group, tabs, and repeater
rules as Blockstudio:

```json title="blockstudio/fields/hero/field.json"
{
  "name": "mytheme/hero",
  "attributes": [
    { "id": "heading", "type": "text" },
    { "id": "description", "type": "textarea" }
  ]
}
```

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "type": "custom/mytheme/hero",
        "idStructure": "hero_{id}",
        "overrides": {
          "heading": { "id": "title" }
        }
      }
    ]
  }
}
```

The resulting template keys are `title` and `hero_description`. They are
recognized in PHP, Twig, Blade, block tags, and inferred attribute shapes.

Missing, ambiguous, invalid, or cyclic file-backed definitions produce a
specific `blockstudio.customField.*` error. Dependent key checks are skipped for
that block so one unresolved definition does not create a second wave of false
unknown-field errors.

Definitions registered only at runtime through the `blockstudio/fields` PHP
filter cannot be inferred by static analysis. Use a `field.json` definition when
the field must contribute statically checked template keys.

### Twig template access

Twig templates are scanned automatically. No annotation needed.

```twig title="blockstudio/hero/index.twig"
<h1>{{ a.title }}</h1>
<p>{{ a.typo }}</p>    {# Error: Field "typo" does not exist in block.json #}
```

### Blade template access

Blade templates are scanned the same way.

```blade title="blockstudio/hero/index.blade.php"
<h1>{{ $a['title'] }}</h1>
<p>{{ $a['typo'] }}</p>    {{-- Error --}}
```

### Block tag validation

Both `<block>` and `<bs:>` tag syntaxes are validated across PHP, Twig, and
Blade templates.

```html
<bs:mytheme-hero title="Hello" />
<!-- OK -->
<bs:mytheme-nonexistent />
<!-- Error: unknown block -->
<bs:mytheme-hero title="Hi" badattr="" />
<!-- Error: unknown attribute -->
<block name="core/separator" />
<!-- OK (core blocks always valid) -->
```

Attributes with `data-*` and `html-*` prefixes are pass-through and never
checked.

### Database record typing

`Db::get()` returns a typed instance based on the `db.php` schema. Record
shapes are inferred automatically.

```php title="db.php"
return [
    'storage' => 'table',
    'fields' => [
        'email' => ['type' => 'string', 'required' => true],
        'name'  => ['type' => 'string'],
    ],
];
```

```php
$db = Db::get('mytheme/subscribers');
$record = $db->create(['email' => 'a@b.com']);

echo $record['email']; // string
echo $record['name'];  // string|null (optional field)
echo $record['typo'];  // Error: Offset 'typo' does not exist
```

Required fields are non-nullable. Optional fields are `type|null`. An `id`
field (int) is always present. The same typing works for the PHP-native
`Blockstudio\\Db\\Schema` / `Blockstudio\\Db\\Field` builder syntax.

### Settings path validation

`Settings::get()` paths are checked against the known settings schema.

```php
Settings::get('tailwind/enabled');  // OK, returns bool
Settings::get('tailwind/enabld');   // Error: Did you mean "tailwind/enabled"?
```

### Hook name validation

Blockstudio filter and action hook names are validated.

```php
add_filter('blockstudio/render', $cb);   // OK
add_filter('blockstudio/rendrr', $cb);   // Error: Did you mean "blockstudio/render"?
```

Dynamic settings hooks like `blockstudio/settings/tailwind/enabled` are always
allowed. Non-blockstudio hooks are ignored.

### Schema validation

The extension validates every Blockstudio schema file in the project.

**block.json**:

- Missing `name` field
- Missing field `type`
- Missing `id` on value fields that require one
- Unknown field types
- `select`/`radio`/`checkbox` without `options` or `populate`
- Duplicate field IDs
- Invalid `pluginDependencies` declarations

Container fields like `group` and `tabs`, plus `custom/*` field references, can
omit `id` when they only expand or wrap other fields.

**field.json** (custom reusable fields):

- Missing `name`
- Missing or empty `attributes`
- Invalid attribute objects or missing field `type`
- Missing `id` on value fields that require one
- Unknown field types
- Missing, ambiguous, invalid, or cyclic nested `custom/*` references

**Extension JSON** (block extensions in `extensions/`):

- Missing `name` (target block)
- Missing `blockstudio` key
- Missing `blockstudio.extend` key

**page.json** (file-based pages):

- Missing `title`
- Missing `slug`
- Invalid `postStatus` values

**db.php**:

- Missing `fields` array
- Invalid field types
- Supports both legacy arrays and `Blockstudio\\Db\\Schema` / `Blockstudio\\Db\\Field`

**rpc.php**:

- Invalid HTTP methods
- Wrong `public` value (must be `true`, `false`, or `'open'`)
- Supports both legacy arrays and attributed object returns

**cron.php**:

- Missing `schedule` on configured tasks
- Missing `callback` on configured tasks
- Supports both legacy arrays and attributed object returns

**blockstudio.json**:

- Shorthand booleans like `"tailwind": true` (must use the nested object
  format `{"enabled": true, "config": ""}`)

## Configuration

The extension discovers Blockstudio files in the analyzed project by default.
When a project uses blocks or reusable fields from a library outside the
project root, add the library path to `blockstudioScanRoots`. The path is
scanned for both `block.json` and `field.json` definitions, so external block
tags and their custom fields can be validated together.

```yaml title="phpstan.neon"
parameters:
  blockstudioScanRoots:
    - vendor/acme/block-library/blockstudio
```

To ignore files, use PHPStan's standard `excludePaths` configuration.

## Analysis presets

The auto-discovered extension remains the compatibility-safe base. Blockstudio
7.6 adds opt-in layers without changing existing projects:

| Preset | Behavior |
| --- | --- |
| `base.neon` | Existing schema, template, hook, settings, and API analysis. |
| `theme.neon` | Base plus WordPress theme roots, style headers, Blockstudio assets, selector scoping, field defaults, and repeater bounds. |
| `extreme-theme.neon` | Theme plus PHPStan `max`, unsafe PHP, output escaping, Tailwind, JavaScript, and Interactivity API checks. |
| `wordpress-render.neon` | Extreme theme plus a caller-supplied live WordPress render probe. |

Installing `blockstudio/phpstan` enables only the base extension. Include an
opt-in layer in an existing PHPStan configuration:

```yaml title="phpstan.neon"
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

`blockstudioThemeExcludePaths` controls the theme scanner.
PHPStan's standard `excludePaths` independently controls PHP analysis.

The canonical command's `phpstan.excludePaths` setting in `blockstudio.json`
(and its `--exclude` option) configures both layers. Relative patterns resolve
against every configured root, so entries such as `vendor/**` bound PHPStan
analysis as well as Blockstudio's project scan.

### Canonical command

The package installs a single executable that defaults to extreme-theme:

```bash
vendor/bin/blockstudio-phpstan --root . -- --no-progress
```

The command defaults PHPStan to a `1G` memory limit so Composer-managed
WordPress projects do not inherit a typical `128M` CLI ceiling. Pass an
explicit PHPStan option after `--` to override it, such as
`-- --memory-limit=512M --no-progress`.

Keep project-wide command defaults in `blockstudio.json`:

```json title="blockstudio.json"
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

Relative roots resolve from the file that declares them. Explicit CLI values
replace the corresponding JSON values. Pass
`--blockstudio-json path/to/project.json` for an alternate configuration
source. Malformed JSON, unsupported `phpstan` keys, invalid values, and a
missing explicit source fail deterministically with exit code `2`.

Select another preset, include a project configuration, add repeatable roots
and exclusions, or ask PHPStan for JSON:

```bash
vendor/bin/blockstudio-phpstan \
  --preset theme \
  --configuration phpstan.neon \
  --root . \
  --exclude 'fixtures/**' \
  --max-files 10000 \
  --error-format json \
  -- --no-progress
```

The wrapper composes configuration in the system temporary directory and
deletes it on exit. It never writes generated configuration, baselines, hooks,
or caches into the analyzed project. Callers can still configure PHPStan's
normal cache explicitly.

The exit contract is:

- `0` when analysis passes
- `1` when PHPStan reports diagnostics
- `2` for invalid usage, configuration, or process execution

### Managed commit hook

To make the extreme-theme analysis a repository commit gate, enable the
Blockstudio-owned hook in `blockstudio.json`:

```json title="blockstudio.json"
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

Synchronize it after installing dependencies or changing the setting:

```bash
vendor/bin/blockstudio-githooks sync
```

The command writes its generated pre-commit hook and ownership record inside
Git's common directory, sets `core.hooksPath` to the managed hook directory,
and safely chains the previously configured pre-commit hook. Repeated syncs
refresh only the generated file, so package upgrades are idempotent.

Set `commit` to `false`, remove it, or remove `blockstudio.json`, then run
`sync` to restore the recorded hook path and delete only Blockstudio-owned
files. You can also run:

```bash
vendor/bin/blockstudio-githooks remove
```

User-owned files are never overwritten or deleted. If someone changes
`core.hooksPath` after Blockstudio was enabled, removal preserves that newer
setting. The generated hook supports linked Git checkouts and paths containing
spaces by resolving the active repository and project root at commit time. It
runs the canonical command from that root, so the same `phpstan` object controls
interactive runs and commits without duplicated generated arguments.

The hook runs `vendor/bin/blockstudio-phpstan` only. Blockstudio does not run a
formatter, and the hook never rewrites project files. Missing dependencies and
analysis failures block the commit with an actionable error.

### Live WordPress rendering

The live layer is deliberately explicit. The caller owns the WordPress
environment and supplies an argv array:

```bash
vendor/bin/blockstudio-phpstan \
  --preset wordpress-render \
  --render-command='["wp","eval-file","tools/render-probe.php"]' \
  --render-working-directory=. \
  --render-timeout=60 \
  --root .
```

The command runs without a shell and must print one JSON object:

```json
{"ok":true}
```

A probe can report a focused failure:

```json
{
  "ok": false,
  "message": "Rendered block failed.",
  "file": "/absolute/path/to/block.json",
  "line": 12
}
```

Non-zero exits, timeouts, malformed JSON, and `ok: false` produce
`blockstudio.wordpress.render`.

### Diagnostic identifiers

New preset diagnostics retain stable `blockstudio.*` IDs:

- Theme structure:
  `blockstudio.theme.root.missing`, `blockstudio.theme.style.missing`,
  `blockstudio.theme.style.header`, `blockstudio.theme.scanLimit`
- Assets and fields:
  `blockstudio.theme.asset.manualEnqueue`,
  `blockstudio.theme.asset.selectorScope`,
  `blockstudio.theme.asset.missing`, `blockstudio.field.default`,
  `blockstudio.field.repeaterBounds`
- PHP:
  `blockstudio.php.forbiddenFunction`,
  `blockstudio.wordpress.rawDatabaseWrite`, `blockstudio.output.unescaped`
- Tailwind:
  `blockstudio.tailwind.compilerMissing`, `blockstudio.tailwind.compile`,
  `blockstudio.tailwind.unknownUtility`,
  `blockstudio.tailwind.semanticToken`
- JavaScript:
  `blockstudio.javascript.syntax`, `blockstudio.javascript.debugOutput`,
  `blockstudio.javascript.bannedApi`, `blockstudio.javascript.leakedGlobal`,
  `blockstudio.javascript.importSpecifier`,
  `blockstudio.javascript.rootGuard`, `blockstudio.javascript.initShape`,
  `blockstudio.javascript.domContract`,
  `blockstudio.javascript.listenerCleanup`,
  `blockstudio.javascript.reducedMotion`
- Interactivity:
  `blockstudio.interactivity.import`,
  `blockstudio.interactivity.moduleImport`,
  `blockstudio.interactivity.namespace`,
  `blockstudio.interactivity.scopedDom`,
  `blockstudio.interactivity.derivedState`,
  `blockstudio.interactivity.handler`, `blockstudio.interactivity.binding`,
  `blockstudio.interactivity.context`,
  `blockstudio.interactivity.orphan`

### Performance

The scanner accepts ordinary materialized directories, deduplicates roots,
skips dependency/build/cache trees, caches file reads in memory, and sorts
diagnostics deterministically. Keep roots narrow, exclude fixture/generated
trees, and set a file limit for large repositories. JavaScript and Tailwind can
be disabled independently. Live rendering never runs unless its preset is
selected.

## Field type shapes

The extension maps every Blockstudio field type to a concrete PHP type:

| Field type                                                    | PHP type                                                                                    |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `text`, `textarea`, `richtext`, `wysiwyg`, `code`             | `string`                                                                                    |
| `date`, `datetime`, `classes`, `html-tag`, `unit`, `gradient` | `string`                                                                                    |
| `number`, `range`                                             | `int\|float`                                                                                |
| `toggle`                                                      | `bool`                                                                                      |
| `select`, `radio`, `checkbox` (single)                        | `string\|int`                                                                               |
| `select`, `checkbox` (multiple)                               | `list<string\|int>`                                                                         |
| `color`                                                       | `array{value: string, opacity: float\|null}`                                                |
| `link`                                                        | `array{href: string, title: string\|null, target: string\|null, opensInNewTab: bool\|null}` |
| `icon`                                                        | `array{set: string, subSet: string, icon: string}`                                          |
| `files` (single)                                              | `array{id: int, url: string, alt: string\|null, mime_type: string\|null}`                   |
| `files` (multiple)                                            | `list<array{id: int, url: string, ...}>`                                                    |
| `group`                                                       | Named groups use underscore prefixes; anonymous groups flatten into the parent scope         |
| `repeater`                                                    | `list<array{...child fields...}>`                                                           |
| `tabs`                                                        | Flattened into parent scope                                                                 |

## API stubs

The extension ships stubs for the full Blockstudio public API:

- `bs_render_block()`, `bs_get_group()`, `bs_get_scoped_class()`, `bs_db_form()`
- `Db::get()`, `Db::create()`, `Db::list()`, `Db::get_record()`, `Db::update()`, `Db::delete()`
- `Settings::get()`, `Settings::get_all()`
- `Field_Registry::instance()`, `Field_Registry::all()`, `Field_Registry::get()`
- `Build::blocks()`, `Build::extensions()`, `Build::get_build_dir()`

These provide autocomplete and type checking without needing the Blockstudio
plugin source on your machine.

## Requirements

- PHP 8.2+
- PHPStan 2.0+
- `phpstan/extension-installer` (recommended, for auto-discovery)

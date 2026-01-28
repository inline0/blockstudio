# Class Migration Workflow

## Overview

Classes in `includes/classes/` are migrated **one at a time** to WordPress Coding Standards. Each class goes through PHPCS validation before being considered complete.

## Migration Process

### Step 1: Add to phpcs.xml

```xml
<!-- Add the class file to be checked -->
<file>./includes/classes/build.php</file>
```

### Step 2: Run PHPCS

```bash
composer cs
```

This shows all coding standard violations.

### Step 3: Auto-fix What's Possible

```bash
composer cs:fix
```

PHPCBF automatically fixes many issues like spacing and indentation.

### Step 4: Manual Fixes

Fix remaining issues manually:

- **Snake_case methods**: `getData()` → `get_data()`
- **Yoda conditions**: `$x === null` → `null === $x`
- **Proper docblocks**: Add `@param`, `@return`, `@var`
- **Spacing**: `if ( $condition )` not `if ($condition)`

### Step 5: Run Tests

```bash
npm run test:v7
```

All 14 tests must pass.

### Step 6: Commit

```bash
git add includes/classes/build.php phpcs.xml
git commit -m "Migrate Build class to WordPress coding standards"
```

## phpcs.xml Structure

```xml
<?xml version="1.0"?>
<ruleset name="Blockstudio">
    <description>WordPress Coding Standards for Blockstudio</description>

    <!-- Entry point -->
    <file>./blockstudio.php</file>
    <file>./includes/class-plugin.php</file>

    <!-- Migrated classes (add as each is completed) -->
    <file>./includes/classes/constants.php</file>
    <file>./includes/classes/block-registry.php</file>
    <!-- ... more files ... -->

    <!-- Exclude directories -->
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>
    <exclude-pattern>*/_reference/*</exclude-pattern>

    <!-- Exclude unmigrated code -->
    <exclude-pattern>*/includes/functions/*</exclude-pattern>
    <exclude-pattern>*/includes/admin/*</exclude-pattern>

    <!-- WordPress coding standards -->
    <rule ref="WordPress"/>

    <!-- PHP 8.2+ -->
    <config name="testVersion" value="8.2-"/>

    <!-- Text domain -->
    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array">
                <element value="blockstudio"/>
            </property>
        </properties>
    </rule>

    <!-- Prefix for globals -->
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

## Migration Checklist

For each class being migrated:

- [ ] Add file to `phpcs.xml`
- [ ] Run `composer cs` and note violations
- [ ] Run `composer cs:fix` for auto-fixes
- [ ] Fix remaining issues manually:
  - [ ] Tabs for indentation (not spaces)
  - [ ] Spaces inside parentheses: `if ( $x )`
  - [ ] Yoda conditions: `null === $var`
  - [ ] Snake_case for methods and variables
  - [ ] Proper docblocks with `@param`, `@return`, `@var`
  - [ ] File header with `@package Blockstudio`
- [ ] Run `composer cs` - must pass with no errors
- [ ] Run `npm run test:v7` - all tests must pass
- [ ] Commit the migrated class

## Migration Priority

Start with classes that have fewer dependencies:

| Priority | Class | Notes |
|----------|-------|-------|
| 1 | `constants.php` | No dependencies |
| 2 | `field-type-config.php` | Configuration only |
| 3 | `block-registry.php` | Core singleton |
| 4 | `utils.php` | Utility functions |
| 5 | `files.php` | File utilities |
| 6 | `settings.php` | Configuration |
| 7 | `field.php` | Field processing |
| 8 | `build.php` | Core engine (complex) |
| 9 | `block.php` | Block rendering |
| 10 | `register.php` | WP block registration |
| 11 | `render.php` | Template rendering |
| 12 | `assets.php` | Asset pipeline |
| 13 | `admin.php` | Admin interface |
| 14 | `rest.php` | REST API |

## Already Migrated

These files are already in `phpcs.xml` and pass WPCS:

- `blockstudio.php`
- `includes/class-plugin.php`
- `includes/classes/constants.php`
- `includes/classes/field-type-config.php`
- `includes/classes/block-registry.php`
- `includes/classes/option-value-resolver.php`
- `includes/classes/attribute-builder.php`
- `includes/classes/file-classifier.php`
- `includes/classes/block-discovery.php`
- `includes/classes/asset-discovery.php`
- `includes/classes/block-registrar.php`
- `includes/classes/error-handler.php`
- `includes/classes/abstract-esmodule.php`
- `includes/interfaces/field-handler-interface.php`
- `includes/interfaces/settings-loader-interface.php`
- All field handlers in `includes/classes/field-handlers/`
- All settings loaders in `includes/classes/settings-loaders/`
- Most other classes (see `phpcs.xml` for complete list)

## Common Migration Issues

### 1. Method Renaming

When renaming methods from camelCase to snake_case, search for all usages:

```bash
# Find all usages of a method
grep -r "getData\(" includes/
```

### 2. Yoda Conditions

```php
// Wrong
if ( $var === null ) { }

// Correct
if ( null === $var ) { }
```

### 3. Array Syntax

```php
// Wrong
$array = [];

// Correct (for WPCS)
$array = array();
```

### 4. Inline Comments

```php
// Wrong
//No space after slashes

// Correct
// Space after slashes
```

### 5. Function Spacing

```php
// Wrong
function myFunc($param) {}

// Correct
function my_func( $param ) {}
```

## Backwards Compatibility

The public API in `includes/functions/functions.php` maintains backwards compatibility. Old method names can call new snake_case methods internally:

```php
// functions.php (public API)
function blockstudio_get_blocks() {
    return \Blockstudio\Build::blocks();
}

// Deprecated wrapper if needed
function blockstudio_getBlocks() {
    _deprecated_function( __FUNCTION__, '7.0.0', 'blockstudio_get_blocks' );
    return blockstudio_get_blocks();
}
```

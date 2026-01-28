# Coding Conventions

## WordPress Coding Standards (PHP)

All PHP code must follow **100% WordPress Coding Standards** with no exceptions. This ensures WordPress.org compatibility.

### Indentation

Use **tabs**, not spaces:

```php
// Correct
function example() {
	$var = 'value';
	if ( $condition ) {
		do_something();
	}
}
```

### Spacing

Spaces inside parentheses:

```php
// Correct
if ( $condition ) { }
foreach ( $array as $item ) { }
function example( $param ) { }

// Wrong
if ($condition) { }
foreach ($array as $item) { }
function example($param) { }
```

### Yoda Conditions

Put the constant/literal on the left:

```php
// Correct
if ( null === $var ) { }
if ( true === $condition ) { }
if ( 'value' === $string ) { }

// Wrong
if ( $var === null ) { }
if ( $condition === true ) { }
if ( $string === 'value' ) { }
```

### Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Functions | snake_case | `get_block_data()` |
| Methods | snake_case | `public function get_instance()` |
| Variables | snake_case | `$block_name` |
| Constants | UPPER_SNAKE | `BLOCKSTUDIO_VERSION` |
| Classes | Pascal_Case | `Block_Registry` |
| Files | lowercase-hyphenated | `class-plugin.php` |
| Hooks | snake_case with prefix | `blockstudio_init` |

### Docblocks

Every function, method, and class needs proper docblocks:

```php
/**
 * Short description.
 *
 * Longer description if needed.
 *
 * @since 7.0.0
 *
 * @param string $name  The block name.
 * @param array  $attrs Block attributes.
 *
 * @return array The processed data.
 */
public function process_block( string $name, array $attrs ): array {
    // ...
}
```

File headers:

```php
<?php
/**
 * Block Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;
```

### Arrays

Use `array()` syntax (WPCS preference):

```php
// Correct
$array = array(
    'key' => 'value',
);

// Also acceptable (but array() preferred)
$array = [
    'key' => 'value',
];
```

### Strings

Single quotes for strings without variables:

```php
// Correct
$string = 'Hello World';
$string = "Hello {$name}";

// Wrong
$string = "Hello World";
```

### Hook Naming

Use underscores, not slashes:

```php
// Correct
do_action( 'blockstudio_init', $this );
apply_filters( 'blockstudio_blocks_data', $data );

// Wrong
do_action( 'blockstudio/init', $this );
```

---

## JavaScript/TypeScript Conventions

### Test Comments

**No JSDoc** on test functions - test names are self-documenting:

```typescript
// CORRECT - descriptive test name, no redundant comments
it("returns undefined for expired keys", async () => {
  const kv = createKv();
  kv.set("key", "value", { ttl: 100 });
  await sleep(150);
  expect(kv.get("key")).toBeUndefined();
});

// WRONG - redundant comment
it("returns undefined for expired keys", async () => {
  // Set a key with TTL and wait for it to expire  <-- DON'T DO THIS
  const kv = createKv();
  kv.set("key", "value", { ttl: 100 });
  await sleep(150);
  expect(kv.get("key")).toBeUndefined();
});
```

### Public API Documentation

Every public export needs comprehensive JSDoc:

```typescript
/**
 * Opens the block inserter sidebar.
 * Handles button state synchronization with retry logic.
 *
 * @param editor - The WordPress editor FrameLocator
 * @example
 * await openBlockInserter(editor);
 */
export const openBlockInserter = async (editor: Editor) => { ... };
```

### Internal Code

No JSDoc for internal/private functions:

```typescript
// CORRECT - Internal helper, no JSDoc
const normalizeSelector = (sel: string) => sel.toLowerCase();

// CORRECT - WHY comment (explains non-obvious logic)
// Retry up to 3 times because button state can desync after resetBlocks()
for (let attempt = 0; attempt < 3; attempt++) { ... }

// WRONG - WHAT comment (obvious from code)
// Click the button  <-- DON'T DO THIS
await button.click();
```

### Comment Policy Summary

| Context | JSDoc | Inline Comments |
|---------|-------|-----------------|
| Public exports | Required | As needed |
| Internal functions | None | WHY only |
| Test functions | None | Non-obvious setup only |
| Test files | None | None |

---

## What NOT to Do

### In PHP

```php
// DON'T use camelCase for functions/methods
function getBlockData() { }  // Wrong
function get_block_data() { }  // Correct

// DON'T skip spaces in control structures
if($x) { }  // Wrong
if ( $x ) { }  // Correct

// DON'T use non-Yoda conditions
if ( $x === null ) { }  // Wrong
if ( null === $x ) { }  // Correct

// DON'T use spaces for indentation
    $x = 1;  // Wrong (spaces)
	$x = 1;  // Correct (tab)
```

### In JS/TS

```typescript
// DON'T add JSDoc to test functions
/**
 * Tests that the function works.
 */  // <-- DON'T DO THIS
it("works correctly", () => { });

// DON'T add "what" comments
// Click the save button  // <-- DON'T DO THIS
await saveButton.click();

// DON'T export internal helpers
export const internalHelper = () => { };  // Wrong
const internalHelper = () => { };  // Correct
```

### General

```php
// DON'T modify _reference/ directory
// It's read-only for snapshot comparison

// DON'T skip tests after changes
// Always run: npm run test:v7

// DON'T commit without PHPCS passing
// Always run: composer cs
```

---

## Quick Reference

### PHP Commands

```bash
composer cs        # Check standards
composer cs:fix    # Auto-fix issues
```

### Test Commands

```bash
npm run test:v7    # Run v7 tests
npm run playground:v7  # Start server
```

### Key Files

| File | Purpose |
|------|---------|
| `phpcs.xml` | PHPCS configuration |
| `includes/class-plugin.php` | WP-compliant singleton example |
| `blockstudio.php` | WP-compliant entry point example |

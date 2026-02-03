# TailwindPHP Architecture

A complete guide to how TailwindPHP compiles Tailwind CSS classes from HTML input to CSS output.

## Table of Contents

- [Overview](#overview)
- [Relationship to TailwindCSS TypeScript](#relationship-to-tailwindcss-typescript)
  - [What's Identical](#whats-identical)
  - [Structural Differences](#structural-differences)
  - [Omitted Features](#omitted-features)
  - [PHP-Specific Additions](#php-specific-additions)
  - [File Mapping](#file-mapping)
  - [Port Deviation Categories](#port-deviation-categories)
- [Pipeline Stages](#pipeline-stages)
  - [1. Input Processing](#1-input-processing)
  - [2. CSS Parsing](#2-css-parsing)
  - [3. Design System Setup](#3-design-system-setup)
  - [4. Candidate Extraction](#4-candidate-extraction)
  - [5. Candidate Parsing](#5-candidate-parsing)
  - [6. Compilation](#6-compilation)
  - [7. AST Optimization](#7-ast-optimization)
  - [8. CSS Output](#8-css-output)
- [Core Components](#core-components)
- [Data Flow Diagram](#data-flow-diagram)
- [AST Node Types](#ast-node-types)
- [Caching Strategy](#caching-strategy)

---

## Overview

TailwindPHP is a PHP port of TailwindCSS v4. It takes HTML content and optional CSS configuration, extracts Tailwind class names, and generates the corresponding CSS.

```php
$css = Tailwind::generate('<div class="flex p-4 text-blue-500">');
```

The compilation follows an 8-stage pipeline:

```
HTML + CSS Input
      ↓
┌─────────────────┐
│  CSS Parsing    │ → AST (Abstract Syntax Tree)
└─────────────────┘
      ↓
┌─────────────────┐
│  Design System  │ → Theme + Utilities + Variants
│     Setup       │
└─────────────────┘
      ↓
┌─────────────────┐
│   Candidate     │ → ["flex", "p-4", "text-blue-500"]
│   Extraction    │
└─────────────────┘
      ↓
┌─────────────────┐
│   Candidate     │ → Structured candidate objects
│    Parsing      │
└─────────────────┘
      ↓
┌─────────────────┐
│  Compilation    │ → CSS AST nodes per candidate
└─────────────────┘
      ↓
┌─────────────────┐
│ AST Optimization│ → Merged, sorted, nested CSS
└─────────────────┘
      ↓
┌─────────────────┐
│   CSS Output    │ → Final CSS string
└─────────────────┘
```

---

## Relationship to TailwindCSS TypeScript

TailwindPHP is a **1:1 port** of TailwindCSS v4 from TypeScript to PHP. The goal is identical CSS output for identical input.

### What's Identical

The following aspects are direct ports that produce the same results:

| Aspect | Description |
|--------|-------------|
| **CSS Output** | Byte-for-byte identical CSS for the same classes |
| **Utility Classes** | All 364+ utilities with same names and behavior |
| **Variants** | All variants (hover, focus, responsive, dark, etc.) |
| **Theme System** | CSS custom properties, namespaces, `theme()` function |
| **Candidate Parsing** | Same parsing rules for class names |
| **Property Order** | Same sort order for generated CSS |
| **CSS Functions** | `theme()`, `--theme()`, `--spacing()`, `--alpha()` |
| **Directives** | `@theme`, `@utility`, `@apply`, `@custom-variant` |

**Test coverage:** 3,468 tests ensure output parity with the TypeScript implementation.

### Structural Differences

These are necessary adaptations due to language differences:

| TypeScript | PHP | Reason |
|------------|-----|--------|
| `async/await` | Synchronous | PHP lacks native async |
| `Map<K, V>` | Associative array | PHP has no Map type |
| `Set<T>` | `array` with keys | PHP has no Set type |
| `BigInt` | `int` (64-bit) | Sufficient for variant bitmasks |
| TypeScript types | PHPDoc annotations | Static typing approach |
| `enum` | `const` flags | PHP 8.1 compatibility |
| ES Modules | `require_once` | Module system |
| Object references | Array copies | PHP arrays are value types |

### Omitted Features

These TypeScript features are intentionally not ported:

| Feature | Reason |
|---------|--------|
| **Source Maps** | Not needed for server-side CSS generation |
| **IDE Intellisense** | `getClassList()`, `getVariants()` - IDE tooling not primary use case |
| **JavaScript Plugins** | Can't execute JS in PHP; plugins implemented natively |
| **Watch Mode** | File watching is handled externally in PHP |
| **PostCSS Integration** | PHP has its own build pipelines |
| **Oxide Engine** | Rust-based scanner not portable to PHP |

### PHP-Specific Additions

Features added in the PHP implementation:

| Addition | Location | Purpose |
|----------|----------|---------|
| **LightningCss.php** | `src/_tailwindphp/` | Pure PHP replacement for lightningcss (Rust) |
| **CssMinifier.php** | `src/_tailwindphp/` | CSS minification |
| **clsx port** | `src/_tailwindphp/lib/clsx/` | Conditional class names |
| **tailwind-merge port** | `src/_tailwindphp/lib/tailwind-merge/` | Class conflict resolution |
| **CVA port** | `src/_tailwindphp/lib/cva/` | Class variance authority |
| **`cn()` function** | `src/index.php` | Combined clsx + merge (shadcn pattern) |
| **`importPaths` option** | `src/index.php` | File-based CSS loading |
| **Performance caches** | Various | LRU cache, regex constants, etc. |

### File Mapping

How PHP files map to TypeScript source:

```
TypeScript (reference/tailwindcss/packages/tailwindcss/src/)
                              ↓
PHP (src/)
```

| TypeScript File | PHP File | Notes |
|-----------------|----------|-------|
| `index.ts` | `index.php` | Main entry, compile pipeline |
| `ast.ts` | `ast.php` | AST node types, toCss() |
| `css-parser.ts` | `css-parser.php` | Character tokenizer |
| `candidate.ts` | `candidate.php` | Class name parsing |
| `compile.ts` | `compile.php` | Candidate → CSS |
| `design-system.ts` | `design-system.php` | Central registry |
| `theme.ts` | `theme.php` | CSS variable storage |
| `utilities.ts` | `utilities.php` + `utilities/*.php` | Split into 15 files |
| `variants.ts` | `variants.php` | Variant definitions |
| `apply.ts` | `apply.php` | @apply directive |
| `at-import.ts` | `at-import.php` | @import resolution |
| `css-functions.ts` | `css-functions.php` | theme(), --theme() |
| `plugin-api.ts` | `plugin.php` | Plugin system |
| — | `_tailwindphp/LightningCss.php` | **PHP-only** (lightningcss replacement) |
| — | `_tailwindphp/lib/*` | **PHP-only** (companion libraries) |

### Port Deviation Categories

All deviations from the TypeScript source are documented with `@port-deviation` markers. There are **89 documented deviations** across the codebase:

#### `@port-deviation:none`
Direct 1:1 port with no significant changes.

```php
// src/selector-parser.php, src/property-order.php, etc.
```

#### `@port-deviation:async`
Synchronous PHP code replacing async/await.

```php
// TypeScript
async function compile(css: string): Promise<string>

// PHP
function compile(string $css): string
```

#### `@port-deviation:storage`
Different data structures for PHP's type system.

```php
// TypeScript: Map<string, ThemeValue>
// PHP: array<string, array{value: string, options: int}>
private array $values = [];
```

#### `@port-deviation:types`
PHPDoc annotations instead of TypeScript types.

```php
/**
 * @param array{kind: string, root: string, ...} $candidate
 * @return array<array{kind: string, ...}>
 */
function compileAstNodes(array $candidate, ...): array
```

#### `@port-deviation:sourcemaps`
Source map tracking omitted throughout.

```php
// TypeScript nodes have: { src: SourceLocation, dst: DestLocation }
// PHP nodes omit these properties entirely
```

#### `@port-deviation:enum`
PHP constants instead of TypeScript enums.

```php
// TypeScript
enum ThemeOptions { None, Inline, Reference, Default, Static, Used }

// PHP
const THEME_OPTION_NONE = 0;
const THEME_OPTION_INLINE = 1 << 0;
const THEME_OPTION_REFERENCE = 1 << 1;
// ...
```

#### `@port-deviation:bigint`
Regular integers instead of BigInt.

```php
// TypeScript: bigint allows unlimited variant combinations
// PHP: 64-bit int limits to 64 variants (sufficient for current use)
$variantOrder |= 1 << $order;
```

#### `@port-deviation:performance`
PHP-specific optimizations maintaining identical output.

```php
// Array accumulation instead of string concat
$parts = [];
for (...) {
    $parts[] = $char;  // Not: $result .= $char
}
return implode('', $parts);
```

#### `@port-deviation:structure`
Different code organization for PHP idioms.

```php
// TypeScript: 6000+ line utilities.ts
// PHP: Split into src/utilities/*.php (15 files)
```

#### `@port-deviation:replacement`
PHP implementation replacing external dependency.

```php
// TypeScript uses lightningcss (Rust library via WASM)
// PHP uses src/_tailwindphp/LightningCss.php (pure PHP)
```

#### `@port-deviation:stub`
Placeholder for functionality not needed in PHP.

```php
// src/canonicalize-candidates.php - IDE tooling, not ported
```

#### `@port-deviation:omitted`
Entire module not applicable to PHP port.

```php
// src/at_import.test.php - Tests require Node.js filesystem features
```

### Deviation Density by File

| File | Deviations | Primary Types |
|------|------------|---------------|
| `index.php` | 6 | async, sourcemaps, modules, plugins, lightningcss, performance |
| `css-parser.php` | 4 | sourcemaps, stack, bom, performance |
| `theme.php` | 4 | storage, sourcemaps, enum, performance |
| `compile.php` | 4 | bigint, sorting, variant-result, performance |
| `design-system.php` | 4 | structure, invalidCandidates, intellisense, substitution |
| `candidate.php` | 3 | caching, node-filtering, types |
| `ast.php` | 5 | structure, sourcemaps, types, performance, location |
| `utilities.php` | 4 | structure, suggestions, featureFlags, types |
| `apply.php` | 4 | tracking, sourcemaps, errors, registration |
| `at-import.php` | 2 | async, sourcemaps |
| `css-functions.php` | 4 | dispatch, errors, fallback-injection, namespace-fallback |
| `plugin.php` | 2 | async, types |
| Other files | 1-2 each | Varies |

### Verifying Parity

The test suite ensures output parity:

```bash
# Extract tests from TypeScript source
composer extract

# Run all 3,373 tests
composer test

# Tests compare PHP output against expected TypeScript output
```

Test sources:
- `test-coverage/utilities/` - Extracted from `utilities.test.ts`
- `test-coverage/variants/` - Extracted from `variants.test.ts`
- `test-coverage/index/` - Extracted from `index.test.ts`
- `test-coverage/css-functions/` - Extracted from `css-functions.test.ts`

---

## Pipeline Stages

### 1. Input Processing

**File:** `src/index.php` → `Tailwind::generate()`

The entry point accepts multiple input formats:

```php
// Simple: HTML string only (uses default Tailwind config)
Tailwind::generate('<div class="flex">');

// With custom CSS
Tailwind::generate($html, '@theme { --color-brand: #3b82f6; }');

// With file imports
Tailwind::generate([
    'content' => $html,
    'importPaths' => '/path/to/styles.css',
]);

// With custom resolver
Tailwind::generate([
    'content' => $html,
    'importPaths' => function($uri, $from) {
        return $uri === 'my-theme' ? '@theme { ... }' : null;
    },
]);
```

**Key operations:**
1. Resolve `importPaths` to load external CSS files
2. Prepend default Tailwind imports if no CSS provided
3. Handle virtual modules (`tailwindcss`, `tailwindcss/preflight`, etc.)

### 2. CSS Parsing

**File:** `src/css-parser.php` → `parse()`

Converts CSS text into an Abstract Syntax Tree (AST). This is a character-by-character tokenizer that handles:

- Style rules (`.class { ... }`)
- At-rules (`@media`, `@theme`, `@utility`, etc.)
- Declarations (`color: red`)
- Comments (`/* ... */`)
- Nested CSS syntax

```php
$css = '.foo { color: red; }';
$ast = parse($css);

// Result:
[
    [
        'kind' => 'rule',
        'selector' => '.foo',
        'nodes' => [
            ['kind' => 'declaration', 'property' => 'color', 'value' => 'red', 'important' => false]
        ]
    ]
]
```

**Performance note:** Uses direct character comparison instead of `ord()` and tracks buffer lengths to avoid repeated `strlen()` calls (~20-30% faster).

### 3. Design System Setup

**File:** `src/design-system.php` → `buildDesignSystem()`

The Design System is the central registry combining:

| Component | Purpose | File |
|-----------|---------|------|
| **Theme** | CSS variable values (`--color-*`, `--spacing-*`) | `src/theme.php` |
| **Utilities** | Utility class definitions (`flex`, `p-*`, `text-*`) | `src/utilities.php` |
| **Variants** | State/responsive modifiers (`hover:`, `md:`, `dark:`) | `src/variants.php` |

**Setup process:**

```
CSS AST
   ↓
┌──────────────────────────────────────────────────────────────┐
│ Walk AST and process directives:                             │
│                                                              │
│  @import "tailwindcss"     → Load theme.css, preflight.css   │
│  @theme { --color-*: ... } → Register theme values           │
│  @utility name { ... }     → Register custom utility         │
│  @custom-variant name      → Register custom variant         │
│  @plugin "typography"      → Load and execute plugin         │
└──────────────────────────────────────────────────────────────┘
   ↓
DesignSystem {
    theme: Theme,
    utilities: Utilities,
    variants: Variants
}
```

**Built-in utilities** are registered from split files:

```
src/utilities/
├── accessibility.php   # sr-only, not-sr-only, forced-colors
├── backgrounds.php     # bg-*, gradient-*
├── borders.php         # border-*, rounded-*, ring-*
├── effects.php         # opacity-*, shadow-*, blur-*
├── filters.php         # filter utilities
├── flexbox.php         # flex-*, justify-*, items-*, gap-*
├── interactivity.php   # cursor-*, select-*, scroll-*
├── layout.php          # container, columns, display, position
├── sizing.php          # w-*, h-*, min-*, max-*, size-*
├── spacing.php         # p-*, m-*, space-*
├── svg.php             # fill-*, stroke-*
├── tables.php          # table-*, border-collapse
├── transforms.php      # scale-*, rotate-*, translate-*
├── transitions.php     # transition-*, duration-*, ease-*
└── typography.php      # text-*, font-*, leading-*, tracking-*
```

### 4. Candidate Extraction

**File:** `src/index.php` → `extractCandidates()`

Extracts potential Tailwind class names from HTML content:

```php
$html = '<div class="flex p-4" className="text-blue-500">';
$candidates = extractCandidates($html);
// → ['flex', 'p-4', 'text-blue-500']
```

**Extraction methods:**
1. Regex for `class="..."` and `className="..."` attributes
2. Split by whitespace
3. Deduplicate results

**Performance note:** Uses pre-compiled regex constants to avoid repeated pattern compilation.

### 5. Candidate Parsing

**File:** `src/candidate.php` → `parseCandidate()`

Converts raw class strings into structured candidate objects. A candidate can be:

| Kind | Example | Description |
|------|---------|-------------|
| `static` | `flex`, `hidden` | No value, just the utility name |
| `functional` | `p-4`, `text-blue-500` | Has a value (named or arbitrary) |
| `arbitrary` | `[color:red]` | Arbitrary property and value |

**Parsing a functional candidate:**

```
Input: "hover:md:text-blue-500/75"
                ↓
┌─────────────────────────────────────┐
│ Split by `:` to find variants       │
│ → ["hover", "md", "text-blue-500/75"]│
└─────────────────────────────────────┘
                ↓
┌─────────────────────────────────────┐
│ Parse utility part                  │
│ → root: "text"                      │
│ → value: {kind: "named", value: "blue-500"}│
│ → modifier: {kind: "named", value: "75"}│
└─────────────────────────────────────┘
                ↓
┌─────────────────────────────────────┐
│ Parse variants                      │
│ → [{kind: "static", root: "hover"}] │
│ → [{kind: "functional", root: "md"}]│
└─────────────────────────────────────┘
                ↓
{
    kind: "functional",
    root: "text",
    value: {kind: "named", value: "blue-500"},
    modifier: {kind: "named", value: "75"},
    variants: [
        {kind: "static", root: "hover"},
        {kind: "functional", root: "md"}
    ],
    important: false,
    raw: "hover:md:text-blue-500/75"
}
```

**Arbitrary values** use bracket syntax:

```
p-[20px]     → value: {kind: "arbitrary", value: "20px"}
[color:red]  → kind: "arbitrary", property: "color", value: "red"
```

### 6. Compilation

**File:** `src/compile.php` → `compileCandidates()`

Converts parsed candidates into CSS AST nodes.

**For each candidate:**

```
Candidate: {kind: "functional", root: "p", value: "4", ...}
                        ↓
┌───────────────────────────────────────────────────────┐
│ 1. Look up utility in Utilities registry              │
│    utilities.get("p") → paddingUtility                │
└───────────────────────────────────────────────────────┘
                        ↓
┌───────────────────────────────────────────────────────┐
│ 2. Call utility's compile function                    │
│    paddingUtility.compileFn(candidate)                │
│    → [decl("padding", "calc(var(--spacing) * 4)")]    │
└───────────────────────────────────────────────────────┘
                        ↓
┌───────────────────────────────────────────────────────┐
│ 3. Wrap in selector                                   │
│    {kind: "rule", selector: ".p-4", nodes: [...]}     │
└───────────────────────────────────────────────────────┘
                        ↓
┌───────────────────────────────────────────────────────┐
│ 4. Apply variants (inside-out)                        │
│    hover: → wrap in "&:hover" rule                    │
│    md:    → wrap in "@media (width >= 768px)"         │
└───────────────────────────────────────────────────────┘
                        ↓
{
    kind: "at-rule",
    name: "@media",
    params: "(width >= 768px)",
    nodes: [{
        kind: "rule",
        selector: ".hover\\:md\\:p-4:hover",
        nodes: [
            {kind: "declaration", property: "padding", value: "..."}
        ]
    }]
}
```

**Variant application** wraps the rule node:

| Variant Type | Example | Transformation |
|--------------|---------|----------------|
| Pseudo-class | `hover:` | Selector → `.class:hover` |
| Pseudo-element | `before:` | Selector → `.class::before` |
| Media query | `md:` | Wrap in `@media (width >= 768px)` |
| Container | `@md:` | Wrap in `@container (width >= 768px)` |
| Supports | `supports-grid:` | Wrap in `@supports (display: grid)` |
| Dark mode | `dark:` | Wrap in `@media (prefers-color-scheme: dark)` |

### 7. AST Optimization

**File:** `src/index.php` → internal optimization during `compile()`

After compilation, the AST is optimized:

1. **Sort nodes** by variant order and property order
2. **Merge duplicate selectors** (same selector = combine declarations)
3. **Hoist `@media` rules** (group same media queries)
4. **Apply CSS nesting transformations** (flatten `&` selectors)
5. **Resolve `theme()` and `--theme()` functions**
6. **Add vendor prefixes** where needed

**LightningCSS equivalent transformations** (`src/_tailwindphp/LightningCss.php`):

```php
// Nesting flattening
".foo { &:hover { color: red; } }"
→ ".foo:hover { color: red; }"

// color-mix() to oklab()
"color-mix(in oklab, var(--color) 50%, transparent)"
→ "oklab(from var(--color) l a b / 50%)"

// calc() simplification
"calc(4 * 1rem)"
→ "4rem"
```

### 8. CSS Output

**File:** `src/ast.php` → `toCss()`

Converts the final AST back to a CSS string:

```php
$css = toCss($ast);
```

**Output formatting:**
- Proper indentation (2 spaces per level)
- Declarations with `;` terminators
- `!important` appended when flagged
- Comments preserved

**Performance note:** Uses array accumulation + `implode()` instead of string concatenation, and pre-computed indent strings (~50% faster).

---

## Core Components

### Theme (`src/theme.php`)

Stores CSS custom property values:

```php
$theme->add('--color-blue-500', '#3b82f6');
$theme->resolve('blue-500', ['--color']); // → "var(--color-blue-500)"
$theme->resolveValue('blue-500', ['--color']); // → "#3b82f6"
```

**Features:**
- Namespace clearing (`--color-*: initial;`)
- Default value handling (won't override non-default)
- LRU cache for `resolveKey()` lookups
- Keyframe storage

### Utilities (`src/utilities.php`)

Registry of utility classes:

```php
// Static utility (no value)
$utilities->static('flex', fn($candidate) => [
    decl('display', 'flex')
]);

// Functional utility (with value)
$utilities->functional('p', fn($candidate) => [
    decl('padding', resolveSpacing($candidate['value']))
]);
```

### Variants (`src/variants.php`)

Registry of variant modifiers:

```php
// Static variant
$variants->static('hover', '&:hover');

// Functional variant
$variants->functional('aria', fn($value) => "&[aria-{$value}=\"true\"]");

// Compound variant (wraps another variant)
$variants->compound('group-hover', 'hover', '.group');
```

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Tailwind::generate()                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────────────┐   │
│  │    HTML     │     │     CSS     │     │    importPaths      │   │
│  │   Content   │     │   (optional)│     │    (optional)       │   │
│  └──────┬──────┘     └──────┬──────┘     └──────────┬──────────┘   │
│         │                   │                       │              │
│         │                   └───────────┬───────────┘              │
│         │                               │                          │
│         │                               ▼                          │
│         │                    ┌─────────────────────┐               │
│         │                    │    CSS Parser       │               │
│         │                    │   css-parser.php    │               │
│         │                    └──────────┬──────────┘               │
│         │                               │                          │
│         │                               ▼                          │
│         │                    ┌─────────────────────┐               │
│         │                    │    CSS AST          │               │
│         │                    │   (array of nodes)  │               │
│         │                    └──────────┬──────────┘               │
│         │                               │                          │
│         │                               ▼                          │
│         │                    ┌─────────────────────┐               │
│         │                    │  buildDesignSystem  │               │
│         │                    │ design-system.php   │               │
│         │                    └──────────┬──────────┘               │
│         │                               │                          │
│         │         ┌─────────────────────┼─────────────────────┐    │
│         │         │                     │                     │    │
│         │         ▼                     ▼                     ▼    │
│         │  ┌───────────┐        ┌─────────────┐       ┌──────────┐│
│         │  │   Theme   │        │  Utilities  │       │ Variants ││
│         │  │ theme.php │        │utilities.php│       │variants. ││
│         │  └───────────┘        └─────────────┘       └──────────┘│
│         │         │                     │                     │    │
│         │         └─────────────────────┼─────────────────────┘    │
│         │                               │                          │
│         │                               ▼                          │
│         │                    ┌─────────────────────┐               │
│         │                    │   DesignSystem      │               │
│         │                    └──────────┬──────────┘               │
│         │                               │                          │
│         ▼                               │                          │
│  ┌─────────────────────┐                │                          │
│  │ extractCandidates   │                │                          │
│  │    index.php        │                │                          │
│  └──────────┬──────────┘                │                          │
│             │                           │                          │
│             ▼                           │                          │
│  ┌─────────────────────┐                │                          │
│  │  Raw Candidates     │                │                          │
│  │ ["flex", "p-4", ...]│                │                          │
│  └──────────┬──────────┘                │                          │
│             │                           │                          │
│             └───────────────┬───────────┘                          │
│                             │                                      │
│                             ▼                                      │
│                  ┌─────────────────────┐                           │
│                  │   parseCandidate    │                           │
│                  │   candidate.php     │                           │
│                  └──────────┬──────────┘                           │
│                             │                                      │
│                             ▼                                      │
│                  ┌─────────────────────┐                           │
│                  │ Parsed Candidates   │                           │
│                  │ [{kind, root, ...}] │                           │
│                  └──────────┬──────────┘                           │
│                             │                                      │
│                             ▼                                      │
│                  ┌─────────────────────┐                           │
│                  │ compileCandidates   │                           │
│                  │    compile.php      │                           │
│                  └──────────┬──────────┘                           │
│                             │                                      │
│                             ▼                                      │
│                  ┌─────────────────────┐                           │
│                  │   Compiled AST      │                           │
│                  │  (sorted rules)     │                           │
│                  └──────────┬──────────┘                           │
│                             │                                      │
│                             ▼                                      │
│                  ┌─────────────────────┐                           │
│                  │   LightningCss      │                           │
│                  │  (optimizations)    │                           │
│                  └──────────┬──────────┘                           │
│                             │                                      │
│                             ▼                                      │
│                  ┌─────────────────────┐                           │
│                  │       toCss         │                           │
│                  │      ast.php        │                           │
│                  └──────────┬──────────┘                           │
│                             │                                      │
│                             ▼                                      │
│                  ┌─────────────────────┐                           │
│                  │    CSS Output       │                           │
│                  │    (string)         │                           │
│                  └─────────────────────┘                           │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## AST Node Types

All AST nodes are PHP arrays with a `kind` discriminator:

### StyleRule

```php
[
    'kind' => 'rule',
    'selector' => '.flex',
    'nodes' => [/* child nodes */]
]
```

### AtRule

```php
[
    'kind' => 'at-rule',
    'name' => '@media',
    'params' => '(width >= 768px)',
    'nodes' => [/* child nodes */]
]
```

### Declaration

```php
[
    'kind' => 'declaration',
    'property' => 'display',
    'value' => 'flex',
    'important' => false
]
```

### Comment

```php
[
    'kind' => 'comment',
    'value' => '/* This is a comment */'
]
```

### Context (internal)

```php
[
    'kind' => 'context',
    'context' => ['layer' => 'utilities'],
    'nodes' => [/* child nodes */]
]
```

### AtRoot (internal)

```php
[
    'kind' => 'at-root',
    'nodes' => [/* nodes to hoist to root */]
]
```

---

## Caching Strategy

TailwindPHP uses multiple caching layers for performance:

### 1. Resource File Cache

```php
// src/index.php
$_resourceFileCache = [];
function readResourceFile(string $filename): string
{
    global $_resourceFileCache;
    if (isset($_resourceFileCache[$filename])) {
        return $_resourceFileCache[$filename];
    }
    // ... load and cache
}
```

Caches `theme.css`, `preflight.css`, etc. for the lifetime of the process.

### 2. Theme Resolve Cache

```php
// src/theme.php
class Theme
{
    private const CACHE_MAX_SIZE = 256;
    private array $resolveKeyCache = [];

    private function resolveKey($value, $keys): ?string
    {
        $cacheKey = $value . '|' . implode('|', $keys);
        if (isset($this->resolveKeyCache[$cacheKey])) {
            return $this->resolveKeyCache[$cacheKey];
        }
        // ... resolve and cache with LRU eviction
    }
}
```

LRU cache for theme lookups (cleared on mutation).

### 3. DefaultMap Lazy Caches

```php
// src/design-system.php
$this->parsedCandidates = new DefaultMap(function ($candidate) {
    return parseCandidate($candidate, $this);
});
```

Candidates are parsed once and cached by their raw string.

### 4. Variant Mask Cache

```php
// src/compile.php
$_variantMaskCache = [];
```

Pre-computed bitmasks for variant ordering.

### 5. Regex Pattern Constants

```php
// src/index.php
const REGEX_CLASS_ATTR = '/class\s*=\s*["\']([^"\']+)["\']/';
```

Compiled once at module load instead of per function call.

---

## Performance Characteristics

| Operation | Typical Time | Notes |
|-----------|--------------|-------|
| Full generate (50 classes) | ~14ms | With all caches warm |
| CSS parsing (preflight.css) | ~1.2ms | 816 ops/s |
| toCss (preflight AST) | ~0.015ms | 65K ops/s |
| Single candidate parse | ~0.01ms | Cached after first parse |

Optimizations provide ~94% improvement over naive implementation:
- Array accumulation vs string concat
- Pre-computed regex patterns
- LRU caching for hot paths
- strlen() caching in loops
- Variant mask pre-computation

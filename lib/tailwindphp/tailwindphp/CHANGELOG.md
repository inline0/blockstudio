# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.0] - 2026-07-18

### Added

- `TailwindCompiler::generateExact()` and `TailwindCompiler::cssExact()` compile exactly the given content or candidate set per call, without accumulating candidates from earlier calls on the same compiler, while still reusing its parsed design system. This is the recommended pattern for per-page CSS from one long-lived compiler: `tw::compile($css)` once, then `generateExact($html, true)` per page.
- `TailwindCompiler::generate()` and `TailwindCompiler::css()` accept an optional `$minify` parameter, and the `build` closure returned by `TailwindPHP\compile()` accepts an optional second `$minify` argument. Minified output is emitted directly during serialization instead of a post-hoc string pass.
- The array returned by `TailwindPHP\compile()` gains additive `buildExact` and `designSystem` keys.

### Changed

- Minified output is now produced by the serializer rather than the string-based `CssMinifier` post-pass. Bytes differ slightly from earlier releases for identical input (structurally equivalent, within 1% in size), so caches keyed on minified output hashes will bust once. Descendant `:not()` selectors are now preserved when minifying: `.x :not(p)` no longer collapses to `.x:not(p)`.
- The CLI `-m`/`--minify` and `--optimize` flags use the same serializer path, so CLI output matches `generate(['minify' => true])` byte for byte. `Tailwind::minify()` remains available as the string-based minifier for arbitrary CSS; prefer the `minify` option or parameter for generated output.
- `TailwindCompiler` introspection (`properties()`, `computedProperties()`, `value()`) now reuses the fully registered design system, so custom `@utility` names return their declarations instead of nothing.

### Upgrade notes

- Subclasses of `TailwindCompiler` that override `generate()` or `css()` must add the optional `bool $minify = false` parameter to their override signature; PHP rejects child declarations that omit a parent's optional parameter.

## [1.4.2] - 2026-06-03

### Fixed

- `@import "tailwindcss"` now preserves Tailwind's canonical cascade layer order so `@layer utilities` overrides `@layer components` and `@layer base` as expected.

## [1.4.1] - 2026-05-27

### Fixed

- PHPStan configuration now covers the value parser walk offset inference reported by the PHP 8.3 code quality workflow.

## [1.4.0] - 2026-05-27

### Added

#### TailwindCSS 4.3 Alignment
- Updated internal TailwindCSS reference from v4.2.1 to v4.3.0
- Added native scrollbar utilities: `scrollbar-auto`, `scrollbar-thin`, `scrollbar-none`, `scrollbar-thumb-*`, `scrollbar-track-*`, and `scrollbar-gutter-*`
- Added `zoom-*` utilities with percentage bare values and arbitrary values
- Added `tab-*` utilities for `tab-size`

### Changed

- Re-extracted all test data from TailwindCSS v4.3.0 reference
- Test suite expanded to 4,074 tests (+25 from v1.3.0)
- Arbitrary transform values now preserve spaces between transform functions to match TailwindCSS v4.3 output

### Fixed

- `clsx()` now avoids PHP 8.5 boolean coercion warnings for `NAN` and treats non-empty strings such as `"0"` with JavaScript truthiness.
- Encoded ampersands in HTML class attributes are decoded before candidate extraction so arbitrary selector classes such as `[&_svg:not([class*=size-])]:size-4` compile when rendered as `[&amp;_svg:not([class*=size-])]:size-4`.

## [1.3.2] - 2026-04-24

### Fixed

- `inferDataType()` now uses namespace-relative first-class callables so vendor-prefixed builds do not dispatch to unprefixed `TailwindPHP\Utils\*` functions.
- CSS minification now preserves comma spacing inside function argument lists, keeping arbitrary math values such as `clamp(9.375rem, 7.635rem + 8.699vw, 15.8125rem)` valid after minification.

### Added

- Regression tests for vendor-prefixed `inferDataType()` dispatch and minified arbitrary math values.

## [1.3.0] - 2026-03-05

### Added

#### TailwindCSS 4.2 Alignment
- Updated internal TailwindCSS reference from v4.1.17 to v4.2.1

#### New Color Palettes
- `mauve` - Purple-tinted neutral (11 shades, 50–950)
- `olive` - Yellow-green neutral (11 shades, 50–950)
- `mist` - Cool blue neutral (11 shades, 50–950)
- `taupe` - Warm brown neutral (11 shades, 50–950)

#### Logical Property Utilities
- `inset-bs-*` / `inset-be-*` - Inset block-start/end positioning
- `mbs-*` / `mbe-*` - Margin block-start/end
- `pbs-*` / `pbe-*` - Padding block-start/end
- `scroll-mbs-*` / `scroll-mbe-*` - Scroll margin block-start/end
- `scroll-pbs-*` / `scroll-pbe-*` - Scroll padding block-start/end
- `border-bs-*` / `border-be-*` - Border block-start/end (width, color, style)

#### Logical Sizing Utilities
- `inline-*` / `min-inline-*` / `max-inline-*` - Inline-size utilities (logical width)
- `block-*` / `min-block-*` / `max-block-*` - Block-size utilities (logical height)
- Static values: `full`, `auto`, `min`, `max`, `fit`, `screen`, `none`, `lh`
- Viewport units: `svw`, `lvw`, `dvw` (inline), `svh`, `lvh`, `dvh` (block)
- Spacing-based functional values with fraction support

#### Font Feature Settings
- `font-features-*` - Arbitrary font-feature-settings via `font-features-["smcp"]`

### Changed

- `start-*` / `end-*` renamed to `inset-s-*` / `inset-e-*` (following TailwindCSS 4.2 deprecation)
- Test suite expanded to 4,049 tests (+35 from v1.2.4)
- Re-extracted all test data from TailwindCSS v4.2.1 reference

### Fixed

- `theme(fontWeight.semibold)` JS compat lookup now resolves correctly against default theme

## [1.2.4] - 2026-02-12

### Fixed

- Fixed responsive breakpoint ordering: replaced bitmask-based variant sorting (`1 << order`) with sorted order arrays to avoid PHP 64-bit integer overflow. With 64+ registered variants, the bitmask overflowed to 0, causing all responsive breakpoints to sort alphabetically (`lg:` before `sm:`) instead of mobile-first.

### Added

- Test for responsive breakpoint mobile-first ordering

### Changed

- Test suite expanded to 4,014 tests (+1 from v1.2.3)

## [1.2.3] - 2026-02-11

### Fixed

- Fixed responsive breakpoint ordering: variants within a group (e.g. `sm`, `md`, `lg`) now get unique incrementing sort orders instead of sharing the same order. Previously, `sm:` rules could appear after `lg:` rules in compiled CSS, breaking the mobile-first cascade.

### Added

- `autoload.php` standalone autoloader for environments that don't use Composer's autoloader (e.g. WordPress plugins with vendor prefixing).

## [1.2.2] - 2025-12-06

### Fixed

- `computedProperties()` and `computedValue()` now run through LightningCSS optimization pipeline for consistent output
  - Color values with opacity now return `oklch()` format instead of `color-mix()`
  - Durations normalized (e.g., `500ms` → `.5s`)
  - Leading zeros removed (e.g., `0.5` → `.5`)
- Fixed documentation showing incorrect syntax for multiple classes (use array, not space-separated string)

### Added

- 11 new tests for LightningCSS optimization in computed values

### Changed

- Test suite expanded to 4,013 tests (+11 from v1.2.1)

## [1.2.1] - 2025-12-06

### Added

#### Theme Accessor Methods
- `tw::colors()` - Get all color values from the design system
- `tw::breakpoints()` - Get all breakpoint values from the design system
- `tw::spacing()` - Get all spacing values from the design system
- All methods available as both static methods and compiler instance methods

### Changed

- Removed unused `CandidateParser.php` and `CssFormatter.php` (dead code)
- Test suite expanded to 4,002 tests (-32 from v1.2.0 due to dead code removal, +9 for new methods)
- Updated compiler documentation (`->generate()` instead of deprecated `->css()`)

## [1.2.0] - 2025-12-05

### Added

#### Full @source Directive Support
- `@source "./path"` - File/directory patterns for content scanning
- `@source not "./ignored"` - Negated patterns to exclude paths
- `@source inline("flex p-4 m-2")` - Inline candidates directly in CSS
- `@source not inline("legacy-class")` - Ignore specific candidates
- Brace expansion support in inline patterns: `@source inline("p-{1,2,4}")`
- Validation: @source cannot be nested or have a body
- 24 new tests for @source directive

### Changed

- Test suite expanded to 4,025 tests (+24 from v1.1.0)
- Inline candidates from `@source inline()` are now compiled on first build

## [1.1.0] - 2025-12-05

### Added

#### TailwindCompiler Class
- `tw::compile()` - Create a reusable `TailwindCompiler` instance for multiple operations
- `$compiler->css()` - Generate CSS from HTML content using the compiled design system
- Supports minification via `minify: true` parameter

#### CSS Property Inspection API
- `tw::properties()` - Get raw CSS properties for class(es) with unresolved CSS variables
- `tw::computedProperties()` - Get computed CSS properties with all variables resolved
- `tw::value()` - Get raw value for a single CSS property
- `tw::computedValue()` - Get computed value for a single CSS property (resolved)
- All methods available as both static methods and compiler instance methods

#### Flexible Input Formats
- All static methods now accept three input formats:
  - String only: `tw::properties('p-4')`
  - String + CSS: `tw::properties('bg-brand', '@import "tailwindcss"; @theme { ... }')`
  - Array: `tw::properties(['content' => 'p-4', 'css' => '@import "tailwindcss";'])`

### Changed

- Expanded test suite to 3,913 tests (+99 from v1.0.1)
- Updated documentation with comprehensive API section

## [1.0.1] - 2025-12-04

### Fixed

- CSS nesting now correctly prefixes all selectors in a selector list
  - `.parent { h1, h2, h3 { ... } }` now correctly outputs `.parent h1, .parent h2, .parent h3 { ... }`
  - Previously only the first selector was prefixed
  - Commas inside pseudo-classes like `:where()`, `:not()`, `:is()` are preserved

### Added

- 4 new tests for CSS nesting selector list handling
- `splitSelectorList()` helper for parsing comma-separated selectors

## [1.0.0] - 2025-12-04

### Added

#### Core CSS Compilation
- Full 1:1 port of TailwindCSS 4.x to PHP (v4.1.17)
- All utility classes (364 utilities across 15 categories)
- All variants (hover, focus, responsive, dark mode, container queries, etc.)
- `@apply` directive with nested selectors
- `@theme` customization with namespace clearing
- `@utility` for custom utilities
- `@custom-variant` support
- `@layer` directives (base, components, utilities)
- `theme()`, `--theme()`, `--spacing()`, `--alpha()` CSS functions
- Preflight CSS reset
- Prefix support (`tw:`)
- Important modifier (`!`)
- Arbitrary values (`[value]`) and arbitrary variants

#### Import System
- `@import` resolution for virtual modules (`tailwindcss`, `tailwindcss/preflight`, etc.)
- File-based `@import` resolution via `importPaths` option
- Nested `@import` resolution
- Import deduplication
- Custom import resolvers (callable for virtual file systems)
- CSS @import modifiers: `layer()`, `supports()`, media queries

#### Plugin System
- `@tailwindcss/typography` - The prose class for typographic defaults
- `@tailwindcss/forms` - Form element reset and styling utilities
- Custom plugin support via `PluginInterface`
- Plugin API: `addBase()`, `addUtilities()`, `matchUtilities()`, `addComponents()`, `addVariant()`, `theme()`

#### Companion Libraries
- **clsx** - Conditional class name construction (27 tests from reference)
- **tailwind-merge** - Intelligent class conflict resolution (52 tests from reference)
- **CVA** - Class Variance Authority for component variants (50 tests from reference)
- `cn()` - Combined clsx + tailwind-merge (shadcn/ui pattern)
- `variants()` - Declarative component variant configuration
- `compose()` - Merge multiple variant components
- `merge()` - Tailwind class conflict resolution
- `join()` - Simple class joining

#### CLI
- 1:1 port of @tailwindcss/cli
- `-i, --input` - Input CSS file
- `-o, --output` - Output file
- `-w, --watch` - Watch mode for development
- `-m, --minify` - Minified output for production
- `--optimize` - Optimize without minifying
- `--cwd` - Custom working directory
- `@source` directive for content scanning

#### Additional Features
- CSS minification
- File-based caching with TTL support
- `tw-animate-css` virtual module support
- `color-mix()` to `oklab()` conversion
- Vendor prefixes (autoprefixer equivalent)
- Keyframe handling and hoisting

### Technical Details

- **PHP 8.2+** required
- **3,807 tests** passing
- No external runtime dependencies
- Zero Node.js requirement

[1.4.2]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.4.2
[1.4.1]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.4.1
[1.4.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.4.0
[1.3.2]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.3.2
[1.3.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.3.0
[1.2.4]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.4
[1.2.3]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.3
[1.2.2]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.2
[1.2.1]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.1
[1.2.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.0
[1.1.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.1.0
[1.0.1]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.0.1
[1.0.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.0.0

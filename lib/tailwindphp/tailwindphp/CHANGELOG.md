# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.2.2]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.2
[1.2.1]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.1
[1.2.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.2.0
[1.1.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.1.0
[1.0.1]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.0.1
[1.0.0]: https://github.com/dnnsjsk/tailwindphp/releases/tag/v1.0.0

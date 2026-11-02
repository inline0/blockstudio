# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.8.4] - 2026-11-02

### Fixed

- The standalone `autoload.php` loads `src/compat/legacy-utilities.php`. Since 1.8.2, environments that load TailwindPHP without Composer's autoloader (for example prefixed WordPress plugin bundles) failed with `Call to undefined function TailwindPHP\Compat\registerLegacyUtilities()` on the first compile.

### Testing

- Assert that the standalone autoloader lists exactly the Composer `files` autoloads in the same order, and compile through it alone in a separate PHP process.

### Upgrade notes

- No output changes from 1.8.3. Projects that load `autoload.php` directly should skip 1.8.2 and 1.8.3.

## [1.8.3] - 2026-11-02

### Fixed

- `has-*` and `not-*` variants serialize their inner selector the way Lightning CSS does in the reference pipeline. A lone `:is()` is unwrapped only when its argument is a single compound that does not start with a type or universal selector, so `has-[.foo]` and `has-[:focus]` still compile to `:has(.foo)` and `:has(:focus)` while `has-[img]`, `has-[input:checked]` and `not-[img]` keep `:has(:is(img))`, `:has(:is(input:checked))` and `:not(:is(img))`. Attribute values inside them are double-quoted (`has-[[data-slot=icon]]` compiles to `:has([data-slot="icon"])`). This covers `has-[…]`, `has-data-*`, `has-aria-*`, `group-has-*`, `peer-has-*` and `not-has-*`.
- Class attribute scanning no longer keeps PHP or JavaScript string delimiters on candidates. In templates such as `class="<?php echo $wide ? 'lg:grid lg:p-12' : ''; ?>"`, leading quotes and trailing quotes, commas and semicolons are dropped, so `lg:grid` and `lg:p-12` are found instead of `'lg:grid` and `lg:p-12'`. Quotes inside arbitrary values (`content-['a']`, `bg-[url('/a.png')]`) are unchanged.

### Testing

- Add 85 variant cases captured from the pinned Tailwind CSS 4.3.3 source through Lightning CSS 1.32.0 with the reference optimizer's options (`pnpm run generate:variants`), comparing the complete generated CSS for `has-*`, `group-has-*`, `peer-has-*`, `not-has-*` and `not-*` variants. Lightning CSS is a development dependency only.
- Add class attribute extraction regressions for PHP ternaries, function arguments, statement terminators, important modifiers and quoted arbitrary values.

### Upgrade notes

- Clear generated CSS caches after upgrading. Compiler state caches invalidate themselves.
- Selectors produced by `has-[…]` and `not-[…]` with type selectors gain a `:is()` wrapper and attribute values gain quotes. Both forms match the same elements with the same specificity.
- Templates that echo class names from PHP or JavaScript expressions inside a `class` attribute can produce utilities that were previously skipped or reported as unknown.

## [1.8.2] - 2026-10-30

### Fixed

- Register Tailwind CSS's upstream legacy utilities, including `bg-gradient-to-*`, `max-w-screen-*`, `overflow-ellipsis`, `decoration-slice`/`decoration-clone`, `flex-grow`/`flex-shrink`, and `start-*`/`end-*`. Legacy background/object position aliases, `order-none`, and `break-words` now share the upstream compatibility registration path. ([#20](https://github.com/inline0/tailwindphp/pull/20), fixes [#19](https://github.com/inline0/tailwindphp/issues/19), thanks @vovayatsyuk)
- Map logical inset properties to the current `inset-s-*` and `inset-e-*` utility names in `tw::cssMap()`.

### Testing

- Include the 13 extracted upstream legacy test cases and 83 additional declaration comparisons captured from the pinned Tailwind CSS 4.3.3 source. The comparisons cover valid and invalid candidates, negative/fractional/arbitrary insets, gradients, flex factors, breakpoint sizes, and custom theme values. Existing upstream assertions remain unchanged.
- Align the CLI version banner with 1.8.2. Development tooling and branding updates since 1.8.1 are included without adding Node dependencies to Composer consumers.

### Upgrade notes

- Clear generated CSS caches after upgrading. Compiler state caches invalidate themselves.
- Legacy gradient aliases intentionally use the upstream `in oklab` declaration directly; modern `bg-linear-*` utilities retain their existing conditional fallbacks.

## [1.8.1] - 2026-10-23

### Fixed

- Bare `rounded` utilities resolve the default radius instead of referencing an undefined variable. Ring-offset widths emit only their offset variables, with width taking precedence over a same-named color. ([#17](https://github.com/inline0/tailwindphp/pull/17), [#18](https://github.com/inline0/tailwindphp/pull/18), thanks @vovayatsyuk)
- Arbitrary background, font, and stroke values select the correct CSS property, including URL backgrounds, background sizes and positions, font families, and stroke widths. Numeric outline and stroke widths serialize in pixels. ([#16](https://github.com/inline0/tailwindphp/issues/16))
- Negative grid row/column starts and ends and negative linear-gradient angles compile correctly. Linear gradients preserve interpolation modifiers, conditional color-space support, and arbitrary-angle fallbacks.
- Inset shadows prefix each complete shadow without inserting `inset` inside nested color or variable functions. CSS variable fallbacks retain Lightning CSS spacing.
- Content extraction recognizes static `->class('…')` and `?->class('…')` arguments, including escaped quotes and arbitrary values, through the public API and CLI. Dynamic arguments and concatenations remain excluded. This adapts the contribution in [#1](https://github.com/inline0/tailwindphp/pull/1) to the current extractor (thanks @fiderosado).

### Testing

- Add 54 reproducible utility cases captured directly from the pinned Tailwind CSS source, plus API, serialization, and CLI regression coverage. Existing upstream test expectations are unchanged.
- Fetch and verify Tailwind CSS 4.3.3 remains the latest stable release; the reference remains pinned to that release.

### Upgrade notes

- Clear generated CSS caches after upgrading. Compiler state caches invalidate themselves.

## [1.8.0] - 2026-10-15

### Added

- Class merging follows tailwind-merge 3.7.0, including Tailwind CSS 4.3 logical sizing, spacing, borders and corners, scrollbar utilities, named containers, decimal fractions, font-weight and family-name hints, and full-name slash lookups. Theme getters include their upstream `themeKey` metadata.
- A reproducible configuration generator and differential corpus verify all 379 upstream class groups and their conflicts against the pinned JavaScript implementation, including variants, important modifiers, and validators.

### Fixed

- Class conflict resolution recognizes `columns-auto`, bare `bg-conic`, container-size names such as `inline-2xl`, `max-h-none`, deprecated `shadow-inner`, and logical-side overrides from axis shorthands. Color functions such as `color()` and `light-dark()` are no longer treated as arbitrary lengths.
- Plugin `addBase()` styles are emitted in `@layer base`, including utilities-only imports and empty candidate builds. Plugins loaded through reference imports still register utilities while keeping base styles out of the output.
- Forms plugin base styles match @tailwindcss/forms 0.5.11: selectors target the appropriate input or select element and place attribute matching inside `:where()`, preserving the upstream specificity.
- Companion updates recognize the new `tailwind-merge@3.7.0` tag naming and monorepo test paths, and regenerate configuration and reference cases before testing.

### Changed

- Pin @tailwindcss/typography 0.5.20, whose runtime styles match the previous release. Tailwind CSS 4.3.3, clsx 2.1.1, CVA 0.7.1 and tw-animate-css 1.4.0 remain current.

## [1.7.1] - 2026-10-14

### Fixed

- The CLI resolves `@source` files, directories, and globs relative to the input CSS file, applies `@source not` exclusions to scanning and watching, and keeps automatic scanning out of `.git`, `vendor`, and `node_modules`. Explicit sources can include dependencies. ([#8](https://github.com/inline0/tailwindphp/issues/8))
- Long options accept space-separated values (`--input app.css`, `--output styles.css`, `--cwd path`) as well as `=`, `-i -` reads stdin, and missing values produce an error. ([#13](https://github.com/inline0/tailwindphp/issues/13))
- `--optimize` keeps readable output in both build and watch mode; `--minify` remains the minified serializer path. ([#9](https://github.com/inline0/tailwindphp/issues/9))
- Plugin `addVariant()` selectors and predefined `matchVariant()` values are applied to the rule, including typography element modifiers. ([#10](https://github.com/inline0/tailwindphp/issues/10))
- CSS mixin `@apply` rules are retained literally inside their parent, in order, including optional bodies. Different mixins no longer merge as identical declaration blocks. ([#12](https://github.com/inline0/tailwindphp/issues/12))
- Reference imports work before or after the utilities import. Built-in virtual modules honour `reference`, remain available to `@apply`, and can subsequently be imported for emitted CSS. ([#14](https://github.com/inline0/tailwindphp/issues/14))
- Composer declares the ctype and mbstring extensions used by the compiler. ([#11](https://github.com/inline0/tailwindphp/issues/11))
- Code quality passes with PHPStan 2.3.1 without suppressing its two new comparison diagnostics. ([#15](https://github.com/inline0/tailwindphp/issues/15))
- Watch mode refreshes filesystem metadata, detects deleted sources, and excludes its generated stylesheet from watched files.

## [1.7.0] - 2026-08-16

### TailwindCSS 4.3.3 alignment

- Updated the internal TailwindCSS reference from v4.3.0 to v4.3.3 and re-extracted every test suite against it. The default theme picks up the new `--font-sans` stack (`-apple-system, BlinkMacSystemFont, 'Segoe UI', …` replacing `ui-sans-serif, system-ui`), achromatic `oklch(… 0 none)` forms for zinc/neutral/mauve greys, and preflight's `:-moz-focusring:where(:not(iframe))` focus rule.
- Bare spacing values now compile through the `--spacing(N)` function like the reference: a multiplier of `1` resolves to `var(--spacing)` itself (`.p-1 { padding: var(--spacing) }`), a multiplier of `0` to a plain zero length, and `space-x-0`/`space-y-0` short-circuit their reverse `calc()` to `margin-inline: 0` / `margin-block: 0`. `auto-cols-*`/`auto-rows-*` accept bare spacing multipliers, `text-*/N` line-height modifiers use the same pipeline, and `mask-linear-*`/`mask-conic-*` emit `0deg`/`1deg` directly.
- Shadow, text-shadow, and drop-shadow opacity modifiers accept every valid opacity value (`isValidOpacityValue`) instead of only positive integers; `shadow-inset-none` emits `inset 0 0 #0000`; `replaceShadowColors()` was rewritten on the value parser and now finds the color in any shadow position, recognizes all color functions (including `--alpha(…)`) and length functions, and treats a single unknown `var(…)` as the color.
- Constant folding matches v4.3.3: `0<unit>` keeps its unit inside `calc(…)`, `0<unit> * <number>` folds to `0<unit>` inside functions, and divisions only fold up to two-decimal precision. `isLength()` accepts `--spacing(…)`, `isColor()` accepts `--alpha(…)`, attribute selectors accept non-ASCII idents (`[data-état]`), and the walk context exposes `index` and `siblings`.

### Added

- Functional `@utility` definitions now implement the full `--value(…)`, `--modifier(…)`, and `--default(…)` resolution engine — literal values, bare-value data types (`number`, `integer`, `ratio`, `percentage`), arbitrary values with data-type hints (`[*]`, `[integer]`, …), theme namespaces including sub-namespace lookups (`--value(--text-*--line-height)`), fraction/modifier interplay, and multiple same-name definitions with different value types. Previously the body was emitted verbatim for any value.
- `@variant` works inside author CSS, including comma-separated compound variants and stacked variants (`@variant hover:focus`), nested `@variant` rules, and `@custom-variant`s that reference other custom variants. Top-level body-less `@variant` and `@variant` with `@slot` convert to `@custom-variant` for backwards compatibility.
- `@apply` recognizes CSS mixins: an `@apply` of only dashed idents is emitted literally per the CSS mixins draft, mixing mixins with utilities throws, and `@apply` with a body throws — matching the reference error messages.
- `@media reference { … }` (the expansion of `@import "…" reference`) wraps its contents in a reference context: `@theme`, `@utility`, and `@custom-variant` definitions inside it are usable while emitting no CSS of their own.
- The color-mix polyfill is a faithful port of the reference pass: theme-variable chains inline, unresolvable `var(…)`/`currentcolor` mixes fall back to the first color, `@supports (color: color-mix(in lab, red, red))` splits at the declaration position, and the evaluation engine reproduces lightningcss's color math — OKLab/OKLCH/Lab/LCH conversions and chroma-bisection gamut mapping verified value-for-value against lightningcss output. User-authored `@property` rules join the fallback pipeline, split into `:root, :host` and universal buckets by `inherits`.
- `@utility` name validation ports the v4.3.3 rules (upstream #19505): at most one `/`, `%` only at the end after a digit, dots only between digits, and escaped names (`@utility foo-1\/2`) unescape before validation.
- The `compile()` options accept `'polyfills'` (e.g. `POLYFILL_NONE` to disable the `@property` fallback emission for CSS modules).

### Fixed

- `has-*` compounding lost its `:has(…)` wrapper entirely (`has-checked` compiled to `.has-checked\:flex:checked`) due to a lost write-back after the selector walk; nested compounds like `group-has-checked` and `not-has-checked` now produce the reference selectors.
- `not-*` rejects pseudo-element variants (`not-first-letter`), matches the reference for attribute variants (`:not([aria-selected="true"])`), and container query negation handles named containers via the value parser (`@container name not (…)`).
- `aria-*`/`data-*` variants validate their generated attribute selector and reject invalid values instead of emitting broken CSS.
- Block-form `@custom-variant` definitions compound correctly: `@slot` substitution splices nodes in place (previously it wrapped them in a stray `&` rule, breaking `group-*`/`peer-*`/`not-*`/`has-*` over custom variants, and could loop forever on custom variants building on other custom variants).
- Multiple utilities registered under the same root all get a chance to compile (`null` from one no longer aborts the rest), matching the reference compile loop.
- The variant order comparator reproduces the reference's BigInt bitmask semantics exactly; `@apply` resolves conflicting utilities in sorted candidate order, so `.foo { @apply content-["a"] content-["b"]; }` and the reverse both resolve to `"b"`.
- `@source inline( "…" )` tolerates whitespace around the argument.
- CSS functions (`--spacing(…)`, `theme(…)`) now substitute inside every compiled candidate — including utilities applied via `@apply` and `@utility` bodies — matching the reference's per-candidate substitution.
- Class attribute scanning matches the value up to the quote that opened it, so an arbitrary value containing the other quote (`class="before:content-['*'] flex"`) no longer truncates the rest of the attribute. Previously every candidate after such a class was silently dropped.
- `@import "tailwindcss/theme.css" theme(reference)` applies the reference option (any `theme(…)` import modifier now expands onto the imported `@theme` blocks): referenced variables emit no `:root` block and utilities carry `var()` fallbacks (`var(--color-red-500, oklch(63.7% .237 25.331))`).
- `aspect-*` resolves through the `--aspect` theme namespace as a variable reference (`aspect-video` → `aspect-ratio: var(--aspect-video)`) instead of hardcoding an inlined ratio, and accepts decimal fractions like the reference.
- Lazily-evaluated `--theme(--font-sans, initial)`-style theme references keep their target variable alive: a full build now emits `--font-sans`/`--font-mono` alongside `--default-font-family`/`--default-mono-font-family`. Previously the emitted `var(--font-sans)` pointed at a pruned variable, so `font-family` on `html` computed to the user-agent default instead of the sans stack.
- `Cli\Application::VERSION` reports the package version again (it was frozen at 1.0.0), and `CliTest` now derives the expected version from the CHANGELOG's latest release heading so a stale constant fails the suite.

### Changed

- The v4.3.1 selector-parser rewrite is ported 1:1 (`src/selector-parser.php`: structured `list`/`complex`/`compound`/`function`/`combinator` AST, `:nth-child(… of <selector>)` support, minified and pretty printing), with its test suite re-ported from the new upstream file (22 → 40 tests). The nesting flattening that rewrite powers is ported as `handleNesting()` in `src/ast.php`: `&` substitutes with the reference's `:is(…)` optimization rules (dropping the `:is()` wrapper only where provably equivalent, `:scope` for parentless `&`, universal-selector removal inside compounds), conditional at-rules hoist around the emitted rules with adjacent merging, and nesting below the first declaration-carrying level stays native — `LightningCss::transformNesting` now only downlevels that remainder, exactly the split between tailwindcss and lightningcss in the reference pipeline.
- Declarations inside every style rule and keyframe block reorder per lightningcss's declaration-handler model (`declaration.rs`): parseable values are absorbed into their property handler's pending state and flushed at the end of the block in the fixed handler-chain order (background, border, outline, …, display, position, …, mask, container), while custom properties, unhandled properties, and vendor-prefixed properties pass through in source order. `var(…)`/`env(…)` references and CSS-wide keywords stay `Property::Unparsed` in the reference, so they flush their owning handler (and the size handler, whose unparsed match arm is unguarded) and pass through in place. Important declarations form their own block appended after the normal ones. This reproduces reference orderings like `background: #00f; display: inline-flex` and preflight's `hr`/`sub, sup` blocks, and un-pends the last upstream inline snapshot, which depended on it.
- CSS nesting now flattens conditional at-rules (`@media`, `@supports`, `@container`, `@starting-style`) **in place, preserving source order**, and merges only directly adjacent siblings with identical conditions — replacing the previous hoist-to-end-and-merge-by-condition strategy, which destroyed cascade order for variant-heavy rules. `@property` registrations emit before hoisted `@keyframes`, shadowed duplicate declarations within a rule are removed (custom properties keep the last value; vendor-prefixed duplicates are preserved), and both the full and incremental serializer paths produce byte-identical output.
- lightningcss parity for the bumped toolchain: `white`/`black` shorten to `#fff`/`#000`, the CSS2 pseudo-elements (`::before`, `::after`, `::first-letter`, `::first-line`) downlevel to their single-colon legacy form, `@media not (feature)` normalizes to `not all and (feature)`, a parentless `&` serializes as `:scope`, number-form oklch/oklab lightness serializes as a percentage (`oklch(0.971 …)` → `oklch(97.1% …)`), angle values in registered custom properties canonicalize to degrees (`3rad` → `171.887deg`), and `-webkit-mask-composite` uses the legacy keywords (`intersect` → `source-in`). Fraction utilities fold to percentages (`w-1/2` → `width: 50%`, `w-1/3` → `33.3333%`; custom properties keep the `calc()` form), adjacent logical pairs with identical values merge into their shorthand (`space-x-0` → `margin-inline: 0`), empty `var()` fallbacks serialize with a space (`var(--tw-blur, )`), content string tokens serialize double-quoted, and `@property` fallback declarations echo the authored initial value while the printed `@property` block strips zero-length units.
- Test extraction understands the v4.3.3 test suites: `describe()`-nested tests are extracted (they were silently skipped, which is how the missing `container` utility went unnoticed), and every `run()` call shape is parsed — Prettier-multiline calls, css template second arguments, template-literal variable bindings, chained array methods, and the `{ polyfills: Polyfills.None }` options argument. `composer extract` now re-extracts variants, and the suite grew from 4,187 to 4,427 tests (utilities 547 → 706, variants 139 → 164, index 78 → 148, css-functions 60 → 64, selector-parser 22 → 40).

### Upgrade notes

- Generated output changes byte-wise versus 1.6.x in the ways listed above (spacing multiplier forms, in-place media order, declaration reordering, color and pseudo-element serialization, duplicate-declaration removal). The CSS is functionally equivalent per the TailwindCSS v4.3.3 reference, but caches keyed on output hashes will bust once.
- Port deviations of record, all lightningcss serialization details with no cascade effect: vendor-prefix duplication counts (the reference emits the authored `-webkit-*` line **and** a generated one where we emit one line), selector-list splitting for downleveled pseudo-elements (`::file-selector-button` and `::-webkit-datetime-edit-*` stay in their authored selector lists instead of splitting into per-pseudo rules), double-quote normalization of authored single-quoted strings (`'Segoe UI'` stays single-quoted), and shorthand combination of co-occurring longhands (never exercised by Tailwind's one-longhand-per-concern output). `.p-0` keeps `padding: 0px` where the reference CLI emits `padding: 0`, because the extracted corpus pins the `0px` form for `--spacing(0)`.

## [1.6.1] - 2026-08-16

### Added

- The `container` layout component utility, ported from `utilities.static('container')`: `width: 100%` plus one `@media (width >= <breakpoint>)` rule per `--breakpoint-*` theme value, sorted ascending by unit then value and honouring custom `@theme { --breakpoint-* }` definitions. Previously only the container-query utilities (`@container`, `@container/name`) existed and `class="container"` emitted nothing. ([#5](https://github.com/inline0/tailwindphp/pull/5), thanks @rhukster)

### Fixed

- Composed `--tw-*` variable groups are now registered with `@property`, matching the reference engine. Utilities that build a shorthand from several custom properties (`translate: var(--tw-translate-x) var(--tw-translate-y)`) previously declared only the variable they own; the unregistered members had no initial value, the composed declaration was invalid at computed-value time, and the browser silently dropped it — `.translate-x-7` updated `--tw-translate-x` in DevTools while nothing moved. Registrations added for translate, scale, rotate-x/y/z + skew, transition duration and ease, leading and tracking, font-variant-numeric, contain, touch-action, scroll-snap strictness, and border-spacing, and the existing filter registration now fires for every `filter`/`backdrop-*` utility instead of only `drop-shadow-*`. A per-class probe of 104 utilities now matches `tailwindcss` 4.3.0's `@property` output exactly, including the utilities that intentionally register nothing (`translate-none`, `scale-[…]`, `transform-cpu/gpu/none`, `normal-nums`, …). ([#7](https://github.com/inline0/tailwindphp/issues/7))
- `normal-nums` no longer emits `--tw-ordinal: initial` and its sibling resets; like the reference, it only sets `font-variant-numeric: normal`.
- Nested child rules are no longer dropped when the parent rule's `@apply` expands to more than one declaration. `@apply` substitution now walks each parent's subtree recursively (matching `apply.ts`) instead of tracking nested rules by index paths that shift as siblings expand, and a nested `@apply`'s dependency propagates to every ancestor rule so topological ordering stays correct. ([#4](https://github.com/inline0/tailwindphp/pull/4), thanks @rhukster)
- Flattening nested CSS now wraps a grouped parent selector in `:is()` before substituting it for `&`, so `.a, .b { @apply hover:underline dark:bg-red-500; }` produces `:is(.a, .b):hover` instead of binding the variant to the last selector only and leaving the declarations on `.a` unconditionally. A lone parent selector is still substituted verbatim, byte-identical to previous output. ([#6](https://github.com/inline0/tailwindphp/pull/6), thanks @rhukster)

## [1.6.0] - 2026-07-22

### Added

- Persistent construction-state cache. `TailwindCompiler` (and `tw::compile()`) accept a `stateCacheFile` option: the expensive, deterministic phase of compiler construction (framework import resolution, resource parsing, theme accumulation) is persisted per CSS input as an OPcache-shareable PHP file and rehydrated on later constructions, dropping warm construction from roughly 25ms to 6ms with byte-identical output. Cache files validate against an engine fingerprint, the input key, and the mtime/size of any filesystem imports; inputs using a custom `importResolver` bypass the cache. `TailwindPHP\StateCache\compileCssCached()` exposes the same behavior around `compile()`'s array API.
- `TailwindPHP\parseCssState()` and `TailwindPHP\finalizeCssState()` split `parseCss()` into its serializable phase and its closure-rebuilding phase, and `TailwindPHP\compileParsed()` assembles the compiled build API from a parse result. `parseCss()` and `compileAst()` behave exactly as before.

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

[1.7.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.7.0
[1.6.1]: https://github.com/inline0/tailwindphp/releases/tag/v1.6.1
[1.6.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.6.0
[1.5.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.5.0
[1.4.2]: https://github.com/inline0/tailwindphp/releases/tag/v1.4.2
[1.4.1]: https://github.com/inline0/tailwindphp/releases/tag/v1.4.1
[1.4.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.4.0
[1.3.2]: https://github.com/inline0/tailwindphp/releases/tag/v1.3.2
[1.3.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.3.0
[1.2.4]: https://github.com/inline0/tailwindphp/releases/tag/v1.2.4
[1.2.3]: https://github.com/inline0/tailwindphp/releases/tag/v1.2.3
[1.2.2]: https://github.com/inline0/tailwindphp/releases/tag/v1.2.2
[1.2.1]: https://github.com/inline0/tailwindphp/releases/tag/v1.2.1
[1.2.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.2.0
[1.1.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.1.0
[1.0.1]: https://github.com/inline0/tailwindphp/releases/tag/v1.0.1
[1.0.0]: https://github.com/inline0/tailwindphp/releases/tag/v1.0.0

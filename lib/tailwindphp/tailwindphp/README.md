<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="./docs/public/logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="./docs/public/logo-light.svg">
    <img alt="TailwindPHP" src="./docs/public/logo-light.svg" height="56">
  </picture>
</p>

<p align="center">
  A 1:1 port of TailwindCSS 4.x to PHP — generate Tailwind utility CSS with no Node.js and no build step.
</p>

<p align="center">
  <a href="https://github.com/inline0/tailwindphp/actions/workflows/tests.yml"><img src="https://github.com/inline0/tailwindphp/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/tailwindphp/tailwindphp"><img src="https://img.shields.io/packagist/v/tailwindphp/tailwindphp.svg" alt="Packagist"></a>
  <a href="https://github.com/inline0/tailwindphp/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="license"></a>
</p>

---

## What is TailwindPHP?

TailwindPHP is a 1:1 port of TailwindCSS 4.x to PHP. It scans your markup for class names and generates the exact same utility CSS that TailwindCSS produces — entirely in PHP, with no Node.js, no build step, and no extensions beyond a standard PHP 8.2+ build.

**The problem:** TailwindCSS is written in TypeScript and runs on Node.js. Any PHP project that wants Tailwind inherits a second runtime, an `npm install`, and a build step in CI and Docker. For WordPress plugins, framework packages, and apps that generate CSS at runtime, that's friction — or a dealbreaker.

**TailwindPHP solves this** by implementing the Tailwind compiler natively in PHP:

- The complete **TailwindCSS v4.3** surface — all 364 utilities, every variant (hover, focus, responsive, dark mode, container queries, arbitrary values), and byte-for-byte output
- The full directive set — `@import`, `@theme`, `@apply`, `@utility`, `@custom-variant`, `@layer`, `@plugin`, and `@source`
- Tailwind's CSS functions — `theme()`, `--theme()`, `--spacing()`, `--alpha()` — plus `color-mix()` → `oklab()` and the transforms Tailwind delegates to LightningCSS
- **Batteries included** — PHP ports of [clsx](https://github.com/lukeed/clsx), [tailwind-merge](https://github.com/dcastil/tailwind-merge), and [CVA](https://github.com/joe-bell/cva) as `cn()`, `merge()`, `join()`, and `variants()`
- **Plugins** — `@tailwindcss/typography` and `@tailwindcss/forms` ported in full, plus a PHP plugin API for your own
- A **CLI** (`bin/tailwindphp`) that mirrors `@tailwindcss/cli`, plus file-based caching and a CSS minifier
- **4,000+ tests** — output is verified against TailwindCSS's own test suites, extracted from the TypeScript source and re-run on every push

## Quick Start

```bash
composer require tailwindphp/tailwindphp
```

```php
use TailwindPHP\tw;

// Generate CSS from markup — only the classes you actually use
$css = tw::generate('<div class="flex items-center gap-4 p-6 bg-blue-500">');

// Customize the theme with a CSS string
$css = tw::generate(
    '<div class="bg-brand p-4">',
    '@import "tailwindcss"; @theme { --color-brand: #3b82f6; }'
);

// Inspect a class without rendering a full stylesheet
tw::computedProperties('p-4');      // ['padding' => '1rem']
tw::computedValue('text-blue-500'); // 'oklch(.546 .245 262.881)'
```

Build conditional class strings and resolve conflicts (clsx + tailwind-merge):

```php
use function TailwindPHP\cn;

cn('px-2 py-1', 'px-4');             // 'py-1 px-4'
cn('btn', ['btn-primary' => true]);  // 'btn btn-primary'
```

Declarative component variants (CVA):

```php
use function TailwindPHP\variants;

$button = variants([
    'base' => 'inline-flex items-center rounded-md font-medium',
    'variants' => [
        'intent' => ['primary' => 'bg-blue-500 text-white', 'ghost' => 'hover:bg-accent'],
        'size'   => ['sm' => 'h-9 px-3', 'lg' => 'h-11 px-8'],
    ],
    'defaultVariants' => ['intent' => 'primary', 'size' => 'sm'],
]);

$button(['size' => 'lg']); // 'inline-flex … bg-blue-500 text-white h-11 px-8'
```

## CLI

A drop-in port of `@tailwindcss/cli` — same options, no Node.js:

```bash
# Build CSS from an input file
./vendor/bin/tailwindphp -i ./src/app.css -o ./dist/styles.css

# Watch and rebuild on change
./vendor/bin/tailwindphp -i ./src/app.css -o ./dist/styles.css --watch

# Minify for production
./vendor/bin/tailwindphp -i ./src/app.css -o ./dist/styles.css --minify
```

Point `@source` at the templates to scan in your input CSS:

```css
@import "tailwindcss";
@source "./templates";
```

## Documentation

Full documentation lives at [tailwindphp.com](https://tailwindphp.com) (or in [`docs/`](./docs) if you're reading the repo).

- [Getting Started](./docs/getting-started.md) — install and generate your first stylesheet
- [Usage](./docs/usage/) — content scanning, theme, directives, imports, and `@source`
- [Class Utilities](./docs/class-utilities/) — `cn()`, `merge()`, `join()`, and `variants()`
- [Plugins](./docs/plugins/) — typography, forms, and writing your own
- [API](./docs/api.md) — the full `tw` surface and the `TailwindCompiler` instance
- [CLI](./docs/cli.md) — `bin/tailwindphp` reference
- [Advanced](./docs/advanced/) — architecture, caching, and performance

## Credits

TailwindPHP ports:

- [TailwindCSS](https://tailwindcss.com) by Tailwind Labs
- [clsx](https://github.com/lukeed/clsx) by Luke Edwards
- [tailwind-merge](https://github.com/dcastil/tailwind-merge) by Dany Castillo
- [CVA](https://github.com/joe-bell/cva) by Joe Bell

## License

MIT

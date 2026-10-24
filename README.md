<p align="center">
  <a href="https://blockstudio.dev">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="./.github/logo-dark.svg">
      <source media="(prefers-color-scheme: light)" srcset="./.github/logo-light.svg">
      <img alt="Blockstudio" src="./.github/logo-light.svg" height="50">
    </picture>
  </a>
</p>

<p align="center">
  The block framework for WordPress
</p>

<p align="center">
  <a href="https://blockstudio.dev/docs"><img src="https://img.shields.io/badge/docs-blockstudio.dev-blue" alt="Documentation"></a>
  <a href="https://github.com/inline0/blockstudio/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-GPL--2.0-blue" alt="License"></a>
</p>

---

Drop a `block.json` and a PHP template into a folder. Blockstudio registers the block, renders the template, and handles fields, assets, and scoped styles. No webpack, no Vite, no React components to write.

```
theme/blockstudio/hero/
├── block.json      ← fields, settings, metadata
├── index.php       ← template (PHP, Twig, or Blade)
├── style.scss      ← auto-compiled, scoped, minified
└── script.js       ← ES module imports from npm
```

## Why Blockstudio

WordPress blocks are powerful but building them is painful. The official approach requires React, JSX, a build toolchain, and hundreds of lines of JavaScript for even a simple block.

Blockstudio removes all of that. You write a JSON config and a PHP template. The framework handles block registration, field rendering, asset compilation, and editor integration. Everything lives in the filesystem, which makes blocks easy to version control, share across projects, and generate with AI coding tools.

## Features

- **27 field types** - text, repeater, tabs, code, classes, color, files, and more, all configured in JSON with conditions, validation, and defaults
- **PHP, Twig, and Blade** - write templates in your preferred language with the same `$a` variable across all three
- **File-based pages** - create WordPress pages from HTML templates with explicit API or CLI syncing, keyed block merging, and editing controls
- **Site Editor templates** - define WordPress templates and template parts from PHP, Twig, Blade, or HTML while preserving Site Editor customizations
- **File-based patterns** - define block patterns as template files, auto-registered without any PHP code
- **Block islands** - hydrate cache-safe blocks or batch-render dynamic fragments after the initial response
- **HTML-to-block parser** - converts standard HTML into native WordPress block markup with extensible renderers and element mapping
- **Extensions** - add custom fields to any core or third-party block via a JSON file
- **Asset pipeline** - SCSS compilation, ES module imports from npm, automatic minification, and scoped loading by naming convention
- **Tailwind CSS v4** - server-side compilation via TailwindPHP with candidate-based caching, no Node.js or CLI needed
- **Bundled UI library** - 59 headless `bsui/*` components on the Interactivity API, from buttons and selects to dialogs, menus, and forms, composable in any template
- **Database layer** - use REST and PHP/JS clients across MySQL, SQLite, JSONC, post meta, custom post types, or Storh
- **Storage** - persist field values in post meta or site options, queryable via `WP_Query` and the REST API
- **Custom fields** - reusable field definitions shared across multiple blocks via filesystem or PHP filter
- **Custom field types** - register namespaced controls with primitive, array, or object values and typed storage schemas
- **SEO integration** - block content visible to Yoast SEO and Rank Math for editor analysis
- **AI-ready** - ships a pre-built context file with documentation and JSON schemas for LLM coding assistants
- **50+ PHP and JS hooks** - customize every aspect of the framework

## Quick Start

1. Install via Composer:

```bash
composer require blockstudio/blockstudio
```

2. Create a `blockstudio` folder in your theme
3. Add a block:

**block.json**

```json
{
  "$schema": "https://blockstudio.dev/schema/block",
  "name": "theme/hero",
  "title": "Hero",
  "blockstudio": {
    "attributes": [
      { "id": "heading", "type": "text", "label": "Heading" },
      { "id": "description", "type": "textarea", "label": "Description" }
    ]
  }
}
```

**index.php**

```php
<section useBlockProps class="hero">
  <h1><?php echo $a['heading']; ?></h1>
  <p><?php echo $a['description']; ?></p>
</section>
```

The block appears in the editor with the fields you defined. Add a `style.scss` or `script.js` to the same folder and they get compiled and enqueued automatically.

## Requirements

- PHP 8.2+
- WordPress 6.7+

## Documentation

**[blockstudio.dev/docs](https://blockstudio.dev/docs)**

## Building Dependencies

The Markdown, YAML, and updater libraries are build dependencies, not public
runtime dependencies. Their complete dependency trees are bundled under
`BlockstudioVendor` in `lib/`, alongside the existing private libraries.

Use PHP 8.4 for the current PHP-Scoper toolchain:

```bash
composer install
composer scope
composer dump-autoload --optimize
composer test:dependencies
```

Commit regenerated files with their lockfile changes. `composer scope -- --check`
rebuilds in a temporary directory and fails if the bundled copies are stale.
It does not modify the working tree. Existing Tailwind, SCSS, minifier, and
Storh bundles are not regenerated by this command.

`lib/dependencies/versions.json` names every package bundled under `lib/` with
the version locked in `composer.lock`, including the bundles this command does
not regenerate. SBOM and security tooling can read it instead of reconstructing
the list from the lockfile and the directory tree. `composer test:dependencies`
fails if a package under `lib/` is missing from it.

`npm run build:release` uses that check, stages a production-only
Composer autoloader, tests isolation with conflicting public libraries in both
load orders, and creates `blockstudio.zip`. Distribution CI and release packaging
use the same script. This command only builds an archive; it does not publish.

## Repository Tooling

Use the Node version in `.nvmrc` with npm. These are contributor requirements;
the installed WordPress plugin does not require Node or Effect.

```bash
nvm use
npm ci
npm ci --prefix packages/registry
npm run lint
npm run typecheck
npm run format:check
npm run build
```

Scripts and configuration are TypeScript. Node runs the tooling directly,
while webpack still builds the React editor through `@wordpress/scripts`,
preserving WordPress externals, asset manifests, entry paths and lazy chunks.
TypeScript 5.9 remains available for generators that use its compiler API.

The shared Effect tooling in `scripts/lib/` handles typed failures, child-process
lifetimes, HTTP status checking and scoped temporary files. Generator/parser
code stays synchronous where it is a short deterministic local operation.
The dependency-install bootstrap stays dependency-free shell; PHP dependency
scoping and its Composer bootstrap stay PHP/shell. Effect is never shipped to
the browser or in the release ZIP.

Oxfmt replaces repository Prettier formatting. Oxlint replaces ESLint, including
typed dot-notation checking and a small `eslint-plugin-react` compatibility
bridge for `no-deprecated`. Prettier itself stays installed because the editor
formats HTML/CSS in the browser. PHP coding standards are unchanged.

Author bundled UI scripts in `src/ui/` and frontend helpers in `src/runtime/`.
`npm run build:runtime` emits their existing `.js` and `.inline.js` paths under
`includes/`, without bundling or rewriting WordPress/CDN imports. Commit those
outputs alongside source changes. `npm run runtime:check` rejects stale or
unowned outputs, and `npm run audit:tooling` rejects unchecked TypeScript and
unexpected handwritten JavaScript. Deliberate JS asset fixtures and scoped
vendor libraries remain unchanged; generated JS is still required by WordPress.

Full suites and all browser tests run in GitHub CI only. Local validation is
limited to static checks, builds, and focused non-E2E tests. `npm run check` is
the CI aggregate, not a local preflight. CI also builds the WordPress assets and
checks ZIP/Composer installations before a tooling candidate can be merged.

### Browser tests without Docker

CI runs the Playwright specs against wp-env. Contributors without Docker can
run single specs against [WordPress Playground](https://wordpress.github.io/wordpress-playground/),
which runs WordPress in WebAssembly. This is a local convenience, not a
replacement for CI.

```bash
npx playwright install chromium                                   # once
npm run test:e2e:playground -- tests/e2e/types/text.ts            # fresh site, run, stop
npm run playground:start                                          # keep a seeded site on :8888, Ctrl-C stops it
```

The script installs the Composer dependencies of the plugin and `tests/theme`
when they are missing (PHP and Composer are required), reads the port and
`wp-config` constants from `tests/wp-env/.wp-env.json`, mounts the repository
as the plugin and `tests/theme` as the `theme` theme, activates both, sets
`/%postname%/` permalinks and calls the E2E seed endpoint. Every run starts from a
new site and uses `playwright.wp-env.config.ts` unchanged. PHP 8.4 and the
latest WordPress are the defaults; set `PLAYGROUND_PHP` or `PLAYGROUND_WP` to
change them. The Playground CLI is pinned in `package.json`.

Differences from wp-env: SQLite instead of MySQL, one site (no PHPUnit, empty or
implementation environments), and no WP-CLI or Docker. `tests/e2e/cli.ts` and
`tests/e2e/seo.ts` call `wp-env run cli` and therefore still need wp-env. Port
8888 must be free.

## Translation Maintenance

English strings are extracted into `languages/blockstudio.pot`. Existing German
and formal German translations are retained. Additional locales are generated
with Codex CLI using `gpt-6-luna` and compiled into the same WordPress translation
formats. The plugin itself never calls AI or downloads translations.

```bash
npm run i18n:locales                 # Refresh the checked-in WordPress locale snapshot
npm run i18n:translate -- --dry-run   # Report missing translations, without calling Codex
npm run i18n:translate               # Generate missing entries in all locales
npm run i18n:translate -- --locale fr_FR
npm run i18n                        # Recompile after manual PO edits
npm run i18n:check                  # Validate extraction and compiled outputs, offline
npm run i18n:coverage               # Verify every snapshot locale is complete, offline
```

Generation requires an authenticated Codex CLI supporting `exec`,
`--ignore-user-config`, `--ephemeral`, and `--output-schema`. It uses saved CLI
authentication; no API key is stored in the repository. Only the public source
strings, translator context, and existing forms for the requested entries are
sent. Codex runs in a temporary read-only workspace with shell execution, apps,
web search, and project instructions disabled.

The locale snapshot combines GlotPress entries with a WordPress locale code and
variants from the WordPress core translation API. `en_US` uses the source strings.
Plural counts and expressions come from upstream metadata, not the model.
Refreshing locales is explicit; normal checks never fetch upstream data.

`--concurrency` accepts 1-8 workers (default 4), `--batch-size` accepts 1-250
entries (default 200), and `--model` can explicitly select another available
Codex model. The default remains `gpt-6-luna` with high reasoning effort;
failures never silently switch it.
Each validated batch is saved atomically. Reruns fill missing forms only, preserve
nonempty translations, and refuse to overwrite concurrent edits. Fuzzy entries
and changed source plurals require human review. A failed run exits nonzero and
keeps completed batches so the same command can resume.

`languages/.i18n-lock/` prevents concurrent generation. Normal completion and
interrupts remove it. After a hard kill, remove a stale lock only after confirming
that no translation process is still running. New catalogs identify their AI
origin in the PO headers; native-speaker corrections are encouraged and retained
on subsequent runs. CI validates completeness, placeholders and generated files
without calling Codex. Full suites and E2E remain CI-only.

## License

[GPL-2.0](LICENSE)

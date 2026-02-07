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

- **26 field types** - text, repeater, tabs, code, classes, color, files, and more, all configured in JSON with conditions, validation, and defaults
- **PHP, Twig, and Blade** - write templates in your preferred language with the same `$a` variable across all three
- **File-based pages** - create WordPress pages from HTML templates with automatic syncing, keyed block merging, and editing controls
- **File-based patterns** - define block patterns as template files, auto-registered without any PHP code
- **HTML-to-block parser** - converts standard HTML into native WordPress block markup with extensible renderers and element mapping
- **Extensions** - add custom fields to any core or third-party block via a JSON file
- **Asset pipeline** - SCSS compilation, ES module imports from npm, automatic minification, and scoped loading by naming convention
- **Tailwind CSS v4** - server-side compilation via TailwindPHP with candidate-based caching, no Node.js or CLI needed
- **Storage** - persist field values in post meta or site options, queryable via `WP_Query` and the REST API
- **Custom fields** - reusable field definitions shared across multiple blocks via filesystem or PHP filter
- **SEO integration** - block content visible to Yoast SEO and Rank Math for editor analysis
- **AI-ready** - ships a pre-built context file with full documentation and JSON schemas (~48k tokens) for LLM coding assistants
- **50+ PHP and JS hooks** - customize every aspect of the framework

## Quick start

1. Install the plugin
2. Create a `blockstudio` folder in your theme
3. Add a block:

**block.json**
```json
{
  "$schema": "https://app.blockstudio.dev/schema/block",
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

## License

[GPL-2.0](LICENSE)

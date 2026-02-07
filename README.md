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

**26 field types** including text, repeater, tabs, code, classes, color, files, and more. All configured in JSON with conditions, validation, defaults, and storage options.

**File-based pages and patterns.** Write HTML templates and Blockstudio parses them into native WordPress block content. Pages sync to the database automatically. Patterns register in memory with no database writes.

**HTML-to-block parser.** The parser converts standard HTML into WordPress block markup. Extensible via filters for custom block types and element mappings. This is what powers pages, patterns, and AI-generated content.

**Asset pipeline.** SCSS compilation, ES module imports from npm, automatic minification, and scoped loading. Name your files (`style.scss`, `script.js`, `editor.css`) and everything is handled automatically.

**Tailwind CSS v4.** Server-side compilation via TailwindPHP. No Node.js, no CLI. Compiled CSS is cached to disk based on extracted class names.

**Storage.** Store field values in post meta or site options instead of block attributes. Queryable via `WP_Query`, available through the REST API.

**AI-ready.** A pre-built context file (`blockstudio-llm.txt`) ships with the full documentation and JSON schemas (~48k tokens). Point any LLM tool to the URL and your coding assistant gets complete knowledge of the framework.

## Quick start

1. Install the plugin
2. Create a `blockstudio` folder in your theme
3. Add a block:

**block.json**
```json
{
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
  <h1><?= $heading ?></h1>
  <p><?= $description ?></p>
</section>
```

That's it. The block appears in the editor with the fields you defined.

## Requirements

- PHP 8.2+
- WordPress 6.7+

## Documentation

**[blockstudio.dev/docs](https://blockstudio.dev/docs)**

## License

[GPL-2.0](LICENSE)

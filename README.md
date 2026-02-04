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

Create production-ready WordPress blocks using a filesystem-based approach. Native fields, inline assets, scoped styles, Twig/Blade support, and more.

## Features

| Feature | Description |
|---------|-------------|
| **Filesystem-based** | Define blocks with `block.json` + template files |
| **Native fields** | 20+ field types with conditions, validation, and storage options |
| **Inline assets** | Auto-compiled CSS/JS with scoping and ES module imports |
| **Template engines** | PHP, Twig, and Blade support out of the box |
| **Block extensions** | Extend core blocks with custom fields |
| **File-based pages** | Create WordPress pages from HTML templates |
| **Tailwind CSS** | Built-in Tailwind v4 integration |

## Quick Start

1. Create a `blockstudio` folder in your theme
2. Add a block folder with `block.json` and `index.php`:

```
theme/
└── blockstudio/
    └── my-block/
        ├── block.json
        └── index.php
```

**block.json**
```json
{
  "name": "theme/my-block",
  "title": "My Block",
  "blockstudio": {
    "attributes": [
      {
        "id": "title",
        "type": "text",
        "label": "Title"
      }
    ]
  }
}
```

**index.php**
```php
<div useBlockProps>
  <h2><?php echo $title; ?></h2>
</div>
```

## Documentation

Full documentation available at **[blockstudio.dev/docs](https://blockstudio.dev/docs)**

## Requirements

- WordPress 6.0+
- PHP 8.2+

## License

GPL-2.0

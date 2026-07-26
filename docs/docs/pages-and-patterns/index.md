---
title: General
description: Create WordPress pages and block patterns from file templates with automatic block parsing.
path: "pages-and-patterns"
order: 50
section: "Pages & Patterns"
subsection: "File Templates"
meta_title: "General"
meta_description: "Create WordPress pages and block patterns from file templates with automatic block parsing."
---

# Pages & Patterns

Blockstudio lets you define WordPress pages, block patterns, and Site Editor templates as files in your theme. Write HTML-like templates, and Blockstudio's parser converts them into native WordPress blocks.

- **Pages** are synced to the database as WordPress posts, keeping your file templates and page content in sync.
- **Patterns** are registered in memory via `register_block_pattern()`, available in the block inserter for users to place anywhere.
- **Site Templates** provide file-backed `wp_template` and `wp_template_part` sources for the WordPress Site Editor.

All three features share the same HTML parser and block syntax.


> **[Building File-Based Pages](/guides/file-based-pages)**
>
> A practical walkthrough of file-based page templates, syncing, and locking.


## Folder Structure

```
theme/
├── pages/
│   ├── about/
│   │   ├── page.json
│   │   └── index.php
│   └── contact/
│       ├── page.json
│       └── index.twig
├── patterns/
    ├── hero/
    │   ├── pattern.json
    │   └── index.php
    └── cta/
        ├── pattern.json
        └── index.blade.php
├── templates/
    └── front-page/
        ├── template.json
        └── index.php
└── parts/
    └── header/
        ├── part.json
        └── index.php
```

Each subfolder contains a JSON config file (`page.json`, `pattern.json`, `template.json`, or `part.json`) and a template file.

## Template Engines

Templates can be written in PHP, Twig, or Blade:

| File | Engine | Requirement |
|------|--------|-------------|
| `index.php` | PHP | None |
| `index.twig` | Twig | [Timber](https://timber.github.io/docs/) |
| `index.blade.php` | Blade | [jenssegers/blade](https://github.com/jenssegers/blade) |

If multiple template files exist, the priority order is: `index.php` > `index.blade.php` > `index.twig`.

Since templates are compiled at initialization time (before any request context), Twig and Blade templates don't have access to dynamic variables. They're useful for built-in functions and filters like `{{ "text"|upper }}` or `{{ strtoupper("text") }}`.

## HTML to Block Mapping

Standard HTML elements are automatically converted to the corresponding WordPress blocks.

### Text

| HTML | Block |
|------|-------|
| `<p>` | `core/paragraph` |
| `<h1>` - `<h6>` | `core/heading` |
| `<ul>` | `core/list` (unordered) |
| `<ol>` | `core/list` (ordered) |
| `<li>` | `core/list-item` |
| `<blockquote>` | `core/quote` |
| `<code>` | `core/code` |
| `<pre>` | `core/preformatted` |

### Media

| HTML | Block |
|------|-------|
| `<img>` | `core/image` |
| `<figure>` | `core/image` (with caption) |
| `<audio>` | `core/audio` |
| `<video>` | `core/video` |

### Layout

| HTML | Block |
|------|-------|
| `<div>`, `<section>` | `core/group` |
| `<hr>` | `core/separator` |
| `<details>` | `core/details` |
| `<table>` | `core/table` |

## Block Syntax

For blocks that don't have a direct HTML equivalent, use the `<block>` element:

```html
<block name="core/cover" url="https://example.com/hero.jpg">
  <h1>Welcome</h1>
  <p>Content inside the cover block.</p>
</block>
```

### Core Blocks

```html
<!-- Pullquote -->
<block name="core/pullquote">
  <p>An important quote.</p>
</block>

<!-- Verse -->
<block name="core/verse">Roses are red,
Violets are blue.</block>

<!-- Cover -->
<block name="core/cover" url="https://example.com/image.jpg">
  <h2>Cover Title</h2>
</block>

<!-- Embed -->
<block name="core/embed" url="https://youtube.com/watch?v=..." providerNameSlug="youtube" />

<!-- Spacer -->
<block name="core/spacer" height="50px" />

<!-- Gallery -->
<block name="core/gallery">
  <img src="https://example.com/1.jpg" alt="Image 1" />
  <img src="https://example.com/2.jpg" alt="Image 2" />
</block>

<!-- Columns -->
<block name="core/columns">
  <block name="core/column">
    <h3>Column 1</h3>
  </block>
  <block name="core/column">
    <h3>Column 2</h3>
  </block>
</block>

<!-- Buttons -->
<block name="core/buttons">
  <block name="core/button" url="/get-started">Get Started</block>
</block>

<!-- Row (flex layout) -->
<block name="core/group" layout='{"type":"flex","flexWrap":"nowrap"}'>
  <p>Item 1</p>
  <p>Item 2</p>
</block>
```

### Custom Blocks

The same syntax works for any registered block:

```html
<block name="blockstudio/section">
  <h1>Welcome</h1>
</block>

<block name="acf/hero" title="Hero Title" background="dark">
  <p>Inner content becomes InnerBlocks.</p>
</block>
```

### Attributes

HTML attributes on `<block>` elements are passed as block attributes. JSON values are supported:

```html
<block name="core/heading" level="3">Custom Heading</block>

<block name="core/group" layout='{"type":"flex","orientation":"vertical"}'>
  <p>Stacked content.</p>
</block>

<img src="https://example.com/photo.jpg" alt="Photo" width="800" height="400" />
```

## Custom Block Renderers

The HTML parser uses a registry-based architecture. Add a builder for a custom
block with `blockstudio/block_tags/builders`:

```php
add_filter( 'blockstudio/block_tags/builders', function( $builders, $parser ) {
    $builders['acf/hero'] = function( array $attrs, string $inner_content ) {
        $inner_blocks = Blockstudio\Block_Tags::parse_inner_blocks( $inner_content );

        return array(
            'blockName'    => 'acf/hero',
            'attrs'        => $attrs,
            'innerBlocks'  => $inner_blocks,
            'innerHTML'    => '',
            'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
        );
    };

    return $builders;
}, 10, 2 );
```

Then use it in any template:

```html
<block name="acf/hero" background="dark">
  <h1>Welcome</h1>
</block>
```

You can also override how core blocks are rendered:

```php
add_filter( 'blockstudio/parser/renderers', function( $renderers, $parser ) {
    $renderers['core/paragraph'] = function( array $attrs, string $inner_content ) {
        $content = trim( $inner_content );
        $attrs['align'] = $attrs['align'] ?? 'center';

        return array(
            'blockName'    => 'core/paragraph',
            'attrs'        => $attrs,
            'innerBlocks'  => array(),
            'innerHTML'    => '<p class="has-text-align-center">' . $content . '</p>',
            'innerContent' => array( '<p class="has-text-align-center">' . $content . '</p>' ),
        );
    };

    return $renderers;
}, 10, 2 );
```

Builder callbacks receive the parsed attributes and raw inner content. They
must return a WordPress block array. See
[Custom renderers](/docs/blocks/rendering#custom-renderers) for the complete
filter order and container example.

### Builder Function Signature

```php
/**
 * @param array  $attrs         Parsed element or block-tag attributes.
 * @param string $inner_content Raw inner content.
 * @return array WordPress block array.
 */
function my_builder( array $attrs, string $inner_content ): array {}
```

## Element Mapping

By default, standard HTML elements like `<h1>`, `<p>`, and `<img>` map to core WordPress blocks. You can override this mapping to point any HTML element to a different block using the `blockstudio/parser/element_mapping` filter:

```php
add_filter( 'blockstudio/parser/element_mapping', function( $mapping ) {
    $mapping['h1'] = 'custom/heading';
    $mapping['h2'] = 'custom/heading';
    $mapping['p']  = 'custom/paragraph';
    $mapping['img'] = 'custom/image';

    return $mapping;
}, 10, 2 );
```

With this filter active, every `<h1>` in your templates will produce a `custom/heading` block instead of `core/heading`. The element's attributes are passed through to the target block. If a renderer is registered for the target block name, it will be used. Otherwise, a generic block structure is created.

This is useful when your theme or plugin provides custom block types that should replace the defaults across all page and pattern templates.

For simple `<p>` mappings, Blockstudio can route inner text directly into a
registered non-core Blockstudio block's `content` richtext attribute. The target
block must declare an attribute with `id: "content"` and `type: "richtext"`.
Nested block tags, mapped child elements, and block-level HTML continue to parse
as inner blocks.

Mapping values may also be callables when the target block depends on element
attributes:

```php
add_filter( 'blockstudio/parser/element_mapping', function( $mapping ) {
    $mapping['div'] = function( array $attrs ) {
        if ( isset( $attrs['width'] ) ) {
            return 'theme/row';
        }

        if ( isset( $attrs['gap'] ) ) {
            return 'theme/flow';
        }

        return 'theme/div';
    };

    return $mapping;
} );
```

Custom blocks mapped from container elements such as `<section>` and `<div>`
parse nested HTML children recursively, so inner headings, images, block tags,
and paragraphs become native child blocks.

## Complete Example

A full page template combining multiple block types:

```html title="index.php"
<div>
  <h1>Welcome to Our Site</h1>
  <p>This is an <strong>introduction</strong> with <em>formatting</em>.</p>

  <block name="core/columns">
    <block name="core/column">
      <h3>Feature One</h3>
      <p>First feature description.</p>
    </block>
    <block name="core/column">
      <h3>Feature Two</h3>
      <p>Second feature description.</p>
    </block>
  </block>

  <block name="core/cover" url="https://example.com/hero.jpg">
    <h2>Our Mission</h2>
    <p>Building amazing things together.</p>
  </block>

  <details>
    <summary>FAQ</summary>
    <p>Answers to common questions.</p>
  </details>

  <block name="core/buttons">
    <block name="core/button" url="/contact">Contact Us</block>
    <block name="core/button" url="/learn-more">Learn More</block>
  </block>
</div>
```

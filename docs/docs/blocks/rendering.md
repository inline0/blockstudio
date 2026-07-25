---
title: Programmatic Rendering
description: Render Blockstudio blocks programmatically via PHP functions or HTML tags.
path: "blocks/rendering"
order: 40
section: "Blocks"
meta_title: "Programmatic Rendering"
meta_description: "Render Blockstudio blocks programmatically via PHP functions or HTML tags."
---

# Programmatic Rendering

With Gutenberg becoming the prominent instrument in creating easily editable websites for clients, it makes sense to create all necessary website areas as blocks. While this approach will cater to most, advanced users and specific use cases might need to use those existing blocks outside the editor.

Blockstudio provides two approaches for rendering blocks programmatically:

- **PHP functions**: `bs_render_block` and `bs_block` for rendering from PHP code
- **HTML tags**: `<bs:block-name>` tags that get replaced with block output anywhere on the page

All Blockstudio specific features like inline styles, scripts, and scoped styles are supported.

[Islands](/docs/blocks/islands) also work through these render paths. A dynamic
island rendered with `bs_render_block()`, `bs_block()`, or a block tag returns
the same placeholder marker as a block rendered from post content, and the
frontend runtime is injected when that marker is present in the response.

## Structured Compositions and Complete Documents

For long-lived tools, previews, exports, and component galleries, use the
versioned `Blockstudio\Render` API. It accepts one declaration or an ordered
list and renders without echoing:

```php
use Blockstudio\Render;

$composition = [
  'root' => 'theme/card',
  'example' => [
    'data' => [
      'heading' => 'Example card',
    ],
    'layers' => [
      [
        'name' => 'theme/button',
        'data' => [
          'label' => 'Continue',
        ],
      ],
    ],
  ],
];

$normalized = Render::normalize($composition);
$html = Render::composition($composition);
$document = Render::document($composition, [
  'title' => 'Component preview',
]);
```

The canonical normalized declaration has four keys:

```php
[
  'name' => 'theme/card',
  'attributes' => [],
  'content' => '',
  'children' => [],
]
```

`data`, `inner`, `innerBlocks`, `root`, `layers`, and `example` are input
conveniences and are normalized away. Nested declarations render recursively
through the same Blockstudio pipeline.

`Render::document()` returns `schemaVersion`, `html`, `body`, `blocks`,
`assets`, `warnings`, and `errors`. The `assets` value separates `head`,
`footer`, `styles`, `scripts`, `modules`, `interactivity`, `ui`, and `tailwind`.
The block list and assets include dependencies referenced by selected templates,
but exclude unrelated and editor-only assets.

To assemble a document around HTML that is already rendered, provide its known
root block names:

```php
$document = Render::document_from_html(
  $rendered_html,
  ['theme/hero', 'theme/button'],
  ['title' => 'Homepage preview']
);
```

Use `Render::content()` for serialized WordPress block content. Consumers that
render several independent documents in one request should call
`Blockstudio\Batch_Render::reset()` between them; `Canvas::documents()` does
this automatically.

## PHP Functions

### Without Data

In its simplest form, the function accepts a single value which is the ID of the block that should be rendered on the page.

```php
bs_render_block('blockstudio/cta');
```

### With Data

To render the block with custom data, an array needs to be used in place of a single value for the first parameter. The value in the data key will be passed to the `$attributes` and `$a` variable inside your block template.

```php
bs_render_block([
  'id' => 'blockstudio/cta',
  'data' => [
    'title' => 'My title',
    'subtitle' => 'My subtitle',
  ],
]);
```

### Nesting

Blocks can be nested within each other using the `bs_block` function in combination with the powerful `$content` variable inside your block templates.

```php title="index.php"
<div>
  <h1><?php echo $a['title']; ?></h1>
  <p><?php echo $a['subtitle']; ?></p>
  <?php echo $content; ?>
</div>
```

```php
echo bs_block([
  'id' => 'blockstudio/cta',
  'data' => [
    'title' => 'My title',
    'subtitle' => 'My subtitle',
  ],
  'content' => bs_block([
    'id' => 'blockstudio/button',
    'data' => [
      'text' => 'Button Text',
    ]
  ])
]);
```

The button block will be rendered in place of the `$content` variable inside the block template.

Embedded blocks rendered with `bs_render_block()` or `bs_block()` always output
frontend-resolved HTML, even when the parent block is currently rendering an
editor preview. Template pseudo-components such as `<RichText />` and
`<InnerBlocks />` are resolved before the embedded block is returned.

### Multiple Slots

It is also possible to create multiple content slots by simply making the `$content` variable an associative array and calling its appropriate keys in the `bs_block` function.

```php title="index.php"
<div>
  <?php echo $content['beforeContent']; ?>
  <h1><?php echo $a['title']; ?></h1>
  <p><?php echo $a['subtitle']; ?></p>
  <?php echo $content['afterContent']; ?>
</div>
```

```php
echo bs_block([
  'id' => 'blockstudio/cta',
  'data' => [
    'title' => 'My title',
    'subtitle' => 'My subtitle',
  ],
  'content' => [
    'beforeContent' => bs_block([
      'id' => 'blockstudio/badge',
      'data' => ['text' => 'Before Content']
    ]),
    'afterContent' => bs_block([
      'id' => 'blockstudio/button',
      'data' => ['text' => 'Button Text']
    ])
  ]
]);
```

## Block Tags

Embed any block using HTML tag syntax. Two formats are supported:

```html
<bs:acme-hero title="Welcome" />
<block name="acme/hero" title="Welcome" />
```

Both render the same block. The `<bs:>` syntax uses the first hyphen as the
namespace separator (`acme-hero` becomes `acme/hero`). The `<block>` syntax
takes the full block name as a `name` attribute.

Both Blockstudio blocks and core WordPress blocks work. Blockstudio blocks
render through the full pipeline (templates, Tailwind, assets). Core blocks
render through WordPress's block rendering system using the built-in
[block renderers](/docs/pages-and-patterns).

### How it works

Block tags behave differently depending on where they appear:

**In block templates and post content**, tags are replaced with rendered HTML
at runtime. The output is the final markup of the referenced block, embedded
directly into the page. There is no WordPress block in the editor for these.
They are purely a rendering mechanism.

**In [Pages, Patterns, and Site Templates](/docs/pages-and-patterns)**, tags are converted to
native WordPress blocks before the file source is synced or registered with
WordPress. This means the blocks appear in the editor, can be edited where the
target supports editing, and go through WordPress's full block lifecycle. The
tag syntax is a shorthand for defining block content in template files.

This distinction matters: block tags in templates produce output, block tags
in pages, patterns, and Site Templates produce native blocks.

### In block templates

Block tags render automatically inside Twig and PHP templates. No setting
needed. This includes the `<bs:>` and `<block>` syntaxes as well as any
registered [prefix](#prefix-shorthands) and alias tags, so a template can emit
them directly instead of calling `bs_render_block()`. The output is rendered
HTML embedded directly into the block's template output:

```twig title="index.twig"
<div class="my-page">
  <bs:mytheme-card title="Featured" />
  <block name="core/separator" />
  <bs:core-paragraph>Rendered by WordPress</bs:core-paragraph>
  <block name="core/heading" level="2">Also WordPress</block>
  <theme-card title="Prefix shorthand" />
</div>
```

### In post content

Page-level rendering (post content, widget areas) is opt-in:

```json title="blockstudio.json"
{
  "blockTags": {
    "enabled": true
  }
}
```

Or via filter:

```php
add_filter('blockstudio/settings/block_tags/enabled', '__return_true');
```

### Syntax

Self-closing tags:

```html
<bs:mytheme-cta title="Get started" variant="primary" />
<block name="core/separator" />
```

Paired tags with inner content:

```html
<bs:mytheme-section layout="wide">
  <p>Content inside the block.</p>
</bs:mytheme-section>

<block name="core/group">
  <block name="core/paragraph">Inside a group.</block>
</block>
```

Mix and match freely. Both syntaxes can nest inside each other:

```html
<bs:mytheme-section>
  <block name="core/heading" level="2">Title</block>
  <bs:mytheme-card title="Features" />
  <block name="core/paragraph">Description text.</block>
</bs:mytheme-section>
```

### Prefix shorthands

Block tags can also use project-specific prefix shorthands. Register a prefix
with one or more namespaces and Blockstudio resolves `<prefix-slug>` to the
first registered block in that namespace order:

```php title="functions.php"
add_filter('blockstudio/block_tags/prefixes', function($prefixes) {
  $prefixes['theme'] = ['theme-components', 'bsui'];

  return $prefixes;
});
```

```html
<theme-card title="Homepage" />
<theme-button label="Get started" />
<theme-ui-feature-matrix />
```

With the example above, `<theme-card>` resolves to `theme-components/card`, `<theme-button>` falls back to `bsui/button` when `theme-components/button` is not registered, and `<theme-ui-feature-matrix>` resolves to `theme-components/ui-feature-matrix`.

Prefixes can also compose. When a prefixed tag does not resolve directly and the
remaining slug is itself a registered prefix tag, resolution recurses, so a
brand prefix can sit on top of a namespace prefix:

```php title="functions.php"
add_filter('blockstudio/block_tags/prefixes', function($prefixes) {
  $prefixes['theme'] = ['theme-components'];
  $prefixes['ui'] = ['bsui'];

  return $prefixes;
});
```

```html
<theme-ui-input />
```

`<theme-ui-input>` has no `theme-components/ui-input` block, so it falls through to
the `ui` prefix and resolves `bsui/input`. Direct matches always win over nested
resolution, and allow/deny rules apply to the final resolved block.

You can also configure prefixes in `blockstudio.json`:

```json title="blockstudio.json"
{
  "blockTags": {
    "prefixes": {
      "theme": ["theme-components", "bsui"]
    }
  }
}
```

Prefixes must be lowercase letters or numbers, start with a letter, and cannot
contain dashes. Explicit aliases from
`blockstudio/block_tags/tag_aliases` take precedence over prefix resolution.
Unknown prefixed tags are left untouched.

### Core blocks

Any block with a registered [renderer](/docs/pages-and-patterns) can be used:

```html
<bs:core-paragraph>A paragraph</bs:core-paragraph>
<bs:core-heading level="2">A heading</bs:core-heading>
<bs:core-separator />
<bs:core-image url="photo.jpg" alt="Photo" />
<bs:core-buttons>
  <bs:core-button url="/about">About</bs:core-button>
</bs:core-buttons>
```

Or with `<block>` syntax:

```html
<block name="core/group">
  <block name="core/columns">
    <block name="core/column">
      <block name="core/paragraph">Left column</block>
    </block>
    <block name="core/column">
      <block name="core/paragraph">Right column</block>
    </block>
  </block>
</block>
```

### Allow and deny lists

Control which blocks can render via tags:

```json title="blockstudio.json"
{
  "blockTags": {
    "enabled": true,
    "allow": ["mytheme/*", "core/*"],
    "deny": ["mytheme/internal-*"]
  }
}
```

`allow` restricts to only matching patterns. `deny` excludes matching patterns
and takes precedence. Both support `*` wildcards via `fnmatch()`. These apply
to both syntaxes and both template-level and page-level rendering.

Filters: `blockstudio/block_tags/allow` and `blockstudio/block_tags/deny`.

### HTML passthrough

Attributes prefixed with `data-` pass through to the rendered block's root
element. Attributes prefixed with `html-` also pass through, with the prefix
stripped. Works with both syntaxes.

```html
<bs:mytheme-card
  title="My Card"
  html-class="featured-card"
  html-id="main-card"
  data-analytics="card-click"
/>

<block name="core/paragraph" html-class="highlight" data-section="intro">
  Highlighted paragraph.
</block>
```

### Custom renderers

Register custom block renderers for additional block types. Renderers take
an attributes array and inner content string, and return a WordPress block
array:

```php
add_filter('blockstudio/block_tags/builders', function ($builders, $parser) {
    $builders['myplugin/custom-block'] = function (array $attrs, string $inner_content) {
        $html = '<div class="my-block">' . $inner_content . '</div>';
        return [
            'blockName'    => 'myplugin/custom-block',
            'attrs'        => $attrs,
            'innerBlocks'  => [],
            'innerHTML'    => $html,
            'innerContent' => [$html],
        ];
    };
    return $builders;
}, 10, 2);
```

For container blocks that need to parse inner content into child blocks, add a
second entry inside the same filter:

```php
$builders['myplugin/wrapper'] = function (array $attrs, string $inner_content) {
    $inner_blocks = Blockstudio\Block_Tags::parse_inner_blocks($inner_content);
    $content = ['<div class="my-wrapper">'];
    foreach ($inner_blocks as $block) {
        $content[] = null;
    }
    $content[] = '</div>';
    return [
        'blockName'    => 'myplugin/wrapper',
        'attrs'        => $attrs,
        'innerBlocks'  => $inner_blocks,
        'innerHTML'    => '<div class="my-wrapper"></div>',
        'innerContent' => $content,
    ];
};
```

`blockstudio/block_tags/builders` applies to both block tags and custom blocks
selected through `blockstudio/parser/element_mapping`. This is the appropriate
hook when an element-mapped project block needs to move inner text into a
Blockstudio field or construct a custom block array.

`blockstudio/block_tags/renderers` and `blockstudio/parser/renderers` receive
the same registry and callback shape. They remain available for compatibility
and final parser-level overrides. The filters run in this order:

1. `blockstudio/block_tags/builders`
2. `blockstudio/block_tags/renderers`
3. `blockstudio/parser/renderers`

A later filter can replace a renderer registered by an earlier one.

### Programmatic usage

Apply tag rendering to any string from PHP:

```php
$html = apply_filters('blockstudio/block_tags/render', $content);
```

### Components

Block tags pair well with [Components](/docs/blocks/components). Components
are blocks that only render programmatically and never appear in the editor.

```html
<bs:mytheme-card title="My Card" description="Card content." />
```

### Parser

Block tags are processed by a lightweight string scanner that replaces
DOMDocument. The parser handles both `<bs:>` and `<block>` syntax, nested
same-name tags with depth tracking, quoted attribute values, and recursive
container blocks. It is purpose-built for this specific use case, benchmarks
faster than both DOMDocument and WordPress's `WP_HTML_Tag_Processor`, and
avoids DOMDocument's known issues with namespace prefix stripping and
attribute lowercasing.

For [Pages, Patterns, and Site Templates](/docs/pages-and-patterns), the same parser also
handles raw HTML elements (`<p>`, `<div>`, `<h1>`, etc.), mapping them to
their corresponding WordPress core blocks.

When an element mapping points `<p>` to a registered non-core Blockstudio block
that declares a `content` richtext attribute, simple inner text is routed into
that `content` attribute automatically. Nested block tags, mapped child
elements, or block-level HTML still fall back to normal inner-block parsing.

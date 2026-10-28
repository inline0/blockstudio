---
title: Programmatic Rendering
description: Render Blockstudio blocks programmatically via PHP functions or HTML tags.
path: "blocks/rendering"
order: 39
section: "Blocks"
subsection: "Rendering"
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
  'contentElement' => 'main',
  'contentClasses' => ['component-preview'],
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

Complete documents may set `bodyClasses`/`bodyAttributes` and optionally wrap
the rendered body with `contentElement`, `contentClasses`, and
`contentAttributes`. The wrapper is restricted to a safe element name; the
returned `body` remains the unwrapped rendered content.
The `head` and `footer` options append trusted document markup at their
respective positions and are included in the returned asset closure.

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

## Rendering Outside the Page

Blockstudio puts a `<!--blockstudio/namespace/slug-->` comment in front of
every block it renders. On a frontend page the buffer reads these comments to
see which blocks are present, loads their assets, and removes the comments
before the response is sent.

Output that does not pass through that buffer keeps the comments. This applies
to REST responses, feeds, AJAX handlers, and HTML you store or send somewhere
else after `do_blocks()`, `Render::content()`, or `bs_render_block()`. Use
`bs_strip_render_markers()` to remove them:

```php
$html = bs_strip_render_markers(do_blocks($post->post_content));
```

The function removes the comments of registered Blockstudio blocks and leaves
everything else untouched, including other HTML comments. It matches the same
markers the page buffer removes. Call it after block registration (`init` or later).

Do not strip markup that is still printed into a buffered frontend page. The
page needs the comments to know which block assets to load, and Blockstudio
removes them itself at the end.

Removing the comments does not add assets. Block CSS and JavaScript files are
only injected by the page buffer. Use
[`bs_block_assets()`](/docs/blocks/assets#inspecting-a-blocks-assets) or
`Render::document()` when the consumer needs them as well.

## Inspecting Blocks

Use `bs_block_fields()` after block registration (on `init` or later) to get
the resolved field definitions without reading internal registries:

```php
$fields = bs_block_fields('theme/card');

if ($fields === null) {
  // Unknown block, or not a Blockstudio block.
}
```

The return value is the registered field-definition array, including attribute
filters, expanded custom fields, overrides, and nested groups, tabs, and
repeaters. A Blockstudio block with no fields returns `[]`. The result is a
copy: changing it does not change the registered block.

These are definitions, not saved values. To validate saved markup, use
[`bs_validate_block()`](#validating-stored-values) instead of maintaining a
separate field walker. WordPress-owned attributes remain separate.

Fields added by [extensions](/docs/extensions) are not part of
`bs_block_fields()`. Use `bs_block_extension_fields()` to get them. It matches
extensions by block name, so it also works for core and third-party blocks:

```php
$fields = array_merge(
  bs_block_fields('core/paragraph') ?? [],
  bs_block_extension_fields('core/paragraph')
);
```

The return value lists the field definitions of every matching extension, in
the order the extensions are registered, with the same structure as
`bs_block_fields()`. A block that no extension matches returns `[]`. Extension
values are saved in the same `attrs.blockstudio.attributes` object as the
block's own field values, so all ids share one namespace. If two extensions
define the same id, both definitions are returned and edit the same value; on
render, the definition of the extension registered later applies.

To read the values of a saved block as its template receives them, use
[`bs_block_values()`](/docs/blocks/attributes/rendering#reading-attributes-programmatically).

Use [`bs_block_assets()`](/docs/blocks/assets#inspecting-a-blocks-assets) to
inspect the block's discovered CSS and JavaScript. For a complete preview
document, including nested blocks and shared runtime dependencies, use
`Render::document()` as described above.

### Validating stored values

Use `bs_validate_block()` after block registration (`init` or later) to check
programmatically generated or imported block attributes before saving them:

```php
$block = parse_blocks($content)[0];
$findings = bs_validate_block($block['blockName'], $block['attrs']);

if ($findings === null) {
  // No Blockstudio fields or matching extensions apply.
} elseif ($findings === []) {
  // No stored-value problems were found.
} else {
  // Review the findings before saving.
}
```

Pass the complete `attrs` array, not just `blockstudio.attributes`. A
Blockstudio render accepts declared fields at the top level as well, and
nested values win when both are supplied. Extensions of core and third-party
blocks read only `blockstudio.attributes`. An empty Blockstudio block is
managed and returns `[]`, not `null`.

Validation is explicit and read-only. It does not render templates, change
values, save posts, query media, or run populate callbacks. It does not run
automatically during saves, page synchronization, or rendering. It is not a
sanitizer, an authorization check, or a check that an attachment exists.

Each finding contains these stable keys:

```php
[
  'code' => 'unknown_option',
  'path' => 'steps/1/style',
  'source' => 'block',
  'message' => 'This value is not among the field options.',
  'value' => 'c',
  'expected' => ['a', 'b'],
]
```

Use `code`, not the translated `message`, in tooling. `source` is `block` or
`extension`. Paths use storage keys and zero-based repeater row or selection
indices, without a leading slash. Literal `~` and `/` in a key are escaped as `~0` and
`~1`. A top-level group prefixes its children (`cta_title`); anonymous groups
and tabs add no level. Groups with an ID inside another group or a repeater
are not supported by the builder, so their keys are not accepted. Message
fields have no stored value. A files field permits its `<id>__size` companion.
At the root of a Blockstudio block, bookkeeping keys and global HTML
attributes forwarded to the rendered element are not unknown fields. This
does not permit arbitrary keys in repeater rows or extensions of core blocks.

| Code | Meaning | `expected` |
| --- | --- | --- |
| `unknown_field` | No retained field declares this storage key. | Known storage keys. |
| `unknown_option` | A selection is not among the known options. | Allowed option values. |
| `wrong_type` | The stored shape is not supported by the field. | Expected storage family or type. |
| `too_few_rows`, `too_many_rows` | A present repeater is outside its `min` or `max`. | The row bound. |
| `definition_conflict` | Applicable definitions for one key require different storage shapes. | Source and field type of each definition. |
| `shadowed_value` | A nested value overrides a different top-level field value. | The winning nested value. |
| `extension_top_level` | The template can read a top-level value but extension bindings cannot. | Its required path under `blockstudio/attributes`. |
| `unknown_attribute` | A top-level key of a Blockstudio block is not read by the render path and is dropped. | Registered attribute names, extension fields and `blockstudio`. |
| `missing_container` | `attributes`, `disabled`, `name` or `editor` is stored at the top level and `blockstudio` is missing. | Its required path under `blockstudio`. |
| `invalid_container` | `blockstudio` or `blockstudio.attributes` is not an object. | `object`. |

A top-level key of a Blockstudio block that is neither a registered attribute
(its fields, the attributes WordPress adds for `supports`, `lock` and
`metadata`), nor a field of a matching extension, nor `blockstudio`, nor a
bookkeeping key or forwarded HTML attribute is dropped before the template
runs. Such a key, for example a mistyped field ID or a values object stored
without its `blockstudio` wrapper, produces a finding because the block
renders without that value. Blocks that only carry extension fields are not
checked for top-level keys.

The accepted shapes follow rendering: multiple selects and checkboxes can
contain a list, one option object, or an imported scalar, including numeric
zero. Numeric strings are accepted for numbers. Files can contain attachment
IDs, ID objects, lists, or string references. Link and icon values must be
objects; custom field types use their registered attribute type.
An `attributes` field holds a list of pairs with a non-empty `attribute` name
and a scalar `value`; optional metadata such as `data.media` is preserved.

WordPress validates registered top-level attributes before rendering, which
can reject a shape accepted inside `blockstudio.attributes`. For example, a
radio value of `"a"` works in the nested object but is dropped at the top level,
where its registered type is `object`. Such a value produces `wrong_type`.
Use the nested object for the same storage shape the editor writes.

Missing fields are not required. Empty nested values (`null`, `false`, `''`,
`[]`) and disabled fields do not produce value errors. An explicitly empty
repeater is still checked against `min`; an absent or disabled repeater is not.
An empty top-level value is reported if WordPress drops it and restores a
non-empty default instead. The same value in the nested object can clear it.
Definition conflicts are reported even when the key has no saved value.

Static options and populate options already resolved at registration are
checked without invoking populate again. Remote `fetch` options and populate
arguments containing `{attributes.*}` are skipped. Color and gradient values
may be custom unless `disableCustomColors` or `disableCustomGradients` is set.
This checks stored values, not `default`, `fallback`, or editor validation rules.

Matching extension fields are included for every block they target. Later
definitions win within the block or its extensions at the root, as on render.
A block's own field and an extension field remain separate definitions of
the same key and both apply. Within repeater rows, the renderer reads the
first field with a matching ID in each source, so its options and bounds
apply. Duplicate definitions are retained for conflict diagnostics:
incompatible storage shapes produce `definition_conflict`, even when a
later duplicate is not read on render.

The function checks one block. Walk `innerBlocks` when validating a document;
synced patterns and other references are not expanded automatically. The
[`wp bs blocks validate`](/docs/dev/cli#validate-stored-fields) command walks
the blocks of one or more posts and reports their block paths.

Sites with a custom render-time resolver can adapt the findings without
changing stored data:

```php
add_filter('blockstudio/blocks/attributes/validate', function($findings, $name, $attrs) {
  if ($name !== 'my-theme/card') {
    return $findings;
  }

  return array_values(array_filter($findings, function($finding) {
    return !($finding['path'] === 'customReference' && $finding['code'] === 'wrong_type');
  }));
}, 10, 3);
```

Always return a list of findings; returning `[]` accepts the block. This
filter runs only for managed blocks, never for the `null` result.

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

Both render the same block. The `<bs:>` syntax turns a hyphen into the
namespace separator (`acme-hero` becomes `acme/hero`). Hyphens are tried from
left to right and the first registered candidate wins, so namespaces that
contain hyphens also resolve: `<bs:acme-ui-button>` tries `acme/ui-button`,
then `acme-ui/button`. The `<block>` syntax takes the full block name as a
`name` attribute.

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

Prefix and alias tags remain supported authoring conveniences. The canonical,
portable spelling is always `<bs:namespace-slug>`, because it records the real
block name without depending on project filters:

```html
<bs:theme-components-card />
<bs:bsui-input />
```

Blockstudio examples, generated diagnostics, and programmatic output use this
canonical spelling. Projects that want to remove an older shorthand surface
can use the standalone, dry-run-first
[canonical tag migration](/docs/dev/migration/v7#canonical-block-tags) without
booting WordPress.

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
stripped. Works with both syntaxes. A passthrough attribute replaces the root
element's existing value rather than merging with it, so `html-class` replaces
the block's own classes.

A Blockstudio block also puts the global attributes it does not declare as
fields on its root element: `id`, `style`, `role`, `title`, `lang`, `dir`,
`hidden`, `tabindex`, and every `aria-*` attribute. A declared field always
wins, so a block with a `title` field receives the value in `$a['title']`
instead. Forwarded styles go through `safecss_filter_attr()`, the filter
WordPress applies to style attributes in post content, and are appended to a
style the template already sets.

Attribute values are HTML, so entities are decoded once when a tag is parsed:
`title="Tom &amp; Jerry"` reaches the template as `Tom & Jerry` and is escaped
again on output. Quoted values may contain `>`, as Tailwind variants and
animation selectors do, in self-closing tags as well as in paired ones.

Any other attribute a block does not declare, including `class`, never reaches
the HTML. With `WP_DEBUG` on, Blockstudio reports each such attribute once per
request through `_doing_it_wrong()`. Declare a field for it, or write it as a
`data-`, `aria-`, or `html-` attribute.

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
that declares a `content` richtext attribute, inner HTML is routed into that
`content` attribute automatically, including inline tags such as `code` and
`img` that are element-mapped at block level. A paragraph whose only child is an
image becomes the block `<img>` maps to. Nested block tags or block-level HTML
still fall back to normal inner-block parsing. `<block name="core/shortcode">`
keeps its shortcode text as the block's content.

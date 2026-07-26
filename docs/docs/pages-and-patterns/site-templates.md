---
title: Site Templates
description: Create WordPress Site Editor templates and template parts from Blockstudio files.
path: "pages-and-patterns/site-templates"
order: 53
section: "Pages & Patterns"
subsection: "File Templates"
meta_title: "Site Templates"
meta_description: "Create WordPress Site Editor templates and template parts from Blockstudio files."
---

# Site Templates

Site Templates let block themes define WordPress `wp_template` and
`wp_template_part` sources with the same file-first syntax used by Blockstudio
pages and patterns.

Use Site Templates when you want to control the theme layout that WordPress
selects through the Site Editor template hierarchy: front page, single posts,
archives, search, 404 pages, headers, footers, and other template parts.

## Pages, Patterns, Or Site Templates?

| Feature | Creates | Best for |
| --- | --- | --- |
| Pages | Posts or custom post type entries | Real content pages, docs pages, landing pages, and routeable content. |
| Patterns | Block patterns | Reusable sections inserted by editors. |
| Site Templates | `wp_template` and `wp_template_part` file sources | Theme layout controlled by the Site Editor and WordPress template hierarchy. |

Site Templates do not create normal pages. They provide the file source that
WordPress uses when resolving a block theme template. If a user customizes the
template in the Site Editor, WordPress stores that customization in the
database and it wins over the file source until it is reset.

## Folder Structure

Place full templates in `templates/` and template parts in `parts/`:

```text
theme/
  templates/
    front-page/
      template.json
      index.php
    single/
      template.json
      index.twig
  parts/
    header/
      part.json
      index.php
    footer/
      part.json
      index.php
```

Each folder has a manifest plus one source file.

| File | Engine |
| --- | --- |
| `index.php` | PHP or HTML-like block markup |
| `index.blade.php` | Blade |
| `index.twig` | Twig |
| `index.html` | HTML-like block markup |

If several source files exist, Blockstudio uses this order:

1. `index.php`
2. `index.blade.php`
3. `index.twig`
4. `index.html`

The source is compiled once into native WordPress block markup. It should not
depend on the current request or the current post. Put runtime behavior inside
dynamic blocks.

## template.json

`template.json` defines a full Site Editor template.

```json title="templates/front-page/template.json"
{
  "slug": "front-page",
  "title": "Front Page",
  "description": "Homepage template",
  "postTypes": ["page"]
}
```

### Properties

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `slug` / `name` | `string` | Folder name | WordPress template slug. Use exact hierarchy slugs such as `front-page`, `single`, `archive-product`, or `404`. |
| `title` | `string` | Title from slug | Display title in the Site Editor. |
| `description` | `string` | `""` | Display description in the Site Editor. |
| `postTypes` | `string[]` | `[]` | Post types for custom templates. Core hierarchy templates can omit this. |
| `source` / `template` / `file` | `string` | Auto-detected index file | Optional source file path relative to the template folder. |
| `status` | `string` | `"publish"` | Template status. |
| `sync` | `boolean` | `false` | Reserved for future database materialization. User customizations are never overwritten in v1. |
| `meta` | `object` | `{}` | Custom metadata for helper APIs. Unknown manifest keys are also stored here. |

Template slugs are not prefixed. WordPress needs exact names for the template
hierarchy:

```text
index
home
front-page
single
single-post
single-product
archive
archive-product
page
page-about
404
search
taxonomy-product_cat
```

## part.json

`part.json` defines a Site Editor template part.

```json title="parts/header/part.json"
{
  "slug": "header",
  "title": "Header",
  "description": "Main site header",
  "area": "header"
}
```

### Properties

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `slug` / `name` | `string` | Folder name | WordPress template part slug. |
| `title` | `string` | Title from slug | Display title in the Site Editor. |
| `description` | `string` | `""` | Display description in the Site Editor. |
| `area` | `string` | `"uncategorized"` | Template part area. Common values are `header`, `footer`, and `sidebar`. |
| `source` / `template` / `file` | `string` | Auto-detected index file | Optional source file path relative to the part folder. |
| `status` | `string` | `"publish"` | Template part status. |
| `meta` | `object` | `{}` | Custom metadata for helper APIs. Unknown manifest keys are also stored here. |

## Authoring Markup

Site Template sources use the same parser as pages and patterns:

```php title="templates/front-page/index.php"
<div class="site-shell">
  <block name="core/template-part" slug="header" />

  <main>
    <h1>Welcome</h1>
    <p>This template is compiled from Blockstudio files.</p>
    <block name="core/post-content" />
  </main>

  <block name="core/template-part" slug="footer" />
</div>
```

You can use:

- standard HTML elements such as `<h1>`, `<p>`, `<ul>`, and `<div>`
- `<block name="namespace/block">`
- `<bs:namespace-block>`
- configured block tag prefixes
- Blockstudio blocks and components

Template part blocks receive the active theme attribute when WordPress renders
the compiled template.

## Site Editor Customizations

Blockstudio provides the file source. WordPress owns user customizations.

The lookup order is:

1. Site Editor database customization
2. native active theme `.html` file
3. Blockstudio file-backed source
4. parent theme or plugin fallbacks

When a user saves a Blockstudio file-backed template in the Site Editor,
WordPress creates a normal `wp_template` or `wp_template_part` database entry.
That saved entry wins over future file changes. Resetting or deleting the
customization reveals the latest file-backed source again.

Blockstudio does not write Site Editor edits back to files in v1.

## Performance

Template discovery is cached per request and persisted with file-watch metadata.
Changing a manifest or source file invalidates the cached compiled content.
Database customizations are not cached over: WordPress still checks them first.

Runtime integrations that compose files from several roots can supply a
[logical discovery source](/docs/dev/discovery-sources) for templates and
template parts. Manifests and selected source templates may live in different
physical roots while retaining one logical theme tree.

## PHP API

```php
$templates = Blockstudio\Site_Templates::templates();
$parts = Blockstudio\Site_Templates::parts();

$template = Blockstudio\Site_Templates::get_template('front-page');
$header = Blockstudio\Site_Templates::get_part('header');

$paths = Blockstudio\Site_Templates::get_paths();
```

Pass an explicit list to read and compile only exact slugs, paths, or source
paths:

```php
$templates = Blockstudio\Site_Templates::templates([
    'front-page',
]);

$parts = Blockstudio\Site_Templates::parts([
    'header',
]);

$errors = Blockstudio\Site_Templates::selection_errors('templates');
```

Omitting the argument preserves complete registry behavior. Passing an empty
list returns no items. Conventional slug and path requests narrow discovery to
their matching directories; custom manifest slugs may require a metadata scan,
but unrelated sources are still not compiled or returned.

Template helper functions are also available:

```php
blockstudio_site_templates();
blockstudio_site_template_parts();
blockstudio_site_template('front-page');
blockstudio_site_template_part('header');
```

## Hooks

The full Site Template extension surface is:

| Hook | Type | Description |
| --- | --- | --- |
| `blockstudio/site_templates/template_paths` | Filter | Adjust full-template discovery roots. |
| `blockstudio/site_templates/part_paths` | Filter | Adjust template-part discovery roots. |
| `blockstudio/site_templates/paths` | Filter | Adjust both path lists as `templates` and `parts`. |
| `blockstudio/site_templates/template_candidates` | Filter | Adjust source-file candidates for a manifest. |
| `blockstudio/site_templates/templates` | Filter | Adjust templates returned by the registry API. |
| `blockstudio/site_templates/parts` | Filter | Adjust template parts returned by the registry API. |
| `blockstudio/site_templates/template_content` | Filter | Adjust compiled full-template source before parsing. |
| `blockstudio/site_templates/part_content` | Filter | Adjust compiled template-part source before parsing. |
| `blockstudio/site_templates/parser` | Filter | Replace the `Html_Parser` instance for one item. |
| `blockstudio/site_templates/source_compiled` | Action | Runs immediately before one selected source is compiled. |
| `blockstudio/site_templates/discovered` | Action | Runs after a cold discovery and compilation pass. |
| `blockstudio/site_templates/registered` | Action | Runs after that rebuilt registry has been persisted. |

Add or replace discovery paths:

```php
add_filter('blockstudio/site_templates/template_paths', function ($paths) {
    $paths[] = get_stylesheet_directory() . '/site-templates';
    return $paths;
});

add_filter('blockstudio/site_templates/part_paths', function ($paths) {
    $paths[] = get_stylesheet_directory() . '/site-parts';
    return $paths;
});

add_filter('blockstudio/site_templates/paths', function ($paths) {
    return $paths;
});
```

Filter discovered data:

```php
add_filter('blockstudio/site_templates/templates', function ($templates) {
    return $templates;
});

add_filter('blockstudio/site_templates/parts', function ($parts) {
    return $parts;
});
```

Filter source before parsing:

```php
add_filter('blockstudio/site_templates/template_content', function ($content, $template) {
    return $content;
}, 10, 2);

add_filter('blockstudio/site_templates/part_content', function ($content, $part) {
    return $content;
}, 10, 2);
```

Change source candidates or the parser:

```php
add_filter(
    'blockstudio/site_templates/template_candidates',
    function ($candidates, $directory, $manifest) {
        $candidates[] = $directory . '/template.custom.php';
        return $candidates;
    },
    10,
    3
);

add_filter(
    'blockstudio/site_templates/parser',
    function ($parser, $item) {
        return $parser;
    },
    10,
    2
);
```

`discovered` and `registered` are cache-rebuild lifecycle actions. They do not
fire on a warm registry cache hit.

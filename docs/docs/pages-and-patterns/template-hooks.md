---
title: Hooks
description: PHP filters and actions for pages, patterns, Site Templates, and the HTML parser.
path: "pages-and-patterns/template-hooks"
order: 55
section: "Pages & Patterns"
meta_title: "Hooks"
meta_description: "PHP filters and actions for pages, patterns, Site Templates, and the HTML parser."
---

# Hooks

Filters and actions available for customizing pages, patterns, Site Templates, and the HTML parser.

## Parser

### blockstudio/block_tags/builders

Register block-array builders used by block tags and element-mapped custom
blocks. The callback for each block receives an attributes array and raw inner
content.

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

`blockstudio/block_tags/renderers` and `blockstudio/parser/renderers` receive
the same registry. They run after `blockstudio/block_tags/builders`, in that
order, and can replace an earlier builder for the same block.

### blockstudio/parser/element_mapping

Override the default HTML element to block mapping. By default, standard HTML elements like `<h1>`, `<p>`, and `<img>` map to core WordPress blocks. Use this filter to point any element to a different block.

```php
add_filter( 'blockstudio/parser/element_mapping', function( $mapping ) {
    $mapping['h1']  = 'custom/heading';
    $mapping['h2']  = 'custom/heading';
    $mapping['p']   = 'custom/paragraph';
    $mapping['img'] = 'custom/image';

    return $mapping;
}, 10, 2 );
```

If `<p>` maps to a registered non-core Blockstudio block with a `content`
richtext attribute, simple inner text is copied to that attribute. Nested block
tags or mapped child elements keep the normal inner-block parsing behavior.

## Pages

### blockstudio/pages/paths

Filter the directories scanned for page templates.

```php
add_filter( 'blockstudio/pages/paths', function( $paths ) {
    $paths[] = get_template_directory() . '/custom-pages';
    $paths[] = MY_PLUGIN_DIR . '/pages';
    return $paths;
} );
```

### blockstudio/pages/manifest_scan_interval

Filter how often frontend requests rescan page roots for a newly added
collection manifest. Existing manifest changes are also guarded by file-watch
metadata. The default is five seconds in local and development environments
and 20 seconds elsewhere.

```php
add_filter( 'blockstudio/pages/manifest_scan_interval', function() {
    return 10;
} );
```

### blockstudio/pages/create_post_data

Filter post data before creating a new page.

```php
add_filter( 'blockstudio/pages/create_post_data', function( $post_data, $page_data ) {
    $post_data['post_author'] = 1;
    return $post_data;
}, 10, 2 );
```

### blockstudio/pages/update_post_data

Filter post data before updating an existing page.

```php
add_filter( 'blockstudio/pages/update_post_data', function( $post_data, $post, $page_data ) {
    return $post_data;
}, 10, 3 );
```

### blockstudio/pages/synced

Action fired after all pages have been synced.

```php
add_action( 'blockstudio/pages/synced', function( $registry ) {
    // $registry is the Page_Registry instance
} );
```

### blockstudio/pages/post_created

Action fired after a page post is created.

```php
add_action( 'blockstudio/pages/post_created', function( $post_id, $page_data ) {
    // Do something after page creation
}, 10, 2 );
```

### blockstudio/pages/post_updated

Action fired after a page post is updated.

```php
add_action( 'blockstudio/pages/post_updated', function( $post_id, $page_data ) {
    // Do something after page update
}, 10, 2 );
```

## Patterns

### blockstudio/patterns/paths

Filter the directories scanned for pattern templates.

```php
add_filter( 'blockstudio/patterns/paths', function( $paths ) {
    $paths[] = get_stylesheet_directory() . '/custom-patterns';
    $paths[] = MY_PLUGIN_PATH . '/patterns';
    return $paths;
} );
```

### blockstudio/patterns/registered

Action fired after all patterns have been registered.

```php
add_action( 'blockstudio/patterns/registered', function( $registry ) {
    // $registry is the Pattern_Registry instance
} );
```

## Site Templates

### blockstudio/site_templates/template_paths

Filter the directories scanned for `template.json` files.

```php
add_filter( 'blockstudio/site_templates/template_paths', function( $paths ) {
    $paths[] = get_stylesheet_directory() . '/site-templates';
    return $paths;
} );
```

### blockstudio/site_templates/part_paths

Filter the directories scanned for `part.json` files.

```php
add_filter( 'blockstudio/site_templates/part_paths', function( $paths ) {
    $paths[] = get_stylesheet_directory() . '/site-parts';
    return $paths;
} );
```

### blockstudio/site_templates/paths

Filter both discovery path lists together. The value contains `templates` and
`parts` arrays.

```php
add_filter( 'blockstudio/site_templates/paths', function( $paths ) {
    return $paths;
} );
```

### blockstudio/site_templates/template_candidates

Filter source-file candidates after the manifest and logical source tree have
been resolved.

```php
add_filter(
    'blockstudio/site_templates/template_candidates',
    function( $candidates, $directory, $manifest ) {
        $candidates[] = $directory . '/template.custom.php';
        return $candidates;
    },
    10,
    3
);
```

### blockstudio/site_templates/templates

Filter full templates returned by the registry API.

```php
add_filter( 'blockstudio/site_templates/templates', function( $templates ) {
    return $templates;
} );
```

### blockstudio/site_templates/parts

Filter template parts returned by the registry API.

```php
add_filter( 'blockstudio/site_templates/parts', function( $parts ) {
    return $parts;
} );
```

### blockstudio/site_templates/template_content

Filter a template source string before Blockstudio parses it into blocks.

```php
add_filter( 'blockstudio/site_templates/template_content', function( $content, $template ) {
    return $content;
}, 10, 2 );
```

### blockstudio/site_templates/part_content

Filter a template part source string before Blockstudio parses it into blocks.

```php
add_filter( 'blockstudio/site_templates/part_content', function( $content, $part ) {
    return $content;
}, 10, 2 );
```

### blockstudio/site_templates/parser

Filter the `Blockstudio\Html_Parser` instance used for one compiled template or
part. Return an `Html_Parser`; other values fall back to the default parser.

```php
add_filter(
    'blockstudio/site_templates/parser',
    function( $parser, $item ) {
        return $parser;
    },
    10,
    2
);
```

### blockstudio/site_templates/discovered

Action fired after a cold discovery and compilation pass, before the rebuilt
registry is persisted.

```php
add_action( 'blockstudio/site_templates/discovered', function( $registry ) {
    // Inspect the rebuilt Site_Template_Registry.
} );
```

### blockstudio/site_templates/registered

Action fired after a rebuilt file-backed Site Editor template registry has been
persisted.

```php
add_action( 'blockstudio/site_templates/registered', function( $registry ) {
    // $registry is the Site_Template_Registry instance
} );
```

The `discovered` and `registered` actions do not fire on a warm registry cache
hit.

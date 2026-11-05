---
title: Storage
description: Store field values in different locations like post meta or options.
path: "blocks/storage"
order: 21
section: "Blocks"
subsection: "Attribute Values"
meta_title: "Storage"
meta_description: "Store field values in different locations like post meta or options."
---

# Storage

By default, all field values are stored as block attributes within the block comment in the post content. The storage feature allows you to additionally store values in post meta (`wp_postmeta`) or options (`wp_options`), making them accessible outside the block context.

## Why Use Storage?

Block attributes are stored inside HTML comments in the post content, which means they're only accessible when parsing block content. By using additional storage locations, you can:

- **Query posts by field values** using `meta_query`
- **Access values via REST API** for headless setups
- **Share data with other plugins** like SEO or caching plugins
- **Create global settings** that persist across posts

## Storage Types

| Type | Location | Scope | Best For |
|------|----------|-------|----------|
| `postMeta` | `wp_postmeta` table | Per post | Queryable post-specific data |
| `option` | `wp_options` table | Global (site-wide) | Site settings, shared values |


> **Note**
>
> Block attribute storage (in the post content HTML comment) always happens automatically. The `storage` property adds **additional** storage locations - it doesn't replace block storage.


## Configuration

Add the `storage` property to any field definition:

```json title="block.json"
{
  "name": "blockstudio/example",
  "title": "Example Block",
  "blockstudio": {
    "attributes": [
      {
        "id": "subtitle",
        "type": "text",
        "label": "Subtitle",
        "storage": {
          "type": "postMeta",
          "postMetaKey": "my_subtitle"
        }
      }
    ]
  }
}
```

## Storage Properties

| Property | Type | Description |
|----------|------|-------------|
| `type` | `string` \| `string[]` | Additional storage location(s): `"postMeta"`, `"option"` |
| `postMetaKey` | `string` | Custom meta key (default: `{block_name}_{field_id}`) |
| `optionKey` | `string` | Custom option key (default: `{block_name}_{field_id}`) |

### Single vs Multiple Storage

```json
// Add post meta storage
"storage": {
  "type": "postMeta"
}

// Add both post meta and option storage
"storage": {
  "type": ["postMeta", "option"]
}
```

## How It Works

1. **Registration**: When WordPress loads, Blockstudio registers meta keys and settings based on your field configurations
2. **Editing**: Values are managed as block attributes in the editor (always stored in block)
3. **Saving**: Post meta is synced when the post is saved. Site options are synced when the post is published or a published post's field value changes.

Autosaves and revisions do not sync either storage location. Draft, pending, private, and scheduled posts can update their own post meta, but do not change live site options. A scheduled post syncs options when WordPress publishes it.

Values are copied literally, including backslashes, quotes, and nested repeater values. Do not add escaping to field values to compensate for WordPress meta storage; Blockstudio handles that boundary. Site options keep the same literal values without additional slashing.

If an earlier version stripped backslashes from a post's meta copy, save the post again after upgrading. The original field value in its block attributes is used to refresh the meta copy.

### Saving Site Options

Options are shared across the entire site. Publishing a new block writes the option fields supplied in its saved attributes. Later saves compare each field with the previous saved version of that block and write only changed values. Saving a post's title, or an unchanged older copy of the block on another post, does not overwrite a newer option value.

A field omitted from the saved attributes leaves its option untouched. This also protects existing options when a new field is added to `block.json`. An explicit `null` deletes an option; empty strings, `false`, `0`, and empty arrays are saved as values. Removing a block does not delete its options.

Use one designated published block to manage each shared option. If multiple blocks deliberately change the same option, the last changed value saved wins. Storage does not load the current option into every block: each block retains its own field values in post content.

For file-based pages, a persistent block `key` lets Blockstudio recognize the same block after it moves in the document. Blocks without a key are matched by block name and position.


## Accessing Stored Values

### In Block Templates

Inside your block template, field values are always available via `$attributes`:


#### PHP

```php title="index.php"
<div>
  <h1><?php echo $a['subtitle']; ?></h1>
</div>
```

#### Twig

```twig title="index.twig"
<div>
  <h1>{{ a.subtitle }}</h1>
</div>
```

### Post Meta (Outside Block Context)

Values stored in `postMeta` can be accessed anywhere in WordPress:


#### PHP

```php
// Get meta value
$subtitle = get_post_meta( $post_id, 'my_subtitle', true );

// Query posts by meta value
$posts = get_posts([
  'meta_key'   => 'my_subtitle',
  'meta_value' => 'Hello World',
]);
```

#### Twig

```twig
{# Using Timber #}
{{ post.meta('my_subtitle') }}

{# Or via function #}
{{ function('get_post_meta', post.id, 'my_subtitle', true) }}
```

Post meta is also available via the REST API:

```bash
GET /wp-json/wp/v2/posts/123
# Response includes: { "meta": { "my_subtitle": "Hello World" } }
```

### Options (Global Values)

Values stored in `option` are accessible site-wide:


#### PHP

```php
$company_name = get_option( 'site_company_name' );
```

#### Twig

```twig
{{ function('get_option', 'site_company_name') }}
```

## Examples

### SEO Meta Fields

Store SEO data in post meta for compatibility with SEO plugins:

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "id": "seo_title",
        "type": "text",
        "label": "SEO Title",
        "storage": {
          "type": "postMeta",
          "postMetaKey": "_yoast_wpseo_title"
        }
      },
      {
        "id": "seo_description",
        "type": "textarea",
        "label": "Meta Description",
        "storage": {
          "type": "postMeta",
          "postMetaKey": "_yoast_wpseo_metadesc"
        }
      }
    ]
  }
}
```

### Featured Content with Querying

Add post meta storage to enable querying posts by field value:

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "id": "featured",
        "type": "toggle",
        "label": "Featured",
        "storage": {
          "type": "postMeta",
          "postMetaKey": "is_featured"
        }
      }
    ]
  }
}
```

Query featured posts:

```php
$featured_posts = get_posts([
  'meta_key'   => 'is_featured',
  'meta_value' => '1',
]);
```

### Global Site Settings

Store site-wide configuration in options:

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "id": "company_name",
        "type": "text",
        "label": "Company Name",
        "storage": {
          "type": "option",
          "optionKey": "site_company_name"
        }
      },
      {
        "id": "support_email",
        "type": "text",
        "label": "Support Email",
        "storage": {
          "type": "option",
          "optionKey": "site_support_email"
        }
      }
    ]
  }
}
```

Access anywhere on your site:

```php
<footer>
  <p>&copy; <?php echo get_option( 'site_company_name' ); ?></p>
  <p>Contact: <?php echo get_option( 'site_support_email' ); ?></p>
</footer>
```

### Post Meta and Option

For data that needs both post-level and global accessibility:

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "id": "important_value",
        "type": "text",
        "label": "Important Value",
        "storage": {
          "type": ["postMeta", "option"],
          "postMetaKey": "important_meta",
          "optionKey": "important_option"
        }
      }
    ]
  }
}
```

## Default Key Names

When you don't specify a custom key, Blockstudio generates one automatically:

| Storage Type | Default Key Format | Example |
|--------------|-------------------|---------|
| `postMeta` | `{block_name}_{field_id}` | `blockstudio_example_subtitle` |
| `option` | `{block_name}_{field_id}` | `blockstudio_example_subtitle` |

The block name is sanitized (slashes replaced with underscores).


> **Warning**
>
> Custom keys are recommended for production use. Default keys may change if you rename your block.

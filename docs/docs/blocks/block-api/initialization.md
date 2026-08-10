---
title: Initialization
description: Execute code during WordPress initialization.
path: "blocks/block-api/initialization"
order: 44
section: "Blocks"
subsection: "Block API"
meta_title: "Initialization"
meta_description: "Execute code during WordPress initialization."
---

# Initialization

Block templates will only be executed when the block is rendered. This is enough for most blocks; however, sometimes you need to execute code during an earlier stage of execution. For example, you may want to register a new post type or do some other type of setup unrelated to the block.

To do this, add one or more PHP files whose names start with `init`, such as `init.php`, `init-helpers.php`, or `init-post-types.php`, to your block directory. Every matching file executes once during the `init` action. For more information on this specific stage, see the [WordPress documentation](https://developer.wordpress.org/reference/hooks/init/).

Init files can live beside a `block.json` or in a standalone directory. Beside a block, each init file is execution-only: the block remains the owner of sibling assets and `rpc.php`, `cron.php`, and `db.php` definitions. In a standalone directory, the init entry owns the directory's sibling assets, which supports file-based [code snippets](/docs/code-snippets).

## Example

```php title="init.php"
<?php
// Register a custom post type
register_post_type('project', [
  'labels' => [
    'name' => 'Projects',
    'singular_name' => 'Project',
  ],
  'public' => true,
  'has_archive' => true,
  'supports' => ['title', 'editor', 'thumbnail'],
]);
```

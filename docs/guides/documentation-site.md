---
title: Building a Documentation Site
description: Build a documentation section from Markdown files with a page collection, its own post type and URL base, a loader for generated pages, and a layout with sidebar navigation.
path: "documentation-site"
order: 10
section: "Guides"
meta_title: "Building a Documentation Site"
meta_description: "Build a documentation section from Markdown files with a page collection, its own post type and URL base, a loader for generated pages, and a layout with sidebar navigation."
---

# Building a Documentation Site

Documentation wants to live next to the code it describes. Writers prefer
Markdown, reviewers want to see changes in a pull request, and a release should
ship its docs in the same commit. WordPress, on the other hand, wants posts in
the database: that is where search, permalinks, revisions, and the editor live.

Blockstudio page collections connect the two. A folder of Markdown files
becomes a set of real WordPress posts with their own post type, a URL base, and
a parent and child hierarchy. A `layout.php` file wraps every page in the
collection with navigation built from the same data. The files stay the source
of truth, and an explicit sync command writes them to the database.

This guide builds a small documentation section for a theme called `acme`, step
by step: a manifest, Markdown pages, a loader that generates a changelog, the
sync, and a layout with a sidebar and previous and next links.

## What You Will Build

```
theme/
└── pages/
    └── docs/
        ├── pages.json
        ├── layout.php
        ├── loader.php
        ├── index.md
        ├── getting-started.md
        ├── data/
        │   └── releases.json
        └── guide/
            ├── index.md
            ├── install.md
            └── configure.md
```

After syncing, the collection serves these URLs:

| Source | Public URL |
| --- | --- |
| `index.md` | `/docs/` |
| `getting-started.md` | `/docs/getting-started/` |
| `guide/index.md` | `/docs/guide/` |
| `guide/install.md` | `/docs/guide/install/` |
| `guide/configure.md` | `/docs/guide/configure/` |
| loader entry | `/docs/changelog/` |

## Step 1: The Collection Manifest

A collection is a folder inside `pages/` that contains a `pages.json` manifest.
Create `pages/docs/pages.json`:

```json title="pages/docs/pages.json"
{
  "collection": "docs",
  "title": "Documentation",
  "postType": "acme_docs",
  "postTypeArgs": {
    "label": "Docs"
  },
  "defaults": {
    "postStatus": "publish",
    "templateLock": "contentOnly"
  }
}
```

`collection` is the collection slug and becomes the public URL base. `postType`
sends every page in the collection to a dedicated post type, so docs do not mix
with your normal pages in the admin. When that post type does not exist yet,
Blockstudio registers it. The registration defaults are public, hierarchical,
available in the REST API, with title, editor, page attributes, thumbnail,
excerpt, and revisions support, and a rewrite slug equal to the collection
slug. `postTypeArgs` is merged over those defaults, so this manifest only
changes the admin label.

`defaults` applies page properties to every page in the collection. Here every
page is published on sync and locked to content-only editing, so editors can fix
a typo in the editor but cannot rearrange the structure that the Markdown
defines.

## Step 2: Write the Pages in Markdown

Inside a collection, every Markdown file is a page. The file location supplies
the identity, so frontmatter is optional. Use it for the title, the sort order,
and any extra data you want to read later.

```md title="pages/docs/index.md"
---
title: Introduction
order: 0
description: What Acme does and where to start.
---

# Introduction

Acme is a toolkit for building fast WordPress sites.
Start with [Getting Started](/docs/getting-started/).
```

````md title="pages/docs/getting-started.md"
---
title: Getting Started
order: 10
---

# Getting Started

Install the theme, then activate it under **Appearance > Themes**.

```bash
wp theme activate acme
```
````

The guide section is a folder with its own `index.md`, which becomes the parent
of the pages next to it:

```md title="pages/docs/guide/index.md"
---
title: Guide
order: 20
---

# Guide

Task-focused walkthroughs for everyday work.
```

```md title="pages/docs/guide/install.md"
---
title: Installation
order: 10
---

# Installation

| Requirement | Version |
| --- | --- |
| WordPress | 6.7+ |
| PHP | 8.2+ |
```

```md title="pages/docs/guide/configure.md"
---
title: Configuration
order: 20
---

# Configuration

Settings live in `acme.json` at the theme root.

> Changes apply on the next deploy.
```

Blockstudio derives a logical path and a page name from each file:

| File | Path | Page name |
| --- | --- | --- |
| `index.md` | `.` | `docs-home` |
| `getting-started.md` | `getting-started` | `docs-getting-started` |
| `guide/index.md` | `guide` | `docs-guide` |
| `guide/install.md` | `guide/install` | `docs-guide-install` |

`guide/install.md` and `guide/install/index.md` produce the same path, so you
can switch between a flat file and a folder later without changing the URL.

`order` sorts siblings and is written to the post's menu order. Pages without an
order sort after ordered pages, then by title. Frontmatter keys that Blockstudio
does not recognize, such as `description` above, are kept in the page's `meta`
array.

During sync, Markdown is parsed as GitHub Flavored Markdown, converted to HTML,
and then parsed into native blocks. The stored post contains blocks, not the
Markdown text. Headings get a stable anchor, tables become `core/table`, block
quotes become `core/quote`, and fenced code becomes a `core/preformatted` block
that keeps the language class. This is the stored content of the installation
page (whitespace trimmed):

```html
<!-- wp:heading {"level":1,"anchor":"installation"} --><h1 id="installation" class="wp-block-heading">Installation</h1><!-- /wp:heading -->
<!-- wp:table --><figure class="wp-block-table"><table>
<thead><tr><th>Requirement</th><th>Version</th></tr></thead>
...
</table></figure><!-- /wp:table -->
```

## Step 3: Generate Pages with a Loader

Some pages are better generated than written. A changelog is a good example:
the release data already exists, and copying it into Markdown by hand invites
drift. A collection can include a `loader.php` that returns extra page
definitions.

Keep the release data in the collection:

```json title="pages/docs/data/releases.json"
[
  { "version": "1.1.0", "date": "2026-09-01", "notes": "Added dark mode." },
  { "version": "1.0.0", "date": "2026-06-15", "notes": "First release." }
]
```

Then build one Markdown page from it:

```php title="pages/docs/loader.php"
<?php
$releases = json_decode(
	(string) file_get_contents( __DIR__ . '/data/releases.json' ),
	true
);

$markdown = "# Changelog\n";

foreach ( $releases as $release ) {
	$markdown .= "\n## {$release['version']}\n\n";
	$markdown .= "Released {$release['date']}. {$release['notes']}\n";
}

return array(
	'pages' => array(
		array(
			'name'        => 'docs-changelog',
			'title'       => 'Changelog',
			'path'        => 'changelog',
			'order'       => 90,
			'content'     => $markdown,
			'contentType' => 'markdown',
		),
	),
);
```

The loader runs during explicit discovery and sync, never on a normal page
view. Keep it deterministic and local: reading a file in the theme is fine,
fetching a remote API is not, because the sync would then depend on that
service being reachable.

Loader pages are marked as generated. If an entry disappears from the loader
output, the next sync treats its post as an orphan and handles it through the
`blockstudio/pages/orphan_action` filter, which moves it to the trash by
default.

A loader can also return `paths` to mount other local folders of Markdown into
the collection, optionally under a prefix. Folders outside the collection root
require the `blockstudio/pages/allow_external_loader_path` filter. See
[Collection Loaders](/docs/pages-and-patterns/pages#collection-loaders) for the
full shape.

## Step 4: Sync

Nothing is written to the database until you ask for it:

```bash
wp bs pages sync
```

The command discovers every page source, registers the `acme_docs` post type,
creates or updates the posts, and prints a summary row with the `discovered`,
`created`, `updated`, `unchanged`, `removed`, and `failed` counts. Running it
again without changes reports every page as unchanged and performs no post
writes. Add `--format=json` when a deployment script needs the full report.

After the sync, the posts mirror the file tree. `guide/install` is a child of
`guide`, which is a child of the collection root, and each post's menu order is
the frontmatter `order`. Collection URLs combine the collection slug with each
page's logical path:

```
/docs/
/docs/getting-started/
/docs/guide/
/docs/guide/install/
/docs/guide/configure/
/docs/changelog/
```

You do not need to resave permalinks. Collection rewrite rules are flushed
automatically when the registered collection post types change.

Make the sync part of your deployment. Frontend and admin requests never run
page discovery, so a deploy that copies new Markdown files without running
`wp bs pages sync` keeps serving the previous content.

## Step 5: The Layout

The posts now exist, but each one renders with the theme's normal single
template and no navigation. A `layout.php` next to `pages.json` wraps the
frontend rendering of every page in the collection:

```php title="pages/docs/layout.php"
<?php
$current = blockstudio_current_page();
$tree    = blockstudio_page_tree( 'docs' );

// Flatten the tree in reading order for previous and next links.
$flat    = array();
$flatten = function ( array $nodes ) use ( &$flatten, &$flat ) {
	foreach ( $nodes as $node ) {
		$flat[] = $node;
		$flatten( $node['children'] );
	}
};
$flatten( $tree );

$keys     = array_column( $flat, 'key' );
$position = array_search( $current['key'] ?? null, $keys, true );
$previous = false !== $position ? ( $flat[ $position - 1 ] ?? null ) : null;
$next     = false !== $position ? ( $flat[ $position + 1 ] ?? null ) : null;

$render_nav = function ( array $nodes ) use ( &$render_nav, $current ) {
	echo '<ul>';
	foreach ( $nodes as $node ) {
		$is_current = ( $node['key'] ?? null ) === ( $current['key'] ?? null );
		printf(
			'<li><a href="%s"%s>%s</a>',
			esc_url( $node['permalink'] ?? '#' ),
			$is_current ? ' aria-current="page"' : '',
			esc_html( $node['title'] )
		);
		if ( array() !== $node['children'] ) {
			$render_nav( $node['children'] );
		}
		echo '</li>';
	}
	echo '</ul>';
};
?>
<div class="docs">
	<nav class="docs-sidebar" aria-label="Documentation">
		<?php $render_nav( $tree ); ?>
	</nav>

	<article class="docs-content">
		<?php echo blockstudio_page_content(); ?>

		<nav class="docs-pager" aria-label="Previous and next">
			<?php if ( $previous ) : ?>
				<a rel="prev" href="<?php echo esc_url( $previous['permalink'] ?? '#' ); ?>">
					<?php echo esc_html( $previous['title'] ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $next ) : ?>
				<a rel="next" href="<?php echo esc_url( $next['permalink'] ?? '#' ); ?>">
					<?php echo esc_html( $next['title'] ); ?>
				</a>
			<?php endif; ?>
		</nav>
	</article>
</div>
```

`blockstudio_page_tree('docs')` returns the collection as nested nodes, sorted
by `order` and then title, with each node's children in `children`. Here it
has one root, the introduction page, with the other pages below it. Each node
carries `key`, `title`, `path`, and `permalink`. Flattening the tree in that
order gives the reading sequence, which is all previous and next links need.

`blockstudio_page_content()` prints the page body that WordPress would render
through `the_content`. Leave it out and the content is hidden. On
`/docs/guide/install/` of a site at `https://example.com` the layout renders
this (trimmed):

```html
<nav class="docs-sidebar" aria-label="Documentation">
  <ul><li><a href="https://example.com/docs/">Introduction</a><ul>
    <li><a href="https://example.com/docs/getting-started/">Getting Started</a></li>
    <li><a href="https://example.com/docs/guide/">Guide</a><ul>
      <li><a href="https://example.com/docs/guide/install/" aria-current="page">Installation</a></li>
      <li><a href="https://example.com/docs/guide/configure/">Configuration</a></li>
    </ul></li>
    <li><a href="https://example.com/docs/changelog/">Changelog</a></li>
  </ul></li></ul>
</nav>
...
<a rel="prev" href="https://example.com/docs/guide/">Guide</a>
<a rel="next" href="https://example.com/docs/guide/configure/">Configuration</a>
```

The layout only runs on singular frontend requests in the main loop. It does
not change the stored `post_content`, and it does not wrap the editor canvas.
Its path is stored on each synced post and is part of the page fingerprint, so
an edited layout is picked up on the next sync.

## Reading Collection Data in PHP

The layout helpers are thin wrappers around the `Blockstudio\Pages` class. Use
either form in templates, blocks, or plugins:

| Helper | Class method | Returns |
| --- | --- | --- |
| `blockstudio_pages('docs')` | `Pages::pages('docs')` | Flat list of the collection's pages, keyed by page key, sorted |
| `blockstudio_page_tree('docs')` | `Pages::tree('docs')` | Nested nodes with `children` |
| `blockstudio_page_children('docs-guide', 'docs')` | `Pages::children('docs-guide', 'docs')` | Direct children of one page |
| `blockstudio_page_collection('docs')` | `Pages::collection('docs')` | Collection data from the manifest |
| `blockstudio_current_page()` | `Pages::current_page()` | The page being rendered, or `null` |
| | `Pages::get_page('docs-guide-install')` | One page by name |

Page arrays include the synced `post_id`, `permalink`, `name`, `key`, `title`,
`path`, `order`, `collection`, `postType`, and the `meta` array. Frontmatter
extras end up in `meta`, so a layout can print the introduction's summary:

```php
<?php $summary = $current['meta']['description'] ?? ''; ?>
```

On frontend requests these functions read already synced posts and the
configuration stored by the last sync. They never read the Markdown files, so
they cost no filesystem work. Only published pages are available on the
frontend.

## Raw Markdown for Tools

Because the pages come from Markdown files, Blockstudio can also serve the
original source. Request a page with a `.md` suffix, such as
`/docs/guide/install.md`, or send `Accept: text/markdown` to the normal URL. The
response omits the frontmatter and points back to the HTML page with a
canonical `Link` header. This is useful for search indexers, assistants, and
other tools that read plain text. Pages generated from inline loader content
have no source file and therefore no `.md` response.

Turn it off with one filter:

```php title="functions.php"
add_filter( 'blockstudio/pages/serve_markdown', '__return_false' );
```

## Tips

**Let paths carry the structure.** Move a file and its path, parent, and URL
follow on the next sync. Use frontmatter `path` only when the URL should differ
from the file location.

**Pair `page.json` with Markdown when a page needs more.** A folder with
`page.json` and `index.md` uses the JSON for page properties such as `postId`
or `templateLock` and the Markdown for content. Frontmatter in `index.md` wins
over matching `page.json` values.

**Write in the files, not in the editor.** With `templateLock: "contentOnly"`,
editors can correct text in place, but the next sync of a changed source
rewrites the stored content from the file. Treat the Markdown as the place where
docs are written. A page that should belong to editors instead gets
`"sync": false` in a `page.json`; normal syncs then skip it, and
`Blockstudio\Pages::force_sync()` creates it once.

**Changing the post type is safe.** If a collection moves from `page` to a
custom post type, already synced posts are migrated in place when their stored
identity matches, so post IDs survive.

## Next Steps

- [Pages reference](/docs/pages-and-patterns/pages): every `page.json` and
  `pages.json` property, loaders, layouts, and the reconciliation report.
- [Building File-Based Pages](/guides/file-based-pages): template locking,
  block editing modes, and keyed merging for pages that clients edit.
- [Site Editor Templates and Parts from Files](/guides/site-editor-templates):
  the same file-first approach for headers, footers, and theme templates.

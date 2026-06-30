# 006: File-Based Site Editor Templates and Template Parts

## Summary

Blockstudio already treats blocks, fields, patterns, and pages as first-class
file-backed primitives. Pages sync files into normal WordPress posts and custom
post types. The next gap is the Site Editor: block themes are built around
`wp_template` and `wp_template_part`, but Blockstudio users cannot currently
author those templates with the same PHP/Twig/Blade/HTML-like workflow they use
for pages and patterns.

This PRD adds file-based Site Editor templates for Blockstudio 7.5.0:

- themes can define full-site templates from files
- themes can define template parts from files
- Blockstudio compiles those sources to native serialized block markup
- WordPress and the Site Editor see them as normal block templates and parts
- user customizations made in the Site Editor remain normal database overrides
- no frontend routing, page sync, or custom post type workaround is required

The user-facing result: a theme can add a file-backed `front-page`, `single`,
`archive`, `header`, or `footer`, edit it in the Site Editor, reset it to the
file version, and use Blockstudio block tags and templating engines throughout.

## Product Context

Blockstudio's file-first story is currently split:

- **Pages** create and update posts from files.
- **Patterns** register reusable block markup from files.
- **Blocks** render from file templates.
- **The Site Editor** still expects native WordPress `.html` block-template
  files or database customizations.

That means a theme author has to leave the Blockstudio workflow when building a
block theme layout. They either write raw block comments in `templates/*.html`
and `parts/*.html`, or they create normal pages instead of real Site Editor
templates. Neither path is ideal.

The Site Editor should become a first-class Blockstudio target:

- use the same parser as pages and patterns
- use the same block tag syntax
- support PHP/Twig/Blade source files
- integrate with WordPress' native template hierarchy and template-part areas
- preserve WordPress' customization model

## Current State

### WordPress template model

WordPress represents Site Editor templates with two special post types:

- `wp_template`
- `wp_template_part`

Core discovers file templates from the active theme:

- `templates/*.html`
- `parts/*.html`

Core overlays database customizations on top of file templates. If a user saves
a file-backed template in the Site Editor, WordPress creates or updates a
`wp_template` or `wp_template_part` post assigned to the active `wp_theme` term.
That database object wins over the file version until the customization is
deleted or reset.

Core exposes a unified `WP_Block_Template` object through APIs such as:

- `get_block_templates()`
- `get_block_template()`
- `get_block_file_template()`
- `register_block_template()` for `wp_template` only

Important constraints:

- `register_block_template()` does not register `wp_template_part`.
- Template and template-part IDs use the active theme format:
  `get_stylesheet() . '//' . $slug`.
- Template parts need an `area` such as `header`, `footer`, or `uncategorized`.
- User customizations must take precedence over any file-backed source.

### Existing Blockstudio file systems

Pages and patterns already provide most of the required building blocks:

- `Page_Discovery` and `Pattern_Discovery` scan file trees.
- `Page_Sync` and `Patterns::register_pattern()` compile source files through
  `Html_Parser`.
- PHP, Twig, Blade, Markdown, and HTML sources are already supported for pages.
- Patterns support PHP, Twig, and Blade sources.
- Block tags resolve to native block markup in pages and patterns.

The new Site Editor feature should reuse this compiler behavior where possible,
but it should not copy the page sync model blindly. Site Editor templates are
not frontend pages and should not be synced into arbitrary post types.

### Existing docs structure

The closest docs section is `docs/content/docs/pages-and-patterns`. That section
currently documents pages, patterns, and template hooks. Site Editor templates
belong there because they are another file-backed block-template target.

## Goals

- Add a first-class file-backed Site Editor template system.
- Support `wp_template` and `wp_template_part`.
- Compile Blockstudio source files into native WordPress block markup.
- Preserve normal WordPress template hierarchy behavior.
- Preserve normal WordPress Site Editor customization behavior.
- Make file-backed templates visible and editable in the Site Editor.
- Make file-backed template parts visible and editable in the Site Editor.
- Support the same block syntax used by pages and patterns:
  - HTML element mapping
  - `<block name="namespace/block">`
  - `<bs:namespace-block>`
  - configured block tag prefixes
  - reusable Blockstudio blocks and components
- Support PHP, Twig, Blade, and HTML-like sources.
- Add public PHP helpers and filters for discovery and introspection.
- Avoid expensive recursive filesystem scans on hot frontend paths.
- Add unit and E2E coverage for template discovery, rendering, Site Editor
  availability, customization precedence, template parts, and cache invalidation.
- Update docs, generated docs/LLM output, readme changelog, and the 7.5 blog
  post draft.

## Non Goals

- Do not write Site Editor customizations back to source files in v1.
- Do not replace WordPress' native `templates/*.html` and `parts/*.html`
  support. Core files must keep working.
- Do not sync every file-backed template into a `wp_template` post by default.
  Database posts are user customizations, not the file source of truth.
- Do not add frontend routes or custom rewrite rules.
- Do not support Markdown templates in v1 unless implementation proves it is
  trivial and well tested. Site Editor templates are layout files, not prose
  content.
- Do not make dynamic request data available to template compilation. Like
  pages and patterns, source files compile to static block markup. Runtime
  behavior should live in dynamic blocks.
- Do not require a block theme conversion or theme.json rewrite.
- Do not make Content Sync responsible for `wp_template` and
  `wp_template_part` as part of this PRD.

## Guiding Principle

Blockstudio should act as a source compiler and provider for WordPress' native
Site Editor template system.

The compiled file template is the fallback source. A Site Editor database
customization wins over it. Resetting the customization should reveal the latest
compiled file version again.

That mirrors WordPress core behavior:

1. database customization
2. active theme file source
3. parent theme file source
4. plugin/template registry source

Blockstudio should insert its compiled file source into that model without
breaking the priority order.

## Proposed File Structure

Use conventional WordPress folder names, but keep Blockstudio sources in folders
with explicit metadata files so they do not collide with core `.html` files:

```text
theme/
  templates/
    front-page/
      template.json
      index.php
    single/
      template.json
      index.twig
    archive-product/
      template.json
      index.blade.php
  parts/
    header/
      part.json
      index.php
    footer/
      part.json
      index.php
```

Supported template source candidates:

| File | Engine | Notes |
| --- | --- | --- |
| `index.php` | PHP/HTML-like | Default source. |
| `index.blade.php` | Blade | Requires the same Blade support used by blocks/pages. |
| `index.twig` | Twig | Requires Timber/Twig. |
| `index.html` | HTML-like | Optional source for already-static block-like markup. |

Priority order should match pages and patterns where possible:

1. `index.php`
2. `index.blade.php`
3. `index.twig`
4. `index.html`

Flat files can be considered later, but v1 should prefer folder-based sources
because they leave room for metadata, assets, examples, and future compiler
settings.

## Template Manifest

`template.json` defines a `wp_template` file source.

```json title="templates/front-page/template.json"
{
  "slug": "front-page",
  "title": "Front Page",
  "description": "Homepage template",
  "postTypes": ["page"]
}
```

Properties:

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `slug` / `name` | `string` | Folder name | WordPress template slug, such as `front-page`, `single`, or `archive-product`. |
| `title` | `string` | Title from slug | Display title in the Site Editor. |
| `description` | `string` | `""` | Display description in the Site Editor. |
| `postTypes` | `string[]` | `[]` | Post types for custom templates. Core hierarchy templates may omit this. |
| `source` / `template` / `file` | `string` | Auto-detected index file | Optional custom source file path relative to the template folder. |
| `status` | `string` | `"publish"` | Template object status. Usually `publish`. |
| `sync` | `boolean` | `false` | Reserved for future DB materialization. Must not overwrite user customizations in v1. |
| `meta` | `object` | `{}` | Blockstudio metadata for helpers and admin views. |

Unknown keys should be preserved under `meta`, matching Pages behavior.

Template slug validation:

- lower-case ASCII
- segments may contain letters, numbers, dashes, and underscores
- no slashes
- no path traversal
- sanitized through `sanitize_title_with_dashes()` or equivalent

Do not auto-prefix slugs. WordPress template hierarchy depends on exact names:

- `index`
- `home`
- `front-page`
- `single`
- `single-post`
- `single-product`
- `archive`
- `archive-product`
- `page`
- `404`
- `search`
- `taxonomy-product_cat`

## Template Part Manifest

`part.json` defines a `wp_template_part` file source.

```json title="parts/header/part.json"
{
  "slug": "header",
  "title": "Header",
  "area": "header"
}
```

Properties:

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `slug` / `name` | `string` | Folder name | WordPress template part slug. |
| `title` | `string` | Title from slug | Display title in the Site Editor. |
| `description` | `string` | `""` | Display description in the Site Editor. |
| `area` | `string` | `"uncategorized"` | Template part area. Use `header`, `footer`, `sidebar`, or a registered custom area. |
| `source` / `template` / `file` | `string` | Auto-detected index file | Optional custom source file path relative to the part folder. |
| `status` | `string` | `"publish"` | Template part object status. Usually `publish`. |
| `meta` | `object` | `{}` | Blockstudio metadata for helpers and admin views. |

`area` must pass through WordPress' template part area normalization. Invalid
areas should fall back to `uncategorized` and record a discovery warning.

## Compilation

Compiled content must be serialized native WordPress block markup.

Implementation should reuse the same parser path as pages and patterns:

1. read/render source file
2. render Blade/Twig when applicable
3. parse HTML-like source through `Html_Parser`
4. apply Blockstudio block tag resolution
5. return serialized block markup for WordPress

Template source files are build-time sources, not runtime templates. They should
not depend on the current query, current post, or request-specific globals.

If developers need runtime output, they should use dynamic blocks inside the
template.

## WordPress Integration

Add a new Site Editor template provider, likely:

- `includes/classes/site-template-discovery.php`
- `includes/classes/site-template-registry.php`
- `includes/classes/site-templates.php`

The provider should expose compiled templates through WordPress' block template
lookup filters, not by creating persistent posts by default.

Preferred integration points:

- append Blockstudio templates in `get_block_templates`
- return a Blockstudio file source in `get_block_file_template`
- avoid `pre_get_block_template` unless tests prove it does not override DB
  customizations
- do not rely solely on `register_block_template()` because it does not cover
  `wp_template_part`

Every returned object must be a `WP_Block_Template` with the same shape core
expects.

For `wp_template`:

- `id`: `get_stylesheet() . '//' . $slug`
- `theme`: `get_stylesheet()`
- `type`: `wp_template`
- `slug`: manifest slug
- `content`: compiled block markup
- `source`: behave as a theme/file source
- `origin`: `theme` or `blockstudio`, whichever gives the correct Site Editor
  reset/original-source behavior in tests
- `status`: `publish`
- `title`: manifest title
- `description`: manifest description
- `is_custom`: `false` for default core hierarchy slugs, `true` for custom
  templates
- `post_types`: manifest `postTypes` when provided
- `has_theme_file`: should behave like a file-backed source if Site Editor reset
  requires it
- `modified`: latest relevant source mtime when available

For `wp_template_part`:

- `id`: `get_stylesheet() . '//' . $slug`
- `theme`: `get_stylesheet()`
- `type`: `wp_template_part`
- `slug`: manifest slug
- `content`: compiled block markup
- `source`: behave as a theme/file source
- `status`: `publish`
- `title`: manifest title
- `description`: manifest description
- `area`: normalized part area
- `has_theme_file`: should behave like a file-backed source if Site Editor reset
  requires it
- `modified`: latest relevant source mtime when available

User customization precedence is mandatory:

- if a matching `wp_template` or `wp_template_part` database post exists for the
  active theme, WordPress must return the database content
- Blockstudio must not append a duplicate file template in lists when a database
  customization with the same slug is already present
- deleting/resetting the customization reveals the compiled file source again

Core block hooks should still apply to returned content. Template part blocks
inside templates should receive the active theme attribute the same way core file
templates do.

## Discovery Paths

Default discovery paths:

- active theme `templates/`
- active theme `parts/`
- parent theme `templates/` and `parts/` when using a child theme

Child theme definitions win over parent theme definitions with the same slug,
matching WordPress core file-template behavior.

Filters:

```php
apply_filters( 'blockstudio/site_templates/template_paths', $paths );
apply_filters( 'blockstudio/site_templates/part_paths', $paths );
```

Optional combined filter:

```php
apply_filters( 'blockstudio/site_templates/paths', array(
  'templates' => $template_paths,
  'parts' => $part_paths,
) );
```

Filters must be documented and tested.

## Public API

Expose a small introspection API:

```php
Blockstudio\Site_Templates::templates();
Blockstudio\Site_Templates::parts();
Blockstudio\Site_Templates::get_template( 'front-page' );
Blockstudio\Site_Templates::get_part( 'header' );
Blockstudio\Site_Templates::get_paths();
Blockstudio\Site_Templates::reset();
```

Global helpers can be added if they are useful in docs:

```php
blockstudio_site_templates();
blockstudio_site_template_parts();
blockstudio_site_template( 'front-page' );
blockstudio_site_template_part( 'header' );
```

Keep the helper surface smaller than Pages unless there is a concrete template
authoring need.

## Filters and Hooks

Discovery and data filters:

```php
blockstudio/site_templates/template_paths
blockstudio/site_templates/part_paths
blockstudio/site_templates/paths
blockstudio/site_templates/template_candidates
blockstudio/site_templates/templates
blockstudio/site_templates/parts
```

Compilation filters:

```php
blockstudio/site_templates/template_content
blockstudio/site_templates/part_content
blockstudio/site_templates/parser
```

Lifecycle actions:

```php
blockstudio/site_templates/registered
blockstudio/site_templates/discovered
```

Actions should pass the registry and enough context for admin/debug tooling.

## Caching and Performance

Template lookup can happen on frontend requests, REST requests, and Site Editor
requests. Discovery must not recursively scan the theme tree once per lookup.

Requirements:

- per-request static cache for discovered templates and parts
- persistent object-cache/transient cache for discovery metadata and compiled
  content
- cache key includes:
  - active stylesheet/theme
  - parent theme when relevant
  - discovered paths
  - source file mtimes
  - manifest file mtimes
  - parser settings that affect output
  - Blockstudio version
- cache invalidates when a source or manifest changes
- no cache should hide a Site Editor database customization
- tests prove repeated `get_block_template()` / `get_block_templates()` calls do
  not trigger repeated recursive scans in one request

The implementation can start with request-level memoization and a persistent
fingerprint cache, but final behavior must avoid the previous Pages-style hot
path scan issue.

## Admin and Canvas

Admin registry views should eventually show templates and parts next to pages
and patterns, but this is not a hard v1 requirement unless it is already easy
to wire into the existing admin registry.

Canvas support is useful but can be incremental:

- file-backed templates should be eligible for preview artboards later
- the first implementation only needs Site Editor/frontend coverage

Do not block the core Site Editor feature on Canvas parity.

## Security

- Only local files from allowed discovery roots should be loaded by default.
- Relative `source`, `template`, and `file` values must stay inside the manifest
  folder unless a filter explicitly allows external paths.
- PHP/Blade/Twig compilation follows the same trust model as existing
  Blockstudio templates: theme code is trusted.
- Manifest JSON must be decoded safely.
- Invalid manifests should be skipped and recorded as registry errors.
- Do not expose raw template source over HTTP.

## Backward Compatibility

- Existing pages are unchanged.
- Existing patterns are unchanged.
- Existing native WordPress `templates/*.html` and `parts/*.html` files are
  unchanged.
- Existing Site Editor customizations are unchanged and must keep winning.
- Existing block themes without Blockstudio `template.json` or `part.json`
  files should behave exactly as before.
- Existing classic themes should not break. If Site Editor templates are not
  supported by the active theme, Blockstudio should either no-op or expose only
  what WordPress can safely consume.

## Implementation Plan

1. Add `Site_Template_Discovery` for `template.json` and `part.json`.
2. Add `Site_Template_Registry` for templates, parts, paths, errors, and cached
   compiled content.
3. Add `Site_Templates` orchestration and register it from `class-plugin.php`.
4. Reuse `Html_Parser` and the existing PHP/Twig/Blade source rendering logic.
5. Build `WP_Block_Template` objects for both template types.
6. Hook into WordPress template lookup so database customizations win and file
   sources appear as fallbacks.
7. Add cache/fingerprint handling.
8. Add child theme vs parent theme precedence.
9. Add public introspection helpers and filters.
10. Add unit tests for discovery, compilation, template object shape, caching,
    precedence, and filters.
11. Add E2E tests for Site Editor visibility, opening/editing, frontend output,
    template part usage, and reset/customization behavior.
12. Add docs, generated docs/LLM, readme changelog, and update the 7.5 blog
    draft.
13. Push a final commit with `[all]` and keep fixing until GitHub CI is green.

## Test Plan

### Unit Tests

Add `tests/unit/site-templates/SiteTemplatesTest.php` or equivalent.

Discovery:

- discovers `templates/front-page/template.json`
- discovers `parts/header/part.json`
- defaults slug from folder name
- normalizes title from slug
- skips invalid JSON
- skips unsafe slugs
- records duplicate slug errors
- child theme template wins over parent theme template
- `template_candidates` filter can add a source candidate
- path filters can add and remove discovery roots

Compilation:

- PHP source compiles to serialized blocks
- Twig source compiles when Timber is available
- Blade source compiles when Blade is available
- HTML-like source compiles
- `<block name="core/paragraph">` works
- `<bs:namespace-block>` works
- configured block tag prefixes work
- parser output matches Pages/Patterns for equivalent markup

Template objects:

- `get_block_templates( array(), 'wp_template' )` includes file-backed
  templates
- `get_block_templates( array(), 'wp_template_part' )` includes file-backed
  parts
- `get_block_template( get_stylesheet() . '//front-page', 'wp_template' )`
  returns the compiled file source when no DB customization exists
- `get_block_template( get_stylesheet() . '//header', 'wp_template_part' )`
  returns the compiled file source when no DB customization exists
- `postTypes` filters template lists correctly
- `area` filters part lists correctly
- custom template slugs report `is_custom === true`
- core hierarchy slugs report `is_custom === false`
- template part objects include normalized `area`

Customization precedence:

- a matching `wp_template` post for the active `wp_theme` term wins over the
  file source
- a matching `wp_template_part` post for the active `wp_theme` term wins over
  the file source
- list results do not include both the DB customization and the file source for
  the same slug
- deleting the customization reveals the file source again

Caching:

- repeated template lookups in one request reuse discovery results
- source file mtime changes invalidate compiled content
- manifest mtime changes invalidate metadata
- changing parser settings changes the cache key when relevant
- DB customizations are never hidden by file-source cache

### E2E Tests

Add fixtures under the E2E theme:

```text
tests/theme/templates/blockstudio-front-page/template.json
tests/theme/templates/blockstudio-front-page/index.php
tests/theme/templates/blockstudio-single/template.json
tests/theme/templates/blockstudio-single/index.twig
tests/theme/parts/blockstudio-header/part.json
tests/theme/parts/blockstudio-header/index.php
```

Browser coverage:

- file-backed template appears in Site Editor template list
- file-backed template can be opened in the Site Editor
- compiled blocks render in the editor canvas
- file-backed template part appears in template part list
- template using a file-backed part renders the part in the Site Editor
- frontend request using the template returns rendered compiled content
- saving in the Site Editor creates/persists a DB customization
- after customization, changing the source file does not overwrite the user's
  saved customization
- resetting/deleting customization returns to the file-backed source
- template part area is visible/usable in the Site Editor UI or REST response

REST/API coverage can be used where UI selectors are brittle:

- `/wp/v2/templates`
- `/wp/v2/template-parts`
- specific template endpoints by ID

### Regression Tests

- native `templates/*.html` still works
- native `parts/*.html` still works
- Pages tests still pass
- Patterns tests still pass
- Content Sync tests still pass
- Block tag tests still pass
- full `[all]` GitHub Actions run is green

## Docs

Add a new docs page:

- `docs/content/docs/pages-and-patterns/site-templates.mdx`

Update:

- `docs/content/docs/pages-and-patterns/meta.json`
- `docs/content/docs/pages-and-patterns/index.mdx`
- `docs/content/docs/pages-and-patterns/template-hooks.mdx`
- `docs/content/docs/blocks/rendering.mdx` if block tag contexts need updating
- `includes/llm/blockstudio-llm.txt`
- `readme.txt` changelog
- existing 7.5 blog draft at `docs/content/blog/blockstudio-7-5.mdx`

Docs must explain:

- when to use Pages vs Patterns vs Site Editor templates
- folder structure
- `template.json`
- `part.json`
- supported source engines
- Site Editor customization precedence
- reset behavior
- template hierarchy slugs
- template part areas
- block tags in templates
- no file write-back in v1
- no raw Markdown endpoints
- performance/cache behavior
- filters/hooks
- testing/release caveats

## Open Decisions

- Whether to expose Blockstudio templates as `source: theme`,
  `source: plugin`, or a custom origin value. Choose based on Site Editor reset,
  REST `original_source`, and UI behavior in tests.
- Whether `index.html` should be supported in v1 or left to WordPress native
  `.html` files.
- Whether to add global helper functions in v1 or keep only
  `Blockstudio\Site_Templates`.
- Whether to expose templates in the existing Blockstudio admin registry during
  the first implementation.
- Whether to add Canvas artboards for Site Editor templates immediately or in a
  follow-up PRD.

## Definition of Done

A block theme can define:

```text
templates/front-page/template.json
templates/front-page/index.php
parts/header/part.json
parts/header/index.php
```

and get all of this with no consumer-side glue:

1. the front-page template appears in the Site Editor
2. the header part appears in the Site Editor
3. the frontend uses the compiled front-page template
4. the template can reference the header part
5. Blockstudio block tags and HTML-like syntax compile to native blocks
6. PHP/Twig/Blade sources compile consistently with pages and patterns
7. saving in the Site Editor creates a normal WordPress customization
8. user customizations win over file sources
9. deleting/resetting the customization reveals the file-backed source again
10. native WordPress `.html` templates and parts continue to work
11. docs, LLM output, changelog, and the 7.5 blog draft are updated
12. local targeted tests pass during development
13. the final GitHub Actions run triggered by `[all]` is green


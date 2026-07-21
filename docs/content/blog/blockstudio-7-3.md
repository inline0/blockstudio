---
title: Blockstudio 7.3
description: Bundled UI components, expanded editing, plugin dependencies, editor policies, and a more reliable parsing pipeline.
date: "2026-05-19"
author: Dennis
path: "blockstudio-7-3"
order: 3
section: "Blog"
meta_title: "Blockstudio 7.3"
meta_description: "Bundled UI components, expanded editing, plugin dependencies, editor policies, and a more reliable parsing pipeline."
---

Blockstudio 7.3 adds a new layer to the framework: reusable UI components that
can be rendered like any other block.

Where 7.1 turned blocks into full-stack applications and 7.2 made larger
projects easier to inspect, validate, and distribute, 7.3 focuses on the daily
experience of building and editing those applications.

The short version:

- **UI components**: a bundled, opt-in `bsui/*` component library powered by the
  WordPress Interactivity API.
- **Apps**: bundled `app/*` examples that show how the UI components compose
  into larger interfaces.
- **Expanded Editor**: a wider drawer for editing Blockstudio fields when the
  sidebar gets too cramped.
- **Scoped asset selectors**: CSS and SCSS assets can use `%selector%` to target
  the block wrapper class directly.
- **Plugin dependencies**: blocks can require active WordPress plugins, with
  optional version constraints.
- **Editor policies**: `blockstudio.json` can configure global blocks,
  patterns, and media behavior using WordPress' PHP-backed editor hooks.
- **Block tag aliases**: custom aliases for block tag authoring.
- **Static analysis and docs**: better PHPStan coverage for external block
  libraries, plus generated docs for settings and hooks.
- **Page and editor fixes**: more reliable file-based page parsing, Tailwind
  candidate detection, reset mode, render cache isolation, and editor preloads.

## UI components

Blockstudio 7.3 ships with a bundled UI library under the `bsui/*` namespace.
The components are normal Blockstudio blocks, so they can be rendered in
templates, file-based pages, patterns, and post content with the same block tag
syntax you already use.

The UI component library is in beta while we gather more feedback. The goal for
7.3 is to make the first component set available, learn from real projects, and
refine naming, behavior, and defaults before calling the API surface stable.

The library is opt-in:

```json title="blockstudio.json"
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "ui": {
    "enabled": true
  }
}
```

Once enabled, simple components can be rendered directly:

```html
<block name="bsui/button" label="Save" variant="default"></block>
<block name="bsui/badge" label="Beta"></block>
<block
  name="bsui/input"
  nameAlt="email"
  type="email"
  placeholder="Email"
></block>
```

The shorter `bs:` syntax works too:

```html
<bs:bsui-button label="Save" variant="default" />
```

The goal is not to turn Blockstudio into a page builder UI kit. The goal is to
make common interface primitives available as blocks, so applications built with
Blockstudio do not need to recreate buttons, dialogs, tabs, selects, form
fields, menus, toolbars, and feedback components in every project.

The first component set includes layout, actions, inputs, overlays, navigation,
feedback, form, and data-display primitives:

- `bsui/button`, `bsui/button-group`, `bsui/toggle`, `bsui/toggle-group`,
  `bsui/toolbar`
- `bsui/input`, `bsui/textarea`, `bsui/select`, `bsui/checkbox`,
  `bsui/radio-group`, `bsui/slider`, `bsui/switch`, `bsui/number-field`,
  `bsui/password-input`, `bsui/phone-input`, `bsui/otp-input`,
  `bsui/date-input`, `bsui/time-picker`
- `bsui/dialog`, `bsui/drawer`, `bsui/popover`, `bsui/menu`,
  `bsui/context-menu`, `bsui/menubar`, `bsui/tooltip`, `bsui/alert-dialog`
- `bsui/tabs`, `bsui/accordion`, `bsui/breadcrumb`, `bsui/pagination`,
  `bsui/navigation-menu`
- `bsui/card`, `bsui/table`, `bsui/stack`, `bsui/separator`,
  `bsui/aspect-ratio`, `bsui/badge`, `bsui/avatar`, `bsui/progress`,
  `bsui/meter`, `bsui/skeleton`, `bsui/spinner`, `bsui/kbd`
- `bsui/form`, `bsui/field`, `bsui/field-group`, `bsui/file-upload`,
  `bsui/color-picker`, `bsui/rating`

Many of these are compound blocks. A `bsui/tabs` block owns the interactive
state, while child blocks such as `bsui/tabs-list`, `bsui/tabs-trigger`, and
`bsui/tabs-panel` describe the structure.

```html
<block name="bsui/tabs" defaultValue="features">
  <block name="bsui/tabs-list">
    <block name="bsui/tabs-trigger" value="features" title="Features"></block>
    <block name="bsui/tabs-trigger" value="pricing" title="Pricing"></block>
  </block>
  <block name="bsui/tabs-panel" value="features">
    <p>Features content.</p>
  </block>
  <block name="bsui/tabs-panel" value="pricing">
    <p>Pricing content.</p>
  </block>
</block>
```

That keeps the markup readable, while the state and behavior are handled by the
Interactivity API.

## App examples

The `app/*` namespace contains examples built from the same UI components.

They are not a separate framework. They are reference implementations for
larger Blockstudio interfaces: compound blocks, nested UI, Interactivity API
state, and places where UI components meet RPC or database-backed features.

```html
<block name="app/kitchen-sink"></block>

<block name="app/chat">
  <block name="app/chat-sidebar"></block>
  <block name="app/chat-header"></block>
  <block name="app/chat-messages"></block>
  <block name="app/chat-input"></block>
</block>
```

The UI library also has its own documentation, visual coverage, and interaction
test harness. Component behavior is not just a collection of templates. It is
something the framework can keep verifying as it grows.

## Expanded Editor

Editing complex blocks in the inspector sidebar can get tight quickly. That is
especially true for repeaters, code fields, rich text, and nested groups.

Blockstudio 7.3 adds an expanded editor action to the block inspector. It opens
a wider drawer from the right side of the editor and renders the same
Blockstudio fields there.

The important part is that this is still the same field system. It does not
duplicate field rendering, it does not introduce a second persistence path, and
changes made in the expanded editor update the selected block just like changes
made in the normal sidebar.

Use it when the sidebar is fine for scanning, but too cramped for actual input.

## Scoped asset selectors

Blockstudio already had `%selector%` support for code fields. In 7.3, block CSS
and SCSS assets can use the same authoring pattern.

In a CSS file, `%selector%` expands to the block's scoped class:

```css title="style.scoped.css"
%selector% {
  --hero-gap: var(--wp--preset--spacing--space-10);
}

%selector%.hero {
  border-bottom: 1px solid currentColor;
}

.hero__title {
  color: red;
}
```

That makes it possible to style the actual wrapper element:

```php title="index.php"
<section class="hero <?php echo bs_get_scoped_class($b['name']); ?>">
  <h1 class="hero__title">Hero</h1>
</section>
```

Regular selectors in `*.scoped.css` still target descendants. `%selector%` is
the explicit hook for the block root.

## Plugin dependencies

Blocks can now declare WordPress plugin dependencies inside the `blockstudio`
key of `block.json`.

The simple form checks that every listed plugin slug is active:

```json title="block.json"
{
  "blockstudio": {
    "pluginDependencies": ["woocommerce"]
  }
}
```

The object form adds version constraints:

```json title="block.json"
{
  "blockstudio": {
    "pluginDependencies": {
      "woocommerce": {
        "version": ">6"
      }
    }
  }
}
```

The identifiers are WordPress plugin slugs, matching WordPress core's plugin
dependency system. Blockstudio resolves active plugins to those slugs and skips
registration if a required plugin is missing, inactive, or below the required
version.

The version constraint uses the same comparison model as PHP's
`version_compare()`. A plain version such as `"6"` is treated as `>=6`.

This is useful for blocks that only make sense when a plugin is available:
WooCommerce product cards, form integrations, SEO integrations, membership
components, booking widgets, or admin UI tied to a third-party plugin.

## Editor policies

The `blockstudio.json` file is also becoming more useful as a project-level
editor policy file.

7.3 adds a PHP-backed `blockEditor` policy layer for settings WordPress already
exposes through server-side hooks. That means you can define global inserter,
pattern, and media behavior without shipping one-off theme glue code.

```json title="blockstudio.json"
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "blockEditor": {
    "blocks": {
      "allow": ["core/*", "my-theme/*"],
      "deny": ["core/embed", "core/freeform"],
      "directory": false,
      "categories": {
        "rename": {
          "text": "Writing",
          "design": "Layout"
        },
        "order": ["my-theme", "text", "media"]
      }
    },
    "patterns": {
      "core": false,
      "remote": false,
      "categories": {
        "deny": ["gallery"],
        "order": ["featured", "buttons"]
      }
    },
    "media": {
      "openverse": false,
      "imageSizes": {
        "allow": ["thumbnail", "large"]
      }
    }
  }
}
```

The first pass intentionally sticks to things backed by PHP: allowed and denied
blocks, block directory loading, inserter category labels/order, pattern
sources and categories, Openverse, image size choices, legacy widget visibility,
and PHP-registered block style removal.

It does not try to rewrite arbitrary core block defaults. Settings like
`core/columns.isStackedOnMobile` live inside the block editor's JavaScript
registration flow, so they need a separate, more explicit API instead of being
pretended into a JSON file.

## Block tag aliases

Block tags gained an alias filter:

```php title="functions.php"
add_filter('blockstudio/block_tags/tag_aliases', function ($aliases) {
    $aliases['theme-button'] = 'bsui/button';
    return $aliases;
});
```

That lets a project define authoring-friendly tags while still resolving to the
canonical block name internally.

```html
<bs:theme-button label="Continue" />
```

This is especially useful when composing UI-heavy templates where the canonical
block names are correct, but not always the nicest authoring surface.

## Static analysis and generated docs

The PHPStan extension gained support for explicit external block-library scan
roots. That means a project can validate `<bs:*>` and `<block>` tags against
blocks that live outside the current plugin or theme, while keeping those roots
registry-only for analysis.

The schema checks were also extended around page metadata and
`pluginDependencies`, so invalid release-time configuration is easier to catch
before it reaches WordPress.

Documentation generation now includes filter references for settings and PHP
hooks. That matters more as `blockstudio.json` becomes a real project policy
file instead of just a few scattered toggles.

## Page and editor reliability

7.3 also includes a set of fixes around file-based pages, editor rendering, and
reset mode.

File-based page parsing now handles attribute-aware custom container mappings
during HTML parsing. `bs:` page tags also resolve hyphenated block namespaces
correctly, so names like `custom-theme/card` no longer trip up the parser.

Generic non-core container tags now serialize valid `innerContent` when they do
not provide wrapper markup. That makes synced page content more predictable and
keeps WordPress block serialization valid.

The editor received a set of fixes around preview and preload behavior:

- Tailwind editor CSS now includes class candidates from file-based page
  templates.
- Editor preloads preserve RichText wrapper classes for repeated blocks of the
  same type.
- Editor preloads match blocks whose default field values are expanded by
  Gutenberg but omitted from saved block markup.
- Registered default attributes expanded by Gutenberg are ignored before
  comparing render cache keys.
- Editor and inserter preview renders now keep separate cache entries, even
  when they share a client id.
- `bs_get_group()` now works cleanly with reused custom field groups, including
  groups whose id matches the custom field name, and only extracts exact group
  prefixes.
- Link fields with `withCreateSuggestion` now wire the WordPress LinkControl
  create flow correctly and create draft pages through the REST API.
- PHP block templates now execute once per render instead of running a discarded
  preflight include.

Reset mode was tightened as well. It now neutralizes Gutenberg editor block
width and vertical margin rules for configured post types, removes WordPress
iframe content/reset styles where needed, and gives Blockstudio cleaner
hover/selection overlays.

The regression coverage for complex InnerBlocks patterns was also expanded to
include nested Blockstudio blocks, so the editor render path is checked against
the pattern insertion cases that are hardest to see manually.

## Performance tooling

Local anonymous `blockstudio-perf=1` probes can now emit `Server-Timing` headers
without injecting the debug panel.

The timings also cover more of the build lifecycle: discovery, asset
processing, registration, and overrides. That makes it easier to see where time
is actually going without turning on a visible profiling UI.

## Why this release matters

The main theme of 7.3 is composition.

UI components make it easier to compose application interfaces. Expanded Editor
makes it easier to edit larger blocks. Plugin dependencies let blocks describe
the plugin context they need. Editor policies move project-level block editor
rules into `blockstudio.json`. Block tag aliases make template authoring nicer.
The parser, PHPStan, and editor fixes make those compositions more reliable once
they move through WordPress.

Blockstudio is still file-based. Blocks are still folders. Templates still do
the work. But the framework now has a stronger UI layer on top of that
foundation.

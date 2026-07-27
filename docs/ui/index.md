---
title: UI Components
description: Enable and render the bundled Blockstudio UI components.
path: "."
order: 41
meta_title: "UI Components"
meta_description: "Enable and render the bundled Blockstudio UI components."
---

# UI Components

Blockstudio 7.3 includes a bundled set of headless UI blocks powered by the WordPress Interactivity API. They are registered under the `bsui/*` namespace and can be composed in templates, file-based pages, patterns, and post content.


> **Note**
>
> UI components are in beta while we gather feedback on the component set,
> naming, behavior, and project usage patterns. They are ready to try in 7.3,
> but the API surface may still be refined before it is marked stable.


## Enable UI Components

Bundled UI components are opt-in. Enable them in your theme's `blockstudio.json`:

```json title="blockstudio.json"
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "ui": {
    "enabled": true
  }
}
```

Or enable them with a filter:

```php title="functions.php"
add_filter('blockstudio/settings/ui/enabled', '__return_true');
```

When enabled, Blockstudio registers the bundled component library from `includes/ui/blocks`.

## Public Inventory and Examples

The bundled implementation blocks remain normal `bsui/*` registrations, but
consumers should use the public family inventory when building a component
gallery:

```php
use Blockstudio\Ui;

$families = Ui::inventory();
$examples = Ui::examples();
```

`Ui::inventory()` groups every compound component under its public root and
lists the implementation registrations required by that family.
`Ui::examples()` adds a deterministic normalized declaration with default and
illustrative data. It preserves required root/child relationships, so the
result can be passed directly to `Blockstudio\Render::document()`.

The public Canvas inventory exposes these examples under its `ui` type and
omits bundled implementation registrations from the normal `blocks` type. This
produces one complete example per public component family instead of a gallery
of internal root, trigger, popup, and layer fragments.

When assembling an already-rendered document, `Ui::global_assets($block_names,
$html)` returns the exact bundled global style and script only when selected
output actually uses a UI component.

## Rendering

Use the normal block tag syntax:

```html
<block name="bsui/button" label="Save" variant="default"></block>
```

The shorter `<bs:>` syntax works too:

```html
<bs:bsui-button label="Save" variant="default" />
```

In Blockstudio templates, block tags render automatically. In file-based pages and patterns, they are synced as native WordPress blocks. If you want to render UI components directly from post content or widget areas, enable page-level block tags:

```json title="blockstudio.json"
{
  "ui": {
    "enabled": true
  },
  "blockTags": {
    "enabled": true,
    "allow": ["bsui/*", "app/*"]
  }
}
```

## Basic Components

Simple components render as a single block:

```html
<block name="bsui/button" label="Continue" variant="default"></block>
<block name="bsui/badge" label="Beta"></block>
<block
  name="bsui/input"
  nameAlt="email"
  type="email"
  placeholder="Email"
></block>
<block name="bsui/textarea" name="message" placeholder="Message"></block>
<block name="bsui/spinner"></block>
```

Attributes map directly to each component's `block.json` attributes. For example, `bsui/button` supports `label`, `icon`, `iconPosition`, `href`, `variant`, `size`, and `disabled`.

```php title="template.php"
bs_render_block([
  'name' => 'bsui/button',
  'data' => [
    'label' => 'Continue',
    'icon' => [
      'set' => 'heroicons',
      'subSet' => 'outline',
      'icon' => 'arrow-right',
    ],
    'iconPosition' => 'right',
  ],
]);
```

## Compound Components

Many UI components are compound blocks. The root block owns the state, and child blocks declare their parent.

### Tabs

```html
<block name="bsui/tabs" defaultValue="tab1">
  <block name="bsui/tabs-list">
    <block name="bsui/tabs-trigger" value="tab1" title="Features"></block>
    <block name="bsui/tabs-trigger" value="tab2" title="Pricing"></block>
    <block name="bsui/tabs-trigger" value="tab3" title="FAQ"></block>
  </block>
  <block name="bsui/tabs-panel" value="tab1">
    <p>Features content.</p>
  </block>
  <block name="bsui/tabs-panel" value="tab2">
    <p>Pricing content.</p>
  </block>
  <block name="bsui/tabs-panel" value="tab3">
    <p>FAQ content.</p>
  </block>
</block>
```

### Dialog

```html
<block name="bsui/dialog">
  <block name="bsui/dialog-trigger" label="Open Dialog"></block>
  <block name="bsui/dialog-backdrop"></block>
  <block name="bsui/dialog-popup">
    <p>Dialog content.</p>
    <block name="bsui/dialog-close" label="Close"></block>
  </block>
</block>
```

### Select

```html
<block name="bsui/select" placeholder="Choose a fruit" nameAlt="fruit">
  <block name="bsui/select-trigger"></block>
  <block name="bsui/select-popup">
    <block name="bsui/select-option" value="apple" label="Apple"></block>
    <block name="bsui/select-option" value="banana" label="Banana"></block>
    <block name="bsui/select-option" value="cherry" label="Cherry"></block>
  </block>
</block>
```

### Form Fields

```html
<block name="bsui/form" block="contact/form" successMessage="Thank you!">
  <block name="bsui/field">
    <block name="bsui/field-label" text="Email"></block>
    <block name="bsui/input" nameAlt="email" type="email"></block>
    <block name="bsui/field-error"></block>
  </block>
</block>
```

## Available Blocks

The bundled UI library includes:

| Area       | Blocks                                                                                                                                                                                                                                                                                           |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Layout     | `bsui/aspect-ratio`, `bsui/card`, `bsui/separator`, `bsui/stack`, `bsui/table`                                                                                                                                                                                                                   |
| Actions    | `bsui/button`, `bsui/button-group`, `bsui/toggle`, `bsui/toggle-group`, `bsui/toolbar`                                                                                                                                                                                                           |
| Inputs     | `bsui/checkbox`, `bsui/color-picker`, `bsui/date-input`, `bsui/file-upload`, `bsui/input`, `bsui/number-field`, `bsui/otp-input`, `bsui/password-input`, `bsui/phone-input`, `bsui/radio-group`, `bsui/rating`, `bsui/select`, `bsui/slider`, `bsui/switch`, `bsui/textarea`, `bsui/time-picker` |
| Overlays   | `bsui/alert-dialog`, `bsui/dialog`, `bsui/drawer`, `bsui/menu`, `bsui/menubar`, `bsui/popover`, `bsui/preview-card`, `bsui/tooltip`                                                                                                                                                              |
| Navigation | `bsui/accordion`, `bsui/breadcrumb`, `bsui/carousel`, `bsui/collapsible`, `bsui/context-menu`, `bsui/navigation-menu`, `bsui/pagination`, `bsui/tabs`                                                                                                                                            |
| Feedback   | `bsui/badge`, `bsui/kbd`, `bsui/meter`, `bsui/progress`, `bsui/skeleton`, `bsui/spinner`, `bsui/text`, `bsui/toast`                                                                                                                                                                              |
| Forms      | `bsui/field`, `bsui/field-group`, `bsui/form`                                                                                                                                                                                                                                                    |

Compound components also expose child blocks such as `bsui/dialog-trigger`, `bsui/dialog-popup`, `bsui/tabs-list`, `bsui/tabs-trigger`, `bsui/tabs-panel`, `bsui/select-trigger`, `bsui/select-popup`, and `bsui/select-option`.

## Styling

UI components ship with their own CSS custom properties using the `--bs-ui-*` prefix. You can override them from your theme CSS:

```css title="style.css"
:root {
  --bs-ui-radius: 0.5rem;
  --bs-ui-primary: oklch(0.45 0.18 260);
  --bs-ui-primary-foreground: white;
}
```

The components are headless in behavior, but opinionated enough to be usable out of the box. Override tokens first, then add component-specific CSS where needed.

Bundled component CSS is emitted in the named `bsui` cascade layer. Unlayered
theme CSS can override component rules without `!important`:

```css title="style.css"
.button-brand {
  background: var(--brand-primary);
}
```

To add a custom `bsui/button` variant, append the select option with the
existing attributes filter and provide the variant CSS with
`blockstudio/ui/button/variants-style`:

```php title="functions.php"
add_filter('blockstudio/blocks/attributes', function($attribute, $block) {
  if (($block['name'] ?? '') === 'bsui/button' && ($attribute['id'] ?? '') === 'variant') {
    $attribute['options'][] = [
      'label' => 'Brand',
      'value' => 'brand',
    ];
  }

  return $attribute;
}, 10, 2);

add_filter('blockstudio/ui/button/variants-style', function($css) {
  return $css . '
[data-bsui-button][data-variant="brand"] {
  background: var(--brand-primary);
  color: white;
}';
});
```

## Related

- [Programmatic Rendering](/docs/blocks/rendering)
- [Components](/docs/blocks/components)
- [Interactivity API](/docs/blocks/interactivity)

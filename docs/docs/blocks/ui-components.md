---
title: UI Components
description: The bundled bsui component library, the design system behind it, and how to theme it.
path: "blocks/ui-components"
order: 41
section: "Blocks"
subsection: "Rendering"
meta_title: "UI Components"
meta_description: "The bundled bsui component library, the design system behind it, and how to theme it."
---

# UI Components

Blockstudio ships a bundled library of 59 headless-styled UI components under the `bsui` namespace, powered by the [WordPress Interactivity API](/docs/blocks/interactivity). Buttons, inputs, selects, dialogs, menus, tabs, forms: the working vocabulary of an application interface, available in templates, file-based pages, patterns, and post content.

Every component is an ordinary Blockstudio block. Each one is a directory with a `block.json` and a PHP template, registered through the same filesystem discovery as your project blocks and rendered with the same block and tag syntax. There is no separate runtime, no build step, and no JavaScript framework: behavior is Interactivity API stores, styling is plain CSS custom properties.

```html
<bs:bsui-button label="Save changes" variant="default" />
```

The full component reference with rendered previews lives at [/ui](/ui), and [/ui/examples](/ui/examples) collects live multi-component compositions. For how the components fit together into working interfaces, see [UI Composition](/docs/blocks/ui-composition).

The library's contract, in one paragraph: one token layer under the `--bs-ui-` prefix, one spacing scale that containers apply through `gap`, one label primitive, one modal surface, per-component stylesheets that load with the block, all component CSS in a cascade layer your theme outranks by default, visibility through the `hidden` attribute, and reduced motion respected globally. The rest of this page unpacks each of those choices.

## Enabling the library

The bundled components are opt-in. Enable them once in `blockstudio.json`:

```json title="blockstudio.json"
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "ui": {
    "enabled": true
  }
}
```

Or with a filter:

```php title="functions.php"
add_filter('blockstudio/settings/ui/enabled', '__return_true');
```

With the setting on, all 59 components register automatically from the plugin's `includes/ui/blocks` directory. There is nothing to enqueue and nothing to import; a component's assets load when the component renders.

## Rendering

Components render like any other block. The canonical tag spelling is `<bs:bsui-slug>`:

```html
<bs:bsui-badge label="Beta" />
<bs:bsui-input name="email" type="email" placeholder="Email" />
<bs:bsui-spinner />
```

The `<block>` syntax and PHP rendering work too:

```html
<block name="bsui/button" label="Continue" variant="outline"></block>
```

```php title="template.php"
bs_render_block([
  'name' => 'bsui/button',
  'data' => [
    'label' => 'Continue',
    'variant' => 'outline',
  ],
]);
```

Structured fields work the same way from PHP. The button's `icon` attribute is a Blockstudio icon field, so it takes the field's array shape:

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

Attributes map directly to each component's `block.json` fields; the reference at [/ui](/ui) lists every attribute with its type, options, and default. In Blockstudio templates, tags render automatically. In file-based pages and patterns they sync as native WordPress blocks. To render components from post content or widget areas, enable page-level [block tags](/docs/blocks/rendering#block-tags):

```json title="blockstudio.json"
{
  "ui": {
    "enabled": true
  },
  "blockTags": {
    "enabled": true,
    "allow": ["bsui/*"]
  }
}
```

### Simple and compound components

Simple components are a single block. Compound components are a family: a root block that owns the state and child blocks that declare their `parent` in `block.json`. The root exposes an `InnerBlocks` area, so the same structure works in the editor and in tags:

```html
<bs:bsui-dialog>
  <bs:bsui-dialog-trigger trigger_label="Open dialog" />
  <bs:bsui-dialog-backdrop />
  <bs:bsui-dialog-popup>
    <p>Dialog content.</p>
    <bs:bsui-dialog-close label="Close" />
  </bs:bsui-dialog-popup>
</bs:bsui-dialog>
```

Parent and child relationships, trigger attributes like `trigger_label`, and the runtime stores behind them are covered in depth on the [UI Composition](/docs/blocks/ui-composition) page.

### In the editor

The components are first-class editor citizens. They register in the inserter's Design category, while child blocks that declare a `parent` appear only inside their family. Inline styles and scripts enqueue in the editor as well as on the frontend, and the Interactivity API is loaded inside the editor iframe, so a dialog opens and a select drops down in the canvas, not just on the published page. Compound roots declare `allowedBlocks` on their `InnerBlocks` areas, so the inserter offers exactly the children that belong inside a dialog popup or a select popup. State that the components seed through `data-wp-context` behaves identically in both places, because context, unlike server-side Interactivity state, is carried in the markup itself.

## The design system

The components look finished out of the box, but every visual decision routes through one small system. Understanding it is the difference between fighting the library and steering it.

### One token layer

All shared values are CSS custom properties with the `--bs-ui-` prefix, defined on `:root` in a single global stylesheet. The palette is oklch, with a complete dark scheme activated by a `.dark` class on any ancestor.

| Group | Tokens | Defaults worth knowing |
| --- | --- | --- |
| Color | The base pair `--bs-ui-background` and `--bs-ui-foreground`; the surfaces and roles `--bs-ui-card`, `--bs-ui-popover`, `--bs-ui-primary`, `--bs-ui-secondary`, `--bs-ui-muted`, `--bs-ui-accent`, `--bs-ui-destructive`, `--bs-ui-success`, `--bs-ui-warning`, and `--bs-ui-info`, each with a `-foreground` pair; the lines `--bs-ui-border`, `--bs-ui-input`, `--bs-ui-ring` | Light neutral scale, near-black primary |
| Radius | `--bs-ui-radius`, `-sm`, `-lg`, `-full` | `0.5rem`; `-sm` and `-lg` derive from it by 2px |
| Typography | `--bs-ui-font-sans`, `--bs-ui-font-mono`, `--bs-ui-font-size` | System font stacks, `0.875rem` |
| Controls | `--bs-ui-control-height`, `--bs-ui-control-padding`, `--bs-ui-popup-padding` | `2.25rem`, `0.75rem`, `0.25rem` |
| Shadows | `--bs-ui-shadow-sm`, `--bs-ui-shadow`, `--bs-ui-shadow-md`, `--bs-ui-shadow-lg` | Layered oklch shadows |
| Modal surface | `--bs-ui-modal-padding`, `--bs-ui-modal-gap`, `--bs-ui-modal-header-gap`, `--bs-ui-modal-footer-gap` | `1.5rem`, `1.5rem`, `0.5rem`, `0.5rem` |
| Spacing | `--bs-ui-space-1` through `--bs-ui-space-8`, `--bs-ui-rhythm`, `--bs-ui-field-gap` | See below |
| Motion | `--bs-ui-transition`, `--bs-ui-transition-slow` | `150ms` and `200ms` eased |

Change a token and every component that reads it follows. Because derived tokens are `calc()` chains, overriding `--bs-ui-radius` alone re-tunes the small and large radii with it.

### The spacing scale and rhythm

Vertical rhythm is not something you add with margins. It comes from a fixed scale:

```css
--bs-ui-space-1: 0.25rem;
--bs-ui-space-2: 0.5rem;
--bs-ui-space-3: 0.75rem;
--bs-ui-space-4: 1rem;
--bs-ui-space-6: 1.5rem;
--bs-ui-space-8: 2rem;
--bs-ui-rhythm: var(--bs-ui-space-4);
--bs-ui-field-gap: 0.375rem;
```

Containers own the rhythm by spacing their children with `gap` on that scale. A `bsui/field` separates its label, control, description, and error with `--bs-ui-field-gap`. A `bsui/field-group` stacks fields on `--bs-ui-rhythm`. A modal popup spaces its header, content, and footer regions with `--bs-ui-modal-gap`. Components do not space themselves with outer margins; the container they sit in owns the distance, so compositions do not fight collapsing margins.

When no container owns the distance you need, insert an explicit step with `bsui/space`. It renders an `aria-hidden` div whose height is one value from the scale, sizes `1`, `2`, `3`, `4`, `6`, and `8`, default `4`:

```html
<bs:bsui-space size="6" />
```

Because both the containers and `bsui/space` read the same tokens, retuning `--bs-ui-space-4` retunes the default rhythm of the whole library at once.

### One label primitive

There is exactly one definition of what a form label looks like: `bsui/label`. The `bsui/field-label` template, the checkbox, the switch, and each `bsui/radio-group` item do not restyle a label; they render that block:

```php title="field/label/index.php"
echo bs_block(
	array(
		'name' => 'bsui/label',
		'data' => array( 'text' => (string) ( $a['text'] ?? '' ) ),
	)
);
```

A change to the label primitive's stylesheet changes every label in the library, and nothing else can drift, because nothing else defines label typography.

### Native form participation

The custom controls are real form fields. Give a component a `name` and it renders a hidden input bound to its state, so a surrounding form posts plain values with no JavaScript on your side. The select's hidden input tracks `state.selectedValue`, the checkbox posts its `value` (default `on`) while checked, and the date input posts an ISO date alongside the formatted display value. Checkbox, radio group, select, slider, switch, rating, and the specialized inputs all follow this pattern.

One naming detail: the `<block>` syntax already uses `name` for the block's own name, so form components also accept `nameAlt` as an equivalent spelling. With `<bs:>` tags, plain `name` works:

```html
<bs:bsui-select name="country" placeholder="Country" />
<block name="bsui/select" nameAlt="country" placeholder="Country"></block>
```

### One modal surface

Dialog, alert dialog, and drawer are one surface wearing three shapes. All three popups read the `--bs-ui-modal-*` tokens for their padding and internal gaps, sit on the same popover background and border tokens, and use the same backdrop: fixed, full-viewport, half-black with a 4px backdrop blur, at `z-index: 50` under a popup at `z-index: 51`. Overriding `--bs-ui-modal-padding` restyles every modal layer in the project consistently.

### The page is the bundle

Each component directory carries its own `style.inline.css` and, where it needs behavior, a `script.inline.js`. Blockstudio inlines a block's styles into the head and its scripts before the closing body tag, once per block, on exactly the pages where the block renders. There is no library-wide stylesheet of 59 components' rules and no bundle to split: a page that renders a button and a select ships the CSS and JS for a button and a select.

Two small global assets exist, the token sheet and a shared helper script, and even those are injected only when the response actually contains a bundled component.

### The bsui cascade layer

Component CSS is emitted inside the named `bsui` cascade layer. The global sheet declares the layer up front, and every per-component stylesheet is wrapped in `@layer bsui { }` at output time. Unlayered CSS always beats layered CSS in the cascade, so any plain rule in your theme overrides component styling without `!important` and without specificity games:

```css title="style.css"
[data-bsui-button][data-variant="default"] {
  border-radius: 0;
}
```

### Hidden, focus, and motion

Three conventions run through every component:

- Visibility is the `hidden` attribute. Stylesheets style the open state through `:not([hidden])`, so setting `hidden` always wins and nothing needs a display override to close.
- Focus is one ring. Focusable parts carry `data-bsui-focus`, and a single global rule draws a 2px `:focus-visible` outline in `--bs-ui-focus-ring`, falling back to `--bs-ui-ring`. Components repoint the ring instead of redrawing it; an invalid field, for example, sets `--bs-ui-focus-ring` to the destructive color.
- Motion respects the user. A global `prefers-reduced-motion` rule collapses component animations and transitions to 1ms.

## Composition first

The library has one rule for sharing a look: render the block. The data attributes in the components' markup, `data-bsui-button`, `data-bsui-label`, and the rest, are a private contract between each component's template and its own stylesheet. Copying one onto your own element borrows today's CSS and none of tomorrow's, and it silently breaks when the component's markup evolves.

Rendering the block instead means the stylesheet and behavior travel with it. The library holds itself to this rule internally. The date input does not reimplement a month grid; its popup renders the calendar component:

```php title="date-input/root/index.php"
<div data-bsui-date-input-popup hidden>
	<?php echo bs_block( array( 'name' => 'bsui/calendar', 'data' => array() ) ); ?>
</div>
```

The field label renders the label primitive, as shown above. The dialog's close control does not style its own button; it renders the system one:

```php title="dialog/close/index.php"
echo bs_block( array(
	'name' => 'bsui/button',
	'data' => $is_x
		? array( 'variant' => 'ghost', 'size' => 'icon', 'label' => '✕' )
		: array( 'variant' => 'outline', 'label' => $a['label'] ?? 'Close' ),
) );
```

Your blocks should do the same. When a project block needs a button that looks like the system button, it renders `bsui/button` with `bs_block()` or a nested tag, and it gets every future refinement of the button for free.

## Accessibility

The components carry their accessibility with them, so a composed page starts from correct semantics rather than retrofitting them:

- Overlays are announced. Dialog popups render `role="dialog"` with `aria-modal="true"`, triggers carry `aria-haspopup`, and disclosure patterns bind `aria-expanded` to their open state.
- Selection widgets use the real patterns. The select renders a `role="listbox"` with `role="option"` children, tracks the active option through `aria-activedescendant`, and reflects choices through `aria-selected`. The checkbox is a `role="checkbox"` button whose `aria-checked` supports `mixed` for the indeterminate state.
- Keyboards work everywhere. Listboxes and menus support arrow keys, Home, End, Enter, Space, and type-ahead matching; modals trap Tab inside themselves and return focus to their trigger on close.
- Labels are real labels. The label primitive renders a `label` element and accepts a `for` attribute, and the decorative `bsui/space` block is `aria-hidden`.

None of this requires configuration; it is how the templates are written.

## Theming

Tokens inherit, so the highest-leverage override is a handful of custom properties on `body`. That wins over the `:root` defaults for everything on the page, regardless of stylesheet order:

```css title="style.css"
body {
  --bs-ui-primary: oklch(0.45 0.18 260);
  --bs-ui-primary-foreground: oklch(0.985 0 0);
  --bs-ui-ring: oklch(0.45 0.18 260);
  --bs-ui-radius: 0.75rem;
  --bs-ui-font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
}
```

Scope the same overrides to a section wrapper to retheme one area. The dark scheme needs no CSS at all; add the class to any ancestor and every token underneath it flips:

```html
<body class="dark">
```

To adjust the dark palette rather than adopt it wholesale, override tokens inside your own `.dark` rule; it participates in the cascade like any other unlayered CSS.

For structural changes beyond tokens, write plain CSS against the component's attributes; the cascade layer guarantees your unlayered rules win. To extend a component's public API, add to its fields and provide the CSS through the per-component style filter. Adding a brand variant to the button:

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

The `blockstudio/ui/{component}/variants-style` CSS is appended inside the component's own stylesheet, inside the `bsui` layer, so it loads exactly when the component does.

## The programmatic inventory

For galleries, previews, and tooling, the `Blockstudio\Ui` class exposes the library as data:

```php
use Blockstudio\Ui;

$families = Ui::inventory();
$examples = Ui::examples();
```

`Ui::inventory()` groups every compound component under its public root and lists the implementation registrations that family needs. `Ui::examples()` derives one deterministic, renderable declaration per family, preserving required root and child relationships, ready to pass to `Blockstudio\Render::document()`. When assembling an already-rendered document, `Ui::global_assets($block_names, $html)` returns the global token sheet and helper script only when the selected output actually uses a bundled component. The `blockstudio/ui/inventory` and `blockstudio/ui/examples` filters let you reshape both.

## The component set

| Area | Components |
| --- | --- |
| Layout and structure | `bsui/aspect-ratio`, `bsui/card`, `bsui/scroll-area`, `bsui/separator`, `bsui/space`, `bsui/stack`, `bsui/table` |
| Actions | `bsui/button`, `bsui/button-group`, `bsui/toggle`, `bsui/toggle-group`, `bsui/toolbar` |
| Inputs and forms | `bsui/calendar`, `bsui/checkbox`, `bsui/color-picker`, `bsui/combobox`, `bsui/date-input`, `bsui/field`, `bsui/field-group`, `bsui/file-upload`, `bsui/form`, `bsui/input`, `bsui/label`, `bsui/number-field`, `bsui/otp-input`, `bsui/password-input`, `bsui/phone-input`, `bsui/radio-group`, `bsui/rating`, `bsui/select`, `bsui/slider`, `bsui/switch`, `bsui/textarea`, `bsui/time-picker` |
| Overlays | `bsui/alert-dialog`, `bsui/context-menu`, `bsui/dialog`, `bsui/drawer`, `bsui/menu`, `bsui/menubar`, `bsui/popover`, `bsui/preview-card`, `bsui/tooltip` |
| Navigation | `bsui/accordion`, `bsui/breadcrumb`, `bsui/carousel`, `bsui/collapsible`, `bsui/navigation-menu`, `bsui/pagination`, `bsui/tabs` |
| Feedback and display | `bsui/avatar`, `bsui/badge`, `bsui/kbd`, `bsui/meter`, `bsui/progress`, `bsui/skeleton`, `bsui/spinner`, `bsui/text`, `bsui/toast` |

Compound families also register their child blocks, such as `bsui/dialog-trigger`, `bsui/dialog-popup`, `bsui/select-trigger`, `bsui/select-popup`, `bsui/select-option`, `bsui/tabs-list`, `bsui/tabs-trigger`, and `bsui/tabs-panel`. Every part, with its attributes and defaults, is documented at [/ui](/ui).


> **[UI Composition](/docs/blocks/ui-composition)**
>
> Parent and child relationships, trigger forwarding, the stores underneath, and the layering model that makes selects work inside dialogs.


> **[Component reference](/ui)**
>
> All 59 components with rendered previews, attributes, and worked examples, plus live compositions at [/ui/examples](/ui/examples).


> **[Programmatic Rendering](/docs/blocks/rendering)**
>
> Block tags, prefixes, and the PHP rendering functions the examples on this page use.


> **[Interactivity](/docs/blocks/interactivity)**
>
> The directive and store model the UI components are built on.

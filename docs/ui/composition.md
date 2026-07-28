---
title: UI Composition
description: How the bundled UI components compose into working interfaces, and the runtime model underneath.
path: "composition"
order: 42
section: "UI"
meta_title: "UI Composition"
meta_description: "How the bundled UI components compose into working interfaces, and the runtime model underneath."
---

# UI Composition

The [bundled UI components](/docs/blocks/ui-components) are designed to be assembled, not used one at a time. A select opens inside a dialog, a form validates inside a drawer, a menu sits in a table row. This page explains the machinery that makes those seams work: how compound families are structured, how triggers forward button props, what the Interactivity API does underneath, and the layering model that keeps stacked surfaces coherent.

## Compound families

A compound component is a root block plus child blocks. The root owns the state; each child declares its place in the family through `parent` and `usesContext` in its `block.json`:

```json title="select/option/block.json"
{
  "name": "bsui/select-option",
  "parent": ["bsui/select-popup"],
  "usesContext": ["bsui/select"]
}
```

Roots expose an `InnerBlocks` area with an `allowedBlocks` list and a starting `template`, so inserting a dialog in the editor scaffolds its trigger, backdrop, and popup, and a family behaves identically whether it is composed in the editor, in a file-based page, or in tags:

```html
<bs:bsui-select name="role" placeholder="Pick a role">
  <bs:bsui-select-trigger />
  <bs:bsui-select-popup>
    <bs:bsui-select-option value="viewer" label="Viewer" />
    <bs:bsui-select-option value="editor" label="Editor" />
  </bs:bsui-select-popup>
</bs:bsui-select>
```

Popups and panels are real `InnerBlocks` areas, not slots with special syntax. A `bsui/dialog-popup` accepts nested blocks the same way a group block does, which is what makes putting a form, a select, or another dialog inside one an ordinary act of composition.

### Flat data or composed children

Some families accept their content as data instead of children. The select renders its own trigger and listbox when you hand it an `options` JSON array, which suits options that come from PHP:

```php title="template.php"
bs_render_block([
  'name' => 'bsui/select',
  'data' => [
    'name' => 'role',
    'placeholder' => 'Pick a role',
    'options' => wp_json_encode([
      ['value' => 'viewer', 'label' => 'Viewer'],
      ['value' => 'editor', 'label' => 'Editor'],
    ]),
  ],
]);
```

Both forms produce the same markup contract and the same runtime behavior. Use children when the structure is authored, `options` when it is computed.

## Trigger forwarding

Overlay components open from a button, and that button should be the system button. Rather than each trigger reimplementing one, the trigger blocks of `bsui/dialog`, `bsui/alert-dialog`, `bsui/drawer`, `bsui/menu`, `bsui/popover`, `bsui/collapsible`, and `bsui/tooltip` embed `bsui/button` through a [block field](/docs/blocks/attributes/block-field):

```json title="dialog/trigger/block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "id": "trigger",
        "type": "block",
        "block": "bsui/button",
        "idStructure": "trigger_{id}"
      }
    ]
  }
}
```

The `idStructure` expands the button's fields onto the trigger with a `trigger_` prefix. Every button prop is available: `trigger_label`, `trigger_variant`, `trigger_size`, `trigger_icon`, `trigger_iconPosition`, `trigger_href`, `trigger_disabled`.

```html
<bs:bsui-dialog-trigger trigger_label="Invite teammate" trigger_variant="outline" />
```

In the trigger's template, `$a['trigger']` is the fully rendered button, so the button's stylesheet and future refinements travel with it. At runtime, a shared helper forwards the wrapper's ARIA wiring (`aria-haspopup`, `aria-expanded`, `aria-controls`, `aria-describedby`, `aria-labelledby`, `id`) onto the inner button and keeps it in sync through a `MutationObserver`, so assistive technology sees one coherent control.

## The Interactivity API underneath

Every interactive family is a [WordPress Interactivity API](/docs/blocks/interactivity) store named after its root block: `bsui/dialog`, `bsui/select`, `bsui/form`, `bsui/tabs`, and so on. Three consequences follow.

**State is seeded server-side.** The root template prints its initial state as `data-wp-context`, straight from the block's attributes:

```html
<div
  data-wp-interactive="bsui/select"
  data-wp-context='{"open":false,"value":"","placeholder":"Pick a role", ...}'
  data-bsui-select-root
>
```

The first paint is correct HTML with no client-side hydration flash, popups start `hidden`, and the same markup works in the editor canvas because context does not depend on a frontend-only state pass.

**Behavior is wired with directives, not with your JavaScript.** The templates connect markup to stores declaratively. The select's trigger, stripped of its PHP, is nothing but directives:

```html
<button
  data-wp-interactive="bsui/select"
  data-wp-on--click="actions.toggle"
  data-wp-on--keydown="actions.handleTriggerKeyDown"
  data-wp-bind--aria-expanded="state.ariaExpanded"
  data-wp-text="state.displayValue"
  data-bsui-select-trigger
  aria-haspopup="listbox"
>
```

`data-wp-on` routes events to actions, `data-wp-bind` keeps attributes in sync with derived state, `data-wp-text` renders state as text, and `data-wp-on-document--click` gives roots their outside-click dismissal. Composing components never requires writing an event handler; the store ships with the block as an inline module.

**Your stores can plug into the seams.** Form controls accept directive expressions as attributes, and the Interactivity API's namespaced syntax points them at any store. `onChange` routes change handling to one of your actions, `checked` binds the control's state to your state, and `bindText` renders a label as a `data-wp-text` span bound to any state path:

```html
<bs:bsui-switch label="Notifications" checked="myPlugin::state.notifications" onChange="myPlugin::actions.toggleNotifications" />
<bs:bsui-checkbox label="0 selected" bindText="myPlugin::state.selectionSummary" />
```

`bindText` is available on `bsui/label`, `bsui/checkbox`, `bsui/switch`, `bsui/toggle`, and `bsui/radio-group` items. On the checkbox, switch, and radio items it flows into the shared label primitive, so state-driven text keeps the exact system label typography. Selects and other inputs additionally dispatch a bubbling `change` event from their root, carrying the new value in `detail`, for coarser integrations.

## Composing from your own blocks

Composition is not limited to pages. Block tags render automatically inside block templates, so a project block can emit `bsui` components directly and wrap them in its own structure:

```php title="my-theme/settings-panel/index.php"
<div useBlockProps class="settings-panel">
  <bs:bsui-switch label="Public profile" name="public" />
  <bs:bsui-space size="4" />
  <bs:bsui-button label="Save" variant="default" />
</div>
```

The same works from PHP with `bs_block()`, including nesting through the `content` key, as described under [programmatic rendering](/docs/blocks/rendering). Either way the component's stylesheet and store load with it, and your block stays a thin layer of arrangement over system parts.

## The layering model

Stacked surfaces are where component libraries usually fall apart. The bundled components follow a small set of rules, written for people building real UIs.

### Modals portal to the body

When a dialog, alert dialog, or drawer opens, its popup and backdrop are moved to `document.body`, with a placeholder comment marking the way home for when it closes. Portaling frees the modal from any ancestor's `overflow`, transform, or stacking context. Opening also locks page scroll: the lock is reference-counted across all overlay components, and it compensates for the scrollbar width so the page does not shift. The backdrop sits at `z-index: 50`, the popup at `51`, focus moves into the popup, Tab is trapped inside it, and closing returns focus to the trigger.

Modals stack. Opening a dialog from inside a dialog pushes onto one shared stack: only the outermost dialog shows its backdrop, and the dialogs behind the top one scale down and peek from behind it. The stack drives a `--bs-ui-nested-dialogs` custom property on each background popup, so the peeking offset and scale deepen with every level, and the parent matches its height to the child so the layers peek evenly. Closing restores everything in reverse, one layer per close.

When a modal closes, focus returns to its trigger, and it returns to the right element. A shared `getAnchor` helper resolves a trigger wrapper to the system button inside it, so the visible control regains focus rather than an invisible wrapper.

### The top modal is never transformed

That peeking effect transforms only the background dialogs. The top modal deliberately stays untransformed, because a transformed element becomes the containing block for `position: fixed` descendants, which would break every floating popup opened inside it.

This is the rule that makes the whole layering model compose. The floating layers all position themselves with fixed coordinates, which only mean the viewport as long as no transformed ancestor intervenes:

- The menu and the combobox compute their position against the trigger with Floating UI's `fixed` strategy, dropping below it and flipping or shifting when the viewport runs out.
- The select does the same when nothing is selected. When it already has a selection, it instead aligns its popup so the selected option sits directly over the trigger, clamped to the viewport, the way native selects behave on macOS.
- The context menu opens at the pointer's `clientX`/`clientY`.

Because the top modal is untransformed, all of these anchor correctly to their triggers inside a dialog, an alert dialog, or a drawer, at any nesting depth. Nothing about the popups knows they are inside a modal, and nothing needs to.

### Escape closes the innermost layer first

One Escape press closes exactly one layer, from the inside out:

1. An open floating layer (a select listbox, a menu, a popover, a date or phone popup) closes first and returns focus to its trigger. The select stops the event's propagation, and the modal handlers additionally stand down whenever any floating layer is open, so the dialog underneath never sees that press.
2. The top modal closes next.
3. With stacked modals, each further Escape pops one more from the stack.

Dismissal is a policy, not an accident. A dialog rendered with `dismissable="false"` ignores both Escape and backdrop clicks. And `bsui/alert-dialog` answers Escape like any modal but deliberately ignores backdrop clicks: the point of the pattern is that the choice has to be made rather than dismissed by accident. Backdrop clicks on a regular dialog close it, since `dismissable` defaults to true.

## A form in a dialog

The pattern that exercises every rule above at once: a trigger opens a modal, the modal contains a validating form, and the form contains a floating select.

```html
<bs:bsui-dialog>
  <bs:bsui-dialog-trigger trigger_label="Invite teammate" trigger_variant="outline" />
  <bs:bsui-dialog-backdrop />
  <bs:bsui-dialog-popup>
    <bs:bsui-dialog-header>
      <bs:bsui-dialog-title content="Invite a teammate" />
      <bs:bsui-dialog-description content="They receive an email with a join link." />
    </bs:bsui-dialog-header>
    <bs:bsui-dialog-content>
      <bs:bsui-form block="team/invite" successMessage="Invitation sent.">
        <bs:bsui-field name="email">
          <bs:bsui-field-label text="Email" />
          <bs:bsui-input name="email" type="email" placeholder="colleague@example.com" required="true" />
          <bs:bsui-field-error />
        </bs:bsui-field>
        <bs:bsui-field name="role">
          <bs:bsui-field-label text="Role" />
          <bs:bsui-select name="role" defaultValue="editor" placeholder="Pick a role">
            <bs:bsui-select-trigger />
            <bs:bsui-select-popup>
              <bs:bsui-select-option value="viewer" label="Viewer" />
              <bs:bsui-select-option value="editor" label="Editor" />
              <bs:bsui-select-option value="admin" label="Admin" />
            </bs:bsui-select-popup>
          </bs:bsui-select>
        </bs:bsui-field>
        <bs:bsui-space size="2" />
        <bs:bsui-button label="Send invite" />
      </bs:bsui-form>
    </bs:bsui-dialog-content>
    <bs:bsui-dialog-footer>
      <bs:bsui-dialog-close label="Cancel" />
    </bs:bsui-dialog-footer>
  </bs:bsui-dialog-popup>
</bs:bsui-dialog>
```

Every tag and attribute above is load-bearing: the trigger forwards to the system button, the title and description take `content`, the fields carry the names the form serializes, and `required="true"` coerces to a real boolean in the tag parser.

### What each store does at runtime

**`bsui/dialog`** seeds `{"open":false,"dismissable":true}` into context on the server. The trigger's click runs `actions.open`: the popup and backdrop portal to the body, scroll locks with scrollbar compensation, the popup animates in over 150ms, focus moves to its first focusable element, and Tab cycles inside it. The header, content, and footer regions space themselves on the modal gap tokens.

**`bsui/select`** waits inside the form with its listbox `hidden`. Clicking the trigger flips `context.open`, positions the listbox with fixed viewport coordinates, and because `defaultValue="editor"` is already selected, aligns the popup so Editor sits directly over the trigger. Arrow keys, Home, End, and type-ahead move the active option; Enter or a click selects; Tab and outside clicks dismiss without selecting. The trigger's visible text updates through `data-wp-text="state.displayValue"`, and the hidden `<input name="role">` tracks the value through `data-wp-bind--value="state.selectedValue"`, which is what the form later serializes. All of this anchors correctly inside the dialog because the top modal is untransformed.

**`bsui/form`** intercepts the submit. The `bsui/button` at the end of the form renders a plain `button` element with no `type` attribute, so it is the form's submit control by HTML default; the Cancel button lives in the footer outside the `form` element and cannot submit anything. On submit, the store serializes the form's `FormData` and sends it through the data API of the block named in `block`, here creating a row against the `team/invite` block's [database schema](/docs/blocks/block-api/database). Validation errors come back per field: each `bsui/field` carries its `name`, gets flagged invalid, repoints the focus ring to the destructive color, and shows the message in its `bsui/field-error` alert. On success, the form body hides and the `successMessage` shows, both through `data-wp-bind--hidden`.

**The layers unwind in order.** With the listbox open, Escape closes the listbox and refocuses the select trigger; the next Escape closes the dialog and refocuses Invite teammate. A backdrop click closes the dialog because `dismissable` was left at its default. Closing unlocks scroll and unportals the popup back to its place in the document.

The same markup pasted into a file-based page syncs as native blocks, and the same composition assembled in the editor previews interactively in the canvas, because every behavior above lives in directives and stores that load in both contexts.

## What keeps these seams working

The seams above are not conventions, they are invariants the library holds wherever it renders: every label resolves to one identical typography, every modal popup shares one surface and dims the page behind it, floating popups keep their anchor to the trigger while the page scrolls, and Escape closes exactly one layer at a time, from the inside out. The guarantees hold in the frontend and in the editor canvas, under the default tokens or your theme's, so a composition that behaves in one context behaves the same in the others.

## Live examples

The compositions on this page are the grammar; [/ui/examples](/ui/examples) is the phrasebook. It collects live, rendered compositions in three tiers: pairs that prove one seam between two or three components, recognizable product patterns built from a handful of them, and complete mini apps assembled the way the components ship inside real products. Each component page at [/ui](/ui) adds its own worked examples, from a labeled field to a calendar behind a popover.


> **[Interactivity](/docs/blocks/interactivity)**
>
> The directive and store model the UI components are built on, and how to use it in your own blocks.

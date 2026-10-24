---
title: Field Types
description: All available field types and their properties in Blockstudio.
path: "blocks/attributes/field-types"
order: 13
section: "Blocks"
subsection: "Attributes"
meta_title: "Field Types"
meta_description: "All available field types and their properties in Blockstudio."
---

# Field Types

Blockstudio provides a variety of field types for building block interfaces.

Projects can also register their own namespaced field types with
[`blockstudio/field_types`](/docs/blocks/attributes/custom-field-types). Use
that when you need a new editor control or a structured value shape. Use
[`custom/{name}` fields](/docs/blocks/attributes/custom-fields) when you only
need to reuse a group of existing fields.


> **[Field Patterns Cookbook](/guides/field-patterns)**
>
> Practical recipes: conditional groups, smart selects, repeaters, and queryable storage.


## General Properties

All fields share these common properties:

| Property      | Type    | Description                                                       |
| ------------- | ------- | ----------------------------------------------------------------- |
| `id`          | string  | Unique identifier for the attribute                               |
| `type`        | string  | The field type                                                    |
| `label`       | string  | Display label shown in the editor                                 |
| `description` | string  | Description text shown below the field                            |
| `default`     | mixed   | Default value for the field                                       |
| `fallback`    | mixed   | Fallback value when field is empty                                |
| `help`        | string  | Help text displayed as a tooltip next to the label                |
| `hidden`      | boolean | Hide the field from the UI                                        |
| `switch`      | boolean | Display a toggle that can disable the field. Defaults to `false`. |
| `translate`   | boolean | Set to `false` to leave the field out of the [WPML configuration](/docs/dev/wpml) |
| `conditions`  | array   | Conditional logic rules                                           |

Files fields with `"returnFormat": "id"` preserve imported string references,
including single values inside repeaters. Unresolved names show fallback cards
without thumbnails; Blockstudio does not resolve them to attachment IDs. See
[portable media references](/guides/field-patterns#return-format-variations) for details.

<!-- GENERATED_FIELD_TYPES_START -->

## attributes

Renders data attribute inputs.

```json
{
  "type": "attributes"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `array` | Default value that should be applied when first adding the block. |
| `fallback` | `array` | Fallback value that that will display when field value is empty. |
| `link` | `boolean` | Enables link selection from dropdown. |
| `media` | `boolean` | Enables media selection from dropdown. |

## block

References another Blockstudio block, expanding its fields inline. In templates, the value returns the rendered block output.

```json
{
  "type": "block"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `block` | `string` | The name of the block to reference (e.g., "mytheme/card"). |
| `returnFormat` | `string` | How the value should be returned in templates. |

## checkbox

Renders a set of checkbox inputs.

```json
{
  "type": "checkbox",
  "options": [
    {
      "value": "option1",
      "label": "Option 1"
    },
    {
      "value": "option2",
      "label": "Option 2"
    }
  ]
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `array \| string \| number` | Default value that should be applied when first adding the block. |
| `fallback` | `array \| string \| number` | Fallback value that that will display when field value is empty. |
| `options` | `array` | Options to choose from. |
| `returnFormat` | `string` | Specifies the return format value. |
| `populate` | `object` |  |

## classes

Renders a field to select CSS classes.

```json
{
  "type": "classes"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `tailwind` | `boolean` | Whether to enable Tailwind classes for this input field. |

## code

Renders a code editor.

```json
{
  "type": "code"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `autoCompletion` | `boolean` | Whether to enable autocompletion or not. |
| `foldGutter` | `boolean` | Whether to show the fold gutter or not. |
| `height` | `string` | The height of the editor. |
| `language` | `string` | The language to use for syntax highlighting. |
| `lineNumbers` | `boolean` | Whether to display line numbers or not. |
| `maxHeight` | `string` | The maximum height of the editor. |
| `minHeight` | `string` | The minimum height of the editor. |
| `popout` | `boolean` | Whether to show a button that opens the editor in a popup window. |

## color

Renders a color palette and color picker.

```json
{
  "type": "color",
  "options": [
    {
      "value": "option1",
      "label": "Option 1"
    },
    {
      "value": "option2",
      "label": "Option 2"
    }
  ]
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `options` | `array` |  |
| `populate` | `object` |  |
| `clearable` | `boolean` | Whether the palette should have a clearing button or not. |
| `disableCustomColors` | `boolean` | Whether to allow custom color or not. |

## date

Renders a date picker.

```json
{
  "type": "date"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `startOfWeek` | `number` | The day that the week should start on. 0 for Sunday, 1 for Monday, etc. |

## datetime

Renders a date and time picker.

```json
{
  "type": "datetime"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `is12Hour` | `boolean` | Whether we use a 12-hour clock. With a 12-hour clock, an AM/PM widget is displayed and the time format is assumed to be MM-DD-YYYY (as opposed to the default format DD-MM-YYYY). |

## files

Renders a button to the media library. Picked items can be reordered inline.

```json
{
  "type": "files",
  "min": 0,
  "max": 100,
  "multiple": false
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `array \| object \| number` | Default value that should be applied when first adding the block. |
| `fallback` | `array \| object \| number` | Fallback value that that will display when field value is empty. |
| `addToGallery` | `boolean` | If true, the gallery media modal opens directly in the media library where the user can add additional images. If false the gallery media modal opens in the edit mode where the user can edit existing images, by reordering them, remove them, or change their attributes. Only applies if gallery === true. |
| `allowedTypes` | `array \| string` | Array with the types of the media to upload/select from the media library. Each type is a string that can contain the general mime type e.g: 'image', 'audio', 'text', or the complete mime type e.g: 'audio/mpeg', 'image/gif'. If allowedTypes is unset all mime types should be allowed. |
| `gallery` | `boolean` | If true, the component will initiate all the states required to represent a gallery. By default, the media modal opens in the gallery edit frame, but that can be changed using the addToGalleryflag. |
| `max` | `number` | Maximum amount of files that can be added. |
| `min` | `number` | Minimum amount of files that can be added. |
| `multiple` | `boolean` | Whether to allow multiple selections or not. |
| `size` | `boolean` | Adds a media size dropdown to the field. |
| `textMediaButton` | `string` | Media button text. |
| `title` | `string` | Title displayed in the media modal. |
| `returnFormat` | `string` | Specifies the return format value. |
| `returnSize` | `string` | The media size to return when using the URL return format. |

## gradient

Renders a gradient palette and gradient picker

```json
{
  "type": "gradient",
  "options": [
    {
      "value": "option1",
      "label": "Option 1"
    },
    {
      "value": "option2",
      "label": "Option 2"
    }
  ]
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `options` | `array` |  |
| `populate` | `object` |  |
| `clearable` | `boolean` | Whether the palette should have a clearing button or not. |
| `disableCustomGradients` | `boolean` | Whether to allow custom color or not. |

## group

Renders multiple fields in an (optionally) collapsible container.

```json
{
  "type": "group"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `title` | `string` | Title text. It shows even when the component is closed. |
| `opened` | `boolean` | When set to true, the component will remain open regardless of the initialOpen prop and the |
| `initialOpen` | `boolean` | Whether or not the panel will start open. |
| `scrollAfterOpen` | `boolean` | Scrolls the content into view when visible. |
| `class` | `string` | Custom CSS class that will be applied to the group. |
| `style` | `object` | Custom CSS styles that will be applied to the group. |
| `attributes` | `array` | Accepts all other field types besides another tabs. |

## html-tag

Renders an HTML tag selector. Useful for choosing heading levels or semantic elements.

```json
{
  "type": "html-tag"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `tags` | `string \| array` | Which tags to include. Use a preset string or an array of tag names. |
| `exclude` | `array` | Tags to exclude from the preset. Only applies when using a preset string for tags. |

## icon

Renders an SVG icon from an icon set.

```json
{
  "type": "icon"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `object` | Default value that should be applied when first adding the block. |
| `fallback` | `object` | Fallback value that that will display when field value is empty. |
| `sets` | `array \| string` | Which icon set to include. Leave empty to include all. |
| `subSets` | `array \| string` | Which sub icon set to include. Leave empty to include all. |
| `returnFormat` | `string` | The format to return the icon in. |

## link

Renders a link control to choose internal or external links.

```json
{
  "type": "link"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `object` | Default value that should be applied when first adding the block. |
| `fallback` | `object` | Fallback value that that will display when field value is empty. |
| `hasRichPreviews` | `boolean` | Whether rich previews should be shown when adding an URL. |
| `noDirectEntry` | `boolean` | Whether to allow turning a URL-like search query directly into a link. |
| `noURLSuggestion` | `boolean` | Whether to add a fallback suggestion which treats the search query as a URL. |
| `opensInNewTab` | `boolean` | Adds a toggle control to the link modal. |
| `showSuggestions` | `boolean` | Whether to present suggestions when typing the URL. |
| `textButton` | `string` | Custom text that should be displayed inside the link button. |
| `withCreateSuggestion` | `boolean` | Whether unmatched link searches can create a new draft page suggestion. |

## message

Renders a message with custom content.

```json
{
  "type": "message"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `value` | `string` | The message to display. Block and attribute data is available in bracket syntax, e.g.: `{block.title}` or `{attributes.text}` |

## number

Renders a number input.

```json
{
  "type": "number",
  "min": 0,
  "max": 100,
  "step": 1
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `number` | Default value that should be applied when first adding the block. |
| `fallback` | `number` | Fallback value that that will display when field value is empty. |
| `dragDirection` | `string` | Determines the drag axis to increment/decrement the value. |
| `dragThreshold` | `number` | If isDragEnabled is true, this controls the amount of px to have been dragged before the value changes. |
| `hideHTMLArrows` | `boolean` | If true, the default input HTML arrows will be hidden. |
| `isShiftStepEnabled` | `boolean` | If true, enables mouse drag gesture to increment/decrement the number value. Holding SHIFT while dragging will increase the value by the shiftStep. |
| `max` | `number` | The maximum value length. |
| `min` | `number` | Minimum value length. |
| `required` | `boolean` | If true enforces a valid number within the control’s min/max range. If false allows an empty string as a valid value. |
| `shiftStep` | `number` | Amount to increment by when the SHIFT key is held down. This shift value is a multiplier to the step value. For example, if the step value is 5, and shiftStep is 10, each jump would increment/decrement by 50. |
| `step` | `number` | Amount by which the value is changed when incrementing/decrementing. It is also a factor in validation as value must be a multiple of step (offset by min, if specified) to be valid. Accepts the special string value any that voids the validation constraint and causes stepping actions to increment/decrement by 1. |

## radio

Renders a set of radio inputs.

```json
{
  "type": "radio",
  "options": [
    {
      "value": "option1",
      "label": "Option 1"
    },
    {
      "value": "option2",
      "label": "Option 2"
    }
  ]
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string \| number` | Default value that should be applied when first adding the block. |
| `fallback` | `string \| number` | Fallback value that that will display when field value is empty. |
| `options` | `array` | Options to choose from. |
| `returnFormat` | `string` | Specifies the return format value. |
| `populate` | `object` |  |

## range

Renders a range input to set a numerical value between two points.

```json
{
  "type": "range",
  "min": 0,
  "max": 100,
  "step": 1
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `number` | Default value that should be applied when first adding the block. |
| `fallback` | `number` | Fallback value that that will display when field value is empty. |
| `allowReset` | `boolean` | If this property is true, a button to reset the slider is rendered. |
| `initialPosition` | `number` | The slider starting position, used when no value is passed. The initialPosition will be clamped between the provided min and max prop values. |
| `isShiftStepEnabled` | `boolean` | If true, enables mouse drag gesture to increment/decrement the number value. Holding SHIFT while dragging will increase the value by the shiftStep. |
| `marks` | `array` | Renders a visual representation of step ticks. Custom mark indicators can be provided by an Array. |
| `max` | `number` | The maximum value length. |
| `min` | `number` | Minimum value length. |
| `railColor` | `string` | CSS color string to customize the rail element’s background. |
| `resetFallbackValue` | `number` | The value to revert to if the Reset button is clicked (enabled by allowReset) |
| `separatorType` | `string` | Define if separator line under/above control row should be disabled or full width. By default it is placed below excluding underline the control icon. |
| `shiftStep` | `number` | Amount to increment by when the SHIFT key is held down. This shift value is a multiplier to the step value. For example, if the step value is 5, and shiftStep is 10, each jump would increment/decrement by 50. |
| `showTooltip` | `boolean` | Forcing the Tooltip UI to show or hide. This is overridden to false when step is set to the special string value any. |
| `step` | `number` | Amount by which the value is changed when incrementing/decrementing. It is also a factor in validation as value must be a multiple of step (offset by min, if specified) to be valid. Accepts the special string value any that voids the validation constraint and causes stepping actions to increment/decrement by 1. |
| `trackColor` | `string` | CSS color string to customize the track element’s background. |
| `withInputField` | `boolean` | Determines if the input number field will render next to the RangeControl. This is overridden to false when step is set to the special string value any. |

## select

Renders a select input with support for single or multiple selections.

```json
{
  "type": "select",
  "options": [
    {
      "value": "option1",
      "label": "Option 1"
    },
    {
      "value": "option2",
      "label": "Option 2"
    }
  ],
  "multiple": false
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `array \| string \| number` | Default value that should be applied when first adding the block. |
| `fallback` | `array \| string \| number` | Fallback value that that will display when field value is empty. |
| `multiple` | `boolean` | If true, multiple options can be selected. "stylisedUi" will be automatically enabled. |
| `options` | `array` | Options to choose from. |
| `returnFormat` | `string` | Specifies the return format value. |
| `populate` | `object` |  |
| `stylisedUi` | `boolean` | Renders a stylised version of a select with the ability to search through items. |
| `allowNull` | `boolean \| string` | Allows the user to select an empty choice. If true, the label will be empty, otherwise the option will render the specified string. |
| `allowReset` | `boolean` | If this property is true, a button to reset the select is rendered. |

## tabs

Renders a tabbed interface for grouping fields.

```json
{
  "type": "tabs"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `tabs` | `array` | The tabs to display. |

## text

Renders a single line text input.

```json
{
  "type": "text",
  "min": 0,
  "max": 100
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `max` | `number` | The maximum value length. |
| `min` | `number` | Minimum value length. |

## textarea

Renders a textarea input.

```json
{
  "type": "textarea",
  "min": 0,
  "max": 100,
  "rows": 4
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `max` | `number` | The maximum value length. |
| `min` | `number` | Minimum value length. |
| `rows` | `number` | The number of rows the textarea should contain. |

## toggle

Renders a true/false toggle.

```json
{
  "type": "toggle"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `boolean` | Default value that should be applied when first adding the block. |
| `fallback` | `boolean` | Fallback value that that will display when field value is empty. |

## repeater

Renders a set of fields that can be repeated.

```json
{
  "type": "repeater",
  "min": 0,
  "max": 100
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `min` | `number` | Minimum amount of rows. |
| `max` | `number` | Maximum amount of rows. |
| `minimized` | `boolean` | Collapse the existing rows when the editor opens. Rows added in the editor start expanded. |
| `textButton` | `string` | Text for the add button. |
| `textMinimized` | `string \| object` | Text that will be displayed when rows are minimized. |
| `textRemove` | `string \| boolean` | Confirmation question shown in the editor when removing a repeater row. A non-empty string changes the question; false removes immediately without a dialog. Omitted, true, or an empty string uses the default question. |
| `attributes` | `array` | Accepts all other field types besides another tabs. |

## richtext

Attribute field for RichText fields.

```json
{
  "type": "richtext"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |

## unit

Renders a number input with a unit dropdown.

```json
{
  "type": "unit"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `disableUnits` | `boolean` | If true, the unit select field is hidden. |
| `isPressEnterToChange` | `boolean` | If true, the ENTER key press is required in order to trigger an onChange. If enabled, a change is also triggered when tabbing away. |
| `isResetValueOnUnitChange` | `boolean` | If true, and the selected unit provides a default value, this value is set when changing units. |
| `isUnitSelectTabbable` | `boolean` | Determines if the unit select field is tabbable. |
| `units` | `array` |  |

## wysiwyg

Renders a WYSIWYG editor.

```json
{
  "type": "wysiwyg"
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `default` | `string` | Default value that should be applied when first adding the block. |
| `fallback` | `string` | Fallback value that that will display when field value is empty. |
| `toolbar` | `object` | The toolbar configuration for the editor. |

<!-- GENERATED_FIELD_TYPES_END -->

## Imported Option Values

Checkboxes and selects with `"multiple": true` normally store an array of
`{ "value": "...", "label": "..." }` objects. They also accept a single option
object, a scalar string or number, or an array of scalar values imported through
the REST API or content tooling. This also works inside repeater rows.

The editor displays these values as selections without rewriting their stored
shape on an untouched save. Adding or removing a selection writes the usual
array of option objects. Numeric `0` and string `"0"` are valid selections.
Templates receive the field's configured `returnFormat`, just as they do for
values selected in the editor. Options still need to match the field's declared
or populated choices.

A native single select without a stored value or default displays a disabled
empty placeholder, not its first option. Choosing the first option explicitly
stores it. Once a choice is stored, the placeholder disappears. Use `allowNull`
when editors should also be able to clear a selection; an empty placeholder does
not make an otherwise required choice clearable.

## Repeater Initial Rows

In the editor, defaults include nested minimum rows before insertion. You do
not need to open the block's settings to initialize them, and inserting the
block does not add a second undo step for those rows.

When a repeater has `min` but no saved value or explicit `default`, the server
initializes that many rows using the row fields' defaults. This also applies
to PHP embeds, block tags, and file-backed page sources that have never been
opened in the editor. For example, `"min": 2` with a text field whose default
is `"New item"` renders two rows containing that value.

Stored rows and an explicit repeater `default` take precedence. An explicitly
saved empty list, `false`, or `null` does not recreate removed rows. Rendering
does not write these generated defaults back to the post. `min` and `max`
constrain editor controls; they do not truncate or pad a saved list on render.
Use [`bs_validate_block()`](/docs/blocks/rendering#validating-stored-values) to
report row-bound violations in imported content.

Row fields without a `default` are `false` in a generated row, so such a row
has no content. Rows that editors add and leave empty look the same. A
template that prints a card, a heading, or a list item for every row should
skip rows without content outside the editor, and keep them in the editor so
their fields stay editable:

```php title="index.php"
<?php foreach ( $a['items'] as $item ) : ?>
  <?php if ( ! $isEditor && ! $item['title'] && ! $item['text'] ) continue; ?>
  <div class="item">
    <h3><?php echo $item['title']; ?></h3>
    <p><?php echo $item['text']; ?></p>
  </div>
<?php endforeach; ?>
```

## Repeater Removal

Removing a row opens the editor's confirmation dialog with **Delete** and
**Cancel** buttons. Cancel or Escape leaves the row unchanged. The row is only
removed after confirmation, including inside nested repeaters.

`textRemove` controls the same behavior for every repeater:

| Value | Behavior |
|-------|----------|
| Omitted, `true`, or `""` | Show the default confirmation question. |
| A non-empty string | Show that custom question, using the existing custom-text translation mechanism. |
| `false` | Remove the row immediately without a dialog. |

For example, `"textRemove": "Remove this team member?"` changes the question.
The existing `min` restriction still prevents removing rows below the minimum.

## WYSIWYG Links

The WYSIWYG link toolbar inserts same-tab links without adding `target` or `rel`
by default. Enable the new-tab option with `toolbar.formats.link.opensInNewTab`:

```json
{
  "id": "content",
  "type": "wysiwyg",
  "toolbar": {
    "formats": {
      "link": {
        "opensInNewTab": true
      }
    }
  }
}
```

Choosing a new tab adds `target="_blank"` and `rel="noreferrer noopener"`.
Turning it off removes those automatic attributes. To apply attributes to every
link in that field, set `toolbar.formats.link.attributes`, for example
`{ "class": "content-link", "rel": "nofollow" }`. Explicit configuration is
preserved, and new-tab safety tokens are added to the configured `rel`.

Typing a URL does not automatically turn it into a link, and clicking an existing
link in the field does not navigate away from the editor. Previously saved link
attributes remain in existing content; they are not migrated automatically.

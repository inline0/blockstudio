---
title: Custom Field Types
description: Register custom editor controls and structured attribute values.
path: "blocks/attributes/custom-field-types"
order: 14
section: "Blocks"
subsection: "Attributes"
meta_title: "Custom Field Types"
meta_description: "Register custom editor controls and structured attribute values."
---

# Custom Field Types

Custom field types let a theme or plugin add new Blockstudio field controls.
They are different from [custom fields](/docs/blocks/attributes/custom-fields):

- `custom/{name}` references a reusable group of existing fields.
- `namespace/type-name` uses a registered field type with its own editor
  control and value shape.

Blockstudio ships the registration system. Your theme or plugin ships the
control.

## When To Use Them

Use a custom field type when a project needs a single purpose editing control
that should behave like a native Blockstudio field everywhere:

- block attributes
- reusable `custom/{name}` field groups
- groups, tabs, and repeaters
- PHP, Twig, and Blade templates
- post meta and option storage
- extension templates such as `{attributes.margin.top}`

For example, a spacing control can store one object instead of four separate
text fields:

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      {
        "id": "margin",
        "type": "acme/dimensions",
        "label": "Margin",
        "sides": ["top", "right", "bottom", "left"],
        "default": {
          "top": "sm",
          "right": "md",
          "bottom": "sm",
          "left": "md"
        }
      }
    ]
  }
}
```

## Naming

Custom field type names must be lowercase, namespaced, and dashcase:

```txt
namespace/type-name
```

Valid examples:

- `acme/dimensions`
- `acme/text-options`
- `theme/spacing-token`

Reserved namespaces:

- `custom/` is reserved for reusable custom field groups.
- `blockstudio/` is reserved for Blockstudio core.

Invalid names are ignored by the filter API and rejected by the helper API.

## PHP Registration

Register the field type metadata in PHP:

```php title="functions.php"
add_action('init', function () {
    wp_register_script(
        'acme-field-dimensions',
        get_stylesheet_directory_uri() . '/fields/dimensions.js',
        ['blockstudio-blocks', 'wp-components', 'wp-element'],
        '1.0.0',
        true
    );

    bs_register_field_type('acme/dimensions', [
        'attribute'     => 'object',
        'default'       => [],
        'editor_script' => 'acme-field-dimensions',
        'storage'       => [
            'type'        => 'object',
            'rest_schema' => [
                'type'                 => 'object',
                'additionalProperties' => ['type' => 'string'],
            ],
        ],
    ]);
});
```

`bs_register_field_type()` returns `true` when registration succeeds and `false`
when the name or definition is invalid. You can unregister a manual
registration with `bs_unregister_field_type()`.

You can also use the filter API:

```php title="functions.php"
add_filter('blockstudio/field_types', function (array $types): array {
    $types['acme/dimensions'] = [
        'attribute'     => 'object',
        'default'       => [],
        'editor_script' => 'acme-field-dimensions',
    ];

    return $types;
});
```

## Definition Keys

| Key                  | Type                      | Description                                                                                                 |
| -------------------- | ------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `attribute`          | `string`, `array`, `null` | WordPress attribute type. Use `string`, `number`, `boolean`, `object`, `array`, or an array of those types. |
| `default`            | mixed                     | Registry-level default when a field does not define its own default.                                        |
| `source`             | string                    | WordPress attribute source for custom serialization cases.                                                  |
| `produces_attribute` | boolean                   | Set to `false` for display-only controls. Defaults to `true`.                                               |
| `editor_script`      | string or array           | Registered script handle or handles to enqueue in the block editor when the type is used.                   |
| `editor_style`       | string or array           | Registered style handle or handles to enqueue in the block editor when the type is used.                    |
| `storage`            | array                     | Storage value type and REST schema hints for post meta and options.                                         |
| `supports`           | array                     | Optional capability flags for your own control.                                                             |

## JavaScript Registration

The editor script registers the matching React control:

```js title="fields/dimensions.js"
const { createElement: el } = wp.element;
const { TextControl } = wp.components;

window.blockstudio.registerFieldType('acme/dimensions', {
  component(props) {
    const value =
      props.value &&
      typeof props.value === 'object' &&
      !Array.isArray(props.value)
        ? props.value
        : {};
    const sides = props.sides || ['top', 'right', 'bottom', 'left'];

    return el(
      'div',
      {},
      sides.map((side) =>
        el(TextControl, {
          key: side,
          label: side,
          value: value[side] || '',
          onChange(nextValue) {
            props.onChange({
              ...value,
              [side]: nextValue,
            });
          },
        }),
      ),
    );
  },
});
```

`registerFieldType()` returns `true` when the registration is accepted. Remove
a client registration with:

```js
window.blockstudio.unregisterFieldType('acme/dimensions');
```

The component receives the field definition properties plus these stable props:

| Prop           | Description                                  |
| -------------- | -------------------------------------------- |
| `type`         | Field type name.                             |
| `id`           | Field ID.                                    |
| `field`        | Full field definition from `block.json`.     |
| `value`        | Current saved value.                         |
| `defaultValue` | Field default value.                         |
| `onChange`     | Store a new value directly.                  |
| `attributes`   | Current block attributes.                    |
| `block`        | Current block type definition.               |
| `clientId`     | Current editor block client ID.              |
| `inRepeater`   | Whether the field is inside a repeater row.  |
| `repeaterId`   | Current repeater path, if any.               |
| `disabled`     | Whether the field is disabled in the editor. |

Custom field type values are stored directly. They do not pass through native
`select`, `radio`, `color`, or `gradient` option normalization.

## Rendering Values

Object values are available like any other Blockstudio attribute.


#### PHP

```php title="index.php"
<div class="<?php echo esc_attr('mt-' . ($a['margin']['top'] ?? '')); ?>">
  <?php echo esc_html($a['title'] ?? ''); ?>
</div>
```

#### Twig

```twig title="index.twig"
<div class="mt-{{ a.margin.top }}">
  {{ a.title }}
</div>
```

#### Blade

```blade title="index.blade.php"
<div class="mt-{{ $a['margin']['top'] ?? '' }}">
  {{ $a['title'] ?? '' }}
</div>
```

Nested object paths also work in extension templates:

```json title="extend.json"
{
  "blockstudio": {
    "extend": "core/group",
    "attributes": [
      {
        "id": "margin",
        "type": "acme/dimensions",
        "set": [
          {
            "attribute": "class",
            "value": "mt-{attributes.margin.top} mb-{attributes.margin.bottom}"
          }
        ]
      }
    ]
  }
}
```

## Storage

When a custom field type is used with `postMeta` or `option` storage,
Blockstudio uses the registered storage value type and REST schema.

```json title="block.json"
{
  "id": "margin",
  "type": "acme/dimensions",
  "label": "Margin",
  "storage": {
    "type": "postMeta",
    "postMetaKey": "acme_margin"
  }
}
```

For `array` and `object` values, define `storage.rest_schema` on the field type
registration. Repeater descendants keep using array-backed storage because a
repeater stores rows.

## Fallback Behavior

If PHP knows the field type but the editor script does not register a matching
control, Blockstudio keeps the saved value and shows a non-editable notice in
the editor. Frontend rendering still receives the saved value.

If a field type is not registered in PHP, Blockstudio leaves it out of the
generated WordPress attributes. Register the PHP definition before block
discovery runs, usually on `init` before Blockstudio builds blocks.

---
title: Filtering Attributes
description: Filter and modify block attributes with PHP hooks.
path: "blocks/attributes/filtering"
order: 14
section: "Blocks"
subsection: "Attributes"
meta_title: "Filtering Attributes"
meta_description: "Filter and modify block attributes with PHP hooks."
---

# Filtering Attributes

Blockstudio provides two methods to filter block attributes.

## Attribute Definitions

`blockstudio/blocks/attributes` filters each field definition while Blockstudio
builds and registers a block. Use it to adjust editor controls, defaults,
conditions, options, and the definition used to validate values during
rendering.

```php title="functions.php"
add_filter('blockstudio/blocks/attributes', function($attribute, $block) {
  if (
    ($block['name'] ?? '') === 'my-theme/code-block' &&
    ($attribute['id'] ?? '') === 'lineNumbers'
  ) {
    $attribute['default'] = true;
    $attribute['conditions'] = [
      [
        [
          'id' => 'language',
          'operator' => '==',
          'value' => 'css'
        ]
      ]
    ];
  }

  return $attribute;
}, 10, 2);
```

The code above sets the default value of `lineNumbers` to `true` and hides the
field unless `language` is set to `css`.

The callback receives one field definition per invocation, including nested
group and repeater fields. It runs during block registration, not once per
frontend render.

Filtered option definitions are also used by the render attribute map. For
example, an option added to a `select`, `radio`, or `checkbox` field is
available in the editor and remains valid when Blockstudio resolves the saved
value for a template:

```php title="functions.php"
add_filter('blockstudio/blocks/attributes', function($attribute, $block) {
  if (
    ($block['name'] ?? '') === 'bsui/button' &&
    ($attribute['id'] ?? '') === 'variant'
  ) {
    $attribute['options'][] = [
      'label' => 'Brand',
      'value' => 'brand',
    ];
  }

  return $attribute;
}, 10, 2);
```

## Rendered Values

`blockstudio/blocks/attributes/render` filters the resolved values immediately
before they are passed to the block template.

```php title="functions.php"
add_filter('blockstudio/blocks/attributes/render', function($attributes, $block) {
  if ($block['name'] === 'my-theme/code-block') {
    // Override attribute value
    $attributes['lineNumbers'] = true;

    // Add computed value
    $attributes['formattedDate'] = date('Y-m-d');
  }
  return $attributes;
}, 10, 2);
```

The example above overrides any value saved in the editor for
`lineNumbers`. Use the definition filter when changing field configuration and
the render filter when computing or overriding template values for a request.

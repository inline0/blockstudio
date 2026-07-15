---
title: Rendering Attributes
description: Access attribute values in your block templates.
path: "blocks/attributes/rendering"
order: 13
section: "Attributes"
meta_title: "Rendering Attributes"
meta_description: "Access attribute values in your block templates."
---

# Rendering Attributes

Inside template files, all attributes can be accessed using the `$attributes` or `$a` variables and the respective ID of the field.


#### PHP

```php title="index.php"
<h1><?php echo $attributes['message']; ?></h1>
<h1><?php echo $a['message']; ?></h1>
```

#### Twig

```twig title="index.twig"
<h1>{{ attributes.message }}</h1>
<h1>{{ a.message }}</h1>
```

## Empty Values

Attribute values return `false` if the field is empty or no option has been selected.


#### PHP

```php title="index.php"
<?php if ($a['message']) : ?>
  <h1><?php echo $a['message']; ?></h1>
<?php endif; ?>
```

#### Twig

```twig title="index.twig"
{% if a.message %}
  <h1>{{ a.message }}</h1>
{% endif %}
```

## Complex Types

### Files

The files field returns an array with file data:


#### PHP

```php title="index.php"
<?php if ($a['image']) : ?>
  <img
    src="<?php echo $a['image']['url']; ?>"
    alt="<?php echo $a['image']['alt']; ?>"
    width="<?php echo $a['image']['width']; ?>"
    height="<?php echo $a['image']['height']; ?>"
  />
<?php endif; ?>
```

#### Twig

```twig title="index.twig"
{% if a.image %}
  <img
    src="{{ a.image.url }}"
    alt="{{ a.image.alt }}"
    width="{{ a.image.width }}"
    height="{{ a.image.height }}"
  />
{% endif %}
```

### Link

The link field returns an object with URL and attributes:


#### PHP

```php title="index.php"
<?php if ($a['link']) : ?>
  <a
    href="<?php echo $a['link']['url']; ?>"
    target="<?php echo $a['link']['target']; ?>"
  >
    <?php echo $a['link']['title']; ?>
  </a>
<?php endif; ?>
```

#### Twig

```twig title="index.twig"
{% if a.link %}
  <a href="{{ a.link.url }}" target="{{ a.link.target }}">
    {{ a.link.title }}
  </a>
{% endif %}
```

### Icon

The icon field returns an object with `set`, `subSet`, and `icon` keys. Use the `bs_render_icon()` and `bs_icon()` helper functions to output the SVG:


#### PHP

```php title="index.php"
<?php if ($a['icon']) : ?>
  <div class="icon">
    <?php bs_render_icon($a['icon']); ?>
  </div>
<?php endif; ?>
```

#### Twig

```twig title="index.twig"
{% if a.icon %}
  <div class="icon">
    {{ bs_icon(a.icon) }}
  </div>
{% endif %}
```

`bs_render_icon()` echoes the SVG directly. `bs_icon()` returns it as a string.

### Attributes

The attributes field type returns an array of `{attribute, value}` pairs for rendering as HTML attributes. Use `bs_render_data_attributes()` to output them:


#### PHP

```php title="index.php"
<div <?php bs_render_data_attributes($a['attributes']); ?>>
  Content
</div>
```

#### Twig

```twig title="index.twig"
<div {{ fn('bs_render_data_attributes', a.attributes) }}>
  Content
</div>
```

`bs_render_data_attributes()` echoes the attributes directly. `bs_data_attributes()` returns the string.

### Group

Group fields prefix their child attribute IDs with the group name. Use `bs_get_group()` to extract group fields into a clean array:


#### PHP

```php title="index.php"
<?php $group = bs_get_group($a, 'settings'); ?>
<h1><?php echo $group['title']; ?></h1>
<p><?php echo $group['description']; ?></p>
```

#### Twig

```twig title="index.twig"
{% set group = fn('bs_get_group', a, 'settings') %}
<h1>{{ group.title }}</h1>
<p>{{ group.description }}</p>
```

This strips the group prefix from attribute keys. For example, `$a['settings_title']` becomes `$group['title']`.

The helper also works when the group comes from a reused custom field:

```json title="fields/section-header/field.json"
{
  "name": "section-header",
  "attributes": [
    {
      "id": "section-header",
      "type": "group",
      "attributes": [
        { "id": "kicker", "type": "text" },
        { "id": "title", "type": "text" }
      ]
    }
  ]
}
```

```json title="block.json"
{
  "blockstudio": {
    "attributes": [
      { "type": "custom/section-header" }
    ]
  }
}
```

```twig title="index.twig"
{% set sectionHeader = fn('bs_get_group', a, 'section-header') %}
<p>{{ sectionHeader.kicker }}</p>
<h2>{{ sectionHeader.title }}</h2>
```

### Repeater

Loop through repeater items:


#### PHP

```php title="index.php"
<?php foreach ($a['items'] as $item) : ?>
  <div class="item">
    <h3><?php echo $item['title']; ?></h3>
    <p><?php echo $item['description']; ?></p>
  </div>
<?php endforeach; ?>
```

#### Twig

```twig title="index.twig"
{% for item in a.items %}
  <div class="item">
    <h3>{{ item.title }}</h3>
    <p>{{ item.description }}</p>
  </div>
{% endfor %}
```


> **[Field Patterns Cookbook](/guides/field-patterns)**

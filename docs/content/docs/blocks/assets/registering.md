---
title: Registering Assets
description: Automatically enqueue CSS and JavaScript files for your blocks.
path: "blocks/assets/registering"
order: 8
section: "Assets"
meta_title: "Registering Assets"
meta_description: "Automatically enqueue CSS and JavaScript files for your blocks."
---

# Registering Assets

Blockstudio will automatically enqueue all files ending with `.css`, `.scss` and `.js` when your block is being used on a page. It is possible to define how assets are being enqueued by using one of the following file names. The `*` is a wildcard that can be replaced with any string of your choice.

Inside block CSS and SCSS files, `%selector%` expands to the block's scoped wrapper class. This lets you target the same element that receives `bs_get_scoped_class()`, even when the file is not a scoped asset.

| Pattern           | Behavior                                                                              |
| ----------------- | ------------------------------------------------------------------------------------- |
| `*.(s)css`        | Enqueues as a `<link>` tag in the editor and on the frontend                          |
| `*.inline.(s)css` | Enqueues contents in an inline `<style>` tag in the editor and on the frontend        |
| `*.editor.(s)css` | Enqueues as a `<link>` tag in the editor only                                         |
| `*.scoped.(s)css` | Enqueues scoped contents in an inline `<style>` tag in the editor and on the frontend |
| `*.js`            | Enqueues as a `<script>` tag in the editor and on the frontend                        |
| `*.inline.js`     | Enqueues contents in an inline `<script>` tag in the editor and on the frontend       |
| `*.editor.js`     | Enqueues as a `<script>` tag in the editor only                                       |
| `*.view.js`       | Enqueues as a `<script>` tag on the frontend only                                     |


> **Note**
>
> The dash notation (`*-inline.css`, `*-editor.css`, etc.) is still supported
> for backward compatibility but is deprecated. Use dot notation for new blocks.


## Inline

Inline styles and scripts have the big advantage that they are directly rendered as style or script tags inside the page. This can enhance loading times, since it saves extra requests that would have to be made otherwise.

- `.js` files are inlined to the end of the body
- `.(s)css` files are inlined to the end of the head
- Each file is only being inlined once

## Scoped

Scoped styles are also inlined, but are prefixed with an ID that is unique to each block. Use the `bs_get_scoped_class` function to add the class to your template.

```php title="index.php"
<section class="hero <?php echo bs_get_scoped_class($b['name']); ?>">
  <h1 class="hero__title">Scope me!</h1>
</section>
```

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

The above will result in the following scoped style:

```html
<style>
  .bs-62df71e6cc9a {
    --hero-gap: var(--wp--preset--spacing--space-10);
  }

  .bs-62df71e6cc9a.hero {
    border-bottom: 1px solid currentColor;
  }

  .bs-62df71e6cc9a .hero__title {
    color: red;
  }
</style>

<section class="hero bs-62df71e6cc9a">
  <h1 class="hero__title">Scope me!</h1>
</section>
```

Regular selectors inside a scoped file still target descendants of the scoped wrapper. Use `%selector%` when you want to style the wrapper itself.

## Global

Besides block specific assets, it is also possible to enqueue global assets, which will be available on all pages, regardless if a block is present. Enqueuing a global asset is done by adding the `global-` prefix to the file name. Any of the suffixes (e.g. `-inline`) can be used in combination.

Possible combinations are:

- `global-styles.(s)css`
- `global-styles.inline.(s)css`
- `global-styles.editor.(s)css`
- `global-styles.scoped.(s)css`
- `global-scripts.js`
- `global-scripts.inline.js`
- `global-scripts.editor.js`
- `global-scripts.view.js`

## Admin

Admin assets are enqueued only in the WordPress admin area. The `admin-` prefix is used to define admin assets.

- `admin-styles.(s)css`
- `admin-scripts.js`

## Block Editor

Block editor assets are enqueued only in the block editor. The `block-editor-` prefix is used to define block editor assets.

- `block-editor-styles.(s)css`
- `block-editor-scripts.js`


> **Note**
>
> If WordPress disables the editor iframe because a client-side block is
> registered with `apiVersion` lower than 3, the editor canvas and WordPress
> admin chrome share the same document. Global or editor scripts that bind to
> `window`, `document`, `body`, wheel events, or document scrolling should guard
> against `body.block-editor-page` or exclude WordPress editor regions such as
> `.interface-complementary-area` and `.interface-navigable-region`.


## Disable Enqueuing

Automatic asset enqueuing can be disabled using the `assets/enqueue` setting. This is useful if you want to handle all asset enqueuing by yourself.

```php title="functions.php"
add_filter('blockstudio/settings', function($settings) {
  $settings['assets']['enqueue'] = false;
  return $settings;
});
```

### Per Block

When a block templates returns nothing, Blockstudio will not enqueue any assets for that particular block. This method comes in handy to disable enqueueing when a certain condition is met.


#### PHP

```php title="index.php"
<?php
  if ( !$a['slides'] ) {
    // or return '';
    return false;
  }
?>

<div>my slider</div>
```

#### Twig

```twig title="index.twig"
{% if not a.slides %}
  <div>my slider</div>
{% endif %}
```

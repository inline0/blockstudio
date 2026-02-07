# General

## Introduction

Blockstudio enables you to create custom WordPress blocks with nothing but PHP using the WordPress [block.json](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) format. It greatly simplifies the developer experience in regard to block registration, lazy loading assets, and custom fields.

## Philosophy

### Core focused

There is no secret sauce. Blockstudio is built on top of core WordPress features and Gutenberg components. It is designed to be a lightweight abstraction layer that helps you get started with custom blocks quickly.

### Server side first

When creating dynamic blocks using nothing but WordPress, there is duplication of logic between PHP and JS code. [There are discussions](https://github.com/WordPress/gutenberg/discussions/38224) about solving this issue, including hydrating blocks on the client side (not good for SEO) or introducing a JSX parser for PHP (complex).

Blockstudio takes a different approach. You use PHP to write your block templates and sprinkle in interactivity like `<RichText />` or `<InnerBlocks />` using JSX-like tags. Inside the editor, those tags will be replaced with their React counterparts. On the frontend, the tags will be replaced with HTML content.

This way, you get the best of both worlds: a server-side rendered block with great interactivity in the editor.

### File system based

While WordPress supports a file-based approach using the [WPDefinedPath](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#wpdefinedpath) string type, it is also possible to link assets or templates from other destinations. Blockstudio doubles down on the file-system as the primary (and only) way of registering blocks. This decision was not made to restrict, but to set a block structure in place that can't be argued with. For example, if a `style.css` or `script.js` file is found in the same folder as a block.json, then you can be sure that it belongs to this exact block only.

### No setup

Blockstudio just works. It is designed to remove friction when composing custom blocks and doesn't come in your way while doing so. Want to use Twig for your template? Simply rename the file extension. Need to include some CSS? Create a `style.css` file. Blockstudio will automatically enqueue it whenever the block is being used on your page, even when it is used outside the post_content with the [rendering function](https://fabrikat.local/blockstudio/documentation/rendering-blocks).

Do you have feature requests or questions? [Message us](mailto:hi@blockstudio.dev).

---

# Activating

Blockstudio can be activated on a site by navigating to the `Blockstudio` menu item in the WordPress admin dashboard.

Alternatively, it is also possible to activate Blockstudio using PHP. This is done by adding the following code constant to your `functions.php` file:

```php
const BLOCKSTUDIO_LICENSE = "YOUR LICENSE KEY";
```

---

# Settings

Blockstudio includes a powerful settings API, that allows setting options via a `blockstudio.json` file inside your theme folder and/or filters. Additionally, allowed users are able to change the settings visually inside the admin area.

## Via

### JSON

If a `blockstudio.json` file is present inside your theme folder, it will be used to set the default options for the current site. A [JSON schema](https://app.blockstudio.dev/schema/blockstudio) is available to validate the file and help with autocompletion when used in an IDE.

The following properties are available.

```json
{
  "$schema": "https://app.blockstudio.dev/schema/blockstudio",
  "users": {
    "ids": [],
    "roles": []
  },
  "assets": {
    "enqueue": true,
    "minify": {
        "css": false,
        "js": false
    },
    "process": {
        "scss": false
    }
  },
  "editor": {
    "formatOnSave": false,
    "assets": [],
    "markup": false
  },
  "library": false
}
```
### Filters

Alternatively you can use the `blockstudio/settings/${setting}` filter to set options via PHP for more flexibility.

```php
add_filter('blockstudio/settings/assets/enqueue', '__return_false');
add_filter('blockstudio/settings/editor/formatOnSave', '__return_true');
add_filter('blockstudio/settings/library', '__return_true');
```
Options set via the `blockstudio/settings/${setting}` filter will override the ones set via the `blockstudio.json` file. Both methods can be used together.

### Admin

Allowed users from the `blockstudio/settings/users/ids` and `blockstudio/settings/users/roles` filters are able to change the settings visually inside the admin area. If the `Save as JSON` checkbox is checked, the settings will be saved to the JSON file, otherwise they will be saved to the database into the `options` table.

Settings set via filters will be grayed out and disabled inside the admin area.

## Settings

### Users

### Assets

### Tailwind

### Editor

### Blockeditor

### Library

### Ai

---

# Registration

Composing your own blocks with Blockstudio is extremely easy. The plugin will look for a `blockstudio` folder within your currently activated theme. Inside it, all subfolders that contain a `block.json` file with a `blockstudio` key will be registered.

## block.json

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "blockstudio": true
}
```
## Template

So far, the custom block is not going to be registered since it is missing a template. To fix that just create an `index.php` in the same folder as your block.json file and its contents will automatically be rendered when your block is used.

```html
<h1>My first native block.</h1>
```
## Conditional logic

Blocks can be registered conditionally using the `conditions` key. It supports all the global variables which are also available for attributes.

[See all conditions](/documentation/attributes/conditional-logic/#global)

The following example will only register the block if the current post type is a page:

```json
{
  "blockstudio": {
    "conditions": [
      [
        {
          "type": "postType",
          "operator": "==",
          "value": "page"
        }
      ]
    ]
  }
}
```
## Custom icon

Custom SVGs icons for blocks can be registered like so:

```json
{
  "blockstudio": {
    "icon": "<svg></svg>"
  }
}
```
WordPress doesn't allow for custom SVG icons inside its own block.json `icon` key, only [Dashicons](https://developer.wordpress.org/resource/dashicons/#editor-video) IDs are allowed here.

### HTML

Blockstudio is using a React element parser to render the icon element, so it is possible to use HTML inside the icon string.

```json
{
  "blockstudio": {
    "icon": "<div>:-)</div>"
  }
}
```
![HTML icon](data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjY3IiBoZWlnaHQ9IjUxMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJibGFjayIgZmlsbC1vcGFjaXR5PSIwIi8+PC9zdmc+) ## Custom paths

By default, Blockstudio will recursively look through the `blockstudio` folder inside your current theme or child theme for blocks. You can change that behaviour in two ways.

### Filter

```php
// Custom path within your theme.
add_filter('blockstudio/path', function () {
    return get_template_directory() . '/blocks';
});

// Custom path within a plugin.
add_filter('blockstudio/path', function () {
    return plugins_url() . '/my-custom-plugin/blocks';
});
```
### Instances

If the above options are not enough, it is possible to initiate the `Blockstudio\Build` class on various folders of your choice:

```php
add_action('init', function () {
  Blockstudio\Build::init([
    'dir' => get_template_directory() . '/client-blocks'
  ]);
});
```
## Filter metadata

Metadata can be filtered before the block is being registered using the `blockstudio/block/meta` filter:

```php
add_filter('blockstudio/blocks/meta', function ($block) {
  if (strpos($block->name, 'marketing') === 0) {
    $block->blockstudio['icon'] = '<svg></svg>';
  }

  return $block;
});
```
The example above is being used internally to give all Blockstudio library elements the same icon.

---

# Templating - Blade

Blade is a templating engine used primarily with the Laravel framework. It enables developers to create expressive, clean templates using a syntax that extends PHP in a simple and intuitive way. Blade templates facilitate common tasks like data display and layout management, helping streamline the development of dynamic web pages.

### Setup

To use Blade templates with Blockstudio, you first need to install the `jenssegers/blade` package using Composer.

```html
composer require jenssegers/blade
```
Next, you need to add a filter to your theme's `functions.php` file to tell Blockstudio how to render Blade templates. Blockstudio automatically collects all Blade templates and maps their paths, allowing you to use Blade's dot syntax for includes and layouts.

```php
add_filter(
    "blockstudio/blocks/render",
    function ($value, $block) {
        $blockPath = $block->path;
        if (str_ends_with($blockPath, ".blade.php")) {
            // Ensure the Blade class exists before trying to use it.
            if (!class_exists(\'Jenssegers\\Blade\\Blade\')) {
                // Optionally, you could log an error or return a message.
                return 'Error: Blade class not found. Please run "composer require jenssegers/blade".';
            }
            $data = $block->blockstudio["data"];
            $bladeData = $data["blade"];
            $blade = new \Jenssegers\Blade\Blade($bladeData["path"], sys_get_temp_dir());

            return $blade->render($bladeData["templates"][$block->name], [
                "a" => $data["attributes"],
                "attributes" => $data["attributes"],
                "b" => $data["block"],
                "block" => $data["block"],
                "c" => $data["context"],
                "context" => $data["context"],
            ]);
        }

        return $value;
    },
    10,
    2
);
```
Your Blade templates will have access to the following variables:

- `a`: An alias for `attributes`.
- `attributes`: The block's attributes.
- `b`: An alias for `block`.
- `block`: Data related to the block itself.
- `c`: An alias for `context`.
- `context`: The block's context (e.g., post ID, post type when in a loop).

To use Blade for your block's template, create an `index.blade.php` file in your block's directory. Blockstudio will then automatically use this file for rendering the block.

---

# Templating - Twig

Twig is a flexible, fast, and secure template engine for PHP. It allows developers to write concise and readable code in templates, enhancing productivity and maintainability. Twig supports a variety of features designed to make templating both powerful and straightforward, making it ideal for projects that require robust, reusable templates.

### Setup

To use Twig templates with Blockstudio, you need to install the `timber/timber` package (which includes Twig) using Composer. Blockstudio will automatically detect if Timber is installed and enable Twig templating for your blocks.

```html
composer require timber/timber
```
Once Timber is installed, Blockstudio will automatically handle the rendering of `index.twig` files found within your block folders. All Twig files will have access to the Timber context, which includes common WordPress data and functions, as well as Blockstudio-specific variables:

- `a`: An alias for `attributes`.
- `attributes`: The block's attributes.
- `b`: An alias for `block`.
- `block`: Data related to the block itself (includes `postId`, `postType`, `context`, etc.).
- `c`: An alias for `context`.
- `context`: The block's context (e.g., `postId`, `postType` when in a loop).
- `isEditor`: Boolean, true if the block is rendering in the editor.
- `isPreview`: Boolean, true if the block is rendering in the inserter preview.

To use Twig for your block's template, simply create an `index.twig` file in your block's directory. Blockstudio will then automatically use this file for rendering the block.

---

# Schema

To improve development with autocomplete and validation in your IDE, Blockstudio introduces its own schema:

[View Blockstudio schema](https://app.blockstudio.dev/schema)

The Blockstudio schema is an (always up-to-date) copy of the WordPress core block.json schema with an additional `blockstudio` property that houses all plugin features like field schemas without interfering with potential future core properties

### Add to JSON

Simply add a `$schema` key with a value of **[https://app.blockstudio.dev/schema](https://app.blockstudio.dev/schema)** to your block.json and your IDE should start to give you hints about Blockstudio specific properties.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "blockstudio": true
}
```
![Blockstudio schema](data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjc5IiBoZWlnaHQ9IjI1MiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJibGFjayIgZmlsbC1vcGFjaXR5PSIwIi8+PC9zdmc+)

---

# Assets - Registering

Blockstudio will automatically enqueue all files ending with `.css`, `.scss` and `.js` when your block is being used on a page. It is possible to define how assets are being enqueued by using one of the following file names. The `*` is a wildcard that can be replaced with any string of your choice.

- ***.(s)css:** enqueues as a <link> tag in the editor and on the frontend
- [*-inline.(s)css:](#inline) enqueues the contents of the file in an inline <style> tag in the editor and on the frontend
- ***-editor.(s)css:** enqueues as a <link> tag in the editor only
- [*-scoped.(s)css:](#scoped) enqueues scoped contents of the file in an inline <style> tag in the editor and on the frontend
- ***.js:** enqueues as a <script> tag in the editor and on the frontend
- [*-inline.js:](#inline) enqueues the contents of the file in an inline <script> tag in the editor and on the frontend
- ***-editor.js:** enqueues as a <script> tag in the editor only
- ***-view.js:** enqueues as a <script> tag on the frontend only

## Inline

Inline styles and scripts have the big advantage that they are directly rendered as style or script tags inside the page. This can enhance loading times, since it saves extra requests that would have to be made otherwise.

- .js files are inlined to the end of the body
- .(s)css files are inlined to the end of the head
- each file is only being inlined once

## Scoped

Scoped styles are also inlined, but are prefixed with an ID that is unique to each block. Use the `bs_get_scoped_class` function to add the class to your template.

```php
<div class="<?php echo bs_get_scoped_class($b["name"]) ?>">
  <h1>Scope me!</h1>
  <p>Scope me too!</p>
</div>
```
```css
h1 {
  color: red;
}
```
The above will result in the following scoped style:

```html
<style>
  .bs-62df71e6cc9a h1 {
      color: red;
  }
  .bs-62df71e6cc9a p {
      color: blue;
  }
</style>

<div class="bs-62df71e6cc9a">
  <h1>Scope me!</h1>
  <p>Scope me too!</p>
</div>
```
## Global

Besides block specific assets, it is also possible to enqueue global assets, which will be available on all pages, regardless if a block is present. Enqueuing a global asset is done by adding the `global-` prefix to the file name. Any of the suffixes (e.g. `-inline`) can be used in combination.

Possible combinations are:

- `global-styles.(s)css`
- `global-styles-inline.(s)css`
- `global-styles-editor.(s)css`
- `global-styles-scoped.(s)css`
- `global-scripts.js`
- `global-scripts-inline.js`
- `global-scripts-editor.js`
- `global-scripts-view.js`

## Admin

Admin assets are enqueued only in the WordPress admin area. The `admin-` prefix is used to define admin assets.

- `admin-styles.(s)css`
- `admin-scripts.js`

## Block Editor

Block editor assets are enqueued only in the block editor. The `block-editor-` prefix is used to define block editor assets.

- `block-editor-styles.(s)css`
- `block-editor-scripts.js`

## Disable enqueuing

### Per block

When a block templates returns nothing, Blockstudio will not enqueue any assets for that particular block. This method comes in handy to disable enqueueing when a certain condition is met.

```php
<?php
if ( !$a['slides'] ) {
  // or return '';
  return false;
}
?>

<div>my slider</div>
```
```twig
{% if not a.slides %}
<div>my slider</div>
{% endif %}
```

---

# Assets - Processing

## Minification

Blockstudio is able to automatically minify all CSS and JS files in a block.

Compiled files will be saved to the `\_dist` folder of the block and enqueued when a block is used. When the source file is updated, Blockstudio checks if a compiled file with that timestamp exists, if not, it will be created.

The [minify](https://github.com/matthiasmullie/minify) library is used for this purpose.

## SCSS

Alternatively, you can use the `.scss` extension and styles will automatically get processed even when the above setting is not `true`.

### Import paths

When using SCSS, it is possible to import other SCSS files. Imports are relative to the file they are imported from.

```css
@import "modules.scss";
```
Additionally, custom import paths can be defined using the `blockstudio/assets/process/scss/importPaths` filter, so files can be imported from other directories without specifying any folder structure.

Please note that the `.scss` extension needs to be present for imports to work properly.

```php
add_filter('blockstudio/assets/process/scss/importPaths', function() {
  $paths[] = get_template_directory() . '/src/scss/';
  return $paths;
});
```
Block assets will be recompiled when any of the imported files change.

## ES Modules

All scripts (inline and default) in Blockstudio load with `type="module"` applied to them by default. This means that it is possible to make use of the [import syntax](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Statements/import) for your blocks' scripts without any additional setup.

Let's imagine the following code inside a **script.js**:

```javascript
import { h, render } from "https://esm.sh/preact@10.15.1";
import htm from "https://esm.sh/htm@3.1.1";
import confetti from "https://esm.sh/canvas-confetti@1.6.0";

const html = htm.bind(h);
const styles =
  `display: block;
    font-family: sans-serif;
    color: black;
    background: #f1f1f1;
    padding: 1rem;
    border-radius: 0.25rem;
    margin: 1rem;
    font-family: sans-serif;
`;

function App(props) {
  confetti();
  console.log(confetti);

  return html`<h1 style="${styles}">Hello ${props.name}!</h1>`;
}

render(
  html`<${App} name="Hello from Preact!" />`,
  document.querySelector("#element")
);
```
The example above would create a component using [Preact](https://preactjs.com/), importing all necessary components and functions from an ESM compatible CDN, in this case **esm.sh**. While there is nothing wrong with this approach, it has the disadvantage of needing to request the necessary assets from an external site, risking broken scripts if the CDN is down.

Blockstudio includes a handy way to download ES Modules to the block, eliminating the need for external ESM CDNs during production.

### Setup

To download a module to your block directory, simply swap the CDN url.

```javascript
import { h, render } from "blockstudio/preact@10.15.1";
import htm from "blockstudio/htm@3.1.1";
import confetti from "blockstudio/canvas-confetti@1.6.0";
```
Blockstudio will automatically check changed .js files for imports and download them to the local block directory. The downloaded files will be placed in a `modules` folder in the block directory.

The script file will be rewritten on save to accustom for the locally hosted module, in the example above, the **generated scripts** would have the following import:

```javascript
import { h, render } from "./modules/preact/10.15.1.js";
import htm from "./modules/htm/10.15.1.js";
import confetti from "./modules/canvas-confetti/1.6.0.js";
```
When inside the editor, the CDN url will still be used (Blockstudio is using esm.sh) when previewing and working on the block.

There you go! The world of NPM is at your fingertips without the boring boilerplate of setting up bundlers and other tools. If you are concerned about performance due to not having a single JS bundle, this [article](https://v8.dev/features/modules#performance) is worth a read.

Since the use of ES modules using the above syntax is completely opt-in, there is no need to activate this feature, it is enabled by default.

### Caveats

There are a couple of things to consider when working with ES modules inside the editor.

#### Version

Normally, modules can be used from CDNs without the version number. In this case, the newest version will always be used. Since Blockstudio is not saving any information into the database, a version number is required when using modules.

#### Same modules

Modules are scoped to their blocks. Even if you use the same module with the same version number across multiple blocks, Blockstudio will still download the requested module to the block. This is mainly because blocks should be self-contained units that can easily be shared across other installations or sites.

On top of that, the same module will be requested twice if both blocks are present on a page. This is not so much of an issue if the block is loading a very specific module like a slider library. However, if you are creating multiple blocks that rely on the same framework as above (Preact), loading the same module multiple times can become a performance issue.

This problem can be solved by using the `script-inline.js` instead of the `script.js` file. Blockstudio will rewrite each of the imports to point to the location of the first occurrence of the module if the name and version number are the same.

## CSS Loader

It is also possible to import CSS files using the same syntax as above.

```javascript
import "blockstudio/swiper@11.0.0/swiper.min.css";
```
The CSS file will be downloaded to the `modules` folder and automatically enqueued when the block is used. As long as the version is the same, only a single version of the CSS file will be enqueued, even if it exists in multiple blocks.

---

# Assets - Code Field

Beside static styles and scripts as files, Blockstudio also supports dynamic asset blocks via the [code field](https://fabrikat.local/blockstudio/documentation/attributes/field-types#code). Depending on your use case, these can be scoped to the block.

## Basic usage

At the most basic level, you can manually render the code field content in your template.

```php
<div useBlockProps>
<h1>My block</h1>
</div>
<style><?php echo $attributes['css']; ?></style>
```
```twig
<div useBlockProps>
<h1>My block</h1>
</div>
<style>{{ attributes.css }}</style>
```
## Scoped selector

To avoid conflicts with other blocks, you can use the `%selector%` variable inside the code field alongside [useBlockProps](https://fabrikat.local/blockstudio/documentation/components/useblockprops) in your rendering template.

### Example

Let's imagine that we want to target the `h1` tag from the example above in our code field.

```css
%selector% h1 {
  color: red;
}
```
Now, Blockstudio will do three things:

- create a unique id for that block instance
- replace `%selector%` with the unique id
- add the same selector to the element marked with `useBlockProps`

The final output will be something like this:

```html
<div data-assets="c9abe0d95c2b">
  <h1>My block</h1>
</div>
<style>
  [data-assets="c9abe0d95c2b"] h1 {
    color: red;
  }
</style>
```
## Automatic rendering

Always having to render the style tag manually can be cumbersome. To make this process easier, you can use the `asset` attribute inside the code field.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "type": "code",
        "id": "code",
        "label": "Custom CSS",
        "language": "css",
        "asset": true
      }
    ]
  }
}
```
This will automatically create `style` tags for code blocks marked as `css` and move them to the head of the document. For fields marked with `javascript` as the language, `script` tags will be created instead and placed at the bottom of the body.

## In extensions

When using code fields inside of [extensions](https://fabrikat.local/blockstudio/documentation/extensions), the `asset` attribute is not necessary. Blockstudio will automatically render the code field content as an asset if the language is `css` or `javascript`.

### Example

```json
{
  "$schema": "https://app.blockstudio.dev/schema/extend",
  "name": "core/*",
  "blockstudio": {
    "extend": true,
    "attributes": [
      {
        "id": "customCss",
        "type": "code",
        "label": "Custom css",
        "language": "css"
      }
    ]
  }
}
```
No additional configuration is needed. The above will show a code field for every `core/*` block in the sidebar and automatically render the content as an asset to the page.

---

# Attributes - Registering

Of course, blocks without dynamic data are kinda boring! Attributes allow you to add data to your blocks and are comparable to custom fields that you know from solutions like ACF or Metabox.

Custom attributes are registered using the **blockstudio** property inside the block.json file.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "supports": {
    "align": true
  },
  "blockstudio": {
    "attributes": [
      {
        "id": "message",
        "type": "text",
        "label": "My message"
      }
    ]
  }
}
```
And that's it! You've just registered your first custom attribute:

![Text field](data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjc5IiBoZWlnaHQ9IjIxOCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJibGFjayIgZmlsbC1vcGFjaXR5PSIwIi8+PC9zdmc+)

---

# Attributes - Rendering

Inside template files, all attributes can be accessed using the `$attributes` or `$a` variables and the respective ID of the field.

```php
<h1><?php echo $attributes['message']; ?></h1>
<h1><?php echo $a['message']; ?></h1>
```
```twig
<h1>{{ attributes.message }}</h1>
<h1>{{ a.message }}</h1>
```
Attribute values render `false` if the field is empty or no option has been selected.

```php
<?php if ($a['message']) : ?>
<h1><?php echo $a['message']; ?></h1>
<?php endif; ?>
```
```twig
{% if a.message %}
<h1>{{ a.message }}</h1>
{% endif %}
```

---

# Attributes - Filtering

Blockstudio provides two methods to filter block attributes.

## In Editor

The first method filters the attributes in the editor. This is useful if you want to adjust the default value of an attribute or its conditions.

```php
add_filter('blockstudio/blocks/attributes', function ($attribute, $block) {
  if (isset($attribute['id']) && $attribute['id'] === 'lineNumbers') {
    $attribute['default'] = true;
    $attribute['conditions'] = [
      [
        [
          'id' => 'language',
          'operator' => '==',
          'value' => 'css',
        ]
      ]
    ];
  }

  return $attribute;
}, 10, 2);
```
The code above will set the default value of the `lineNumbers` attribute to `true` and will hide the attribute if the `language` attribute is not set to `css`. Keep in mind that this filter is only evaluated when inserting blocks in the editor.

## On Frontend

The second method filters the attributes on the frontend. This is useful if you want to adjust the attributes before they are passed to the block template.

```php
add_filter('blockstudio/blocks/attributes/render', function ($value, $key, $block) {
  if (
    $key === 'copyButton' &&
    $block['name'] === 'blockstudio-element/code'
  ) {
    $value = true;
  }

  return $value;
}, 10, 3);
```
Keep in mind that the above filter will override any values set in the editor.

---

# Attributes - Disabling

Attributes can be deactivated on a per-block basis by hovering over the left side of the UI in the sidebar and clicking it when the blue border appears. This comes in handy when an attribute should be disabled temporarily, while leaving the filled content intact.

![Hovering over attribute](https://fabrikat.local/blockstudio/wp-content/themes/fabrikat/src/assets/blockstudio/images/docs/fields-activated.png) The UI of the attribute will be slightly translucent if it is deactivated.

![Field deactivated](https://fabrikat.local/blockstudio/wp-content/themes/fabrikat/src/assets/blockstudio/images/docs/fields-deactivated.png) The same principle also works for single elements when using the **files** attribute type:

![Files field activated](https://fabrikat.local/blockstudio/wp-content/themes/fabrikat/src/assets/blockstudio/images/docs/fields-files-activated.png)

---

# Attributes - Conditional Logic

Fields can be shown depending on conditions.

## Operators

There are 8 different operators which can be used:

- **==** - values are equal
- **!=** - values are not equal
- **includes** - value is included in reference value
- **!includes** - value is not included in reference value
- **empty** - value is empty
- **!empty** - value is not empty
- **<** - value is smaller than reference value
- **>** - value is bigger than reference value
- **<=** - value is smaller than reference value or equal
- **>=** - value is bigger than reference value or equal

## Global

By default, Blockstudio comes with 4 global conditions: post type, post ID, user role and user ID.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "supports": {
    "align": true
  },
  "blockstudio": {
    "attributes": [
      {
        "id": "message",
        "type": "text",
        "label": "My message",
        "conditions": [
          [
            {
              "type": "postId",
              "operator": "==",
              "value": "1386"
            },
            {
              "type": "postType",
              "operator": "==",
              "value": "post"
            }
          ]
        ]
      }
    ]
  }
}
```
In the example above, the text attribute will only show in the editor if the **post ID is 1386** and the **post type is post**. Please note that the [camelCase](https://en.wikipedia.org/wiki/Camel_case) convention is being used for the **type keys**. (postType, postId, userRole, userId)

If you want to create **or** conditions instead, simply move the conditions into their own array:

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "message",
        "type": "text",
        "label": "My message",
        "conditions": [
          [
            {
              "type": "postId",
              "operator": "==",
              "value": "1386"
            },
            {
              "type": "postType",
              "operator": "==",
              "value": "post"
            }
          ],
          [
            {
              "type": "postType",
              "operator": "==",
              "value": "jobs"
            }
          ]
        ]
      }
    ]
  }
}
```
In the example above, the text attribute will only show in the editor if the **post ID is 1386** and the **post type is post** or the **post type is jobs**.

### Custom

Custom conditions can be added using the `blockstudio/blocks/conditions` filter:

```php
add_filter('blockstudio/blocks/conditions', function ($conditions) {
  $conditions['purchasedProduct'] = userHasPurchasedProduct();

  return $conditions;
});
```
```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "message",
        "type": "text",
        "label": "My message",
        "conditions": [
          [
            {
              "type": "purchasedProduct",
              "operator": "==",
              "value": "1"
            }
          ]
        ]
      }
    ]
  }
}
```
## Block

It is also possible to set conditions that work between attributes. Instead of setting a **type** key, simply set the **id** of the attribute you want to check against.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "type": "toggle",
        "id": "copyButton",
        "label": "Copy Button"
      },
      {
        "type": "text",
        "id": "copyButtonText",
        "label": "Copy Button Text",
        "default": "Copy",
        "conditions": [
          [
            {
              "id": "copyButton",
              "operator": "==",
              "value": true
            }
          ]
        ]
      }
    ]
  }
}
```
You can combine global conditions with block condition as you please.

### Repeater

By default, conditions for attributes inside repeaters depend on the attributes of the currently repeated element.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "type": "toggle",
        "id": "toggle",
        "label": "Toggle"
      },
      {
        "type": "repeater",
        "id": "repeater",
        "label": "Repeater",
        "attributes": [
          {
            "type": "toggle",
            "id": "toggle",
            "label": "Toggle"
          },
          {
            "type": "text",
            "id": "text",
            "label": "Text",
            "conditions": [
              [
                {
                  "id": "toggle",
                  "operator": "==",
                  "value": true
                }
              ]
            ]
          }
        ]
      }
    ]
  }
}
```
Since attribute IDs are scoped to the current repeater element, the text attribute inside the repeater will only show if the toggle **inside** the repeater is set to true.

If you want to check against the toggle outside the repeater, you can apply `"context": "main"` to the condition, and it will show the text attribute if the toggle **outside** the repeater is set to true.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "type": "toggle",
        "id": "toggle",
        "label": "Toggle"
      },
      {
        "type": "repeater",
        "id": "repeater",
        "label": "Repeater",
        "attributes": [
          {
            "type": "toggle",
            "id": "toggle",
            "label": "Toggle"
          },
          {
            "type": "text",
            "id": "text",
            "label": "Text",
            "conditions": [
              [
                {
                  "id": "toggle",
                  "operator": "==",
                  "value": true,
                  "context": "main"
                }
              ]
            ]
          }
        ]
      }
    ]
  }
}
```

---

# Attributes - Populating Options

Instead of supplying different field types like Posts, Users or Terms, Blockstudio allows for a more modular way of populating data right from the `block.json`. This feature currently works for `select`, `radio`,`checkbox`, `color` and `gradient` field types.

Data can be populated in three different ways:

- [Query](#query) return results from a post, user or term query (only `select`, `radio`, `checkbox`)
- [Fetch](#fetch) return results from an external source (only `select`, `radio`,`checkbox`)
- [Function](#function) return results from a custom function
- [Custom](#custom) return results from a custom dataset

## Query

When choosing the query mode, **Posts**, **Users** or **Terms** can be populated. Getting started is easy, simply add the **populate** attribute to your **block.json** and define the type that should be queried for your block.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "supports": {
    "align": true
  },
  "blockstudio": {
    "attributes": [
      {
        "id": "posts",
        "type": "select",
        "label": "Posts",
        "populate": {
          "type": "query",
          "query": "posts" // or "users" or "terms"
        }
      }
    ]
  }
}
```
Following functions are used internally:

- **posts:** [get_posts](https://developer.wordpress.org/reference/functions/get_posts/)
- **users:** [get_users](https://developer.wordpress.org/reference/functions/get_users/)
- **terms:** [get_terms](https://developer.wordpress.org/reference/functions/get_terms/)

### Arguments

Custom arguments can be passed using the **arguments** attribute.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "posts",
        "type": "select",
        "label": "Posts",
        "populate": {
          "type": "query",
          "query": "posts",
          "arguments": {
            "post_type": "jobs",
            "numberposts": "20"
          }
        }
      }
    ]
  }
}
```
### Return format

By default, Blockstudio will return following data.

- **posts:** value: post object, label: post_title
- **users:** value: user object, label: display_name
- **terms:** value: term object, label: name

The response can be customised with the `returnFormat` attribute.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "posts",
        "type": "select",
        "label": "Posts",
        "populate": {
          "type": "query",
          "query": "posts",
          "arguments": {
            "post_type": "posts",
            "numberposts": "20"
          },
          "returnFormat": {
            "value": "id" // only accepts "id"
            "label": "post_name" // accepts any value found in the respective object
          }
        }
      }
    ]
  }
}
```
### Fetch

The `query` population type allows fetching data from the server by using the `fetch` option.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "posts",
        "type": "select",
        "label": "Posts",
        "populate": {
          "type": "query",
          "query": "posts",
          "fetch": true,
          "arguments": {
            "post_type": "posts",
            "numberposts": "20"
          }
        }
      }
    ]
  }
}
```
Using the settings above, Blockstudio will automatically enable `stylisedUi` for the attribute and initially fetch the first 20 posts from the `get_posts` query. Upon typing in a value in the select field, the search value will be used as the `s` argument in the query, returning posts for the search term.

This will also work for the `users` and `terms` query types.

When fetching users, you might have to adjust the `search_columns` property in the arguments object to get appropriate results for your query. See the [get_users](https://developer.wordpress.org/reference/functions/get_users/) documentation for more information.

## Function

Instead of relying on the built-in query functions, it is possible to use a custom function to populate options. This can be useful when you want to return data not covered by the built in query types.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "postTypes",
        "type": "checkbox",
        "label": "Post types",
        "populate": {
          "type": "function",
          "function": "get_post_types"
        }
      }
    ]
  }
}
```
### Arguments

Similar to the query type, custom arguments can be passed to the function using the `arguments` key. Internally, Blockstudio uses [call_user_func_array](https://www.php.net/manual/en/function.call-user-func-array.php), so all arguments have to be passed as an array.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "postTypes",
        "type": "checkbox",
        "label": "Post types",
        "populate": {
          "type": "function",
          "function": "get_post_types",
          "arguments": [[], "objects"]
        }
      }
    ]
  }
}
```
### Return format

Blockstudio will always look for the keys set in the `returnFormat` object. If not available, it will look for the `value` and `label` key in the returned data. If those are not available either, it'll fallback to the first value in the returned array.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "postTypes",
        "type": "checkbox",
        "label": "Post types",
        "populate": {
          "type": "function",
          "function": "get_post_types",
          "arguments": [[], "objects"],
          "returnFormat": {
            "value": "name",
            "label": "label"
          }
        }
      }
    ]
  }
}
```
## Mixing

It is possible to combine options and the data from queries or functions.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "users",
        "type": "radio",
        "label": "Users",
        "options": {
          {
              "value": "administrators",
              "label": "Administrators"
          },
          {
              "value": "editors",
              "label": "Editors"
          }
        },
        "populate": {
          "type": "query",
          "query": "users",
          "position": "before" // this will add the populate options before the static options (defaults to "after")
        }
      }
    ]
  }
}
```
## Custom data

Instead of relying on one of the built-in ways to populate options with data, it is possible to create custom datasets that can be easily reused within fields.

Adding custom data is done using the `blockstudio/blocks/populate` filter.

```php
add_filter('blockstudio/blocks/attributes/populate', function ($options) {
  $options['customData'] = [
    [
      'value' => 'custom-1',
      'label' => 'Custom 1',
    ],
    [
      'value' => 'custom-2',
      'label' => 'Custom 2',
    ],
    [
      'value' => 'custom-3',
      'label' => 'Custom 3',
    ],
  ];

  return $options;
});
```
Then call the dataset inside the **block.json** file.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "posts",
        "type": "select",
        "label": "Posts",
        "populate": {
          "type": "custom",
          "custom": "customData"
        }
      }
    ]
  }
}
```
## External data

It is possible to fetch data from an external source using the `fetch` type. The search will be appended to the `searchUrl` argument.

```json
{
  "name": "blockstudio/fetch",
  "title": "Fetch",
  "description": "Fetch field",
  "blockstudio": {
    "attributes": [
      {
        "id": "select",
        "type": "select",
        "label": "Select",
        "multiple": true,
        "populate": {
          "type": "fetch",
          "arguments": {
            "urlSearch": "https://fabrikat.io/streamline/wp-json/wp/v2/posts?search="
          },
          "returnFormat": {
            "value": "id",
            "label": "title.rendered"
          }
        }
      }
    ]
  }
}
```

---

# Attributes - Block Attributes

The `$attributes` or `$a` variables only give you access to data registered in the **blockstudio** property. To access information about standard block data like alignment or typography, simply use the `$block` or `$b` variables.

## Rendering

```php
<h1>The block is aligned: <?php echo $block['align']; ?></h1>
<h1>The block is aligned: <?php echo $b['align']; ?></h1>
```
```twig
<h1>The block is aligned: {{ block.align }}</h1>
<h1>The block is aligned: {{ b.align }}</h1>
```
## Defaults

If you want to set a default value for a property that is set by WordPress like `align`, you can do so by adding a `default` key to the property definition inside the `attributes` key.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/block",
  "title": "Native Block",
  "icon": "star-filled",
  "description": "Native Block.",
  "supports": {
    "align": ["left", "center", "right"]
  },
  "attributes": {
    "align": {
      "type": "string",
      "default": "center"
    }
  },
  "blockstudio": true
}
```

---

# Attributes - HTML Utilities

Sometimes it might be necessary to use the value of attributes inside your CSS or JS files. Blockstudio provides two helper functions to render the value of attributes as data attributes or CSS variables.

Let's imagine a block with the following attribute structure:

```json
"blockstudio": {
  "attributes": [
    {
      "id": "message",
      "type": "text",
      "label": "My message"
    },
    {
      "id": "color",
      "type": "color",
      "label": "My color"
    }
  ]
}
```
## Data attributes

```php
<h1 <?php bs_render_attributes($a); ?>>
<?php echo $a['message']; ?>
</h1>
```
```twig
<h1 {{ fn('bs_render_attributes', a) }}>
{{ a.message }}
</h1>
```
The `bs_render_attributes` function will render the attributes as data attributes on the element.

```html
<h1 data-message="Hello" data-color="#7C3AED">
  Hello
</h1>
```
The `bs_render_attributes` function accepts a second parameter to specify the attributes to render. If no attributes are specified, all attributes will be rendered.

```php
<h1 <?php bs_render_attributes($a, ['color']); ?>>
<?php echo $a['message']; ?>
</h1>
```
```twig
<h1 {{ fn('bs_render_attributes', a, ['color']) }}>
{{ a.message }}
</h1>
```
The above example will render the following HTML:

```html
<h1 data-color="#7C3AED">
  Hello
</h1>
```
If you don't want to render the output, simply use the `bs_attributes` function.

## CSS variables

Similarly, the `bs_render_variables` function will render the attributes as CSS variables on the element. This is useful if you want to use the attributes in a CSS file.

```php
<h1 style="<?php bs_render_variables($a); ?>">
<?php echo $a['message']; ?>
</h1>
```
```twig
<h1 style="{{ fn('bs_render_variables', a) }}">
{{ a.message }}
</h1>
```
```html
<h1 style="--message: Hello; --color: #7C3AED;">
  Hello
</h1>
```
Similarly, the `bs_render_variables` function accepts a second parameter to specify the attributes to render. If no attributes are specified, all attributes will be rendered.

```php
<h1 style="<?php bs_render_variables($a, ['color']); ?>">
<?php echo $a['message']; ?>
</h1>
```
```twig
<h1 style="{{ fn('bs_render_variables', a, ['color']) }}">
{{ a.message }}
</h1>
```
The above example will render the following HTML:

```html
<h1 style="--color: #7C3AED;">
  Hello
</h1>
```
If you don't want to render the output, simply use the `bs_variables` function.

## String conversion

The `bs_render_attributes` and `bs_render_variables` functions will convert attribute IDs to kebab case. For example, the IDs `myAttribute` or `my_attribute` will be converted to `my-attribute`.

---

# Variations

The [Block Variation API](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/) allows you to register custom variations for existing blocks.

Let's imagine a block that should have two different variations depending on the value of a simple select field.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/variations",
  "title": "Native Variation",
  "description": "Native Blockstudio Variation block.",
  "icon": "star-filled",
  "variations": [
    {
      "name": "variation-2",
      "title": "Native Variation 2",
      "description": "Native Blockstudio Variation block 2.",
      "attributes": {
        "select": {
          "value": "variation-2"
        }
      }
    }
  ],
  "blockstudio": {
    "attributes": [
      {
        "id": "select",
        "type": "select",
        "label": "Select",
        "options": [
          {
            "value": "variation-1",
            "label": "Variation 1"
          },
          {
            "value": "variation-2",
            "label": "Variation 2"
          }
        ],
        "default": {
          "value": "variation-1"
        }
      }
    ]
  }
}
```
The above will create three blocks in the inserter, the main one, and two variations.

## Hiding attributes

Attributes will automatically render the appropriate input in the sidebar. Since variation blocks depend on specific attribute values, you might want to hide those fields from the sidebar. To do so, you can use the `hidden` option.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "select",
        "type": "select",
        "label": "Select",
        "hidden": true,
        "options": [
          {
            "value": "variation-1",
            "label": "Variation 1"
          },
          {
            "value": "variation-2",
            "label": "Variation 2"
          }
        ]
      }
    ]
  }
}
```

---

# Transforms

The [Block Transforms API](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-transforms/) allows you to define ways in how to transform blocks. Blockstudio currently supports three different types:

- [block to block:](#block-to-block) transform a block into another block
- [enter:](#enter) insert a block when the user enters text and presses `Enter`
- [prefix:](#prefix) insert a block when the user enters text and presses `Space`

## Block to Block

Block to block transforms allow you to transform a block into another block.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/transforms",
  "title": "Native Transforms",
  "icon": "star-filled",
  "description": "Native Blockstudio Transforms.",
  "blockstudio": {
    "transforms": {
      "from": [
        {
          "type": "block",
          "blocks": [
            "blockstudio/transforms-2",
            "blockstudio/transforms-3"
          ]
        }
      ]
    },
    "attributes": [
      {
        "id": "text",
        "type": "text",
        "label": "Text"
      }
    ]
  }
}
```
When transforming into the new block, the attributes of the old block will be passed to the new block. In the example above, the `text` attribute will be passed to the new block. The same goes for all `InnerBlocks` content.

It is important to note that the attribute types have to be the same for the block being transformed into and the block being transformed from. For example, if the block being transformed into has an attribute with the ID of `text` but the type of `number`, it won't appear in the transform list, as this would cause a type mismatch and rendering error.

The number of attributes between the two blocks doesn't have to be the same.

## Enter

Enter transforms allow you to insert a block when the user types certain content and then presses the `Enter` key.

```json
{
  "blockstudio": {
    "transforms": {
      "from": [
        {
          "type": "enter",
          "regExp": "/^-{3,}$/"
        }
      ]
    }
  }
}
```
In the example above, the block will be inserted when the user types three or more dashes (`-`) and then presses `Enter`.

## Prefix

Prefix transforms allow you to insert a block when the user types certain content and then presses the `Space` key.

```json
{
  "blockstudio": {
    "transforms": {
      "from": [
        {
          "type": "prefix",
          "regExp": "???"
        }
      ]
    }
  }
}
```
In the example above, the block will be inserted when the user types three question marks (`?`) and then presses `Space`.

---

# Initialization

Block templates will only be executed when the block is rendered. This is enough for most blocks; however, sometimes you need to execute code during an earlier stage of execution. For example, you may want to register a new post type or do some other type of setup unrelated to the block.

To do this, you can add a PHP file that starts with `init-`, like `init.php` or `init-post-types.php` to your block directory. This file is executed during the `init` action. For more information on this specific stage, see the [WordPress documentation](https://developer.wordpress.org/reference/hooks/init/).

Any `init.php` file that is found within the block directory will be executed, regardless if it is part of a block context or not. This makes it perfect for organizing code snippets that are not related to any certain blocks.

---

# Rendering

With Gutenberg becoming the prominent instrument in creating easily editable websites for clients, it makes sense to create all necessary website areas as blocks. While this approach will cater to most, advanced users and specific use cases might need to use those existing blocks outside the editor.

Blockstudio provides two specific functions for exactly this use case:

- `bs_render_block`: immediately renders the block to the page (no "echo "needed)
- `bs_block`: needs an "echo" to be rendered to the page. This function is useful when [nesting](#nesting) blocks.

All Blockstudio specific features like inline styles, scripts, and scoped styles are supported when using the render functions.

## Without data

In its simplest form, the function accepts a single value which is the ID of the block that should be rendered on the page.

```php
bs_render_block('blockstudio/cta');
```
## With data

To render the block with custom data, an array needs to be used in place of a single value for the first parameter. The value in the data key will be passed to the `$attributes` and `$a` variable inside your block template.

```php
bs_render_block([
  'id' => 'blockstudio/cta',
  'data' => [
    'title' => 'My title',
    'subtitle' => 'My subtitle',
  ],
]);
```
## Nesting

Blocks can be nested within each other using the `bs_block` function in combination with the powerful `$content` variable inside your block templates.

```php
<div>
  <h1><?php echo $a['title']; ?></h1>
  <p><?php echo $a['subtitle']; ?></p>
  <?php echo $content; ?>
</div>
```
```php
echo bs_block([
  'id' => 'blockstudio/cta',
  'data' => [
    'title' => 'My title',
    'subtitle' => 'My subtitle',
  ],
  'content' => bs_block([
    'id' => 'blockstudio/cta',
    'data' => [
      'text' => 'Button Text',
    ]
  ])
]);
```
The button block will be rendered in place of the `$content` variable inside the block template.

### Multiple slots

It is also possible to create multiple content slots by simply making the `$content` variable an associative array and calling it approriate keys in the `bs_block` function.

```php
<div>
  <?php echo $content['beforeContent']; ?>
  <h1><?php echo $a['title']; ?></h1>
  <p><?php echo $a['subtitle']; ?></p>
  <?php echo $content['afterContent']; ?>
</div>
```
```php
echo bs_block([
  'id' => 'blockstudio/cta',
  'data' => [
    'title' => 'My title',
    'subtitle' => 'My subtitle',
  ],
  'content' => [
    'beforeContent' => bs_block([
      'id' => 'blockstudio/badge',
      'data' => ['text' => 'Before Content']
    ]),
    'afterContent' => bs_block([
      'id' => 'blockstudio/cta',
      'data' => ['text' => 'Button Text']
    ])
  ]
]);
```

---

# Components - useBlockProps

By default, Gutenberg has to create a wrapper around blocks to make them interactive inside the editor. This can become problematic for some block types like containers or columns, since the markup between editor and frontend will be different. The [useBlockProps](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/) hook makes it possible to mark an element inside the block template as the root element, thus creating markup parity between editor and frontend.

## Usage

Simply add the `useBlockProps` attribute to the root element of your block.

```html
<div useBlockProps class="my-class">
  <!--
  This element will be rendered
  as the root element of your block
  in Gutenberg.
  -->
</div>
```
Blockstudio will automatically combine classes and attributes from the editor (alignment classes etc.) along with whatever is defined in the block templates. [get_block_wrapper_attributes](https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/) is being used under the hood for that.

```html
<div class="my-class wp-block-your-black-name alignright">
</div>
```
## Considerations

### Root element

Since there can only be one root element in a block template, when using `useBlockProps`, the block template has to have a single root element. For example, the following will not work and Gutenberg will create a wrapper around the template.

```html
<div useBlockProps>
  First root element.
</div>
<span>
  Second root element.
</span>
```
Similarly, useBlockProps can only be used once per block template.

### @ sign

Blockstudio converts the block template to valid React code inside Gutenberg using [html-react-parser](https://www.npmjs.com/package/html-react-parser). This comes with the limitation that the `@` character can't be used inside an HTML attribute name.

Libraries like [Alpine.js](https://alpinejs.dev/start-here) use the `@` characters to define directives on HTML elements.

```html
<button useBlockProps @click="console.log('hi')">
  Click me
</button>
```
Inside Gutenberg, this `@click` attribute will be stripped from the element. (it will still work on the frontend, of course)

To combat this issue, you can use the alternative syntax Alpine.js provides.

```html
<button useBlockProps x-on:click="console.log('hi')">
  Click me
</button>
```
The above will work inside Gutenberg and on the frontend.

---

# Components - InnerBlocks

Inner blocks allow you to insert additional blocks to your blocks. Under the hood, Blockstudio is using the [InnerBlocks](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/nested-blocks-inner-blocks/) component. Please note that it is only possible to use one InnerBlocks component per block, this is a WordPress limitation.

## Basic usage

To use InnerBlocks, you need to add the `InnerBlocks` component to your block.

```html
<InnerBlocks /> or
<InnerBlocks/>
```
The composition is up to you. You can nest the `InnerBlocks` component as deep as you want or just use it by itself.

## Properties

### allowedBlocks

The `allowedBlocks` prop allows you to define which blocks can be inserted into the `InnerBlocks` component.

```php
<InnerBlocks allowedBlocks="<?php echo esc_attr(wp_json_encode(['core/heading', 'core/paragraph'])); ?>" />
```
```twig
<InnerBlocks allowedBlocks="{{ ['core/heading', 'core/paragraph']|json_encode|escape('html_attr') }}" />
```
### tag

By default, the `InnerBlocks` component is rendered as a `div` element. You can change this by using the `tag` prop.

```html
<InnerBlocks tag="section" />
```
### template

The `template` prop allows you to define a template for the `InnerBlocks` component.

```php
<InnerBlocks template="<?php echo esc_attr( wp_json_encode([['core/heading', ['placeholder' => 'Book Title']], ['core/paragraph', ['placeholder' => 'Summary']]])); ?>" />
```
```twig
<InnerBlocks template="{{ [['core/heading', { placeholder: 'Book Title' }], ['core/paragraph', { placeholder: 'Summary' }]]|json_encode|escape('html_attr') }}" />
```
### templateLock

The `templateLock` prop allows you to define if the template can be modified or not.

- contentOnly: prevents all operations. Additionally, the block types that don't have content are hidden from the list view and can't gain focus within the block list. Unlike the other lock types, this is not overrideable by children.
- all: prevents all operations. It is not possible to insert new blocks. Move existing blocks or delete them.
- insert: prevents inserting or removing blocks, but allows moving existing ones.
- false: prevents locking from being applied to an InnerBlocks area even if a parent block contains locking.

### renderAppender

The `renderAppender` prop allows you to define the block appender type.

- default: display the default block appender, typically the paragraph style appender when the paragraph block is allowed.
- button: display a + icon button as the appender.

### useBlockProps

`useBlockProps` will remove the outer wrapper of the `InnerBlocks` component inside the editor. See [useBlockProps](/documentation/components/useblockprops) for more information.

## Context

### Registering

Blockstudio supports context for the `InnerBlocks` component. This allows you to pass data from the parent block to the child blocks. While it uses the WordPress core [context mechanism](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-context/) under the hood, Blockstudio provides a more convenient API.

Instead of having to define all attributes that should be passed in the context separately, you can simply subscribe to all attributes of a parent block.

Let's say you have a container block setup like so:

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/container",
  "title": "Container",
  "category": "design",
  "icon": "star-filled",
  "description": "Container block.",
  "blockstudio": {
    "attributes": [
      {
        "id": "full-width",
        "type": "toggle",
        "label": "Makes section full width"
      }
    ]
  }
}
```
Inside your child block, simply use the `usesContext` property with the parent block name to gain access to all parent attributes in your block template.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/element",
  "title": "Element",
  "category": "design",
  "icon": "star-filled",
  "description": "Element block.",
  "usesContext": ["blockstudio/container"],
  "parent": ["blockstudio/container"],
  "blockstudio": true
}
```
### Templates

The `$context` and `$c` variables are available inside your block templates to access all parent attributes. Child blocks will automatically reload in the editor if parent data changes.

```php
<?php $container = $context['blockstudio/container']; ?>

<h1>
<?php echo $container['full-width']
    ? 'is full width'
    : 'no full width'; ?>
</h1>
```
```twig
{% set container = context['blockstudio/container'] %}

<h1>
{{ container['full-width']
    ? 'is full width'
    : 'no full width' }}
</h1>
```
## Frontend wrapper

`InnerBlocks` has to render a wrapper element around its children inside the editor, however, it can be removed from the frontend by using `blockstudio/blocks/components/innerblocks/frontend/wrap` filter.

```php
add_filter('blockstudio/blocks/components/innerblocks/frontend/wrap', function ($render, $block) {
  if ($block->name === 'blockstudio/my-block') {
    $render = false;
  }

  return $render;
}, 10, 2);
```
## Return early

If you want to return early from the `render` method of your block, you can check if the `$innerBlocks` (it contains the `<InnerBlocks/>` content) variable is empty. This comes in handy if you don't want to render anything if there are no inner blocks.

```php
<?php if (strip_tags($innerBlocks) === '') {
  return;
} ?>
<InnerBlocks tag="section" />
```
Since `<InnerBlocks/>` is will always render an empty paragraph tag, you can use the `strip_tags` function to check if the content is empty.

## Dynamic templates

The [Select](https://fabrikat.local/blockstudio/documentation/attributes/field-types/#select) and [Radio](https://fabrikat.local/blockstudio/documentation/attributes/field-types/#radio) field types support dynamically generating the `InnerBlocks` template depending on the field value. This is useful if you want to allow the user to select a different template for the `InnerBlocks` component.

```html
<InnerBlocks tag="section" />
```
To do so, simply add a `innerBlocks` key to the options array with the template.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/type-select-innerblocks",
  "title": "Native Select InnerBlocks",
  "category": "blockstudio-test-native",
  "icon": "star-filled",
  "description": "Native Blockstudio Select InnerBlocks block.",
  "blockstudio": {
    "attributes": [
      {
        "id": "layout",
        "type": "select",
        "label": "Layout",
        "options": [
          {
            "value": "1",
            "label": "One",
            "innerBlocks": [
              {
                "name": "core/columns",
                "innerBlocks": [
                  {
                    "name": "core/column",
                    "innerBlocks": [
                      {
                        "name": "core/heading",
                        "attributes": {
                          "content": "This is the left Heading.",
                          "level": 1
                        }
                      },
                      {
                        "name": "core/paragraph",
                        "attributes": {
                          "content": "This is the left Paragraph."
                        }
                      }
                    ]
                  },
                  {
                    "name": "core/column",
                    "innerBlocks": [
                      {
                        "name": "core/heading",
                        "attributes": {
                          "content": "This is the right Heading.",
                          "level": 1
                        }
                      },
                      {
                        "name": "core/paragraph",
                        "attributes": {
                          "content": "This is the right Paragraph."
                        }
                      }
                    ]
                  }
                ]
              }
            ]
          },
          {
            "value": "2",
            "label": "Two",
            "innerBlocks": [
              {
                "name": "core/columns",
                "innerBlocks": [
                  {
                    "name": "core/column",
                    "innerBlocks": [
                      {
                        "name": "core/heading",
                        "attributes": {
                          "content": "This is the left Heading.",
                          "level": 2
                        }
                      },
                      {
                        "name": "core/paragraph",
                        "attributes": {
                          "content": "This is the left Paragraph."
                        }
                      }
                    ]
                  },
                  {
                    "name": "core/column",
                    "innerBlocks": [
                      {
                        "name": "core/heading",
                        "attributes": {
                          "content": "This is the right Heading.",
                          "level": 2
                        }
                      },
                      {
                        "name": "core/paragraph",
                        "attributes": {
                          "content": "This is the right Paragraph."
                        }
                      }
                    ]
                  }
                ]
              }
            ]
          }
        ],
        "allowNull": "Select an option"
      }
    ]
  }
}
```

---

# Components - RichText

The `RichText` component allows rendering an editable input inside the editor and a static HTML Element in the frontend. It is based on the [RichText](https://developer.wordpress.org/block-editor/reference-guides/richtext/) component WordPress provides.

## Basic usage

To use `RichText` in its most basic form, you need to add the appropriate attribute to your block.json and add the `RichText` component to your block.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/rich-text",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio RichText block.",
  "blockstudio": {
    "attributes": [
      {
        "id": "myRichText",
        "type": "richtext"
      }
    ]
  }
}
```
```html
<RichText attribute="myRichText" /> or
<RichText attribute="myRichText"/>
```
You can use an unlimited number of RichText components in your block.

## Properties

### allowedFormats

You can limit the formats that can be used in the RichText component by using the `allowedFormats` prop.

```php
<RichText attribute="myRichText" allowedFormats="<?php echo esc_attr(wp_json_encode(['core/bold', 'core/italic'])); ?>" />
```
```twig
<RichText attribute="myRichText" allowedFormats="{{ ['core/bold', 'core/italic']|json_encode|escape('html_attr') }}" />
```
### placeholder

You can set a placeholder for the RichText component by using the `placeholder` prop.

```html
<RichText attribute="myRichText" placeholder="Enter some text" />
```
### preserveWhiteSpace

Whether to preserve white space characters in the value. Normally tab, newline and space characters are collapsed to a single space. If turned on, soft line breaks will be saved as newline characters, not as line break elements.

```html
<RichText attribute="myRichText" preserveWhiteSpace="true" />
```
### tag

By default, the `RichText` content is rendered inside a `p` element. You can change this by using the `tag` prop.

```html
<RichText attribute="myRichText" tag="h1" />
```
### withoutInteractiveFormatting

By default, all formatting controls are present. This setting can be used to remove formatting controls that would make content interactive.

```html
<RichText attribute="myRichText" withoutInteractiveFormatting="true" />
```

---

# Components - MediaPlaceholder

The `MediaPlaceholder` component provides a placeholder to add media items to a block. The same component is being used in the `core/image` block. It is based on the [React component](https://github.com/WordPress/gutenberg/blob/trunk/packages/block-editor/src/components/media-placeholder/README.md) with the same name.

## Basic usage

To use `MediaPlaceholder` in its most basic form, you need to add the appropriate attribute to your block.json and add the `MediaPlaceholder` component to your block.

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/media-placeholder",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio MediaPlaceholder block.",
  "blockstudio": {
    "attributes": [
      {
        "id": "media",
        "type": "files"
      }
    ]
  }
}
```
```php
<MediaPlaceholder attribute="media" /> or
<MediaPlaceholder attribute="media"/>
<?php foreach ($a['media'] as $file) {
  echo wp_get_attachment_image($file['ID']);
} ?>
```
```twig
<MediaPlaceholder attribute="media" /> or
<MediaPlaceholder attribute="media"/>
{% for file in a.media %}
  {{ fn('wp_get_attachment_image', file.ID) }}
{% endfor %}
```
Once a media file has been selected, the `MediaPlaceholder` component will not be rendered anymore.

## Properties

### accept

A string passed to FormFileUpload that tells the browser which file types can be upload to the upload window the browser use e.g.: `image/*` or `video/*`. More information about this string is available in [here](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file#Unique_file_type_specifiers).

### allowedTypes

Array with the types of the media to upload/select from the media library. Each type is a string that can contain the general mime type e.g.: image, audio, text, or the complete mime type e.g.: `audio/mpeg`, `image/gif`. If allowedTypes is unset, all mime types should be allowed.

```php
<MediaPlaceholder allowedTypes="<?php echo esc_attr( wp_json_encode(['image', 'video'])); ?>" />
```
```twig
<MediaPlaceholder allowedTypes="{{ ['image', 'video']|json_encode|escape('html_attr') }}" />
```
### autoOpenMediaUpload

If true, the MediaUpload component auto-opens the picker of the respective platform.

### disableDropZone

If true, the Drop Zone will not be rendered. Users won't be able to drag & drop any media into the component or the block. The UI controls to upload the media via file, url or the media library would be intact.

### dropZoneUIOnly

If true, only the Drop Zone will be rendered. No UI controls to upload the media will be shown. The `disableDropZone` prop still takes precedence over `dropZoneUIOnly` – specifying both as true will result in nothing to be rendered.

### icon

Dashicon to display to the left of the title.

### isAppender

If true, the property changes the look of the placeholder to be adequate to scenarios where new files are added to an already existing set of files, e.g., adding files to a gallery. If false, the default placeholder style is used.

### disableMediaButtons

If true, only the Drop Zone will be rendered. No UI controls to upload the media will be shown

### labels

An object that can contain a title and instructions properties.

```php
<MediaPlaceholder labels="<?php echo esc_attr(wp_json_encode(['title' => 'Custom title', 'instructions' => 'Custom instructions'])); ?>"" />
```
```twig
<MediaPlaceholder labels="{{ {title: 'Custom title', instructions: 'Custom instructions'}|json_encode|escape('html_attr') }}" />
```

---

# Environment

Sometimes it is necessary to show content in your blocks exclusively when it is being rendered inside the Gutenberg editor and not on the frontend. This technique is very useful to provide users with helpful information and placeholders if the necessary data hasn't been input yet.

## Editor

```php
<?php if ($isEditor) : ?>
This content is only going to be rendered inside the editor.
<?php else : ?>
This content is only going to be rendered on the frontend.
<?php endif; ?>
```
```twig
{% if isEditor %}
This content is only going to be rendered inside the editor.
{% else %}
This content is only going to be rendered on the frontend.
{% endif %}
```
## Preview

Blockstudio adds another environment variable that will come in handy for block developers. `$isPreview` allows you to conditionally render content inside the block preview window when hovering over a block inside the block inserter.

```php
<?php if ($isPreview) : ?>
This content is only going to be rendered inside the block preview.
<?php else : ?>
This content is only going to be rendered on the frontend and editor.
<?php endif; ?>
```
```twig
{% if isPreview %}
This content is only going to be rendered inside the block preview.
{% else %}
This content is only going to be rendered on the frontend and editor.
{% endif %}
```
![Block preview](data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTk2IiBoZWlnaHQ9IjQ1MSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJibGFjayIgZmlsbC1vcGFjaXR5PSIwIi8+PC9zdmc+)

---

# Context

When blocks are used inside a loop (for example, a Query block), the block is executed once for each iteration of the loop. Blockstudio provides handy shortcuts for accessing the current loop and outer context.

```php
<div>
<h1>
  This is the data of current
  element inside the loop:
  <?php echo $block['context']['postId'] ?>
  <?php echo $block['context']['postType'] ?>
</h1>
<h1>
  This is the data the current post:
  <?php echo $block['postId'] ?>
  <?php echo $block['postType'] ?>
</h1>
</div>
```
```twig
<div>
<h1>
  This is the data of current
  element inside the loop:
  {{ block.context.postId }}
  {{ block.context.postType }}
</h1>
<h1>
  This is the data the current post:
  {{ block.postId }}
  {{ block.postType }}
</h1>
</div>
```

---

# Preview

Gutenberg allows previewing blocks when hovering over the block in the fixed block inserter. To enable the preview of blocks made with Blockstudio, simply set the `example` key to an empty object.

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "example": {},
  "blockstudio": true
}
```
## Custom data

It is also possible to provide structured data in the `example` key, so any custom attributes are correctly being rendered.

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "example": {
    "attributes": {
      "title": "This title will be shown in the preview"
    }
  },
  "blockstudio": true
}
```
```php
<h1><?php echo $a['title']; ?></h1>
```
```twig
<h1>{{ a.title }}</h1>
```
## InnerBlocks

If your block relies on `InnerBlocks`, it is possible to provide a template for the InnerBlocks.

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "example": {
    "innerBlocks": [
      {
        "name": "core/paragraph",
        "attributes": {
          "content": "This is the default InnerBlocks block."
        }
      }
    ]
  },
  "blockstudio": true
}
```

---

# Post Meta

Since block templates are just normal PHP files, you can use any PHP code you want in them. However, since blocks don't have access to the global `$post` variable inside the editor, [get_the_ID()](https://developer.wordpress.org/reference/functions/get_the_id/) and other functions that depend on the variable will only work on the frontend.

Blockstudio provides four different ways to access the current post ID in the editor and the frontend.

```php
echo $post_id;
echo $postId;
echo $block['postId'];
echo $b['postId'];
```
```twig
{{ post_id }};
{{ postId }};
{{ block.postId }};
{{ b.postId }};
```
## Getting post meta

All common custom field plugins and WordPress itself provide the possibility to access post meta using a post ID.

### ACF

```php
echo get_field('field_name', $post_id);
```
```twig
{{ fn('get_field', 'field_name', postId) }};
```
### Metabox

```php
echo rwmb_get_value('field_id', null, $post_id);
```
```twig
{{ fn('rwmb_get_value', 'field_id', postId) }};
```
## Refreshing blocks

By default, blocks won't be refreshed when you save or update a post. Thus, old data might be displayed in the editor after saving. To fix this, you can use the `refreshOn` key inside your `block.json` to tell Blockstudio to refresh the block when a post is saved.

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "blockstudio": {
    "refreshOn": [
      "save"
    ]
  }
}
```

---

# Overrides

Blockstudio includes the possibility to override blocks on a very granular level. This can be useful for shared block libraries when you want to change the behavior of a block without having to create a new block from scratch.

## block.json

To get started, set the name of the block you want to override as the `name` key and set the `override` key to `true`.

```json
{
  "name": "blockstudio/my-block",
  "title": "My overridden block title",
  "blockstudio": {
    "override": true
  }
}
```
When the `override` property is set to `true`, the data from the new `block.json` will be merged with the data from the original block. This means that you can override any property of the original block.

## Attributes

Attributes can either be overridden by `id` or added additionally. For example, let's imagine that we want to override the `width` attribute of a block and add a new `height` attribute.

```json
{
  "name": "blockstudio/my-block",
  "title": "My overridden block title",
  "blockstudio": {
    "override": true,
      "attributes": [
      {
        "id": "width",
        "type": "number",
        "default": 280,
        "label": "Override width"
      },
      {
        "id": "height",
        "type": "number",
        "default": 280,
        "label": "New height"
      }
    ]
  }
}
```
When an attribute with the same `id` is found, the data will be merged with the original attribute. Attributes which are not found will be added to the block.

**Important**: When overriding attributes, you have to provide the `type` of the original attribute, even if it stays the same.

### Complex structures

When overriding attributes with complex structures (repeaters, groups, tabs, etc.), you have to provide the full structure of the attribute to the point of the item that should be overridden.

```json
{
  "name": "blockstudio/my-block",
  "blockstudio": {
    "override": true,
    "attributes": [
      {
        "id": "tabs",
        "type": "tabs",
        "tabs": [
          {
            "id": "tab1",
            "attributes": [
              {
                "key": "group1",
                "type": "group",
                "title": "Group",
                "attributes": [
                  {
                    "id": "text",
                    "type": "text",
                    "label": "Override text"
                  }
                ]
              }
            ]
          },
          {
            "id": "tab2",
            "title": "Override tab",
            "attributes": [
              {
                "id": "group",
                "type": "group",
                "title": "Group ID",
                "attributes": [
                  {
                    "id": "text",
                    "type": "text",
                    "label": "Override text"
                  }
                ]
              }
            ]
          }
        ]
      }
    ]
  }
}
```
## Assets

Asset files will be replaced when the file name matches. For example, if a `style.css` file exists in the original block, it will be replaced by the `style.css` file in the overriding block. Files with names that do not match the original block will be added to the block.

## Rendering template

Rendering templates can be overridden by providing a new template file, so either `index.php`, `index.twig` or `index.blade.php`.

### Twig blocks

Twig templates can be overridden on a more granular level using the [block feature](https://twig.symfony.com/doc/3.x/functions/block.html) in Twig.

Let's imagine the following rendering template for a fictional image block:

```twig
<h1>Images:</h1>
{% for image in a.images %}
  <div class="content">
    {% block image %}
      <img src="{{ image.url }}" class="image" />
    {% endblock %}
  </div>
{% endfor %}
```
Instead of replacing the whole template, we can use Twigs [extends](https://twig.symfony.com/doc/3.x/tags/extends.html) feature to override certain parts of the template while keeping the rest intact.

```twig
{% extends 'index.twig' %}

{% block image %}
  <figure>
    <img src="{{ image.url }}" class="image" />
    <figcaption>{{ image.caption }}</figcaption>
  </figure>
{% endblock %}
```

---

# Loading

Blockstudio blocks are dynamic, meaning that they must be rendered on the server to be displayed in Gutenberg. This rendering is facilitated by a `fetch` call to the Blockstudio block renderer. Upon first opening the editor, each block fetches its respective template, which results in multiple requests depending on the number of blocks present on the page.

On some hosts, this can cause timeouts and errors while loading other essential assets needed for the editor to function properly.

## Disabling

Rendering template loading can be disabled by setting the `disableLoading` key in `block.json` to `true`. A placeholder will be rendered in place of the block template. All field settings will still be available when opening the sidebar.

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "blockstudio": {
    "blockEditor": {
        "disableLoading": true
      }
    }
  }
}
```

---

# Hooks - PHP

Below you'll find a list of all available PHP hooks that can be used to extend or adjust the functionality of the plugin.

## Path

This filter allows you to adjust the path of the block folder. By default, this is `blockstudio` inside the currently active theme. Alternatively it is possible to [create a new instance](https://fabrikat.local/blockstudio/documentation/registration/#instances) for multiple source directories.

```php
add_action('blockstudio/path', function() {
  $path = get_stylesheet_directory() . '/blocks';
  return $path;
});
```
## Init

### global

This action fires after the plugin has registered all blocks.

```php
add_action('blockstudio/init', function() {
  // do something
});
```
### instance

This action fires after the plugin has registered all blocks of a specific instance.

```php
add_action('blockstudio/init/$instance', function() {
  // do something
});
```
### global/before

This action fires before the plugin has registered all blocks.

```php
add_action('blockstudio/init/before', function() {
  // do something
});
```
### global/before/instance

This action fires before the plugin has registered all blocks of a specific instance.

```php
add_action('blockstudio/init/$instance', function() {
  // do something
});
```
## Blocks

### render

This filter allows you to adjust the output of a block before it is rendered.

```php
add_filter('blockstudio/blocks/render', function ($value, $block, $isEditor, $isPreview) {
  if ($block->name === 'blockstudio/my-block') {
    $value = str_replace('%CONTENT%', 'Replace in frontend', $value);

    if ($isEditor) {
      $value = str_replace('%CONTENT%', 'Only replace in editor', $value);
    }
    if ($isPreview) {
      $value = str_replace('%CONTENT%', 'Only replace in preview', $value);
    }
  }

  return $value;
}, 10, 4);
```
### meta

This filter allows you to adjust the data of the block.json file before it is registered.

```php
add_filter('blockstudio/blocks/meta', function ($block) {
  if (strpos($block->name, 'marketing') === 0) {
    $block->blockstudio['icon'] = '<svg></svg>';
  }

  return $block;
});
```
The above code would change the icon of all blocks starting with **marketing**.

### conditions

This filter allows you to add custom conditions which can be used within blocks.

```php
add_filter('blockstudio/blocks/conditions', function ($conditions) {
  $conditions['purchasedProduct'] = userHasPurchasedProduct();

  return $conditions;
});
```
### attributes

This filter allows you to adjust the attributes of a block before the block is registered. See [Filtering](https://fabrikat.local/blockstudio/documentation/attributes/filtering/) for more information.

```php
add_filter('blockstudio/blocks/attributes', function ($attribute, $block) {
  if (isset($attribute['id']) && $attribute['id'] === 'lineNumbers') {
    $attribute['default'] = true;
    $attribute['conditions'] = [
      [
        [
          'id' => 'language',
          'operator' => '==',
          'value' => 'css',
        ]
      ]
    ];
  }

  return $attribute;
}, 10, 2);
```
### attributes/render

This filter allows you to adjust the attributes of a block before it is rendered. See [Filtering](https://fabrikat.local/blockstudio/documentation/attributes/filtering/) for more information.

```php
add_filter('blockstudio/blocks/attributes/render', function ($value, $key, $block) {
  if (
    $key === 'copyButton' &&
    $block['name'] === 'blockstudio-element/code'
  ) {
    $value = true;
  }

  return $value;
}, 10, 3);
```
### attributes/populate

This filter allows you to add custom data to the options of a `checkbox`, `select` or `radio` field. See [Populating options](https://fabrikat.local/blockstudio/documentation/attributes/populating-options/) for more information.

```php
add_filter('blockstudio/blocks/attributes/populate', function ($options) {
  $options['customData'] = [
    [
      'value' => 'custom-1',
      'label' => 'Custom 1',
    ],
    [
      'value' => 'custom-2',
      'label' => 'Custom 2',
    ],
    [
      'value' => 'custom-3',
      'label' => 'Custom 3',
    ],
  ];

  return $options;
});
```
### components/useblockprops/render

This filter allows you to adjust the output of the `useBlockProps` content.

```php
add_filter('blockstudio/blocks/components/useblockprops/render', function ($attributes, $block) {
  if ($block->name === 'blockstudio/title') {
    $attributes['class'] = $attributes['class'] . ' new-class';
  }

  return $attributes;
}, 10, 2);
```
### components/innerblocks/render

This filter allows you to adjust the output of the `<InnerBlocks />` content.

```php
add_filter('blockstudio/blocks/components/innerblocks/render', function ($value, $block) {
  if ($block->name === 'blockstudio/columns') {
    $value = str_replace('%CONTENT%', 'replace', $value);
  }

  return $value;
}, 10, 2);
```
### components/innerblocks/frontend/wrap

This filter allows you to remove the `<InnerBlocks />` wrapper from the frontend.

```php
add_filter('blockstudio/blocks/components/innerblocks/frontend/wrap', function ($render, $block) {
  if ($block->name === 'blockstudio/my-block') {
    $render = false;
  }

  return $render;
}, 10, 2);
```
### components/richtext/render

This filter allows you to adjust the output of the `<RichText />` content.

```php
add_filter('blockstudio/blocks/components/richtext/render', function ($value, $block) {
  if ($block->name === 'blockstudio/title') {
    $value = str_replace('%CONTENT%', 'replace', $value);
  }

  return $value;
}, 10, 2);
```
## Settings

### path

This filter allows you to adjust the path of the `blockstudio.json` file. By default, this is `blockstudio.json` inside the currently active theme.

```php
add_filter('blockstudio/settings/path', function() {
  $path = get_stylesheet_directory() . '/settings';
  return $path;
});
```
## Assets

### enable

This filter allows you to disable the asset processing and enqueueing of a specific asset type.

```php
add_filter("blockstudio/assets/enable", function ($value, $data) {
  if ($data['type'] === 'css') {
   return false;
  }

  return true;
}, 10, 2);
```
### process/scss/importPaths

This filter allows you to add additional paths to the `@import` statement of the SCSS compiler.

```php
add_filter('blockstudio/assets/process/scss/importPaths', function() {
  $paths[] = get_template_directory() . '/src/scss/';
  return $paths;
});
```
### process/css/content

This filter allows you to adjust the content of the CSS file before it is being compiled.

```php
add_filter('blockstudio/assets/process/css/content', function($content) {
  $content = str_replace('background-color: red;', 'background-color: blue;', $content);
  return $content;
});
```
### process/js/content

This filter allows you to adjust the content of the JS file before it is being compiled.

```php
add_filter('blockstudio/assets/process/js/content', function($content) {
  $content = str_replace('console.log("hi");', 'console.log("hello");', $content);
  return $content;
});
```
## Render

### global

This filter allows you to adjust the output of the page before it is being rendered.

```php
add_action('blockstudio/render', function($output, $blocks) {
  if ($blocks['ui/heading']) {
    $output = str_replace('<h1', '<h1 class="text-4xl font-semibold mb-4"', $output);
  }

  return $output;
}, 10, 2);
```
### head

This filter allows you to adjust the output of the `<head>` tag before it is being rendered.

```php
add_action('blockstudio/render/head', function($output, $blocks) {
  if ($blocks['ui/heading']) {
    $output .= '<style>h1 { color: black; }</style>';
  }

  return $output;
}, 10, 2);
```
### footer

This filter allows you to adjust the output of the `</body>` before it is being rendered.

```php
add_action('blockstudio/render/footer', function($output, $blocks) {
  if ($blocks['ui/heading']) {
    $output .= '<style>h1 { color: black; }</style>';
  }

  return $output;
}, 10, 2);
```

---

# Hooks - JavaScript

Besides PHP hooks, Blockstudio also provides [JavaScript events](https://developer.mozilla.org/en-US/docs/Web/Events) to manipulate values and blocks inside the editor.

Since events are tied to block names and attribute IDs, we will use `namespace/my-block` as the name in the following examples. Of course, you will need to replace it with your own block name.

## Blocks

### refresh

This event can be dispatched to refresh blocks.

```javascript
const RefreshEvent = new CustomEvent('blockstudio/namespace/my-block/refresh');
document.dispatchEvent(RefreshEvent);
```
### loaded

This event is dispatched when the blocks are loaded. It can be used to initialise code once the block has loaded in the editor.

```javascript
document.addEventListener('blockstudio/namespace/my-block/loaded', function() {
  console.log('my-block loaded');
});
```
Alternatively, you can listen to all blocks.

```javascript
document.addEventListener('blockstudio/loaded', function() {
  console.log('my-block loaded');
});
```
### rendered

This event is dispatched when the blocks are rendered. It can be used to initialise code once the block has rendered in the editor.

```javascript
document.addEventListener('blockstudio/namespace/my-block/rendered', function() {
  console.log('my-block rendered');
});
```
Alternatively, you can listen to all blocks.

```javascript
document.addEventListener('blockstudio/rendered', function() {
  console.log('my-block rendered');
});
```
## Attributes

### update

This event is dispatched when an attribute is updated. For this example we will imagine a text field with the ID of `text`.

```javascript
document.addEventListener('blockstudio/namespace/my-block/attributes/text/update', function (event) {
  const {
    attribute,
    attributes,
    block,
    value,
    clientId,
    repeaterId
  } = event.detail;
});
```
Alternatively, you can listen to all attribute updates.

```javascript
document.addEventListener('blockstudio/attributes/update', function (event) {
  const {
    attribute,
    attributes,
    block,
    value,
    clientId,
    repeaterId
  } = event.detail;
});
```
#### Example

```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/events",
  "title": "Native Events",
  "category": "blockstudio-test-native",
  "icon": "star-filled",
  "description": "Native Blockstudio Events block.",
  "blockstudio": {
    "attributes": [
      {
        "id": "select",
        "type": "select",
        "label": "Layouts",
        "options": [
          {
            "value": "layout-1",
            "label": "Layout 1"
          },
          {
            "value": "layout-2",
            "label": "Layout 2"
          }
        ]
      }
    ]
  }
}
```
```javascript
document.addEventListener(
  'blockstudio/blockstudio/events/attributes/select/update',
  ({ detail }) => {
    // Get data from event.
    const { clientId, value } = detail;

    // Get current block and InnerBlocks.
    const currentBlock = wp.data
      .select('core/block-editor')
      .getBlocksByClientId(clientId)[0];
    const childBlocks = currentBlock.innerBlocks;

    // Remove current InnerBlocks.
    const clientIds = childBlocks.map((block) => block.clientId);
    clientIds.forEach((item) => {
      wp.data.dispatch('core/block-editor').removeBlock(item);
    });

    // Add new layout depending on the current value.
    if (value.value === 'layout-1') {

      const heading = wp.blocks.createBlock('core/heading', {
        content: 'This is the first layout',
      });
      const paragraph = wp.blocks.createBlock('core/paragraph', {
        content: 'This is a paragraph from the first layout',
      });
      wp.data.dispatch('core/editor').insertBlock(heading, 0, clientId);
      wp.data.dispatch('core/editor').insertBlock(paragraph, 1, clientId);

    } else if (value.value === 'layout-2') {

      const heading = wp.blocks.createBlock('core/heading', {
        content: 'This is the second layout',
      });
      const heading2 = wp.blocks.createBlock('core/heading', {
        content: 'With another heading',
        level: 3,
      });
      const paragraph = wp.blocks.createBlock('core/paragraph', {
        content: 'This is a paragraph from the second layout',
      });
      wp.data.dispatch('core/editor').insertBlock(heading, 0, clientId);
      wp.data.dispatch('core/editor').insertBlock(heading2, 1, clientId);
      wp.data.dispatch('core/editor').insertBlock(paragraph, 2, clientId);

    }
  }
);
```
The example above will change the InnerBlocks template depending on the option selected the `select` field.

#### With key

Since IDs are not necessarily unique across attributes (for example inside and outside repeaters), you can specify a `key` inside the attribute object to uniquely identify the attribute when using events.

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "repeater",
        "type": "repeater",
        "label": "Repeater",
        "attributes": [
          {
            "id": "text",
            "type": "text",
            "label": "Text"
          },
          {
            "id": "repeater",
            "type": "repeater",
            "label": "Repeater",
            "textButton": "Add repeater",
            "attributes": [
              {
                "key": "my-unique-key",
                "id": "text",
                "type": "text",
                "label": "Text"
              }
            ]
          }
        ]
      }
    ]
  }
}
```
With the setup above, listening to the key instead of the ID will only trigger the event when the text field inside the repeater is updated.

```javascript
document.addEventListener(
"blockstudio/namespace/my-block/attributes/my-unique-key/update",
  function (event) {
    const {
      attribute,
      attributes,
      block,
      value,
      clientId,
      repeaterId
    } = event.detail;
  }
);
```

---

# AI

AI-assisted coding is rapidly transforming the way developers build software, offering the potential to accelerate development cycles, reduce boilerplate, and even help with complex problem-solving. Large Language Models (LLMs) can write code, explain concepts, and debug issues, but their effectiveness heavily relies on the quality and comprehensiveness of the context they are given.

Blockstudio's new AI Context Generation feature is designed to bridge this gap, providing a seamless way to feed your AI coding assistant a rich, up-to-date understanding of your specific Blockstudio environment.

### Why Blockstudio and AI are a Perfect Match

Blockstudio's philosophy has always centered around a **file-first approach**. Each block, its configuration (`block.json`), template (PHP, Twig, or Blade), styles, and scripts reside together in a dedicated folder. This clear, organized structure is inherently AI-friendly:

- **Clarity and Discoverability**: LLMs can more easily understand the components of a block when all its related files are co-located and follow a predictable pattern.
- **Reduced Ambiguity**: Unlike database-driven block configurations, Blockstudio's file-based system provides a transparent and version-controllable source of truth for your block definitions.
- **Focused Context**: When an LLM knows the specific files related to a block, it can provide more targeted and relevant assistance.

### The Power of the Generated Context File

Blockstudio takes its AI-friendliness a step further by automatically generating a comprehensive `blockstudio-llm.txt` context file. This file is specifically designed for use with LLM tools (such as Cursor) and acts as a detailed briefing for the AI on your current Blockstudio setup.

The generated context file compiles crucial data from your WordPress installation, including:

- All available block definitions, their names, and their exact file paths.
- Current Blockstudio-specific settings, giving the AI insight into how your environment is configured.
- Relevant block schemas, outlining the structure and attributes of your custom blocks.
- Combined Blockstudio documentation, providing the AI with official information on Blockstudio APIs and features.

This creates a ready-to-use resource that significantly improves prompt engineering. With this detailed context, your AI assistant can:

- Generate more accurate and relevant code for your custom Blockstudio blocks.
- Better understand the relationships between different blocks and settings.
- Help debug issues with a deeper knowledge of your specific implementation.
- Assist in writing new block templates, scripts, or style sheets that align with your existing project structure.

### Enabling and Accessing the AI Context

The AI Context Generation feature can be managed from the main Blockstudio settings page within your WordPress admin area.

1. **Toggle AI Feature**: Simply activate the toggle switch next to the "AI" section to enable the context file generation.
2. **Accessing the Context File**: Once enabled, you have multiple ways to access the `blockstudio-llm.txt` file: - **Download Button**: Click the "Download the compiled .txt file" button to save a copy locally.
- **View Link**: Click the "View the compiled .txt file" link to open the context file directly in your browser (opens in a new tab).
- **Direct URL**: A dedicated URL is provided (e.g., `https://your-site.com/blockstudio/blockstudio-llm.txt`). This link will always serve the most up-to-date version of the context file, making it ideal for integrations or direct use in LLM tools.

The direct URL ensures that any LLM tool referencing it will always have the latest information about your Blockstudio environment without needing manual updates. By leveraging this feature, you can make your AI coding assistant a more powerful and informed partner in your Blockstudio development workflow.

---

# Extensions

Extensions allow you to extend any registered block in your WordPress installation using a simple `.json` based API, which is similar to the `block.json` configuration. This includes core, third-party and blocks made with Blockstudio.

All [field types](https://fabrikat.local/blockstudio/documentation/attributes/field-types) can be added to any block, with the data being saved to the block's attributes. The complete feature set of attributes like [filtering](https://fabrikat.local/blockstudio/documentation/attributes/filtering), [disabling](https://fabrikat.local/blockstudio/documentation/attributes/disabling), [conditional logic](https://fabrikat.local/blockstudio/documentation/attributes/conditional-logic) and [populating options](https://fabrikat.local/blockstudio/documentation/attributes/populating-options) are available for extensions.

## Extension types

Extended values can now be utilized in more versatile ways:

- **class**: Add a class using the attribute value to the block.
- **style**: Add inline styles using the attribute value to the block.
- **attributes**: Set any attribute value directly on the block, enhancing its functionality or accessibility.
- **data attributes**: Use extension values to set specific data attributes, enabling more complex interactions and dynamic content adjustments.

## Setup

Imagine the following scenario: you want to add a custom class `select` field to every core block. All you need to do is add this `.json` file anywhere in your block folder, and it will be automatically registered as an extension.

```json
{
  "$schema": "https://app.blockstudio.dev/schema/extend",
  "name": "core/*",
  "priority": 10,
  "blockstudio": {
    "extend": true,
    "attributes": [
      {
        "id": "customColor",
        "type": "select",
        "label": "Custom color",
        "options": [
          {
            "value": "#f00",
            "label": "Red"
          },
          {
            "value": "#00f",
            "label": "Blue"
          }
        ],
        "set": [
          {
            "attribute": "style",
            "value": "color: {attributes.customColor}"
          }
        ]
      }
    ]
  }
}
```
![Block extensions](data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTU0IiBoZWlnaHQ9IjY2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJibGFjayIgZmlsbC1vcGFjaXR5PSIwIi8+PC9zdmc+) This will add a `customColor` field to every core block, allowing you to set the color of the block's text. Let's break down the configuration from top to bottom:

### File name

Since there is no rendering template, an extension doesn't need a matching `index` file and thus doesn't need to be named `block.json`. This allows storing all extensions conveniently in a single folder.

### Schema

Blockstudio provides a custom JSON schema for extension configurations:

[View Extension schema](https://app.blockstudio.dev/schema/extend)

### Name

The `name` property of the configuration object defines which blocks should be extended. There are three options depending on your needs:

- **blockName**: A single block name, e.g. `core/paragraph`.
- **[blockNameA, blockNameB]**: An array of block names, e.g. `["core/paragraph", "core/heading"]`.
- **core/***: A wildcard to extend all blocks of a certain type, e.g. `core/&ast;`.

### Blockstudio key

Just like when using `block.json` for custom blocks, the `blockstudio` property has to be present in the configuration object with `extend` set to `true`.

### Position

By default, extensions will render in its own tab in the block inspector. If you want to move the extensions to a different position, you can use the `group` property. The following values are currently available:

- **settings**: The default position.
- **styles**: The styles tab.
- **advanced**: The advanced tab.

```json
{
  "$schema": "https://app.blockstudio.dev/schema/extend",
  "name": "core/*",
  "blockstudio": {
    "extend": true,
    "group": "styles",
    "attributes": []
  }
}
```
### Priority

The `priority` property allows you to define the order in which extensions are rendered. Extensions with a higher priority will be rendered later. This is useful if you want an extension to appear before another one.

## Attributes

For more information on how attributes work, please refer to the [attributes section](https://fabrikat.local/blockstudio/documentation/attributes/field-types).

The only thing you need to know about attributes inside extensions is the `set` property. It is used to apply the value of the attribute to one of the [types](https://fabrikat.local/blockstudio/documentation/extensions#extension-types) of the block.

For example, the following configuration will set the `style` attribute of the block to `color: red` or `color: blue` depending on the value of the `select` field.

```json
{
  "$schema": "https://app.blockstudio.dev/schema/extend",
  "name": "core/*",
  "blockstudio": {
    "extend": true,
    "attributes": [
      {
        "id": "customColor",
        "type": "select",
        "label": "Heading color",
        "options": [
          {
            "value": "#f00",
            "label": "Red"
          },
          {
            "value": "#00f",
            "label": "Blue"
          }
        ],
        "set": [
          {
            "attribute": "style",
            "value": "color: {attributes.customColor}"
          }
        ]
      }
    ]
  }
}
```
### Getting values

Dot notation inside curly braces is used to get the value of an attribute.

It is also possible to get nested values when attributes are not returning a single value, for example, when setting the return format to `both` on field types with options.

```json
{
  "attributes": [
    {
      "id": "customColor",
      "type": "select",
      "label": "Heading color",
      "options": [
        {
          "value": "#f00",
          "label": "Red"
        },
        {
          "value": "#00f",
          "label": "Blue"
        }
      ],
      "returnFormat": "both",
      "set": [
        {
          "attribute": "style",
          "value": "color: {attributes.customColor.value}"
        },
        {
          "attribute": "data-color",
          "value": "{attributes.customColor.label}"
        }
      ]
    }
  ]
}
```
The example above will set the `style` attribute to `color: #f00` or `color:#00f` and the `data-color` attribute to `Red` or `Blue` depending on the value.

For now, only values from the current attribute can be used inside `set` definitions. The ability to use values from other attributes might be added in the future.

### Arrays

When using arrays, the `set` property will be applied to every item in the array.

### Attributes field

When using the `attributes` field, there is no `set` property needed, as the field automatically maps the values to the attribute name and value

---

# Code Snippets

Although Blockstudios main focus is on custom blocks, its file-first approach is also suitable for code snippets.

## Setup

To create a new code snippet, simply create a new folder and place an `init.php` file in it. Blockstudio will always execute that file. For more information on the init file, check the [Initialization](https://fabrikat.local/blockstudio/documentation/initialization/) page.

## Styles and scripts

Folders marked as code snippets will also process and enqueue styles and scripts. To enqueue global styles and scripts, use the `global` [prefix for the asset name](https://fabrikat.local/blockstudio/documentation/assets/registering/#global).

Assets inside code snippet folders also support all [processing](https://fabrikat.local/blockstudio/documentation/assets/processing/).

## Custom directories

The above technique can also be used on directories outside of the folder where all blocks are stored. For example, let's say you want to store global assets like styles and scripts inside your theme. Simply create a new Blockstudio instance using the [`init` method](https://fabrikat.local/blockstudio/documentation/registration/#instances).

```php
add_action('init', function () {
  Blockstudio\Build::init([
    'dir' => get_template_directory() . '/assets'
  ]);
});
```
Now, simply follow the [setup](#setup) instructions inside the to register a code snippet inside the `assets` folder.

---

# Editor - General

Blockstudio includes a full code editor, capable of editing and creating blocks all within the WordPress admin area. The current feature set includes:

- Creating a custom plugin if no block source has been found
- Creating new files
- Editing files
- Renaming files
- Deleting files
- Creating new blocks
- Deleting blocks
- Importing data
- Exporting data
- Editing assets
- Adding assets
- Deleting assets
- Live preview of blocks

## Architecture

Just like many other popular online IDEs, Blockstudio is using the [Monaco Editor](https://microsoft.github.io/monaco-editor/) for code editing. Since VS Code is powered by this very same editor, VSC users should feel right at home with it.

Besides that, the editor interface is making heavy use of [Gutenberg components](https://developer.wordpress.org/block-editor/reference-guides/components/), ensuring similarities in look and behaviour to the WordPress admin area and Gutenberg itself.

## Safety

Although there are checks in place that will prevent the saving of blocks in case the code creates a critical error on your site, it is advisable to either use the editor locally or on a staging environment.

Keep in mind that the Blockstudio editor is not much different from using an editor on your computer, as it directly modifies the actual files in your block directory.

## Setup

This will activate the editor (accessible in the WordPress admin menu) for the users with the ID of 1 and 19.

This will activate the editor for all users with the role of `administrator` and `editor`.

### Preview assets

Assets that were registered using [`wp_enqueue_scripts`](https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/), [`admin_enqueue_scripts`](https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/) or [`enqueue_block_editor_assets`](https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/) can be added.

Similarly, it is also possible to add additional styles for just a single block using the following configuration inside the block.json:

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "blockstudio": {
    "editor": {
      "assets": ["stylesheet-handle"]
    }
  }
}
```
## Outlook

The introduction of the editor is an exciting development for Blockstudio users and WordPress developers working with Gutenberg. Now that there is an accompanying interface for block directories, importing custom blocks from the library directly into your theme will be possible in the near future.

On top of that, more work will be put into removing the barriers for integrated block development within WordPress. Tailwind, SCSS and a neat solution for web components as blocks are on the cards, so stay tuned!

---

# Editor - Examples

The `default` value for attributes will be used in the editor. If you prefer to preview the block with different data, you can use the [example](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#example) key.

Keep in mind that the example data will be also be used when [previewing](https://fabrikat.local/blockstudio/documentation/environment/#preview) the block

## Usage

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "example": {
    "attributes": {
      "title": "This is a title"
    }
  },
  "blockstudio": {
    "attributes": [
      {
        "type": "text",
        "id": "title",
        "label": "Title"
      }
    ]
  }
}
```
### Select

When adding examples to select-like field types with a values and labels (select, radio, checkbox etc.), the object has to include the `value` key.

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "example": {
    "attributes": {
      "select": {
        "value": "javascript"
      }
    }
  },
  "blockstudio": {
    "attributes": [
      {
        "type": "select",
        "id": "select",
        "label": "Select"
      }
    ]
  }
}
```
### Images

Blockstudio provides an easy way to populate the `files` field type with example data.

```json
{
  "name": "blockstudio/native",
  "title": "Native Block",
  "category": "text",
  "icon": "star-filled",
  "description": "Native Blockstudio block.",
  "example": {
    "attributes": {
      "files": {
        "blockstudio": true,
        "type": "image",
        "amount": 20
      }
    }
  },
  "blockstudio": {
    "attributes": [
      {
        "type": "files",
        "id": "images",
        "label": "Images"
      }
    ]
  }
}
```

---

# Editor - Gutenberg

Since 5.2, it is possible to edit blocks directly from Gutenberg. By clicking the "Edit with Blockstudio" button in the sidebar, the editor will open in a separate window and allow you to make changes to the block itself or its assets and preview them inside Gutenberg.

Editing blocks using this method has one advantage over the built-in editor preview that Blockstudio provides: the same block inserted multiple times with different settings can be previewed at once.

Please note that the [editor needs to be active](https://fabrikat.local/blockstudio/documentation/editor/general/#setup) in order for the preview button to show up.

## Limitations

At the moment, any changes made to the `block.json` file will not be reflected inside Gutenberg. This functionality will be added in a future update.

---

# Editor - Tailwind

Blockstudio supports writing [Tailwind](https://tailwindcss.com/) directly in the editor.

## Setup

Once enabled, you can start writing Tailwind inside your rendering templates.

### How does it work?

Blockstudio uses the [Tailwind CDN version](https://tailwindcss.com/docs/installation/play-cdn) to compile your Tailwind classes when developing in the editor. Upon saving a block, all used Tailwind classes are saved to a file and automatically enqueued on the frontend and block editor.

### Recompiling

If you need to recompile Tailwind classes after changes in your templates not made with the Blockstudio editor, you can do so by simply saving the settings again. This will recompile the Tailwind classes and save them to the file again.

## Config

---

# Library

Since version 3.1.0, Blockstudio includes a library of prebuilt elements. Of course, these elements are making use of all the features that are available to Blockstudio users. In fact, the library is actively being used to [dogfood](https://en.wikipedia.org/wiki/Eating_your_own_dog_food) our own product, helping us fast-track development and ensure a good developer experience for everyone.

The library is a first step in making Blockstudio more available as a general collection of ready-to-use blocks for non developers, all the while being built with the most advanced block framework for WordPress. More blocks are actively being developed!

## Activating

There are currently 5 blocks available in the library.

## Blocks

- **blockstudio-element/gallery** Arrange images in a gallery layout.
- **blockstudio-element/code** Highlight text like in a code editor.
- **blockstudio-element/slider** Display a carousel of images.
- **blockstudio-element/image-comparison** Compare two images (e.g. before/after) using a slider.
- **blockstudio-element/icon** Display an icon.

---

# Composer

Blockstudio is available as a Composer package.

## Installation

```bash
composer require blockstudio/blockstudio
```

## Embedding in Plugins and Themes

Blockstudio is fully namespaced and uses a singleton pattern, so it can be safely embedded within other plugins or themes without class name collisions. The singleton pattern ensures only one instance runs, even if multiple plugins bundle Blockstudio.

---
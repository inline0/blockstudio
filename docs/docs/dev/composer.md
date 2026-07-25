---
title: Composer
description: Install Blockstudio using Composer.
path: "dev/composer"
order: 65
section: "Dev"
meta_title: "Composer"
meta_description: "Install Blockstudio using Composer."
---

# Composer

Blockstudio is available on [Packagist](https://packagist.org/packages/blockstudio/blockstudio). There are several ways to install it via Composer depending on your project setup.

## As a Plugin

Use `composer/installers` to install Blockstudio directly into your `wp-content/plugins/` directory. This is the standard approach for Composer-managed WordPress projects like Bedrock.

```json title="composer.json"
{
  "require": {
    "composer/installers": "^2.0",
    "blockstudio/blockstudio": "^7.0"
  },
  "extra": {
    "installer-paths": {
      "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  }
}
```

Activate Blockstudio through the WordPress admin or WP-CLI:

```bash
wp plugin activate blockstudio
```

Your project's Composer autoloader must be loaded by the WordPress app so
Composer can register Blockstudio. In Bedrock this is handled automatically. In
other setups, use a must-use plugin:

```php title="wp-content/mu-plugins/autoloader.php"
<?php
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
```

When Blockstudio is included through Composer's autoloader before WordPress has
started loading plugins, the Composer bootstrap defers initialization until the
plugin loading environment is available. This lets Bedrock and other
site-level Composer setups include the package early without booting
Blockstudio before WordPress plugin APIs are ready.

## As a Must-Use Plugin

Install Blockstudio into `mu-plugins/` so it loads automatically without activation. Override the installer path to target the mu-plugins directory:

```json title="composer.json"
{
  "require": {
    "composer/installers": "^2.0",
    "blockstudio/blockstudio": "^7.0"
  },
  "extra": {
    "installer-paths": {
      "wp-content/mu-plugins/{$name}/": ["type:wordpress-plugin"]
    }
  }
}
```

WordPress only auto-loads PHP files at the root of `mu-plugins/`, not subdirectories. Add a loader file:

```php title="wp-content/mu-plugins/blockstudio-loader.php"
<?php
require_once WPMU_PLUGIN_DIR . '/blockstudio/blockstudio.php';
```

No activation needed. Blockstudio loads on every request automatically.

## Bundled in a Theme

Install Blockstudio as a dependency inside your theme. This keeps everything self-contained without requiring a separate plugin.

```json title="my-theme/composer.json"
{
  "require": {
    "blockstudio/blockstudio": "^7.0"
  }
}
```

Load it in your theme's `functions.php`:

```php title="functions.php"
<?php
require_once __DIR__ . '/vendor/autoload.php';
```

Blockstudio bootstraps automatically through Composer's autoloader when it is
loaded by WordPress. Asset URLs resolve to the correct theme directory. Blocks
defined in your theme's `blockstudio/` folder work exactly as they would with a
plugin install. This also works when the public theme directory is a symlink:
Blockstudio maps the package's physical path through the active or parent theme
URL instead of exposing the server path.

## Bundled in a Plugin

Install Blockstudio as a dependency inside another plugin. The same autoload bootstrap applies.

```json title="my-plugin/composer.json"
{
  "require": {
    "blockstudio/blockstudio": "^7.0"
  }
}
```

Load it in your plugin's main file:

```php title="my-plugin.php"
<?php
/*
Plugin Name: My Plugin
*/
require_once __DIR__ . '/vendor/autoload.php';
```

Asset URLs resolve to the correct plugin vendor directory.

## Supported loading-mode checks

Blockstudio continuously verifies the same package in four supported runtime
positions:

| Mode | Bootstrap |
| --- | --- |
| WordPress plugin | WordPress activates `blockstudio.php` |
| Must-use plugin | A root loader requires the package entry point |
| Composer dependency in a theme | The theme requires Composer's autoloader |
| Composer dependency in a plugin | The host plugin requires Composer's autoloader |

The full CI matrix boots WordPress independently in every mode and asserts
that `BLOCKSTUDIO_VERSION` is defined. This guards the one-instance bootstrap
without introducing a second host abstraction.

## Custom Package URL

Normal plugin, must-use plugin, bundled plugin, active theme, parent theme, and
symlinked-theme installs resolve automatically. For a custom deployment where
the package is exposed through another public URL, define `BLOCKSTUDIO_URL`
before Composer loads Blockstudio. Include the trailing slash:

```php title="functions.php"
<?php
define(
    'BLOCKSTUDIO_URL',
    get_stylesheet_directory_uri() . '/packages/blockstudio/'
);

require_once __DIR__ . '/vendor/autoload.php';
```

You can instead register `blockstudio/url` before loading the Composer
autoloader:

```php title="functions.php"
<?php
add_filter(
    'blockstudio/url',
    function (string $url, string $directory): string {
        return get_stylesheet_directory_uri() . '/vendor/blockstudio/blockstudio/';
    },
    10,
    2
);

require_once __DIR__ . '/vendor/autoload.php';
```

The filter receives the resolved URL and the physical package directory.

## Dependencies

Blockstudio's runtime dependencies (TailwindPHP, ScssPhp, Minify) are bundled and namespaced inside the plugin. The only Composer dependency installed into your `vendor/` directory is `yahnis-elsts/plugin-update-checker`, which handles version conflict resolution internally.

```
composer.json requires:
  php >= 8.2
  yahnis-elsts/plugin-update-checker ^5.6
```

## Updates

Composer installations receive updates through Composer. The built-in GitHub updater is automatically disabled when Blockstudio is loaded from a `vendor/` directory.

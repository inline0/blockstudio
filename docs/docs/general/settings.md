---
title: Settings
description: Configure Blockstudio with blockstudio.json or filters.
path: 'general/settings'
order: 2
section: 'General'
meta_title: 'Settings'
meta_description: 'Configure Blockstudio with blockstudio.json or filters.'
---

# Settings

Blockstudio includes a powerful settings API, that allows setting options via a `blockstudio.json` file inside your theme folder and/or filters. Additionally, allowed users are able to change the settings visually inside the admin area.

## Via JSON

If a `blockstudio.json` file is present inside your theme folder, it will be used to set the default options for the current site. A [JSON schema](https://blockstudio.dev/schema/blockstudio) is available to validate the file and help with autocompletion when used in an IDE.

The following properties are available:

```json title="blockstudio.json"
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "users": {
    "ids": [],
    "roles": []
  },
  "assets": {
    "enqueue": true,
    "reset": {
      "enabled": false,
      "fullWidth": []
    },
    "minify": {
      "css": false,
      "js": false
    },
    "process": {
      "scss": false
    }
  },
  "cache": {
    "enabled": true,
    "path": "blockstudio/cache"
  },
  "themeDefaults": {
    "titleTag": true,
    "suppressDirectoryUpdates": false,
    "syncPagesInDevelopment": false
  },
  "performance": {
    "profile": "compat",
    "wordpress": {
      "headNoise": false,
      "embeds": false,
      "xmlrpc": false,
      "editor": false,
      "frontendAssets": false,
      "media": false,
      "heartbeat": false
    },
    "preload": {
      "links": "off"
    },
    "media": {
      "lazy": false,
      "skeleton": false,
      "metadata": false,
      "rootMargin": "300px"
    },
    "measurement": {
      "enabled": false,
      "queryMonitor": false,
      "headers": false,
      "timings": false
    }
  },
  "content": {
    "enabled": false,
    "id": "default",
    "path": "content",
    "includePageSyncManaged": false,
    "authors": "ignore",
    "postTypes": [],
    "meta": {
      "include": [],
      "exclude": ["_edit_lock", "_edit_last", "_wp_old_slug"],
      "references": {}
    },
    "taxonomies": [],
    "media": "manifest"
  },
  "editor": {
    "formatOnSave": false,
    "assets": [],
    "markup": false
  },
  "ui": {
    "enabled": false
  },
  "library": false,
  "blockEditor": {
    "disableLoading": false,
    "enhance": false,
    "cssClasses": [],
    "cssVariables": [],
    "blocks": {
      "allow": [],
      "deny": [],
      "directory": true,
      "categories": {
        "allow": [],
        "deny": [],
        "rename": {},
        "order": []
      },
      "styles": {
        "deny": {}
      },
      "legacyWidgets": {
        "hide": []
      }
    },
    "patterns": {
      "core": true,
      "remote": true,
      "theme": true,
      "blockstudio": true,
      "categories": {
        "allow": [],
        "deny": [],
        "rename": {},
        "order": []
      }
    },
    "media": {
      "openverse": true,
      "imageSizes": {
        "allow": [],
        "deny": []
      }
    }
  }
}
```

## Via Filters

Alternatively you can use the `blockstudio/settings/${setting}` filter to set options via PHP for more flexibility.

```php title="functions.php"
add_filter('blockstudio/settings/assets/enqueue', '__return_false');
add_filter('blockstudio/settings/editor/formatOnSave', '__return_true');
add_filter('blockstudio/settings/ui/enabled', '__return_true');
add_filter('blockstudio/settings/block_editor/patterns/remote', '__return_false');
```

Options set via the `blockstudio/settings/${setting}` filter will override the ones set via the `blockstudio.json` file. Both methods can be used together.

Runtime values also expose `blockstudio/performance/${setting}` filters and one
final `blockstudio/performance/config` filter. The latter must return a valid
configuration object.

## Reading settings from PHP

`Blockstudio\Settings` loads the active source, reports malformed JSON, and
automatically reloads when `blockstudio.json` changes:

```php title="functions.php"
use Blockstudio\Settings;

$enabled = Settings::get_bool('performance/media/lazy');
$margin = Settings::get_string('performance/media/rootMargin', '300px');
$roles = Settings::get_array('users/roles');
$ttl = Settings::get_int('performance/staticPrerender/ttl', 86400);

foreach (Settings::errors() as $error) {
    error_log($error);
}
```

Use `Settings::get_raw()` when an integration needs only values explicitly
declared by the active JSON or options source. `Settings::fingerprint()` returns
a deterministic effective-settings identity. Long-running processes can call
`Settings::reload()` explicitly; normal access invalidates automatically.

The resolved performance profile is available through
`Blockstudio\Runtime_Settings::current()`. It supports slash or dot paths:

```php title="functions.php"
use Blockstudio\Runtime_Settings;

$runtime = Runtime_Settings::current();

if ($runtime->enabled('measurement/queryMonitor')) {
    $hash = $runtime->hash();
}
```

## Available Settings

### users

| Option  | Type  | Default | Description                   |
| ------- | ----- | ------- | ----------------------------- |
| `ids`   | array | `[]`    | User IDs with editor access   |
| `roles` | array | `[]`    | User roles with editor access |

### assets

| Option            | Type    | Default | Description                                                 |
| ----------------- | ------- | ------- | ----------------------------------------------------------- |
| `enqueue`         | boolean | `true`  | Auto-enqueue block assets                                   |
| `reset.enabled`   | boolean | `false` | Remove core block styles and apply the editor utility reset |
| `reset.fullWidth` | array   | `[]`    | Post types that use the full-width editor layout            |
| `minify.css`      | boolean | `false` | Minify CSS output                                           |
| `minify.js`       | boolean | `false` | Minify JS output                                            |
| `process.scss`    | boolean | `false` | Process SCSS files                                          |

With `reset.enabled` active, Blockstudio also copies sanitized frontend
`body_class` values into the editor canvas. Use
`blockstudio/editor/canvas/body_class` to adjust the editor-only class list.

### cache

| Option    | Type    | Default               | Description                                          |
| --------- | ------- | --------------------- | ---------------------------------------------------- |
| `enabled` | boolean | `true`                | Enable Blockstudio file-backed caches                |
| `path`    | string  | `"blockstudio/cache"` | Cache path, relative to `WP_CONTENT_DIR` or absolute |

By default cache files are written to `wp-content/blockstudio/cache`, outside
the uploads directory. A relative `path` is resolved from `WP_CONTENT_DIR`; an
absolute path is used directly. This supports hosts that provide a dedicated
writable cache volume.

When enabled, Blockstudio caches runtime build payloads, prebuilt block
registration data, and resolved editor assets. Runtime cache entries are
invalidated when watched block files, field files, asset files, settings, active
plugins, WordPress, PHP, or Blockstudio versions change.

The `blockstudio/settings/cache/path` setting filter changes the configured
value. For deployment-specific path resolution, `blockstudio/cache/dir` filters
the resolved base directory:

```php title="functions.php"
add_filter('blockstudio/cache/dir', function (string $directory): string {
    return WP_CONTENT_DIR . '/cache/blockstudio';
});
```

### themeDefaults

| Option                     | Type    | Default | Description                                                                      |
| -------------------------- | ------- | ------- | -------------------------------------------------------------------------------- |
| `titleTag`                 | boolean | `true`  | Enable WordPress title-tag theme support                                         |
| `suppressDirectoryUpdates` | boolean | `false` | Remove active child and parent themes from directory update results              |
| `syncPagesInDevelopment`   | boolean | `false` | Reconcile the existing `Blockstudio\Pages` source when page files change locally |

Page synchronization is limited to the `local` environment or `WP_DEBUG`.
`blockstudio/theme_defaults/sync_pages_in_development` can apply an additional
environment gate. Patterns continue to use the existing
`Blockstudio\Patterns` API; these defaults do not introduce alternate page or
pattern facades.

### performance

The `compat` profile leaves generic WordPress behavior unchanged. `speed` and
`strict` enable the same opt-in frontend defaults; every child setting can
override its profile value.

| Option                     | Type    | Compat default | Speed/strict default | Description                                      |
| -------------------------- | ------- | -------------- | -------------------- | ------------------------------------------------ |
| `profile`                  | string  | `"compat"`     | —                    | `compat`, `speed`, or `strict`                   |
| `wordpress.headNoise`      | boolean | `false`        | `true`               | Remove generic head discovery and emoji output   |
| `wordpress.embeds`         | boolean | `false`        | `true`               | Remove oEmbed discovery and host scripts         |
| `wordpress.xmlrpc`         | boolean | `false`        | `true`               | Disable XML-RPC and pingbacks                    |
| `wordpress.editor`         | boolean | `false`        | `true`               | Disable remote editor discovery surfaces         |
| `wordpress.frontendAssets` | boolean | `false`        | `true`               | Remove generic core frontend assets              |
| `wordpress.media`          | boolean | `false`        | `true`               | Apply image output defaults                      |
| `wordpress.heartbeat`      | boolean | `false`        | `true`               | Throttle Heartbeat outside editors               |
| `preload.links`            | string  | `"off"`        | `"intent"`           | Prefetch same-origin documents after user intent |
| `media.lazy`               | boolean | `false`        | `true`               | Use Blockstudio's image loader                   |
| `media.skeleton`           | boolean | `false`        | `true`               | Show the built-in loading skeleton               |
| `media.metadata`           | boolean | `false`        | `true`               | Declare use of `assets/media.json`               |
| `media.rootMargin`         | string  | `"300px"`      | `"300px"`            | Lazy-loader intersection margin                  |
| `measurement.enabled`      | boolean | `false`        | `false`              | Enable runtime measurement APIs                  |
| `measurement.queryMonitor` | boolean | `false`        | `false`              | Include queries taking at least 50ms             |
| `measurement.headers`      | boolean | `false`        | `false`              | Send profile and config hash headers             |
| `measurement.timings`      | boolean | `false`        | `false`              | Send the elapsed runtime header                  |

Static prerender settings are schema-stable under
`performance.staticPrerender`, including `ttl`, invalidation, early serving,
dynamic paths, and warming. They remain disabled unless explicitly enabled.

Measurements are available programmatically:

```php title="functions.php"
use Blockstudio\Performance_Measurement;

$snapshot = Performance_Measurement::snapshot();
```

When headers are enabled, Blockstudio sends
`X-Blockstudio-Performance-Profile`,
`X-Blockstudio-Performance-Config`, and optionally
`X-Blockstudio-Performance-Time`. The
`blockstudio/performance/measurement_enabled` action receives the resolved
runtime settings.

#### Media metadata and images

Generate stable dimensions for theme assets and optional WordPress attachments:

```php title="functions.php"
use Blockstudio\Media_Metadata_Builder;

(new Media_Metadata_Builder())->write(
    get_stylesheet_directory(),
    true
);
```

This writes deterministic metadata to `assets/media.json`. Templates can then
render local or attachment images through the public helper:

```php title="index.php"
<?php
echo bs_media_image([
    'src' => 'assets/images/hero.webp',
    'alt' => 'Hero',
    'class' => 'hero-media',
    'sources' => [
        ['srcset' => '/hero-small.webp', 'media' => '(max-width: 640px)'],
    ],
]);
?>
```

The helper always emits known width, height, and aspect ratio values when
metadata exists. Lazy mode uses only `blockstudio-*` classes, attributes,
handles, and globals.

### content

| Option                   | Type    | Default                                        | Description                                                   |
| ------------------------ | ------- | ---------------------------------------------- | ------------------------------------------------------------- |
| `enabled`                | boolean | `false`                                        | Enable Content Sync configuration                             |
| `id`                     | string  | `"default"`                                    | Content-set namespace stored on synced entities               |
| `path`                   | string  | `"content"`                                    | Theme-relative content file directory                         |
| `includePageSyncManaged` | boolean | `false`                                        | Include Page Sync managed posts without owning their body     |
| `authors`                | string  | `"ignore"`                                     | Author handling (`ignore` or existing-user `login`)           |
| `postTypes`              | array   | `[]`                                           | Allowlisted post types                                        |
| `meta.include`           | array   | `[]`                                           | Glob patterns for meta keys to sync                           |
| `meta.exclude`           | array   | `["_edit_lock", "_edit_last", "_wp_old_slug"]` | Glob patterns for meta keys to exclude                        |
| `meta.references`        | object  | `{}`                                           | Declared meta references rewritten between IDs and UIDs       |
| `taxonomies`             | array   | `[]`                                           | Allowlisted registered taxonomies for terms and relationships |
| `media`                  | string  | `"manifest"`                                   | Attachment reference behavior (`manifest` or `none`)          |

Content Sync is managed through `wp bs content` and projects allowlisted posts,
postmeta, and declared references to portable files. See
[Content Sync](/docs/content-sync) for the workflow and file
format.

### editor

| Option         | Type    | Default | Description                         |
| -------------- | ------- | ------- | ----------------------------------- |
| `formatOnSave` | boolean | `false` | Format block.json on save           |
| `assets`       | array   | `[]`    | Additional assets to load in editor |
| `markup`       | boolean | `false` | Enable markup editing               |

### library

| Type    | Default | Description              |
| ------- | ------- | ------------------------ |
| boolean | `false` | Enable the block library |

### tailwind

| Option    | Type    | Default | Description                         |
| --------- | ------- | ------- | ----------------------------------- |
| `enabled` | boolean | `false` | Enable Tailwind CSS compilation     |
| `config`  | string  | `""`    | Tailwind v4 CSS-first configuration |

### ui

| Option    | Type    | Default | Description                                                       |
| --------- | ------- | ------- | ----------------------------------------------------------------- |
| `enabled` | boolean | `false` | Register the bundled `bsui/*` UI components and `app/*` demo apps |

### blockEditor

| Option           | Type    | Default | Description                                                                    |
| ---------------- | ------- | ------- | ------------------------------------------------------------------------------ |
| `disableLoading` | boolean | `false` | Disable block loading in editor                                                |
| `enhance`        | boolean | `false` | Enable Blockstudio editor hover and selection affordances                      |
| `cssClasses`     | array   | `[]`    | Stylesheet URLs to extract CSS classes from for the classes field autocomplete |
| `cssVariables`   | array   | `[]`    | Stylesheet URLs to extract CSS variables from for the code field autocomplete  |

#### blockEditor.blocks

Use `blockEditor.blocks` for project-wide block inserter policy. `allow` and `deny` accept block names and wildcard patterns such as `core/*`.

```json title="blockstudio.json"
{
  "blockEditor": {
    "blocks": {
      "allow": ["core/*", "my-theme/*"],
      "deny": ["core/embed", "core/freeform"],
      "directory": false,
      "categories": {
        "rename": {
          "text": "Writing",
          "design": "Layout"
        },
        "order": ["my-theme", "text", "media"]
      },
      "legacyWidgets": {
        "hide": ["archives", "calendar"]
      }
    }
  }
}
```

`blocks.styles.deny` unregisters styles that were registered through WordPress'
PHP block style registry:

```json title="blockstudio.json"
{
  "blockEditor": {
    "blocks": {
      "styles": {
        "deny": {
          "my-theme/card": ["outline"],
          "my-theme/media": ["framed"]
        }
      }
    }
  }
}
```

#### blockEditor.patterns

Use `blockEditor.patterns` to disable global pattern sources and to filter
pattern categories.

```json title="blockstudio.json"
{
  "blockEditor": {
    "patterns": {
      "core": false,
      "remote": false,
      "theme": true,
      "blockstudio": true,
      "categories": {
        "deny": ["gallery"],
        "rename": {
          "featured": "Featured Layouts"
        },
        "order": ["featured", "buttons"]
      }
    }
  }
}
```

#### blockEditor.media

Use `blockEditor.media` for global media inserter policy.

```json title="blockstudio.json"
{
  "blockEditor": {
    "media": {
      "openverse": false,
      "imageSizes": {
        "allow": ["thumbnail", "large"],
        "deny": ["medium_large"]
      }
    }
  }
}
```

> **[UI Components](/docs/blocks/ui-components)**
>
> Enable and compose the bundled headless UI components introduced in Blockstudio 7.3.

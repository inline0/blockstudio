---
title: Media
description: Stable image dimensions, lazy loading, and the media metadata manifest.
path: "production/media"
order: 59
section: "Production"
meta_title: "Media"
meta_description: "Stable image dimensions, lazy loading, and the media metadata manifest."
---

# Media

Theme images live outside the media library, so WordPress knows nothing about
their dimensions and every unstyled image shifts layout while it loads.
Blockstudio closes that gap with a generated metadata manifest, a template
helper that always emits real dimensions, and an optional lazy loader.

## Rendering images

`bs_media_image()` renders theme assets with real width, height, and a reserved
aspect ratio:

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

The output is a `figure.blockstudio-media` with an inline `aspect-ratio` around
an `img` carrying real `width`, `height`, `loading`, and `decoding` values.

`src` takes a theme-relative path, an absolute path, or a full URL, and
`attachmentId` covers media library images. `eager` opts out of lazy loading
above the fold, and `sources` emits a `<picture>` element with the given
alternates.

The helper always emits known width, height, and aspect ratio values when
metadata exists. Lazy mode uses only `blockstudio-*` classes, attributes,
handles, and globals.

## The metadata manifest

Dimensions come from `assets/media.json`, written by
`Blockstudio\Media_Metadata_Builder` in a build step or by hand:

```php title="functions.php"
use Blockstudio\Media_Metadata_Builder;

(new Media_Metadata_Builder())->write(
    get_stylesheet_directory(),
    true
);
```

This writes deterministic metadata for the theme's assets and, with the second
argument, optional WordPress attachments. Regenerate it whenever files under
`assets/` change.

`Media_Metadata_Builder::build( string $root )` returns the manifest without
writing it; `write()` is the variant that persists.
`Blockstudio\Media_Metadata::reset()` clears the in-process cache after a
rebuild, which long-lived build processes need before rendering against fresh
metadata.

## Lazy loading

The `performance.media` settings drive the frontend loader:

```json title="blockstudio.json"
{
  "performance": {
    "media": {
      "lazy": true,
      "skeleton": true,
      "metadata": true,
      "rootMargin": "300px"
    }
  }
}
```

With `lazy` on, the real URL moves to `data-src` behind a correctly sized
placeholder and Blockstudio's intersection-based loader swaps it in as the
image approaches the viewport, `rootMargin` ahead of time. `skeleton` shows the
built-in loading placeholder, and `metadata` declares that `assets/media.json`
is in use.

The `speed` and `strict` [performance profiles](/docs/production/performance)
enable the loader by default; `compat` leaves it off. Every child value can
override its profile default.

> **[Settings](/docs/general/settings#performance)**
>
> The full `performance.media` option table.

> **[Static Prerendering](/docs/production/static-prerendering)**
>
> Media metadata is step two of the production adoption order.

---
title: Logical Discovery Sources
description: Supply composed file inventories to Blockstudio discovery and runtime caches.
path: "dev/discovery-sources"
order: 63
section: "Dev"
meta_title: "Logical Discovery Sources"
meta_description: "Supply composed file inventories to Blockstudio discovery and runtime caches."
---

# Logical Discovery Sources

Blockstudio normally discovers files from physical theme directories. Runtime
tools can replace those directories with a logical inventory. This is useful
for previews, overlays, generated themes, remote mounts, and other environments
where related files do not share one physical root.

Blockstudio still parses and registers blocks, pages, collections, patterns,
fields, templates, and template parts. The source only decides which logical
files are visible and where each visible file can be read.

## Inventory sources

Use the `blockstudio/discovery/sources` filter to replace the default
`Filesystem_Discovery_Source` objects:

```php title="functions.php"
use Blockstudio\Inventory_Discovery_Source;

add_filter(
    'blockstudio/discovery/sources',
    function (array $sources, string $context, array $paths): array {
        if ('blocks' !== $context) {
            return $sources;
        }

        return [
            new Inventory_Discovery_Source(
                'preview:feature-card',
                '/workspace/feature-card/blockstudio',
                [
                    'card/block.json' => [
                        'path' => '/theme/blockstudio/card/block.json',
                        'provenance' => [
                            'layer' => 'parent',
                            'inherited' => true,
                        ],
                    ],
                    'card/index.php' => [
                        'path' => '/workspace/feature-card/blockstudio/card/index.php',
                        'provenance' => [
                            'layer' => 'preview',
                        ],
                    ],
                ],
                'parent-and-preview-fingerprint',
                ['/theme/blockstudio', '/workspace/feature-card/blockstudio']
            ),
        ];
    },
    10,
    3
);
```

The available contexts are:

- `blocks`
- `fields`
- `pages`
- `patterns`
- `site-templates`
- `site-template-parts`

An inventory is the final visible tree. Compose layers before returning it:

- Use one entry for each visible logical path.
- Let the selected layer provide the physical path.
- Omit deleted logical paths.
- Return entries in any order. Blockstudio sorts them by logical path.
- Include empty logical directories in the constructor's optional sixth
  argument when directory visibility matters.

`Inventory_Discovery_Source` is convenient for array-backed inventories.
Integrations can implement the `Discovery_Source` interface when entries are
resolved lazily or come from another storage system.

## Sibling resolution

Logical siblings can resolve to different physical roots. A parent
`block.json` can use an overlay `index.php`, and an overlay page can inherit its
parent `pages.json`, loader, or layout. Blockstudio resolves these relationships
by logical path before it reads the selected physical files.

Loader paths outside a collection retain the existing
`blockstudio/pages/allow_external_loader_path` security boundary.

## Identity and invalidation

Every source provides:

- A stable source ID for the selected runtime.
- A fingerprint that changes with the visible inventory.
- Physical watch inputs for hot reload and cache validation.
- Provenance and metadata for each entry.

Blockstudio includes source IDs and fingerprints in runtime, page manifest,
Site Editor template, Canvas, and Tailwind cache identities.

Use `blockstudio/cache/context` when the same source selection can render under
multiple runtime variants:

```php title="functions.php"
add_filter(
    'blockstudio/cache/context',
    fn ($context, string $scope) => [
        'preview' => 'feature-card',
        'site' => get_current_blog_id(),
    ],
    10,
    2
);
```

The context value must be serializable and stable for equivalent requests.
Tailwind keeps last-good CSS in a context-specific namespace, so one preview
cannot receive another preview's fallback CSS.

## Generated assets

Mark inherited or read-only entries with `inherited: true`, `readOnly: true`,
or `writable: false` in provenance or metadata. Blockstudio then writes
compiled assets to a context-specific directory under uploads instead of next
to the parent source.

Two filters cover custom runtime routing:

```php title="functions.php"
add_filter(
    'blockstudio/generated_output/path',
    function (string $path, string $source, string $scope, array $metadata) {
        return $path;
    },
    10,
    4
);

add_filter(
    'blockstudio/files/url',
    function (string $url, string $path, string $scope) {
        return $url;
    },
    10,
    3
);
```

Use the URL filter when selected files live outside `wp-content` or are served
through a runtime endpoint.

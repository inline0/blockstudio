# Documentation Migration PRD

Migrate Blockstudio documentation from Twig templates to OneDocs MDX format.

**Source:** `/fabrikat/app/public/wp-content/themes/fabrikat/src/twig/blockstudio/docs/documentation/`
**Target:** `/blockstudio7/docs/content/docs/`

---

## Progress

### Core Pages

- [x] index.mdx (general.twig → Introduction)
- [x] getting-started.mdx (new)
- [x] registration.mdx
- [x] initialization.mdx
- [x] activating.mdx
- [x] loading.mdx
- [x] environment.mdx
- [x] library.mdx
- [x] preview.mdx
- [x] post-meta.mdx
- [x] overrides.mdx
- [x] transforms.mdx
- [x] variations.mdx
- [x] composer.mdx
- [x] ai.mdx
- [x] code-snippets.mdx
- [x] context.mdx
- [x] settings.mdx
- [x] schema.mdx
- [x] extensions.mdx
- [x] rendering.mdx

### Attributes (`/attributes/`)

- [x] registering.mdx
- [x] field-types.mdx
- [x] block-attributes.mdx
- [x] conditional-logic.mdx
- [x] filtering.mdx
- [x] rendering.mdx
- [x] populating-options.mdx
- [x] html-utilities.mdx
- [x] disabling.mdx

### Components (`/components/`)

- [x] innerblocks.mdx
- [x] richtext.mdx
- [x] mediaplaceholder.mdx
- [x] useblockprops.mdx

### Editor (`/editor/`)

- [x] general.mdx
- [x] tailwind.mdx
- [x] gutenberg.mdx
- [x] examples.mdx

### Hooks (`/hooks/`)

- [x] php.mdx
- [x] javascript.mdx

### Assets (`/assets/`)

- [x] registering.mdx
- [x] processing.mdx
- [x] code-field.mdx

### Templating (`/templating/`)

- [x] twig.mdx
- [x] blade.mdx

### Rendering (`/rendering/`) - MERGED INTO MAIN rendering.mdx

- [x] Twig content merged
- [x] Blade content merged

---

## Summary

| Section | Done | Total | Progress |
|---------|------|-------|----------|
| Core Pages | 21 | 21 | 100% |
| Attributes | 9 | 9 | 100% |
| Components | 4 | 4 | 100% |
| Editor | 4 | 4 | 100% |
| Hooks | 2 | 2 | 100% |
| Assets | 3 | 3 | 100% |
| Templating | 2 | 2 | 100% |
| **Total** | **45** | **45** | **100%** |

---

## Notes

- Blog/release notes excluded from migration
- JSON data files for dynamic content stored in `/public/data/`
- Using Fumadocs/OneDocs MDX components for tabs, callouts, etc.
- Rendering section (twig.twig, blade.twig) merged into main rendering.mdx since they contained duplicate content from templating section

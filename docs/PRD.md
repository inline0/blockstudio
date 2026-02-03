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
- [ ] initialization.mdx
- [ ] activating.mdx
- [ ] loading.mdx
- [ ] environment.mdx
- [ ] library.mdx
- [ ] preview.mdx
- [ ] post-meta.mdx
- [ ] overrides.mdx
- [ ] transforms.mdx
- [ ] variations.mdx
- [ ] composer.mdx
- [ ] ai.mdx
- [ ] code-snippets.mdx
- [x] context.mdx
- [x] settings.mdx
- [x] schema.mdx
- [x] extensions.mdx

### Attributes (`/attributes/`)

- [x] registering.mdx
- [x] field-types.mdx
- [ ] block-attributes.mdx
- [ ] conditional-logic.mdx
- [ ] filtering.mdx
- [ ] rendering.mdx
- [ ] populating-options.mdx
- [ ] html-utilities.mdx
- [ ] disabling.mdx

### Components (`/components/`)

- [x] innerblocks.mdx
- [ ] richtext.mdx
- [ ] mediaplaceholder.mdx
- [ ] useblockprops.mdx

### Editor (`/editor/`)

- [ ] general.mdx
- [x] tailwind.mdx
- [ ] gutenberg.mdx
- [ ] examples.mdx

### Hooks (`/hooks/`)

- [x] php.mdx
- [x] javascript.mdx

### Assets (`/assets/`)

- [ ] registering.mdx
- [ ] processing.mdx
- [ ] code-field.mdx

### Templating (`/templating/`)

- [ ] twig.mdx
- [ ] blade.mdx

### Rendering (`/rendering/`)

- [ ] twig.mdx
- [ ] blade.mdx

---

## Summary

| Section | Done | Total | Progress |
|---------|------|-------|----------|
| Core Pages | 6 | 20 | 30% |
| Attributes | 2 | 9 | 22% |
| Components | 1 | 4 | 25% |
| Editor | 1 | 4 | 25% |
| Hooks | 2 | 2 | 100% |
| Assets | 0 | 3 | 0% |
| Templating | 0 | 2 | 0% |
| Rendering | 0 | 2 | 0% |
| **Total** | **12** | **46** | **26%** |

---

## Notes

- Blog/release notes excluded from migration
- JSON data files for dynamic content stored in `/public/data/`
- Using Fumadocs/OneDocs MDX components for tabs, callouts, etc.

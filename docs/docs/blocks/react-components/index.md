---
title: General
description: Use React-powered editor components directly in your PHP, Twig, or Blade templates.
path: "blocks/react-components"
order: 26
section: "Blocks"
subsection: "React Components"
meta_title: "General"
meta_description: "Use React-powered editor components directly in your PHP, Twig, or Blade templates."
---

# React Components

Blockstudio provides template components that map to native WordPress editor features. In the editor, they become interactive React components. On the frontend, they render as plain HTML.

- [useBlockProps](/docs/blocks/react-components/useblockprops):Mark the block wrapper element
- [InnerBlocks](/docs/blocks/react-components/innerblocks):Allow nested blocks within your block
- [RichText](/docs/blocks/react-components/richtext):Inline rich text editing
- [MediaPlaceholder](/docs/blocks/react-components/mediaplaceholder):Media upload and selection

## Attribute Syntax

`RichText` and `InnerBlocks` accept single-quoted and double-quoted attributes in PHP, Twig, and Blade templates:

```html
<RichText attribute='myRichText' tag='h2' />
<InnerBlocks tag="section" class="content" />
```

Unquoted values such as `tag=h2` also work. Quote values containing spaces or JSON, and escape dynamic values for HTML attributes. Empty quoted values stay empty, while an attribute without a value is boolean.

RichText field selectors also support [nested repeater paths](/docs/blocks/react-components/richtext#repeater-fields), such as `attribute="items[0].heading"`.

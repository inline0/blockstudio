import { createMDX } from "fumadocs-mdx/next";

const withMDX = createMDX();

/** @type {import('next').NextConfig} */
const config = {
  async redirects() {
    return [
      // Documentation root
      { source: "/documentation", destination: "/docs", permanent: true },
      { source: "/documentation/general", destination: "/docs", permanent: true },

      // General
      { source: "/documentation/activating", destination: "/docs/general/getting-started", permanent: true },
      { source: "/documentation/settings", destination: "/docs/general/settings", permanent: true },

      // Blocks: top-level docs
      { source: "/documentation/registration", destination: "/docs/blocks/registration", permanent: true },
      { source: "/documentation/schema", destination: "/docs/blocks/schema", permanent: true },
      { source: "/documentation/initialization", destination: "/docs/blocks/initialization", permanent: true },
      { source: "/documentation/rendering", destination: "/docs/blocks/rendering", permanent: true },
      { source: "/documentation/context", destination: "/docs/blocks/context", permanent: true },
      { source: "/documentation/preview", destination: "/docs/blocks/preview", permanent: true },
      { source: "/documentation/environment", destination: "/docs/blocks/environment", permanent: true },
      { source: "/documentation/post-meta", destination: "/docs/blocks/post-meta", permanent: true },
      { source: "/documentation/overrides", destination: "/docs/blocks/overrides", permanent: true },
      { source: "/documentation/loading", destination: "/docs/blocks/loading", permanent: true },
      { source: "/documentation/variations", destination: "/docs/blocks/variations", permanent: true },
      { source: "/documentation/transforms", destination: "/docs/blocks/transforms", permanent: true },
      { source: "/documentation/extensions", destination: "/docs/extensions", permanent: true },
      { source: "/documentation/code-snippets", destination: "/docs/code-snippets", permanent: true },
      { source: "/documentation/library", destination: "/docs", permanent: true },
      { source: "/documentation/composer", destination: "/docs/dev/composer", permanent: true },
      { source: "/documentation/ai", destination: "/docs/dev/ai", permanent: true },

      // Blocks: templating
      { source: "/documentation/templating/blade", destination: "/docs/blocks/templating/blade", permanent: true },
      { source: "/documentation/templating/twig", destination: "/docs/blocks/templating/twig", permanent: true },

      // Blocks: assets
      { source: "/documentation/assets/registering", destination: "/docs/blocks/assets/registering", permanent: true },
      { source: "/documentation/assets/processing", destination: "/docs/blocks/assets/processing", permanent: true },
      { source: "/documentation/assets/code-field", destination: "/docs/blocks/assets/code-field", permanent: true },

      // Blocks: attributes
      { source: "/documentation/attributes/registering", destination: "/docs/blocks/attributes/registering", permanent: true },
      { source: "/documentation/attributes/rendering", destination: "/docs/blocks/attributes/rendering", permanent: true },
      { source: "/documentation/attributes/filtering", destination: "/docs/blocks/attributes/filtering", permanent: true },
      { source: "/documentation/attributes/disabling", destination: "/docs/blocks/attributes/disabling", permanent: true },
      { source: "/documentation/attributes/conditional-logic", destination: "/docs/blocks/attributes/conditional-logic", permanent: true },
      { source: "/documentation/attributes/populating-options", destination: "/docs/blocks/attributes/populating-options", permanent: true },
      { source: "/documentation/attributes/block-attributes", destination: "/docs/blocks/attributes/block-attributes", permanent: true },
      { source: "/documentation/attributes/field-types", destination: "/docs/blocks/attributes/field-types", permanent: true },
      { source: "/documentation/attributes/html-utilities", destination: "/docs/blocks/attributes/html-utilities", permanent: true },

      // Blocks: components
      { source: "/documentation/components/useblockprops", destination: "/docs/blocks/components/useblockprops", permanent: true },
      { source: "/documentation/components/innerblocks", destination: "/docs/blocks/components/innerblocks", permanent: true },
      { source: "/documentation/components/richtext", destination: "/docs/blocks/components/richtext", permanent: true },
      { source: "/documentation/components/mediaplaceholder", destination: "/docs/blocks/components/mediaplaceholder", permanent: true },

      // Blocks: hooks
      { source: "/documentation/hooks/php", destination: "/docs/blocks/hooks/php", permanent: true },
      { source: "/documentation/hooks/javascript", destination: "/docs/blocks/hooks/javascript", permanent: true },

      // Blocks: editor
      { source: "/documentation/editor/general", destination: "/docs", permanent: true },
      { source: "/documentation/editor/examples", destination: "/docs", permanent: true },
      { source: "/documentation/editor/gutenberg", destination: "/docs", permanent: true },
      { source: "/documentation/editor/tailwind", destination: "/docs/tailwind", permanent: true },

      // Old blog posts (no longer exist individually)
      { source: "/blog/blockstudio-3-1", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-3-2", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-4-0", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-4-1", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-4-2", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-5-0", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-5-1", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-5-2", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-5-3", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-5-4", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-5-5", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-5-6", destination: "/blog", permanent: true },
      { source: "/blog/blockstudio-6-0", destination: "/blog", permanent: true },

      // Old feature/marketing pages
      { source: "/features/editor", destination: "/features/blocks", permanent: true },
      { source: "/features/fields", destination: "/features/blocks", permanent: true },
      { source: "/features/composition", destination: "/features/pages", permanent: true },
      { source: "/pricing", destination: "/", permanent: true },
      { source: "/compare", destination: "/", permanent: true },
      { source: "/configurator", destination: "/", permanent: true },
      { source: "/changelog", destination: "/blog", permanent: true },
      { source: "/roadmap", destination: "/", permanent: true },
      { source: "/downloads/blockstudio", destination: "/", permanent: true },

      // Account pages
      { source: "/account", destination: "/", permanent: true },
      { source: "/account/:path*", destination: "/", permanent: true },
      { source: "/checkout", destination: "/", permanent: true },
      { source: "/purchase-confirmation", destination: "/", permanent: true },
      { source: "/transaction-failed", destination: "/", permanent: true },
      { source: "/reset-password", destination: "/", permanent: true },

      // Legal pages
      { source: "/cookie-policy", destination: "/", permanent: true },
      { source: "/privacy-policy", destination: "/", permanent: true },
      { source: "/terms-of-service", destination: "/", permanent: true },
      { source: "/legal-notice", destination: "/", permanent: true },

      // Catch trailing slashes on documentation paths
      { source: "/documentation/:path+/", destination: "/docs/:path+", permanent: true },
    ];
  },
};

export default withMDX(config);

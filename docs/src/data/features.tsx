import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import {
  Blocks,
  BookOpen,
  Braces,
  Code,
  Component,
  Database,
  FileCode,
  FileJson,
  FileText,
  Filter,
  GitMerge,
  Key,
  Layers,
  LayoutGrid,
  Lock,
  Package,
  Paintbrush,
  Pencil,
  Puzzle,
  RefreshCw,
  Search,
  Settings,
  Sparkles,
  Split,
  Target,
  ToggleRight,
  Wand2,
  Zap,
} from 'lucide-react';

interface CodeTab {
  label: string;
  code: string;
  lang: string;
}

interface FeatureItem {
  headline: ReactNode;
  description: ReactNode;
  code: string | CodeTab[];
  lang?: string;
  ctaLabel: string;
  ctaHref: string;
}

interface DetailItem {
  icon: LucideIcon;
  title: string;
  description: string;
}

export interface FeaturePageData {
  icon: LucideIcon;
  title: string;
  description: string;
  features: FeatureItem[];
  details: DetailItem[];
  docsHref: string;
}

const code = (text: string) => (
  <code className="text-fd-foreground font-mono text-sm">{text}</code>
);

export const features: Record<string, FeaturePageData> = {
  blocks: {
    icon: Blocks,
    title: 'Custom blocks in 3 files',
    description:
      'Define fields in block.json, render them in a template, style with CSS or SCSS. No JavaScript, no build step, no boilerplate.',
    features: [
      {
        headline: 'Define fields in JSON',
        description: (
          <>
            <p>
              Add a {code('blockstudio')} key to your block.json. Define
              attributes with types like {code('text')}, {code('toggle')}, and{' '}
              {code('color')}. Blockstudio generates the editor UI
              automatically. 26 field types available, from simple text inputs
              to repeaters and code editors.
            </p>
            <p>
              Full JSON Schema support gives you autocomplete and validation in
              VS Code, PhpStorm, and any editor that supports it.
            </p>
          </>
        ),
        code: `{
  "name": "starter/hero",
  "title": "Hero",
  "blockstudio": {
    "attributes": {
      "heading": {
        "type": "text"
      },
      "showCta": {
        "type": "toggle",
        "label": "Show CTA"
      },
      "background": {
        "type": "color"
      }
    }
  }
}`,
        lang: 'json',
        ctaLabel: 'Field types reference',
        ctaHref: '/docs/blocks/attributes/field-types',
      },
      {
        headline: 'React components in PHP templates',
        description: (
          <>
            <p>
              Use {code('<RichText />')} for inline editing in the block editor
              and static HTML on the frontend. {code('<InnerBlocks />')} for
              nested child blocks. And {code('useBlockProps')} to mark the block
              wrapper. All from a single template file.
            </p>
            <p>
              In the editor, these become real React components. On the
              frontend, they render as plain server-side HTML. No JavaScript
              shipped to your visitors.
            </p>
          </>
        ),
        code: `<div useBlockProps class="container">
  <RichText
    class="text-xl font-semibold"
    tag="h1"
    attribute="richtext"
    placeholder="Enter headline"
  />

  <?php if ($a['showCta']): ?>
    <a href="<?= $a['ctaUrl'] ?>">
      <?= $a['ctaLabel'] ?>
    </a>
  <?php endif; ?>

  <InnerBlocks class="mt-4 p-4 border" />
</div>`,
        lang: 'php',
        ctaLabel: 'Explore components',
        ctaHref: '/docs/blocks/components',
      },
      {
        headline: 'Write in PHP, Twig, or Blade',
        description: (
          <>
            <p>
              Use the template language you already know. PHP templates work out
              of the box. Add Timber for Twig or jenssegers/blade for Blade.
              Same variables, same components, same behavior.
            </p>
            <p>
              Template variables {code('$a')} (attributes), {code('$b')}{' '}
              (block), {code('$c')} (context), and {code('$innerBlocks')} are
              available in all three languages.
            </p>
          </>
        ),
        code: `{# index.twig #}
<div useBlockProps class="card">
  <RichText
    tag="h2"
    attribute="title"
    placeholder="Card title"
  />

  <img src="{{ a.image.url }}"
       alt="{{ a.image.alt }}" />

  <div class="card__body">
    {{ a.description }}
  </div>

  <InnerBlocks allowedBlocks='["core/button"]' />
</div>`,
        lang: 'twig',
        ctaLabel: 'Template languages',
        ctaHref: '/docs/blocks/templating/twig',
      },
      {
        headline: 'Conditional logic and dynamic options',
        description: (
          <>
            <p>
              Show and hide fields based on other values. Operators include{' '}
              {code('==')}, {code('!=')}, {code('includes')}, {code('empty')},
              and comparison operators. Combine multiple conditions with OR
              logic.
            </p>
            <p>
              Populate select options dynamically from posts, users, terms, or
              external APIs. Mix static options with populated data. No
              hardcoded lists.
            </p>
          </>
        ),
        code: `{
  "attributes": {
    "layout": {
      "type": "select",
      "options": ["default", "featured", "minimal"],
      "populate": {
        "type": "query",
        "query": "posts",
        "arguments": {
          "post_type": "layout",
          "posts_per_page": -1
        }
      }
    },
    "columns": {
      "type": "range",
      "min": 1,
      "max": 4,
      "conditions": [{
        "id": "layout",
        "operator": "!=",
        "value": "minimal"
      }]
    }
  }
}`,
        lang: 'json',
        ctaLabel: 'Conditional logic',
        ctaHref: '/docs/blocks/attributes/conditional-logic',
      },
      {
        headline: 'ES modules without a build step',
        description: (
          <>
            <p>
              Add a {code('script.js')} and it loads as an ES module. Use{' '}
              {code('import')} syntax natively. Pull packages from npm with the{' '}
              {code('npm:')} prefix and Blockstudio downloads them locally for
              production. No bundler, no config.
            </p>
            <p>
              The editor uses a CDN for instant preview. The frontend uses local
              modules for zero external dependencies. Same module, same version,
              enqueued only once.
            </p>
          </>
        ),
        code: `import { register } from
  "npm:swiper@11.0.0/element/bundle";

import "npm:swiper@11.0.0/swiper.min.css";

register();

const el = document.querySelector(".swiper");
Object.assign(el, {
  slidesPerView: 3,
  spaceBetween: 20,
  loop: true,
});
el.initialize();`,
        lang: 'js',
        ctaLabel: 'Asset processing',
        ctaHref: '/docs/blocks/assets',
      },
    ],
    details: [
      {
        icon: Blocks,
        title: '3-file workflow',
        description:
          'block.json for fields, a template for markup, an optional stylesheet. No boilerplate, no build step.',
      },
      {
        icon: Component,
        title: 'React in PHP',
        description:
          'RichText, InnerBlocks, and useBlockProps work directly in PHP, Twig, or Blade templates.',
      },
      {
        icon: Paintbrush,
        title: 'SCSS and Tailwind',
        description:
          'Use plain CSS, SCSS, or Tailwind CSS. Blockstudio compiles and minifies assets automatically.',
      },
      {
        icon: Wand2,
        title: '26 field types',
        description:
          'Text, color, image, repeater, select, code editor, and more. All configured in JSON.',
      },
      {
        icon: FileJson,
        title: 'JSON Schema',
        description:
          'Full autocomplete and validation in VS Code, PhpStorm, and any editor with JSON Schema support.',
      },
      {
        icon: Code,
        title: 'Three template languages',
        description:
          'PHP out of the box. Twig via Timber. Blade via jenssegers/blade. Same variables, same components.',
      },
      {
        icon: Package,
        title: 'npm imports',
        description:
          'Import npm packages directly in script.js. Downloaded locally for production, CDN in the editor.',
      },
      {
        icon: Zap,
        title: 'Interactivity API',
        description:
          'Enable WordPress Interactivity API with a single flag. Works in both the editor and frontend.',
      },
      {
        icon: Database,
        title: 'Post meta and options storage',
        description:
          'Store field values in post meta or site options alongside block attributes. Queryable via WP_Query.',
      },
      {
        icon: Filter,
        title: 'Conditional logic',
        description:
          'Show and hide fields based on other values with operators like ==, !=, includes, empty, and comparisons.',
      },
      {
        icon: Search,
        title: 'Dynamic populations',
        description:
          'Populate select options from posts, users, terms, functions, or external APIs.',
      },
      {
        icon: Layers,
        title: 'Variations and overrides',
        description:
          "Register block variations with different defaults. Override any existing block's attributes, assets, or templates.",
      },
    ],
    docsHref: '/docs/general/getting-started',
  },

  pages: {
    icon: FileText,
    title: 'Full pages, defined in code',
    description:
      'Create complete WordPress pages from template files. HTML maps to core blocks automatically. Blockstudio keeps the editor in sync with your codebase.',
    features: [
      {
        headline: 'Write HTML, get blocks',
        description: (
          <>
            <p>
              Standard elements like {code('<h1>')}, {code('<p>')}, and{' '}
              {code('<ul>')} map to core blocks automatically. Use the{' '}
              {code('<block>')} tag for everything else. Any block name, any
              attributes, any nesting level.
            </p>
            <p>
              Define entire page layouts in your codebase. Blockstudio parses
              your templates and creates real WordPress pages with real blocks,
              fully editable in the block editor.
            </p>
          </>
        ),
        code: `<div>
  <h1 blockEditingMode="contentOnly">About Us</h1>
  <p>We build tools for WordPress developers.</p>
  <img src="/team.jpg" alt="Our team" />
</div>

<block name="core/columns">
  <block name="core/column">
    <h2>Our Mission</h2>
    <p>Making block development fast and simple.</p>
  </block>
  <block name="core/column">
    <block name="blockstudio/features" layout="grid">
      <h2>Our Stack</h2>
      <ul>
        <li>PHP and WordPress</li>
        <li>Zero JavaScript</li>
      </ul>
    </block>
  </block>
</block>`,
        lang: 'html',
        ctaLabel: 'Page syntax reference',
        ctaHref: '/docs/pages-and-patterns/pages',
      },
      {
        headline: 'Keyed blocks preserve content',
        description: (
          <>
            <p>
              Add a {code('key')} attribute to any block. When the template
              changes and Blockstudio re-syncs, keyed blocks merge the new
              template attributes with the user&apos;s existing content. Unkeyed
              blocks get fully replaced.
            </p>
            <p>
              Build evolving templates where the structure updates across
              deployments but client content stays intact. Perfect for marketing
              sites and landing pages.
            </p>
          </>
        ),
        code: [
          {
            label: 'index.php',
            lang: 'html',
            code: `<h1 key="title">About Us</h1>

<block name="core/cover" key="hero"
  url="/hero.jpg">
  <h2>Welcome to our company</h2>
  <p>Edit this content freely.</p>
</block>

<block name="core/columns" key="team">
  <block name="core/column">
    <h3>Our Team</h3>
  </block>
</block>`,
          },
          {
            label: 'page.json',
            lang: 'json',
            code: `{
  "name": "about",
  "title": "About Us",
  "slug": "about",
  "templateLock": "contentOnly"
}






`,
          },
        ],
        ctaLabel: 'Keyed blocks',
        ctaHref: '/docs/pages-and-patterns/pages#keys',
      },
      {
        headline: 'Template locking and editing modes',
        description: (
          <>
            <p>
              Control exactly what clients can do.{' '}
              {code('"templateLock": "all"')} prevents any changes.{' '}
              {code('"contentOnly"')} locks structure but allows text editing.{' '}
              {code('"insert"')} lets users edit and reorder but not add or
              remove blocks.
            </p>
            <p>
              Override per element with the {code('blockEditingMode')}{' '}
              attribute. Lock the page but make specific sections editable, or
              disable editing on individual blocks entirely.
            </p>
          </>
        ),
        code: [
          {
            label: 'index.php',
            lang: 'html',
            code: `<h1>Welcome to Our Product</h1>
<p>The best solution for your needs.</p>

<block name="core/cover"
  blockEditingMode="disabled"
  url="/static-banner.jpg">
  <p>Promotional banner</p>
</block>

<block name="core/group"
  blockEditingMode="default">
  <h2>Customizable Section</h2>
</block>`,
          },
          {
            label: 'page.json',
            lang: 'json',
            code: `{
  "name": "landing",
  "title": "Landing Page",
  "slug": "landing",
  "templateLock": "contentOnly",
  "blockEditingMode": "contentOnly"
}





`,
          },
        ],
        ctaLabel: 'Template locking',
        ctaHref: '/docs/pages-and-patterns/pages#template-locking',
      },
    ],
    details: [
      {
        icon: RefreshCw,
        title: 'Automatic sync',
        description:
          'Pages sync to WordPress on every admin load. Change your template, the editor updates instantly.',
      },
      {
        icon: Lock,
        title: 'Template locking',
        description:
          'Lock the entire page or individual blocks. contentOnly, all, insert, or full freedom.',
      },
      {
        icon: Key,
        title: 'Keyed blocks',
        description:
          'Assign keys so user edits persist across template updates. Sync structure, keep content.',
      },
      {
        icon: GitMerge,
        title: 'Version controlled',
        description:
          'Pages live as files in your theme or plugin. Track changes in Git, deploy across environments.',
      },
      {
        icon: FileText,
        title: 'Any post type',
        description:
          'Pages default to the page post type, but you can target any custom post type in page.json.',
      },
      {
        icon: Split,
        title: 'Editing modes',
        description:
          'Set page-level defaults and override per element. Disabled, contentOnly, or default editing.',
      },
      {
        icon: Database,
        title: 'Block Bindings',
        description:
          'Connect blocks to post meta via the WordPress Block Bindings API. Content lives in meta, survives syncs.',
      },
      {
        icon: Settings,
        title: 'PHP API',
        description:
          'Programmatic access: force sync, lock/unlock pages, get post IDs, and query registered pages.',
      },
    ],
    docsHref: '/docs/pages-and-patterns/pages',
  },

  patterns: {
    icon: LayoutGrid,
    title: 'Reusable patterns from template files',
    description:
      'Define block patterns as template files. Same HTML syntax as pages, registered automatically in the block inserter.',
    features: [
      {
        headline: 'Template files, not registration code',
        description: (
          <>
            <p>
              Create a folder with a {code('pattern.json')} and a template file.
              Blockstudio registers the pattern automatically, no{' '}
              {code('register_block_pattern()')} boilerplate.
            </p>
            <p>
              Patterns are inserted as real, editable blocks. Users get a
              starting layout they can fully customize, while you maintain the
              templates in version control.
            </p>
          </>
        ),
        code: `<div>
  <h2>Pricing</h2>
  <p>Simple, transparent pricing.</p>

  <block name="core/columns">
    <block name="core/column">
      <h3>Starter</h3>
      <p>For small teams getting started.</p>
      <block name="core/button" url="/signup">
        Get Started
      </block>
    </block>
    <block name="core/column">
      <h3>Pro</h3>
      <ul>
        <li>Priority support</li>
        <li>Custom integrations</li>
      </ul>
      <block name="core/button" url="/signup?plan=pro">
        Go Pro
      </block>
    </block>
  </block>
</div>`,
        lang: 'html',
        ctaLabel: 'Pattern syntax',
        ctaHref: '/docs/pages-and-patterns/patterns',
      },
      {
        headline: 'Configure with pattern.json',
        description: (
          <>
            <p>
              Set the title, description, categories, keywords, and viewport
              width in {code('pattern.json')}. Restrict patterns to specific
              post types or block types. Control inserter visibility.
            </p>
            <p>
              Categories organize patterns in the inserter. Keywords make them
              searchable. Viewport width controls the preview size. All
              configured in a single JSON file next to your template.
            </p>
          </>
        ),
        code: `{
  "name": "pricing-table",
  "title": "Pricing Table",
  "description": "Two-column pricing layout",
  "categories": ["featured", "commerce"],
  "keywords": ["pricing", "plans", "table"],
  "viewportWidth": 1200,
  "blockTypes": ["core/group"],
  "postTypes": ["page", "landing"]
}`,
        lang: 'json',
        ctaLabel: 'Pattern configuration',
        ctaHref: '/docs/pages-and-patterns/patterns',
      },
    ],
    details: [
      {
        icon: BookOpen,
        title: 'Same syntax as pages',
        description:
          'Patterns use the same HTML parser. Standard HTML maps to core blocks, the <block> tag handles the rest.',
      },
      {
        icon: LayoutGrid,
        title: 'Auto-registered',
        description:
          'Drop a folder with a template and a pattern.json. Blockstudio registers it in the inserter automatically.',
      },
      {
        icon: Pencil,
        title: 'Fully editable',
        description:
          'Unlike pages, patterns are inserted as editable block content. Users can customize each instance.',
      },
      {
        icon: Layers,
        title: 'Categorized and searchable',
        description:
          'Add categories and keywords in pattern.json. Users find your patterns instantly in the inserter.',
      },
      {
        icon: FileCode,
        title: 'Any block, any nesting',
        description:
          'Use core blocks, custom blocks, or Blockstudio blocks. Nest as deep as you need.',
      },
      {
        icon: Target,
        title: 'Post type restrictions',
        description:
          'Limit patterns to specific post types. Show pricing patterns only on landing pages.',
      },
      {
        icon: Braces,
        title: 'Block type associations',
        description:
          'Associate patterns with specific block types. They appear as suggestions when that block is inserted.',
      },
      {
        icon: GitMerge,
        title: 'Version controlled',
        description:
          'Patterns live as files in your theme or plugin. Track changes in Git, deploy across environments.',
      },
    ],
    docsHref: '/docs/pages-and-patterns/patterns',
  },

  extensions: {
    icon: Puzzle,
    title: 'Add fields to any block',
    description:
      'Extend core blocks, third-party blocks, or your own with custom fields. Pure JSON, no templates, no render callbacks.',
    features: [
      {
        headline: 'Custom fields, zero templates',
        description: (
          <>
            <p>
              Create a JSON file, target a block with {code('name')}, and define
              your fields. The {code('set')} property maps field values directly
              to HTML attributes: classes, styles, data attributes, or anything
              else.
            </p>
            <p>
              No templates, no render callbacks. Blockstudio handles the output
              automatically using the WordPress HTML Tag Processor.
            </p>
          </>
        ),
        code: `{
  "name": "core/*",
  "blockstudio": {
    "extend": true,
    "attributes": {
      "animation": {
        "type": "select",
        "label": "Animation",
        "options": ["none", "fade", "slide"],
        "set": [{
          "attribute": "class",
          "value": "animate-{attributes.animation}"
        }]
      }
    }
  }
}`,
        lang: 'json',
        ctaLabel: 'Extension basics',
        ctaHref: '/docs/other/extensions',
      },
      {
        headline: 'Map values to HTML with set',
        description: (
          <>
            <p>
              The {code('set')} property is the core of extensions. Map field
              values to classes, inline styles, data attributes, or any HTML
              attribute using dot notation like {code('{attributes.fieldId}')}.
            </p>
            <p>
              Access nested values from fields that return objects. Apply values
              conditionally. Multiple {code('set')} entries per field let you
              output to different attributes from a single input.
            </p>
          </>
        ),
        code: `{
  "attributes": {
    "customColor": {
      "type": "color",
      "label": "Custom Color",
      "returnFormat": "both",
      "set": [
        {
          "attribute": "style",
          "value": "color: {attributes.customColor.value}"
        },
        {
          "attribute": "data-color",
          "value": "{attributes.customColor.label}"
        },
        {
          "attribute": "class",
          "value": "has-custom-color"
        }
      ]
    }
  }
}`,
        lang: 'json',
        ctaLabel: 'The set property',
        ctaHref: '/docs/other/extensions#set',
      },
      {
        headline: 'Target one block or thousands',
        description: (
          <>
            <p>
              Target a single block by name: {code('"core/paragraph"')}. Target
              multiple blocks with an array:{' '}
              {code('["core/paragraph", "core/heading"]')}. Or use wildcards
              like {code('"core/*"')} to extend every block in a namespace.
            </p>
            <p>
              Extensions live as JSON files in your blocks folder. Multiple
              extensions can target the same block. Priority controls the order
              they appear in the inspector.
            </p>
          </>
        ),
        code: [
          {
            label: 'extend-animation.json',
            lang: 'json',
            code: `{
  "name": "core/*",
  "blockstudio": {
    "extend": true,
    "priority": 10,
    "attributes": {
      "animation": {
        "type": "select",
        "options": ["none", "fade", "slide"],
        "set": [{
          "attribute": "class",
          "value": "animate-{attributes.animation}"
        }]
      }
    }
  }
}

`,
          },
          {
            label: 'extend-visibility.json',
            lang: 'json',
            code: `{
  "name": ["core/group", "core/cover"],
  "blockstudio": {
    "extend": true,
    "priority": 20,
    "group": "advanced",
    "attributes": {
      "visibility": {
        "type": "select",
        "label": "Visibility",
        "options": ["visible", "hidden-mobile"],
        "set": [{
          "attribute": "class",
          "value": "{attributes.visibility}"
        }]
      }
    }
  }
}`,
          },
        ],
        ctaLabel: 'Targeting blocks',
        ctaHref: '/docs/other/extensions',
      },
    ],
    details: [
      {
        icon: Target,
        title: 'Target any block',
        description:
          'Extend a single block, a list of blocks, or use wildcards like core/* to target entire namespaces.',
      },
      {
        icon: Paintbrush,
        title: 'The set property',
        description:
          'Map field values to classes, styles, data attributes, or any HTML attribute. Dot notation for nested values.',
      },
      {
        icon: Wand2,
        title: 'Conditional fields',
        description:
          'Show and hide fields based on other values. Same conditional logic operators as blocks.',
      },
      {
        icon: Layers,
        title: 'Full feature set',
        description:
          'All 26 field types, conditional logic, populated data sources, and the same field properties as blocks.',
      },
      {
        icon: Settings,
        title: 'Inspector positioning',
        description:
          'Place fields in the settings, styles, or advanced tab. Control exactly where your fields appear.',
      },
      {
        icon: ToggleRight,
        title: 'Priority ordering',
        description:
          'Multiple extensions can target the same block. Priority controls the order they appear in the inspector.',
      },
      {
        icon: Sparkles,
        title: 'No render callbacks',
        description:
          'Blockstudio applies set values using the WordPress HTML Tag Processor. No PHP templates needed.',
      },
      {
        icon: FileJson,
        title: 'JSON Schema',
        description:
          'Dedicated extension schema for full autocomplete and validation in your editor.',
      },
    ],
    docsHref: '/docs/other/extensions',
  },
};

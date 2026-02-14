import {
  Code,
  Database,
  GitBranch,
  SlidersHorizontal,
  FileJson,
  Share2,
  Sparkles,
  Search,
  Terminal,
  Repeat,
  List,
  Braces,
} from 'lucide-react';
import Link from 'next/link';
import { Section, SectionIcon } from './section';

const features = [
  {
    icon: Database,
    title: 'Flexible Storage',
    description:
      'Store field values in post meta, options, or both at once. Query blocks by meta values, access data via REST API.',
    href: '/docs/blocks/storage',
  },
  {
    icon: GitBranch,
    title: 'Conditional Logic',
    description:
      'Show and hide fields based on other field values. 10 comparison operators, nested conditions, and global rules for post type or user role.',
    href: '/docs/blocks/attributes/conditional-logic',
  },
  {
    icon: SlidersHorizontal,
    title: '50+ PHP & JS Hooks',
    description:
      'Filters and actions for every stage: block registration, field rendering, asset loading, and template output. Full control without forking.',
    href: '/docs/blocks/hooks/php',
  },
  {
    icon: FileJson,
    title: 'JSON Schema',
    description:
      'Every configuration file is backed by a JSON Schema. Instant autocomplete, inline docs, and validation in VS Code and JetBrains.',
    href: '/docs/blocks/schema',
  },
  {
    icon: Share2,
    title: 'Context API',
    description:
      'Share data between parent and child blocks. Define what a parent provides in JSON and access the values in any nested template.',
    href: '/docs/blocks/context',
  },
  {
    icon: Sparkles,
    title: 'AI Integration',
    description:
      'Auto-generated context files describe your entire block library. Works with Cursor, Claude, and other AI coding assistants out of the box.',
    href: '/docs/dev/ai',
  },
  {
    icon: Search,
    title: 'SEO Integration',
    description:
      'Automatic content injection for Yoast, Rank Math, and SEOPress. Field content is extracted and analyzed. No setup required.',
    href: '/docs/blocks/seo',
  },
  {
    icon: Terminal,
    title: 'Programmatic Rendering',
    description:
      'Render any block in PHP with bs_render_block(). Use your blocks as reusable components outside the editor.',
    href: '/docs/blocks/rendering',
  },
  {
    icon: Braces,
    title: 'Custom Fields',
    description:
      'Define reusable field sets as JSON files. Reference them across blocks with the custom field type. Update once, apply everywhere.',
    href: '/docs/blocks/attributes/custom-fields',
  },
  {
    icon: Repeat,
    title: 'Block Transforms',
    description:
      'Let users transform one block into another. Add enter and prefix shortcuts so blocks can be inserted by typing.',
    href: '/docs/blocks/transforms',
  },
  {
    icon: List,
    title: 'Dynamic Options',
    description:
      'Populate select, radio, and checkbox fields from post queries, taxonomies, user lists, or custom PHP functions.',
    href: '/docs/blocks/attributes/populating-options',
  },
  {
    icon: Code,
    title: 'HTML Utilities',
    description:
      'Render field values as data attributes or CSS custom properties directly on the block wrapper. One line in your template.',
    href: '/docs/blocks/attributes/html-utilities',
  },
];

export function DeveloperExperience() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Code />
        </SectionIcon>
      }
      title="Built for developers"
      description="Power features that make complex blocks simple to build and maintain."
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 sm:gap-10 pb-4">
        {features.map((feature) => (
          <Link
            key={feature.title}
            href={feature.href}
            className="group flex flex-col gap-2 text-sm/7"
          >
            <div className="flex items-start gap-3 text-fd-foreground">
              <div className="flex items-center h-[1lh]">
                <feature.icon className="h-3.5 w-3.5 text-fd-primary" />
              </div>
              <h3 className="font-semibold group-hover:text-fd-primary transition-colors">
                {feature.title}
              </h3>
            </div>
            <p className="text-fd-muted-foreground text-pretty">
              {feature.description}
            </p>
          </Link>
        ))}
      </div>
    </Section>
  );
}

import Link from "next/link";
import {
  Code,
  Database,
  GitBranch,
  SlidersHorizontal,
  FileJson,
  Share2,
  Sparkles,
  Layers,
  Shield,
  Zap,
} from "lucide-react";
import { Section, SectionIcon } from "./section";

const features = [
  {
    icon: Database,
    title: "Flexible Storage",
    description:
      "Store field values in post meta, options, or custom tables. Assign multiple storage locations to a single field for cross-context access.",
    href: "/docs/storage",
  },
  {
    icon: GitBranch,
    title: "Conditional Logic",
    description:
      "Show and hide fields based on other field values. 10 comparison operators, nested conditions, and global visibility rules.",
    href: "/docs/attributes/conditional-logic",
  },
  {
    icon: SlidersHorizontal,
    title: "50+ PHP & JS Hooks",
    description:
      "Filters and actions for every stage — block registration, field rendering, asset loading, and template output. Full control without forking.",
    href: "/docs/hooks/php",
  },
  {
    icon: FileJson,
    title: "JSON Schema Validation",
    description:
      "Every configuration file is backed by a JSON Schema. Get instant autocomplete, inline docs, and validation in VS Code and other editors.",
    href: "/docs/schema",
  },
  {
    icon: Share2,
    title: "Context API",
    description:
      "Share data between parent and child blocks without prop drilling. Define providers and consumers in JSON, access values in any template.",
    href: "/docs/context",
  },
  {
    icon: Sparkles,
    title: "AI Integration",
    description:
      "Auto-generated context files describe your entire block library. Works with Cursor, Claude, and other AI coding assistants out of the box.",
    href: "/docs/ai",
  },
  {
    icon: Layers,
    title: "Nested InnerBlocks",
    description:
      "Support multiple InnerBlocks zones, template locking, and allowed block restrictions. Compose complex layouts from simple blocks.",
    href: "/docs/blocks/inner-blocks",
  },
  {
    icon: Shield,
    title: "WordPress Native",
    description:
      "Blocks are registered through the WordPress block API. No proprietary runtime — your blocks work with any theme, plugin, or page builder.",
    href: "/docs/getting-started",
  },
  {
    icon: Zap,
    title: "Zero Config Assets",
    description:
      "SCSS compilation, ES module bundling, and automatic minification. Name your files, Blockstudio handles the rest. No webpack, no Vite.",
    href: "/docs/assets",
  },
];

export function DeveloperExperience() {
  return (
    <Section
      icon={<SectionIcon><Code /></SectionIcon>}
      title="Built for developers"
      description="Power features that make complex blocks simple to build and maintain."
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 sm:gap-10 px-6 pb-4 lg:px-10">
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

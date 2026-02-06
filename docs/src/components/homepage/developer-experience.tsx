import Link from "next/link";
import {
  Database,
  GitBranch,
  SlidersHorizontal,
  FileJson,
  Share2,
  Sparkles,
} from "lucide-react";
import { Section } from "./section";

const features = [
  {
    icon: Database,
    title: "Storage",
    description: "Post meta, options, or custom tables. Multi-storage for a single field.",
    href: "/docs/storage",
  },
  {
    icon: GitBranch,
    title: "Conditional Logic",
    description: "10 operators, nested conditions, and global visibility rules.",
    href: "/docs/attributes/conditional-logic",
  },
  {
    icon: SlidersHorizontal,
    title: "50+ Hooks",
    description: "PHP and JavaScript filters for every stage of block rendering.",
    href: "/docs/hooks/php",
  },
  {
    icon: FileJson,
    title: "JSON Schema",
    description: "Full IDE autocomplete and validation for all configuration files.",
    href: "/docs/schema",
  },
  {
    icon: Share2,
    title: "Context API",
    description: "Share data between parent and child blocks without prop drilling.",
    href: "/docs/context",
  },
  {
    icon: Sparkles,
    title: "AI Integration",
    description: "Auto-generated context files for AI coding assistants.",
    href: "/docs/ai",
  },
];

export function DeveloperExperience() {
  return (
    <Section
      title="Built for developers"
      description="Power features that make complex blocks simple."
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border-t">
        {features.map((feature) => (
          <Link
            key={feature.title}
            href={feature.href}
            className="flex flex-col gap-y-2 items-start py-8 px-6 transition-colors hover:bg-fd-secondary/20 border-b sm:border-r sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r lg:[&:nth-child(3n)]:border-r-0 lg:[&:nth-last-child(-n+3)]:border-b-0 sm:[&:nth-last-child(-n+2)]:border-b-0 lg:[&:nth-last-child(-n+2)]:border-b sm:[&:last-child]:border-b-0"
          >
            <div className="bg-fd-primary/10 p-2 rounded-lg mb-1">
              <feature.icon className="h-5 w-5 text-fd-primary" />
            </div>
            <h3 className="text-base font-medium text-fd-foreground">
              {feature.title}
            </h3>
            <p className="text-sm text-fd-muted-foreground">
              {feature.description}
            </p>
          </Link>
        ))}
      </div>
    </Section>
  );
}

'use client';

import Link from 'fumadocs-core/link';
import {
  NavigationMenuContent,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuTrigger,
} from 'fumadocs-ui/components/ui/navigation-menu';
import {
  Blocks,
  ChevronDown,
  FileText,
  LayoutGrid,
  Puzzle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const items: {
  icon: LucideIcon;
  title: string;
  description: string;
  href: string;
}[] = [
  {
    icon: Blocks,
    title: 'Blocks',
    description:
      'Custom blocks with JSON and PHP templates. 26 field types, zero JavaScript.',
    href: '/features/blocks',
  },
  {
    icon: FileText,
    title: 'Pages',
    description:
      'Full WordPress pages from template files, synced automatically to the editor.',
    href: '/features/pages',
  },
  {
    icon: LayoutGrid,
    title: 'Patterns',
    description:
      'Reusable block patterns as template files, registered in the inserter.',
    href: '/features/patterns',
  },
  {
    icon: Puzzle,
    title: 'Extensions',
    description:
      'Add custom fields to any block with a single JSON file. No templates needed.',
    href: '/features/extensions',
  },
];

export function FeaturesMenu() {
  return (
    <NavigationMenuItem>
      <NavigationMenuTrigger className="inline-flex items-center gap-1 rounded-md p-2 text-sm text-fd-muted-foreground transition-colors hover:text-fd-accent-foreground">
        Features
        <ChevronDown className="size-3 transition-transform duration-200 group-data-[state=open]:rotate-180" />
      </NavigationMenuTrigger>
      <NavigationMenuContent className="grid grid-cols-2 gap-2 p-4 md:grid-cols-4">
        {items.map((item) => (
          <NavigationMenuLink key={item.href} asChild>
            <Link
              href={item.href}
              className="flex flex-col gap-3 rounded-lg p-3 transition-colors hover:bg-fd-accent/80 hover:text-fd-accent-foreground"
            >
              <div className="flex size-8 items-center justify-center rounded-md border bg-fd-secondary">
                <item.icon className="size-4 text-fd-primary" />
              </div>
              <div>
                <p className="text-sm font-medium">{item.title}</p>
                <p className="mt-1 text-sm leading-relaxed text-fd-muted-foreground">
                  {item.description}
                </p>
              </div>
            </Link>
          </NavigationMenuLink>
        ))}
      </NavigationMenuContent>
    </NavigationMenuItem>
  );
}

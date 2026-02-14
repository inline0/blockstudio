import type { ReactNode } from 'react';
import { FolderTree, Database, Braces, type LucideIcon } from 'lucide-react';
import { Section } from '../homepage/section';

function IconBox({ children }: { children: ReactNode }) {
  return (
    <div className="flex items-center justify-center rounded-lg border bg-fd-secondary text-fd-primary size-10 [&_svg]:size-5">
      {children}
    </div>
  );
}

function InfoCard({
  icon: Icon,
  title,
  description,
}: {
  icon: LucideIcon;
  title: string;
  description: string;
}) {
  return (
    <div className="flex flex-col gap-3">
      <IconBox>
        <Icon />
      </IconBox>
      <h3 className="text-base font-semibold text-fd-foreground">{title}</h3>
      <p className="text-sm leading-relaxed text-fd-muted-foreground text-pretty">
        {description}
      </p>
    </div>
  );
}

const items = [
  {
    icon: FolderTree,
    title: 'File-based everything',
    description:
      'Blocks, pages, patterns, and extensions are directories with JSON configs and template files. AI agents read and write files natively.',
  },
  {
    icon: Database,
    title: 'Zero database config',
    description:
      'No admin panels to click through. Every setting is a JSON key. Agents can scaffold a complete block library without touching a browser.',
  },
  {
    icon: Braces,
    title: 'Predictable structure',
    description:
      'Every block follows the same convention: block.json for config, a template for markup, an optional stylesheet. Agents learn the pattern once.',
  },
];

export function WhyFilesystem() {
  return (
    <Section>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-10 sm:gap-8">
        {items.map((item) => (
          <InfoCard key={item.title} {...item} />
        ))}
      </div>
    </Section>
  );
}

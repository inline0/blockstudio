import {
  Code,
  FormInput,
  LayoutGrid,
  FolderTree,
  Monitor,
  Paintbrush,
  MousePointerClick,
  FileJson,
  Wrench,
} from 'lucide-react';
import { Section, SectionIcon } from '../homepage/section';

const cards = [
  {
    icon: Code,
    title: 'Two files per block',
    description:
      "A JSON config and a template. That's the entire block. Small surface area means agents get it right on the first try.",
  },
  {
    icon: FormInput,
    title: '30+ field types',
    description:
      'Text, image, repeater, color, date, relationship, and more. All defined in JSON, all available as template variables.',
  },
  {
    icon: LayoutGrid,
    title: 'JSON Schema validation',
    description:
      'Every config file has a JSON Schema. Agents are great at following schema rules, so blocks come out valid on the first try.',
  },
  {
    icon: FolderTree,
    title: 'Auto-registration',
    description:
      'Drop files in a directory and blocks register themselves. No manual registration, no config files to update.',
  },
  {
    icon: Monitor,
    title: 'Canvas live preview',
    description:
      'Blocks render in a live preview canvas inside the editor. See the result the moment files are saved.',
  },
  {
    icon: Paintbrush,
    title: 'Tailwind built-in',
    description:
      'Tailwind v4 compiles per-block with zero config. Agents write utility classes and styles just work.',
  },
  {
    icon: MousePointerClick,
    title: 'Element Grabber',
    description:
      'Cmd+click any element on the frontend to copy its HTML and file path. Paste it into your AI chat as instant context.',
  },
  {
    icon: FileJson,
    title: 'HTML to blocks',
    description:
      'Pages and patterns are plain HTML files. Blockstudio converts them to native WordPress blocks automatically.',
  },
];

export function DetailCards() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Wrench />
        </SectionIcon>
      }
      title="Built for the AI workflow"
      description="Blocks, pages, patterns, settings. Everything is a file an agent can read, write, and modify."
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
        {cards.map((card) => (
          <div key={card.title} className="flex flex-col gap-2 text-sm/7">
            <div className="flex items-start gap-3 text-fd-foreground">
              <div className="flex items-center h-[1lh]">
                <card.icon className="h-3.5 w-3.5 text-fd-primary" />
              </div>
              <h3 className="font-semibold">{card.title}</h3>
            </div>
            <p className="text-fd-muted-foreground text-pretty">
              {card.description}
            </p>
          </div>
        ))}
      </div>
    </Section>
  );
}

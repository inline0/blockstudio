import { Puzzle, Wand2, Target, Paintbrush, Layers } from 'lucide-react';
import { Button } from 'onedocs';
import { CodeCard } from './code-card';
import { Feature } from './feature';
import { Section, SectionIcon } from './section';

const extensionCode = `{
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
}`;

const details = [
  {
    icon: Target,
    title: 'Target any block',
    description:
      'Extend a single block, a list of blocks, or use wildcards like core/* to target entire namespaces at once.',
  },
  {
    icon: Paintbrush,
    title: 'The set property',
    description:
      'Map field values directly to HTML. Add classes, inline styles, data attributes, or any HTML attribute. No template needed.',
  },
  {
    icon: Wand2,
    title: 'Conditional fields',
    description:
      'Show and hide fields based on other values. In the example, Duration only appears when Animation is set.',
  },
  {
    icon: Layers,
    title: 'Full feature set',
    description:
      'Extensions support all 26 field types, conditional logic, populated data sources, and the same field properties as blocks.',
  },
];

export async function Extensions() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Puzzle />
        </SectionIcon>
      }
      title="Add fields to any block"
      description="Extend core blocks, third-party blocks, or your own with custom fields. Pure JSON, no templates, no code."
    >
      <div className="flex flex-col gap-10">
        <Feature
          headline="Custom fields, zero templates"
          description={
            <>
              <p>
                Create a JSON file, target a block with{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  name
                </code>
                , and define your fields. The{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  set
                </code>{' '}
                property maps field values directly to HTML attributes: classes,
                styles, data attributes, or anything else.
              </p>
              <p>
                No templates, no render callbacks. Blockstudio handles the
                output automatically using the WordPress HTML Tag Processor.
              </p>
            </>
          }
          cta={
            <Button href="/docs/other/extensions" className="w-max">
              Learn more about extensions &rarr;
            </Button>
          }
          demo={<CodeCard code={extensionCode} lang="json" />}
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
          {details.map((detail) => (
            <div key={detail.title} className="flex flex-col gap-2 text-sm/7">
              <div className="flex items-start gap-3 text-fd-foreground">
                <div className="flex items-center h-[1lh]">
                  <detail.icon className="h-3.5 w-3.5 text-fd-primary" />
                </div>
                <h3 className="font-semibold">{detail.title}</h3>
              </div>
              <p className="text-fd-muted-foreground text-pretty">
                {detail.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </Section>
  );
}

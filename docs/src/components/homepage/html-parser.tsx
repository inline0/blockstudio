import { Code } from 'lucide-react';
import { Button } from 'onedocs';
import { CodeCard } from './code-card';
import { Feature } from './feature';
import { Section, SectionIcon } from './section';

const mappingCode = `add_filter(
  'blockstudio/parser/element_mapping',
  function ( $mapping ) {
    $mapping['h1']  = 'custom/heading';
    $mapping['h2']  = 'custom/heading';
    $mapping['p']   = 'custom/paragraph';
    $mapping['img'] = 'custom/image';

    return $mapping;
  }
);`;

export async function HtmlParser() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Code />
        </SectionIcon>
      }
      title="Customizable element mapping"
      description="Override which block any HTML element maps to. Point standard tags like <h1>, <p>, and <img> to your own block types."
    >
      <div>
        <Feature
          headline="Remap HTML to custom blocks"
          description={
            <p>
              By default, standard HTML elements map to core WordPress blocks.
              Use the{' '}
              <code className="text-fd-foreground font-mono text-sm">
                element_mapping
              </code>{' '}
              filter to point any element to a different block type. Every{' '}
              <code className="text-fd-foreground font-mono text-sm">
                {'<h1>'}
              </code>{' '}
              in your templates will produce your custom block instead of{' '}
              <code className="text-fd-foreground font-mono text-sm">
                core/heading
              </code>
              .
            </p>
          }
          cta={
            <Button
              href="/docs/pages-and-patterns#element-mapping"
              className="w-max"
            >
              Learn more &rarr;
            </Button>
          }
          demo={<CodeCard code={mappingCode} lang="php" />}
        />
      </div>
    </Section>
  );
}

import { Braces } from 'lucide-react';
import { Button } from 'onedocs';
import { CodeCard } from '../homepage/code-card';
import { Feature } from '../homepage/feature';
import { Section, SectionIcon } from '../homepage/section';

const llmPreview = `# Blockstudio
Context for LLM coding assistants.

## Blocks
Blocks are defined by a directory containing
a block.json and a template file (PHP, Twig,
or Blade). Fields go in the "blockstudio" key.

## 30+ Field Types
text, textarea, number, email, url, password,
range, select, toggle, checkbox, radio, image,
gallery, file, color, date, link, richtext,
code, repeater, group, message, tab, accordion,
relationship, taxonomy, user, post_object ...

## Schemas
\`\`\`json
{"definitions":{"Attribute":{"anyOf":[...]}}}
\`\`\`

## Template Variables
All field values are available as top-level
variables: $text, $image, $repeater, etc.

~48,000 tokens, fits in any modern context.`;

export function LlmContext() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Braces />
        </SectionIcon>
      }
      title="48k tokens of structured context"
      description="A single file with the complete documentation and all JSON schemas, built for LLM coding assistants."
    >
      <Feature
        headline="Everything an agent needs to know"
        description={
          <>
            <p>
              Blockstudio ships a pre-built{' '}
              <code className="text-fd-foreground font-mono text-sm">
                blockstudio-llm.txt
              </code>{' '}
              file containing the complete documentation, every field type,
              template pattern, and configuration option. Schemas are
              deduplicated to keep the token count low.
            </p>
            <p>
              Point your AI tool to the URL and every prompt has full context
              about Blockstudio. Works with Claude Code, Cursor, GitHub Copilot,
              and any tool that accepts a URL or file.
            </p>
          </>
        }
        cta={
          <Button href="/docs/dev/ai" className="w-max">
            AI integration docs &rarr;
          </Button>
        }
        demo={<CodeCard code={llmPreview} lang="markdown" />}
      />
    </Section>
  );
}

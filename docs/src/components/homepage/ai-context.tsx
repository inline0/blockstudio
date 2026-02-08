import { Bot, FileText, Braces, Zap, Wrench } from "lucide-react";
import { Button } from "onedocs";
import { Section, SectionIcon } from "./section";
import { Feature } from "./feature";
import { CodeCard } from "./code-card";

const preview = `# Blockstudio
Context about the Blockstudio WordPress block framework
for LLM coding assistants.

## Documentation

### Getting Started
Create a folder with a block.json and a template file...

### Blocks
Blocks are the fundamental building unit...

### Attributes
Fields are defined in the blockstudio key of block.json...

## Schemas

### Block Schema (blockstudio key from block.json)
\`\`\`json
{"definitions":{"Attribute":{"anyOf":[...]}},...}
\`\`\`

### Settings Schema (blockstudio.json)
\`\`\`json
{"type":"object","properties":{"tailwind":{...},...}}
\`\`\``;

const details = [
  {
    icon: FileText,
    title: "Full documentation",
    description:
      "All docs pages compiled into a single file, with code examples preserved and formatting stripped.",
  },
  {
    icon: Braces,
    title: "JSON schemas",
    description:
      "Block, settings, page, and extension schemas with deduplication of shared definitions.",
  },
  {
    icon: Zap,
    title: "Optimized for tokens",
    description:
      "~48k tokens. Fits comfortably in modern context windows alongside your own code.",
  },
  {
    icon: Wrench,
    title: "Works everywhere",
    description:
      "Cursor, Claude Code, GitHub Copilot, or any LLM tool that accepts a URL or text file.",
  },
];

export async function AiContext() {
  return (
    <Section
      icon={<SectionIcon><Bot /></SectionIcon>}
      title="AI-ready documentation"
      description="A static context file with the full documentation and schemas, built for LLM coding assistants."
    >
      <div className="flex flex-col gap-10">
        <Feature
          headline="48k tokens of structured context"
          description={
            <>
              <p>
                Blockstudio ships a pre-built{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  blockstudio-llm.txt
                </code>{" "}
                file containing the complete documentation and all JSON schemas.
                The file is assembled at build time, with repeated definitions
                deduplicated to keep the token count low.
              </p>
              <p>
                Enable the setting, point your AI tool to the URL, and your
                coding assistant gets full context about every field type,
                template pattern, and configuration option in Blockstudio.
              </p>
            </>
          }
          cta={
            <Button href="/docs/ai" className="w-max">
              Learn more about AI integration &rarr;
            </Button>
          }
          demo={
            <CodeCard code={preview} lang="markdown" />
          }
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

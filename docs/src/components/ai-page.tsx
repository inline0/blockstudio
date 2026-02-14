import {
  FolderTree,
  FileJson,
  FormInput,
  Code,
  Monitor,
  MousePointerClick,
  Paintbrush,
  LayoutGrid,
  Braces,
  Database,
} from "lucide-react";
import { CTASection, Button } from "onedocs";
import { Section, SectionIcon } from "./homepage/section";
import { Feature } from "./homepage/feature";
import { CodeCard } from "./homepage/code-card";

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

~48,000 tokens — fits in any modern context.`;

const whyFilesystem = [
  {
    icon: FolderTree,
    title: "File-based everything",
    description:
      "Blocks, pages, patterns, and extensions are directories with JSON configs and template files. AI agents read and write files natively.",
  },
  {
    icon: Database,
    title: "Zero database config",
    description:
      "No admin panels to click through. Every setting is a JSON key. Agents can scaffold a complete block library without touching a browser.",
  },
  {
    icon: Braces,
    title: "Predictable structure",
    description:
      "Every block follows the same convention: block.json for config, a template for markup, an optional stylesheet. Agents learn the pattern once.",
  },
];

const detailCards = [
  {
    icon: Code,
    title: "Two files per block",
    description:
      "A JSON config and a template. That's the entire block. Small surface area means agents get it right on the first try.",
  },
  {
    icon: FormInput,
    title: "30+ field types",
    description:
      "Text, image, repeater, color, date, relationship, and more. All defined in JSON, all available as template variables.",
  },
  {
    icon: LayoutGrid,
    title: "Three template engines",
    description:
      "PHP, Twig, or Blade. Agents pick the syntax that fits the project. No lock-in.",
  },
  {
    icon: FolderTree,
    title: "Auto-registration",
    description:
      "Drop files in a directory and blocks register themselves. No manual registration, no config files to update.",
  },
  {
    icon: Monitor,
    title: "Canvas live preview",
    description:
      "Blocks render in a live preview canvas inside the editor. See the result the moment files are saved.",
  },
  {
    icon: Paintbrush,
    title: "Tailwind built-in",
    description:
      "Tailwind v4 compiles per-block with zero config. Agents write utility classes and styles just work.",
  },
  {
    icon: MousePointerClick,
    title: "Element Grabber",
    description:
      "Cmd+click any element on the frontend to copy its HTML and file path. Paste it into your AI chat as instant context.",
  },
  {
    icon: FileJson,
    title: "HTML to blocks",
    description:
      "Pages and patterns are plain HTML files. Blockstudio converts them to native WordPress blocks automatically.",
  },
];

function TerminalGraphic() {
  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
      <div className="rounded-2xl bg-fd-secondary/50 p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
        <div className="flex items-center gap-2 mb-4">
          <div className="size-3 rounded-full bg-fd-muted-foreground/20" />
          <div className="size-3 rounded-full bg-fd-muted-foreground/20" />
          <div className="size-3 rounded-full bg-fd-muted-foreground/20" />
        </div>
        <div className="text-fd-muted-foreground">~/starter-theme</div>
        <div className="mt-1">
          <span className="text-emerald-400">{"❯"}</span>{" "}
          <span className="text-fd-foreground">
            Create a pricing block with three tiers,
          </span>
        </div>
        <div className="text-fd-foreground pl-4">
          a toggle for monthly/annual billing, and
        </div>
        <div className="text-fd-foreground pl-4">
          a CTA button for each tier.
        </div>
        <div className="mt-4 flex flex-col gap-1.5">
          <div>
            <span className="text-emerald-400">{"✓"}</span>{" "}
            <span className="text-fd-muted-foreground">Created</span>{" "}
            <span className="text-fd-foreground">blockstudio/pricing/block.json</span>
          </div>
          <div>
            <span className="text-emerald-400">{"✓"}</span>{" "}
            <span className="text-fd-muted-foreground">Created</span>{" "}
            <span className="text-fd-foreground">blockstudio/pricing/index.php</span>
          </div>
          <div>
            <span className="text-emerald-400">{"✓"}</span>{" "}
            <span className="text-fd-muted-foreground">Created</span>{" "}
            <span className="text-fd-foreground">blockstudio/pricing/style.css</span>
          </div>
        </div>

        <div className="mt-6 pt-5 border-t border-fd-border/50">
          <span className="text-emerald-400">{"❯"}</span>{" "}
          <span className="text-fd-foreground">
            Now create a pricing page with a hero
          </span>
          <div className="text-fd-foreground pl-4">
            and FAQ section.
          </div>
          <div className="mt-4 flex flex-col gap-1.5">
            <div>
              <span className="text-emerald-400">{"✓"}</span>{" "}
              <span className="text-fd-muted-foreground">Created</span>{" "}
              <span className="text-fd-foreground">pages/pricing/page.json</span>
            </div>
            <div>
              <span className="text-emerald-400">{"✓"}</span>{" "}
              <span className="text-fd-muted-foreground">Created</span>{" "}
              <span className="text-fd-foreground">pages/pricing/pricing.php</span>
            </div>
          </div>
          <div className="mt-4 text-fd-muted-foreground">5 files written</div>
        </div>
      </div>

      <div className="rounded-2xl bg-fd-secondary/50 p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
        <div className="flex items-center gap-2 mb-4">
          <span className="text-fd-muted-foreground text-xs font-sans font-medium tracking-wide uppercase">Files</span>
        </div>
        <div className="flex flex-col gap-0.5 text-fd-muted-foreground">
          <div>blockstudio/</div>
          <div className="pl-4">
            {"├── "}
            <span className="text-fd-foreground">pricing/</span>
            <span className="ml-2 rounded bg-emerald-400/15 px-1.5 py-0.5 text-[11px] font-sans font-medium text-emerald-400">NEW</span>
          </div>
          <div className="pl-8">
            {"├── "}
            <span className="text-fd-foreground">block.json</span>
          </div>
          <div className="pl-8">
            {"├── "}
            <span className="text-fd-foreground">index.php</span>
          </div>
          <div className="pl-8">
            {"└── "}
            <span className="text-fd-foreground">style.css</span>
          </div>
          <div className="pl-4">
            {"└── "}hero/

          </div>

          <div className="mt-4">pages/</div>
          <div className="pl-4">
            {"├── "}
            <span className="text-fd-foreground">pricing/</span>
            <span className="ml-2 rounded bg-emerald-400/15 px-1.5 py-0.5 text-[11px] font-sans font-medium text-emerald-400">NEW</span>
          </div>
          <div className="pl-8">
            {"├── "}
            <span className="text-fd-foreground">page.json</span>
          </div>
          <div className="pl-8">
            {"└── "}
            <span className="text-fd-foreground">pricing.php</span>
          </div>
          <div className="pl-4">
            {"└── "}about/
          </div>
        </div>
      </div>
    </div>
  );
}

export async function AiPage() {
  return (
    <div className="flex flex-col">
      {/* Hero */}
      <section className="px-6 pt-16 pb-12 sm:pt-24 sm:pb-16 lg:px-16 xl:px-20">
        <div className="mx-auto max-w-4xl text-center flex flex-col items-center">
          <span className="inline-flex items-center gap-1.5 rounded-full border border-fd-border bg-fd-secondary/50 pl-1 pr-1 py-1 text-xs text-fd-muted-foreground mb-6">
            <span className="rounded-full bg-fd-primary px-1.5 py-0.5 text-xs font-medium text-fd-primary-foreground">
              Build with AI
            </span>
          </span>
          <h1 className="text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl lg:text-6xl text-balance">
            AI-native block development
          </h1>
          <p className="mt-4 max-w-2xl text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance">
            Blockstudio's file-based architecture means AI coding agents can scaffold blocks, pages, and patterns from a single prompt. No GUI, no database, just files.
          </p>
        </div>

        <div className="mt-12 max-w-4xl mx-auto">
          <TerminalGraphic />
        </div>
      </section>

      {/* Why filesystem */}
      <section className="border-t px-6 py-16 sm:py-20 lg:px-16 xl:px-20">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-10 sm:gap-8">
          {whyFilesystem.map((item) => (
            <div key={item.title} className="flex flex-col gap-3">
              <div className="flex size-10 items-center justify-center rounded-lg border bg-fd-secondary text-fd-primary [&_svg]:size-5">
                <item.icon />
              </div>
              <h3 className="text-base font-semibold text-fd-foreground">
                {item.title}
              </h3>
              <p className="text-sm leading-relaxed text-fd-muted-foreground text-pretty">
                {item.description}
              </p>
            </div>
          ))}
        </div>
      </section>

      {/* Context file */}
      <Section
        border
        icon={<SectionIcon><Braces /></SectionIcon>}
        title="48k tokens of structured context"
        description="A single file with the complete documentation and all JSON schemas, built for LLM coding assistants."
      >
        <Feature
          headline="Everything an agent needs to know"
          description={
            <>
              <p>
                Blockstudio ships a pre-built{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  blockstudio-llm.txt
                </code>{" "}
                file containing the complete documentation, every field type,
                template pattern, and configuration option. Schemas are
                deduplicated to keep the token count low.
              </p>
              <p>
                Point your AI tool to the URL and every prompt has full context
                about Blockstudio. Works with Claude Code, Cursor, GitHub
                Copilot, and any tool that accepts a URL or file.
              </p>
            </>
          }
          cta={
            <Button href="/docs/dev/ai" className="w-max">
              AI integration docs &rarr;
            </Button>
          }
          demo={
            <CodeCard code={llmPreview} lang="markdown" />
          }
        />
      </Section>

      {/* Detail grid */}
      <section className="border-t px-6 py-16 sm:py-20 lg:px-16 xl:px-20">
        <div className="mb-10">
          <h2 className="text-2xl font-semibold tracking-tight text-fd-foreground sm:text-3xl">
            Built for the AI workflow
          </h2>
          <p className="mt-3 text-fd-muted-foreground max-w-2xl text-balance">
            Every feature in Blockstudio is a file an agent can read, write, or
            modify. No clicks required.
          </p>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
          {detailCards.map((card) => (
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
      </section>

      {/* CTA */}
      <div className="border-t">
        <CTASection
          title="Ready to build with AI?"
          description="Create your first block in under a minute."
          cta={{ label: "Get Started", href: "/docs/general/getting-started" }}
        />
      </div>
    </div>
  );
}

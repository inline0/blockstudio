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
  ArrowDownRight,
  ShieldCheck,
  Zap,
  Gauge,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import type { ReactNode } from "react";
import { Button } from "onedocs";
import { PlusSection } from "./plus-section";
import { Section, SectionIcon } from "./homepage/section";
import { Feature } from "./homepage/feature";
import { CodeCard } from "./homepage/code-card";
import { ExpandableCode } from "./expandable-code";

function IconBox({
  children,
  size = "md",
  muted,
}: {
  children: ReactNode;
  size?: "sm" | "md";
  muted?: boolean;
}) {
  const sizeClass = size === "sm" ? "size-8 [&_svg]:size-4" : "size-10 [&_svg]:size-5";
  const colorClass = muted ? "text-fd-muted-foreground" : "text-fd-primary";
  return (
    <div className={`flex items-center justify-center rounded-lg border bg-fd-secondary ${colorClass} ${sizeClass}`}>
      {children}
    </div>
  );
}

function Panel({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div className={`rounded-2xl bg-fd-secondary/50${className ? ` ${className}` : ""}`}>
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

function ComparisonHeader({
  title,
  subtitle,
  tokens,
  highlight,
}: {
  title: string;
  subtitle: string;
  tokens: string;
  highlight?: boolean;
}) {
  return (
    <div className="flex items-center justify-between px-1">
      <div className="flex items-center gap-3">
        <IconBox size="sm" muted={!highlight}>
          <Code />
        </IconBox>
        <div>
          <h3 className="text-sm font-semibold text-fd-foreground">{title}</h3>
          <p className="text-xs text-fd-muted-foreground">{subtitle}</p>
        </div>
      </div>
      <span
        className={`rounded-full px-2.5 py-1 text-xs font-medium ${
          highlight
            ? "bg-emerald-400/15 text-emerald-400"
            : "bg-fd-muted-foreground/15 text-fd-muted-foreground"
        }`}
      >
        {tokens}
      </span>
    </div>
  );
}

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

const blockstudioPageCode = `<h1>Build Websites That Convert</h1>
<p>The all-in-one platform for creating
beautiful, high-performance landing pages.</p>

<block name="core/columns">
  <block name="core/column">
    <h3>Lightning Fast</h3>
    <p>Pages load in under one second.</p>
  </block>
  <block name="core/column">
    <h3>Easy to Use</h3>
    <p>No technical skills required.</p>
  </block>
  <block name="core/column">
    <h3>SEO Optimized</h3>
    <p>Built-in best practices from day one.</p>
  </block>
</block>

<img src="/dashboard.jpg" alt="Dashboard" />

<ul>
  <li>Drag-and-drop editor</li>
  <li>A/B testing built in</li>
  <li>Analytics dashboard</li>
</ul>`;

const wordpressPageCode = `<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Build Websites
That Convert</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The all-in-one platform for creating
beautiful, high-performance landing pages.</p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Lightning Fast</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Pages load in under one second.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Easy to Use</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>No technical skills required.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">SEO Optimized</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Built-in best practices from day one.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:image {"url":"/dashboard.jpg",
"alt":"Dashboard"} -->
<figure class="wp-block-image">
<img src="/dashboard.jpg" alt="Dashboard"/>
</figure>
<!-- /wp:image -->

<!-- wp:list {"ordered":false} -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li>Drag-and-drop editor</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>A/B testing built in</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Analytics dashboard</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->`;

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

const agents = [
  {
    name: "Claude Code",
    viewBox: "0 0 24 24",
    paths: ["M17.3041 3.541h-3.6718l6.696 16.918H24Zm-10.6082 0L0 20.459h3.7442l1.3693-3.5527h7.0052l1.3693 3.5528h3.7442L10.5363 3.5409Zm-.3712 10.2232 2.2914-5.9456 2.2914 5.9456Z"],
  },
  {
    name: "Codex CLI",
    viewBox: "0 0 24 24",
    paths: ["M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.872zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z"],
  },
  {
    name: "Gemini CLI",
    viewBox: "0 0 24 24",
    paths: ["M11.04 19.32Q12 21.51 12 24q0-2.49.93-4.68.96-2.19 2.58-3.81t3.81-2.55Q21.51 12 24 12q-2.49 0-4.68-.93a12.3 12.3 0 0 1-3.81-2.58 12.3 12.3 0 0 1-2.58-3.81Q12 2.49 12 0q0 2.49-.96 4.68-.93 2.19-2.55 3.81a12.3 12.3 0 0 1-3.81 2.58Q2.49 12 0 12q2.49 0 4.68.96 2.19.93 3.81 2.55t2.55 3.81"],
  },
  {
    name: "Cursor",
    viewBox: "0 0 24 24",
    paths: ["M11.503.131 1.891 5.678a.84.84 0 0 0-.42.726v11.188c0 .3.162.575.42.724l9.609 5.55a1 1 0 0 0 .998 0l9.61-5.55a.84.84 0 0 0 .42-.724V6.404a.84.84 0 0 0-.42-.726L12.497.131a1.01 1.01 0 0 0-.996 0M2.657 6.338h18.55c.263 0 .43.287.297.515L12.23 22.918c-.062.107-.229.064-.229-.06V12.335a.59.59 0 0 0-.295-.51l-9.11-5.257c-.109-.063-.064-.23.061-.23"],
  },
  {
    name: "Windsurf",
    viewBox: "0 0 24 24",
    paths: ["M23.55 5.067c-1.2038-.002-2.1806.973-2.1806 2.1765v4.8676c0 .972-.8035 1.7594-1.7597 1.7594-.568 0-1.1352-.286-1.4718-.7659l-4.9713-7.1003c-.4125-.5896-1.0837-.941-1.8103-.941-1.1334 0-2.1533.9635-2.1533 2.153v4.8957c0 .972-.7969 1.7594-1.7596 1.7594-.57 0-1.1363-.286-1.4728-.7658L.4076 5.1598C.2822 4.9798 0 5.0688 0 5.2882v4.2452c0 .2147.0656.4228.1884.599l5.4748 7.8183c.3234.462.8006.8052 1.3509.9298 1.3771.313 2.6446-.747 2.6446-2.0977v-4.893c0-.972.7875-1.7593 1.7596-1.7593h.003a1.798 1.798 0 0 1 1.4718.7658l4.9723 7.0994c.4135.5905 1.05.941 1.8093.941 1.1587 0 2.1515-.9645 2.1515-2.153v-4.8948c0-.972.7875-1.7594 1.7596-1.7594h.194a.22.22 0 0 0 .2204-.2202v-4.622a.22.22 0 0 0-.2203-.2203Z"],
  },
  {
    name: "Amp",
    viewBox: "0 0 28 28",
    paths: [
      "M13.9197 13.61L17.3816 26.566L14.242 27.4049L11.2645 16.2643L0.119926 13.2906L0.957817 10.15L13.9197 13.61Z",
      "M13.7391 16.0892L4.88169 24.9056L2.58872 22.6019L11.4461 13.7865L13.7391 16.0892Z",
      "M18.9386 8.58315L22.4005 21.5392L19.2609 22.3781L16.2833 11.2374L5.13879 8.26381L5.97668 5.12318L18.9386 8.58315Z",
      "M23.9803 3.55632L27.4422 16.5124L24.3025 17.3512L21.325 6.21062L10.1805 3.23698L11.0183 0.0963593L23.9803 3.55632Z",
    ],
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
    title: "JSON Schema validation",
    description:
      "Every config file has a JSON Schema. Agents are great at following schema rules, so blocks come out valid on the first try.",
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
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <Panel className="p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
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
      </Panel>

      <Panel className="p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
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
            {"├── "}hero/
          </div>
          <div className="pl-8">
            {"├── "}block.json
          </div>
          <div className="pl-8">
            {"└── "}index.php
          </div>
          <div className="pl-4">
            {"└── "}testimonials/
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
            {"├── "}about/
          </div>
          <div className="pl-4">
            {"└── "}contact/
          </div>
        </div>
      </Panel>
    </div>
  );
}

export async function AiPage() {
  return (
    <div className="flex flex-col">
      <section className="px-6 pt-16 pb-12 sm:pt-24 sm:pb-16 lg:px-16 xl:px-20">
        <div className="grid grid-cols-1 xl:grid-cols-[3fr_7fr] gap-12 xl:gap-16 xl:items-start">
          <div>
            <span className="inline-flex items-center gap-1.5 rounded-full border border-fd-border bg-fd-secondary/50 pl-1 pr-1 py-1 text-xs text-fd-muted-foreground mb-6">
              <span className="rounded-full bg-fd-primary/5 px-1.5 py-0.5 text-xs font-medium text-fd-primary">
                Build with AI
              </span>
            </span>
            <h1 className="text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl text-balance">
              AI-native block development
            </h1>
            <p className="mt-4 text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance">
              Blockstudio&apos;s file-based architecture means AI coding agents can scaffold blocks, pages, and patterns from a single prompt. No GUI, no database, just files.
            </p>
          </div>
          <TerminalGraphic />
        </div>
      </section>

      <Section>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-10 sm:gap-8">
          {whyFilesystem.map((item) => (
            <InfoCard key={item.title} {...item} />
          ))}
        </div>
      </Section>

      <Section
        title="Works with every AI coding tool"
        description="Any agent that can read and write files works with Blockstudio out of the box."
      >
        <div className="grid grid-cols-3 sm:grid-cols-6 gap-4">
          {agents.map((agent) => (
            <div key={agent.name} className="flex flex-col items-center gap-3 rounded-xl border bg-fd-secondary/30 p-5 transition-colors hover:bg-fd-secondary/50">
              <svg viewBox={agent.viewBox} className="size-6 fill-fd-muted-foreground" aria-hidden="true">
                {agent.paths.map((d, i) => (
                  <path key={i} d={d} />
                ))}
              </svg>
              <span className="text-xs text-fd-muted-foreground">{agent.name}</span>
            </div>
          ))}
        </div>
      </Section>

      <Section
        icon={<SectionIcon><Gauge /></SectionIcon>}
        title="3x fewer tokens per page"
        description="WordPress stores blocks as serialized comments with duplicated markup and class names. Blockstudio pages are plain HTML. Agents generate less, get it right more often."
      >
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
          <div className="flex flex-col gap-3">
            <ComparisonHeader title="Blockstudio" subtitle="Plain HTML template" tokens="~370 tokens" highlight />
            <Panel className="overflow-hidden">
              <CodeCard code={blockstudioPageCode} lang="html" />
            </Panel>
          </div>

          <div className="flex flex-col gap-3">
            <ComparisonHeader title="WordPress" subtitle="Serialized block markup" tokens="~1,100 tokens" />
            <ExpandableCode>
              <Panel className="overflow-hidden">
                <CodeCard code={wordpressPageCode} lang="html" />
              </Panel>
            </ExpandableCode>
          </div>
        </div>

        <div className="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8">
          {[
            { icon: ArrowDownRight, title: "Less output to generate", description: "No comment delimiters, no duplicated class names, no wrapper markup. The agent writes what it means." },
            { icon: ShieldCheck, title: "Less to get wrong", description: "WordPress block validation fails on a single mismatched class or missing attribute. HTML has no such constraint." },
            { icon: Zap, title: "Faster and cheaper", description: "Fewer output tokens means faster responses and lower API costs across every model and provider." },
          ].map((card) => (
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

      <Section
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

      <Section
        icon={<SectionIcon><Wrench /></SectionIcon>}
        title="Built for the AI workflow"
        description="Blocks, pages, patterns, settings. Everything is a file an agent can read, write, and modify."
      >
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
      </Section>

      <div className="border-t">
        <PlusSection />
      </div>
    </div>
  );
}

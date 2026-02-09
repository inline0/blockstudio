import { LayoutGrid, BookOpen, Layers, Pencil } from "lucide-react";
import { Button } from "onedocs";
import { Section, SectionIcon } from "./section";
import { Feature } from "./feature";
import { CodeCard } from "./code-card";

const templateCode = `<div>
  <h2>Pricing</h2>
  <p>Simple, transparent pricing.</p>

  <block name="core/columns">
    <block name="core/column">
      <h3>Starter</h3>
      <p>For small teams getting started.</p>
      <block name="core/button" url="/signup">
        Get Started
      </block>
    </block>
    <block name="core/column">
      <h3>Pro</h3>
      <ul>
        <li>Priority support</li>
        <li>Custom integrations</li>
      </ul>
      <block name="core/button" url="/signup?plan=pro">
        Go Pro
      </block>
    </block>
  </block>
</div>`;

const details = [
  {
    icon: BookOpen,
    title: "Same syntax as pages",
    description:
      "Patterns use the same HTML parser. Standard HTML maps to core blocks, the <block> tag handles everything else.",
  },
  {
    icon: LayoutGrid,
    title: "Auto-registered",
    description:
      "Drop a folder with a template and a pattern.json. Blockstudio registers it and adds it to the inserter automatically.",
  },
  {
    icon: Pencil,
    title: "Fully editable",
    description:
      "Unlike pages, patterns are inserted as editable block content. Users can customize each instance independently.",
  },
  {
    icon: Layers,
    title: "Categorized & searchable",
    description:
      "Add categories and keywords in pattern.json. Users find your patterns instantly in the inserter search.",
  },
];

export async function Patterns() {
  return (
    <Section
      icon={<SectionIcon><LayoutGrid /></SectionIcon>}
      title="Reusable patterns from template files"
      description="Define block patterns as template files. Same HTML syntax as pages, registered automatically in the block inserter."
    >
      <div className="flex flex-col gap-10">
        <Feature
          headline="Template files, not registration code"
          description={
            <>
              <p>
                Create a folder with a{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  pattern.json
                </code>{" "}
                and a template file. Blockstudio registers the pattern
                automatically, no{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  register_block_pattern()
                </code>{" "}
                boilerplate.
              </p>
              <p>
                Patterns are inserted as real, editable blocks. Users get a
                starting layout they can fully customize, while you maintain the
                templates in version control.
              </p>
            </>
          }
          cta={
            <Button href="/docs/pages-and-patterns/patterns" className="w-max">
              Learn more about patterns &rarr;
            </Button>
          }
          demo={<CodeCard code={templateCode} lang="html" />}
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

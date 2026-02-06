import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Files, Folder, File } from "onedocs/components";
import { Section } from "./section";

const templateCode = `<block name="core/columns">
  <block name="core/column">
    <h3>Starter</h3>
    <p>For small teams getting started.</p>
    <block name="core/button" url="/signup">
      Get Started
    </block>
  </block>
  <block name="core/column">
    <h3>Pro</h3>
    <p>For growing teams that need more.</p>
    <block name="core/button" url="/signup?plan=pro">
      Go Pro
    </block>
  </block>
</block>`;

const jsonCode = `{
  "name": "pricing",
  "title": "Pricing Table",
  "categories": ["content"],
  "keywords": ["pricing", "plans"]
}`;

export async function Patterns() {
  return (
    <Section
      title="Template-based patterns"
      description="Define reusable block patterns as template files. Same HTML-to-block syntax as pages, registered automatically in the block inserter."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 px-6 lg:px-10">
        <div className="flex flex-col gap-4">
          <Files>
            <Folder name="patterns" defaultOpen>
              <Folder name="pricing" defaultOpen>
                <File name="pattern.json" />
                <File name="index.php" />
              </Folder>
              <Folder name="testimonials">
                <File name="pattern.json" />
                <File name="index.php" />
              </Folder>
            </Folder>
          </Files>
          <CodeBlock code={jsonCode} lang="json" />
        </div>
        <div className="flex flex-col gap-4">
          <CodeBlock code={templateCode} lang="html" />
          <div className="flex flex-col gap-3 text-sm text-fd-muted-foreground">
            <p>
              Patterns use the same HTML parser as pages — standard HTML
              maps to core blocks, and the{" "}
              <code className="text-fd-foreground font-mono">
                &lt;block&gt;
              </code>{" "}
              tag handles everything else. Patterns are registered in memory
              and show up in the inserter immediately.
            </p>
            <p>
              Unlike pages, patterns don&apos;t sync to the database. They&apos;re
              inserted as editable block content, so users can customize
              each instance independently.
            </p>
            <Link
              href="/docs/patterns"
              className="text-fd-primary hover:underline font-medium"
            >
              Learn more about patterns &rarr;
            </Link>
          </div>
        </div>
      </div>
    </Section>
  );
}

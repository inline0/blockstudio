import { Component } from "lucide-react";
import { Button } from "onedocs";
import { Section, SectionIcon } from "./section";
import { Feature } from "./feature";
import { CodeCard } from "./code-card";

const templateCode = `<div useBlockProps class="container">
  <RichText
    class="text-xl font-semibold"
    tag="h1"
    attribute="richtext"
    placeholder="Enter headline"
  />

  <?php if ($a['showCta']): ?>
    <a href="<?= $a['ctaUrl'] ?>">
      <?= $a['ctaLabel'] ?>
    </a>
  <?php endif; ?>

  <InnerBlocks class="mt-4 p-4 border" />
</div>`;

export async function Composition() {
  return (
    <Section
      icon={<SectionIcon><Component /></SectionIcon>}
      title="React features, PHP templates"
      description="Blockstudio brings the best of both worlds. Native WordPress editor components like RichText and InnerBlocks, accessible directly in your PHP templates. No JavaScript, no build step."
    >
      <div>
        <Feature
          headline="All inside the same file"
          description={
            <>
              <p>
                Use{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<RichText />"}
                </code>{" "}
                for inline editing in the block editor and static HTML on the
                frontend.{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<InnerBlocks />"}
                </code>{" "}
                for nested child blocks. And{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  useBlockProps
                </code>{" "}
                to mark the block wrapper. All from a single template file.
              </p>
              <p>
                Blockstudio handles the editor integration automatically. In the
                editor, these become real React components. On the frontend,
                they render as plain server-side HTML.
              </p>
            </>
          }
          cta={
            <Button href="/docs/blocks/components" className="w-max">
              Explore components &rarr;
            </Button>
          }
          demo={<CodeCard code={templateCode} lang="php" />}
        />
      </div>
    </Section>
  );
}

import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Files, Folder, File } from "onedocs/components";
import { Section } from "./section";

const templateCode = `<block name="core/cover" url="https://example.com/hero.jpg">
  <h1>About Us</h1>
  <p>We build tools for WordPress developers.</p>
</block>

<block name="core/columns">
  <block name="core/column">
    <h2>Our Mission</h2>
    <p>Making block development fast and simple.</p>
  </block>
  <block name="core/column">
    <h2>Our Stack</h2>
    <p>PHP, WordPress, and zero JavaScript.</p>
  </block>
</block>`;

const jsonCode = `{
  "name": "about",
  "title": "About",
  "slug": "about",
  "postStatus": "publish",
  "templateLock": "all"
}`;

export async function Pages() {
  return (
    <Section
      title="File-based pages"
      description="Define full WordPress pages as template files. HTML maps to core blocks automatically — write markup, Blockstudio syncs it to the editor."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 px-6 lg:px-10">
        <div className="flex flex-col gap-4">
          <Files>
            <Folder name="pages" defaultOpen>
              <Folder name="about" defaultOpen>
                <File name="page.json" />
                <File name="index.php" />
              </Folder>
              <Folder name="contact">
                <File name="page.json" />
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
              Standard HTML elements like{" "}
              <code className="text-fd-foreground font-mono">&lt;h1&gt;</code>,{" "}
              <code className="text-fd-foreground font-mono">&lt;p&gt;</code>,{" "}
              <code className="text-fd-foreground font-mono">&lt;ul&gt;</code>{" "}
              map to core blocks automatically. For everything else, use the{" "}
              <code className="text-fd-foreground font-mono">
                &lt;block&gt;
              </code>{" "}
              tag.
            </p>
            <p>
              Pages sync to WordPress on admin load. Lock templates to
              prevent edits, or use keyed blocks to preserve user changes
              across template updates.
            </p>
            <Link
              href="/docs/pages"
              className="text-fd-primary hover:underline font-medium"
            >
              Learn more about pages &rarr;
            </Link>
          </div>
        </div>
      </div>
    </Section>
  );
}

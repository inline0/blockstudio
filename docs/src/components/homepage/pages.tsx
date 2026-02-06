import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Files, Folder, File } from "onedocs/components";
import { Section } from "./section";

const templateCode = `<main>
  <h1><?= $a['title'] ?></h1>
  <div class="content">
    <?= $innerBlocks ?>
  </div>
</main>`;

const jsonCode = `{
  "title": "About",
  "slug": "about",
  "status": "publish",
  "blockstudio": {
    "attributes": {
      "title": {
        "type": "text",
        "default": "About Us"
      }
    }
  }
}`;

export async function Pages() {
  return (
    <Section
      title="File-based pages"
      description="Build full WordPress pages as template files. Define metadata in JSON, write markup in PHP. Pages auto-sync to the editor as native blocks."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 px-6 lg:px-10">
        <div className="flex flex-col gap-4">
          <Files>
            <Folder name="pages" defaultOpen>
              <File name="about.php" />
              <File name="about.json" />
              <File name="contact.php" />
              <File name="contact.json" />
            </Folder>
          </Files>
          <CodeBlock code={jsonCode} lang="json" />
        </div>
        <div className="flex flex-col gap-4">
          <CodeBlock code={templateCode} lang="php" />
          <div className="flex flex-col gap-3 text-sm text-fd-muted-foreground">
            <p>
              Each page is a pair of files: a JSON file for metadata (title,
              slug, status) and a template file for markup. Blockstudio
              syncs them to WordPress automatically.
            </p>
            <p>
              Pages support the same field types, template variables, and
              InnerBlocks as regular blocks. Edit content in the block
              editor, render with your template.
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

import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Files, Folder, File } from "onedocs/components";
import { Blocks, FileText, LayoutGrid, Puzzle } from "lucide-react";
import { Section } from "./section";
import { Placeholder } from "./placeholder";

const items = [
  {
    icon: Blocks,
    title: "Blocks",
    description:
      "Custom blocks from JSON + a template file. Define fields, write markup, ship. No JavaScript, no build step.",
    href: "/docs/blocks",
    tree: (
      <Files>
        <Folder name="hero" defaultOpen>
          <File name="block.json" />
          <File name="index.php" />
          <File name="style.scss" />
        </Folder>
      </Files>
    ),
  },
  {
    icon: FileText,
    title: "Pages",
    description:
      "Full WordPress pages as file-based templates. Define metadata in JSON, write the page in PHP. Pages auto-sync to the editor as native blocks.",
    href: "/docs/pages",
    tree: (
      <Files>
        <Folder name="pages" defaultOpen>
          <File name="about.php" />
          <File name="about.json" />
        </Folder>
      </Files>
    ),
    placeholder: "WordPress pages list showing synced template page",
  },
  {
    icon: LayoutGrid,
    title: "Patterns",
    description:
      "Reusable block patterns from template files. Automatically registered in the block inserter, always in sync with your code.",
    href: "/docs/patterns",
    tree: (
      <Files>
        <Folder name="patterns" defaultOpen>
          <File name="pricing.php" />
          <File name="pricing.json" />
        </Folder>
      </Files>
    ),
    placeholder: "Block inserter showing registered pattern",
  },
  {
    icon: Puzzle,
    title: "Extensions",
    description:
      "Add custom fields to any block — core, third-party, or your own. Use wildcards to extend entire namespaces at once.",
    href: "/docs/extensions",
    code: {
      lang: "json",
      code: `{
  "blockstudio": {
    "extend": "core/*",
    "attributes": {
      "animation": {
        "type": "select",
        "set": "style",
        "options": ["fade", "slide"]
      }
    }
  }
}`,
    },
  },
];

export async function BeyondBlocks() {
  return (
    <Section
      title="Beyond blocks"
      description="A complete framework for blocks, pages, patterns, and extensions — all from template files."
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 px-6 lg:px-10">
        {items.map((item) => (
          <Link
            key={item.title}
            href={item.href}
            className="group flex flex-col gap-y-4 rounded-lg p-6 transition-colors hover:bg-fd-secondary/20"
          >
            <div>
              <div className="flex items-center gap-3 mb-2">
                <div className="bg-fd-primary/10 p-1.5 rounded-lg">
                  <item.icon className="h-4 w-4 text-fd-primary" />
                </div>
                <h3 className="text-base font-medium text-fd-foreground">
                  {item.title}
                </h3>
              </div>
              <p className="text-sm text-fd-muted-foreground text-pretty">
                {item.description}
              </p>
            </div>
            {item.tree}
            {item.placeholder && (
              <Placeholder label={item.placeholder} aspect="wide" />
            )}
            {item.code && (
              <CodeBlock code={item.code.code} lang={item.code.lang} />
            )}
          </Link>
        ))}
      </div>
    </Section>
  );
}

import { CodeBlock } from "onedocs";
import { Files, Folder, File } from "onedocs/components";
import { Section } from "./section";

const items = [
  {
    title: "Blocks",
    description: "JSON schema + template file. No JavaScript required.",
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
    title: "Pages",
    description: "File-based WordPress pages that sync to native blocks.",
    tree: (
      <Files>
        <Folder name="pages" defaultOpen>
          <File name="about.php" />
          <File name="about.json" />
        </Folder>
      </Files>
    ),
    code: {
      lang: "json",
      code: `{
  "title": "About",
  "slug": "about",
  "status": "publish"
}`,
    },
  },
  {
    title: "Patterns",
    description: "Template-based patterns registered automatically.",
    tree: (
      <Files>
        <Folder name="patterns" defaultOpen>
          <File name="pricing.php" />
          <File name="pricing.json" />
        </Folder>
      </Files>
    ),
    code: {
      lang: "json",
      code: `{
  "title": "Pricing Table",
  "categories": ["content"]
}`,
    },
  },
  {
    title: "Extensions",
    description: "Add fields to any block with a single JSON file.",
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
      description="A complete framework for blocks, pages, patterns, and extensions."
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 border-t">
        {items.map((item) => (
          <div
            key={item.title}
            className="flex flex-col gap-y-4 py-8 px-6 border-b sm:border-r sm:[&:nth-child(2n)]:border-r-0 sm:[&:nth-last-child(-n+2)]:border-b-0 [&:last-child]:border-b-0"
          >
            <div>
              <h3 className="text-base font-medium text-fd-foreground">
                {item.title}
              </h3>
              <p className="mt-1 text-sm text-fd-muted-foreground">
                {item.description}
              </p>
            </div>
            {item.tree}
            {item.code && (
              <CodeBlock code={item.code.code} lang={item.code.lang} />
            )}
          </div>
        ))}
      </div>
    </Section>
  );
}

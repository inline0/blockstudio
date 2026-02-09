import type { EvalSuite } from "./types.ts";
import { jsonParse } from "./scorers/json-parse.ts";
import { jsonSchema } from "./scorers/json-schema.ts";
import { requiredFiles, hasProperties } from "./scorers/structure.ts";

const suite: EvalSuite = {
  name: "Page Evals",
  cases: [
    {
      name: "Simple page",
      prompt:
        'Create a Blockstudio page definition. The page should be called "About Us" with slug "about-us" and be published. Include the page.json and a simple index.php template with a heading and paragraph.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties("page.json", {
          path: "postStatus",
          value: "publish",
        }),
        requiredFiles("page.json", "index.php"),
      ],
    },
    {
      name: "Template locking",
      prompt:
        'Create a Blockstudio page.json for a "Landing Page" that has full template locking enabled (templateLock set to "all") so users cannot add, move, or remove blocks. Show only the page.json.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties("page.json", {
          path: "templateLock",
          value: "all",
        }),
      ],
    },
    {
      name: "Block editing mode",
      prompt:
        'Create a Blockstudio page.json for a "Locked Page" that has blockEditingMode set to "disabled" and is saved as a draft. Show only the page.json.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties(
          "page.json",
          { path: "blockEditingMode", value: "disabled" },
          { path: "postStatus", value: "draft" }
        ),
      ],
    },
    {
      name: "Keyed block merging",
      prompt:
        'Create a Blockstudio page with an index.php template that demonstrates keyed block merging. Include at least two blocks with key attributes so they can be synced without duplicating. Show both the page.json and index.php.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        requiredFiles("page.json", "index.php"),
        (() => {
          return (output: string) => {
            const hasKey = output.includes("key=") || output.includes('"key"');
            return {
              name: "KeyedMerge",
              score: hasKey ? 1 : 0,
              details: hasKey
                ? "key attributes found"
                : "No key attributes found",
            };
          };
        })(),
      ],
    },
    {
      name: "Block bindings to post meta",
      prompt:
        'Create a Blockstudio page with an index.php template that uses block bindings to bind a core/paragraph block\'s content to a custom post meta field called "subtitle". Show both the page.json and index.php.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        requiredFiles("page.json", "index.php"),
        (() => {
          return (output: string) => {
            const hasBindings =
              output.includes("metadata") && output.includes("bindings");
            return {
              name: "Bindings",
              score: hasBindings ? 1 : 0,
              details: hasBindings
                ? "Binding patterns found"
                : "No binding patterns found",
            };
          };
        })(),
      ],
    },
    {
      name: "Pinned to post ID",
      prompt:
        'Create a Blockstudio page.json that is pinned to a specific post with postId 2. The page should be called "Homepage" and published. Show only the page.json.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties(
          "page.json",
          { path: "postId", value: 2 },
          { path: "postStatus", value: "publish" }
        ),
      ],
    },
    {
      name: "templateFor",
      prompt:
        'Create a Blockstudio page.json for a page called "Product Template" that uses templateFor set to "product" so it applies to the product custom post type. The page should be published. Show only the page.json.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties(
          "page.json",
          { path: "templateFor", value: "product" },
          { path: "postStatus", value: "publish" }
        ),
      ],
    },
    {
      name: "Sync disabled",
      prompt:
        'Create a Blockstudio page.json for a page called "Static Page" with sync set to false so the page content is not automatically synced. The page should be published. Show only the page.json.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties(
          "page.json",
          { path: "sync", value: false },
          { path: "postStatus", value: "publish" }
        ),
      ],
    },
    {
      name: "Custom postType",
      prompt:
        'Create a Blockstudio page.json for a page called "Portfolio Item" that creates content for the "portfolio" post type instead of the default page type. Set postType to "portfolio" and postStatus to "publish". Show only the page.json.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties(
          "page.json",
          { path: "postType", value: "portfolio" },
          { path: "postStatus", value: "publish" }
        ),
      ],
    },
    {
      name: "contentOnly with keyed blocks",
      prompt:
        'Create a Blockstudio page with blockEditingMode "contentOnly" and an index.php template that has two core/paragraph blocks with unique key attributes (key="intro" and key="body") so they are preserved during sync. Show both the page.json and index.php.',
      scorers: [
        jsonParse(),
        jsonSchema("page"),
        hasProperties("page.json", {
          path: "blockEditingMode",
          value: "contentOnly",
        }),
        requiredFiles("page.json", "index.php"),
        (() => {
          return (output: string) => {
            const hasKey1 = output.includes('key="intro"') || output.includes("key='intro'");
            const hasKey2 = output.includes('key="body"') || output.includes("key='body'");
            const score = (hasKey1 ? 0.5 : 0) + (hasKey2 ? 0.5 : 0);
            return {
              name: "KeyedBlocks",
              score,
              details: `key=intro: ${hasKey1}, key=body: ${hasKey2}`,
            };
          };
        })(),
      ],
    },
  ],
};

export default suite;

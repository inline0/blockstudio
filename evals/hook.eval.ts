import type { EvalSuite } from "./types.ts";
import { hookPresence } from "./scorers/php-syntax.ts";
import { extractCodeBlocks } from "./utils/extract.ts";

function hasPhpCode(): (output: string) => { name: string; score: number; details: string } {
  return (output: string) => {
    const blocks = extractCodeBlocks(output);
    const content = blocks.map((b) => b.content).join("\n");
    const hasPhp = content.includes("<?php") || content.includes("add_filter") || content.includes("add_action");
    return {
      name: "PhpCode",
      score: hasPhp ? 1 : 0,
      details: hasPhp ? "PHP code found" : "No PHP code found",
    };
  };
}

const suite: EvalSuite = {
  name: "Hook Evals",
  cases: [
    {
      name: "useBlockProps render filter",
      prompt:
        'Write PHP code that uses a Blockstudio filter hook to modify the useBlockProps output for a specific block. The filter should add a custom data attribute "data-animated" to all blocks of type "acme/hero". Show the complete PHP code with add_filter.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio", "use_block_props"),
      ],
    },
    {
      name: "Custom parser renderer",
      prompt:
        'Write PHP code that adds a custom HTML-to-block renderer via the blockstudio/parser/renderers filter. The renderer should handle a custom <accordion> HTML element and convert it to core/details blocks. Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio/parser/renderers"),
      ],
    },
    {
      name: "Element mapping filter",
      prompt:
        'Write PHP code that uses the blockstudio/parser/element_mapping filter to map a custom HTML element <hero> to a specific Blockstudio block "acme/hero-section". Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio/parser/element_mapping"),
      ],
    },
    {
      name: "Tailwind CSS filter",
      prompt:
        'Write PHP code that uses the blockstudio/tailwind/css filter to modify the generated Tailwind CSS output. The filter should add a custom CSS rule for a utility class. Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio/tailwind/css"),
      ],
    },
    {
      name: "blockstudio/block/render filter",
      prompt:
        'Write PHP code that uses the blockstudio/block/render filter to modify the HTML output of a rendered block. The filter should wrap the output of "acme/card" blocks in an additional div with class "card-wrapper". Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio/block/render"),
      ],
    },
    {
      name: "blockstudio/block/meta filter",
      prompt:
        'Write PHP code that uses the blockstudio/block/meta filter to modify block metadata before registration. The filter should add a custom "supports" key to all blocks. Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio/block/meta"),
      ],
    },
    {
      name: "blockstudio/block/attributes filter",
      prompt:
        'Write a single PHP code snippet that uses the blockstudio/block/attributes filter to modify block attributes before they are passed to the template. The filter should add a computed "fullName" attribute by combining first and last name fields. Show only the PHP code in one code block.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio/block/attributes"),
      ],
    },
    {
      name: "blockstudio/block/conditions filter",
      prompt:
        'Write PHP code that uses the blockstudio/block/conditions filter to register a custom condition type called "user_role" that checks the current user role. Show the complete PHP code with add_filter.',
      scorers: [
        hasPhpCode(),
        hookPresence("add_filter", "blockstudio/block/conditions"),
      ],
    },
    {
      name: "Settings filters",
      prompt:
        'Write PHP code that uses three Blockstudio settings filters: blockstudio/settings/tailwind to modify Tailwind config, blockstudio/settings/editor to change editor settings, and blockstudio/settings/assets to modify asset processing. Show the complete PHP code with all three add_filter calls.',
      scorers: [
        hasPhpCode(),
        hookPresence(
          "add_filter",
          "blockstudio/settings/tailwind",
          "blockstudio/settings/editor",
          "blockstudio/settings/assets"
        ),
      ],
    },
    {
      name: "blockstudio/init action",
      prompt:
        'Write PHP code that hooks into the blockstudio/init action and the blockstudio/init/before action. The init hook should register a custom taxonomy, and the init/before hook should log a message. Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence(
          "add_action",
          "blockstudio/init",
          "blockstudio/init/before"
        ),
      ],
    },
    {
      name: "Component render filters",
      prompt:
        'Write PHP code that uses the blockstudio/render/use_block_props filter to add custom props, the blockstudio/render/inner_blocks filter to modify inner blocks output, and the blockstudio/render/rich_text filter to modify RichText rendering. Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence(
          "add_filter",
          "blockstudio/render/use_block_props",
          "blockstudio/render/inner_blocks",
          "blockstudio/render/rich_text"
        ),
      ],
    },
    {
      name: "Path filters",
      prompt:
        'Write PHP code that uses the blockstudio/path filter to add a custom block directory path, and the blockstudio/pages/paths filter to add a custom pages directory. Show the complete PHP code.',
      scorers: [
        hasPhpCode(),
        hookPresence(
          "add_filter",
          "blockstudio/path",
          "blockstudio/pages/paths"
        ),
      ],
    },
  ],
};

export default suite;

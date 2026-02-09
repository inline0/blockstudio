import type { EvalSuite } from "./types.ts";
import { jsonParse } from "./scorers/json-parse.ts";
import { configStructure } from "./scorers/structure.ts";

const suite: EvalSuite = {
  name: "Configuration Evals",
  cases: [
    {
      name: "Tailwind enabled",
      prompt:
        'Create a Blockstudio blockstudio.json configuration file that enables Tailwind CSS support with a custom config that adds a custom color "--color-brand: #ff6600". Show only the blockstudio.json.',
      scorers: [
        jsonParse(),
        configStructure("tailwind.enabled", "tailwind.config"),
      ],
    },
    {
      name: "Asset settings",
      prompt:
        'Create a Blockstudio blockstudio.json that configures asset processing: enable CSS and JS minification, and enable SCSS processing. Show only the blockstudio.json.',
      scorers: [
        jsonParse(),
        configStructure(
          "assets.minify.css",
          "assets.minify.js",
          "assets.process.scss"
        ),
      ],
    },
    {
      name: "Editor settings",
      prompt:
        'Create a Blockstudio blockstudio.json that enables format-on-save in the code editor, restricts block editing to only user role "administrator", and enqueues a custom editor stylesheet handle "my-editor-styles". Show only the blockstudio.json.',
      scorers: [
        jsonParse(),
        configStructure("editor.formatOnSave", "users.roles"),
        (() => {
          return (output: string) => {
            const hasEditorAssets =
              output.includes('"assets"') && output.includes("my-editor-styles");
            return {
              name: "EditorAssets",
              score: hasEditorAssets ? 1 : 0,
              details: hasEditorAssets
                ? "Editor assets configured"
                : "Missing editor assets config",
            };
          };
        })(),
      ],
    },
    {
      name: "Block editor settings",
      prompt:
        'Create a Blockstudio blockstudio.json that configures block editor settings: set blockEditor.disableLoading to true, blockEditor.cssClasses to true to enable CSS class selection, and enable the block library with library set to true. Show only the blockstudio.json.',
      scorers: [
        jsonParse(),
        (() => {
          return (output: string) => {
            const hasDisableLoading =
              output.includes('"disableLoading"') || output.includes('"blockEditor"');
            const hasCssClasses = output.includes('"cssClasses"');
            const hasLibrary = output.includes('"library"');
            const found = [hasDisableLoading, hasCssClasses, hasLibrary].filter(Boolean).length;
            return {
              name: "BlockEditor",
              score: found / 3,
              details: `disableLoading: ${hasDisableLoading}, cssClasses: ${hasCssClasses}, library: ${hasLibrary}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Full combined config",
      prompt:
        'Create a complete Blockstudio blockstudio.json that combines all configuration sections: users with roles ["administrator", "editor"], assets with CSS and JS minification enabled, tailwind enabled with a custom config, editor with formatOnSave true, and library set to true. Show only the blockstudio.json.',
      scorers: [
        jsonParse(),
        configStructure("users.roles", "tailwind.enabled", "editor.formatOnSave"),
        (() => {
          return (output: string) => {
            const hasAssets = output.includes('"assets"');
            const hasLibrary = output.includes('"library"');
            const score = (hasAssets ? 0.5 : 0) + (hasLibrary ? 0.5 : 0);
            return {
              name: "CombinedConfig",
              score,
              details: `assets: ${hasAssets}, library: ${hasLibrary}`,
            };
          };
        })(),
      ],
    },
  ],
};

export default suite;

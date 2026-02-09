import type { EvalSuite } from "./types.ts";
import { jsonParse } from "./scorers/json-parse.ts";
import { extensionStructure, hasConditions } from "./scorers/structure.ts";

const suite: EvalSuite = {
  name: "Extension Evals",
  cases: [
    {
      name: "Extend core/paragraph with set",
      prompt:
        'Create a Blockstudio block extension JSON file that extends core/paragraph. Add a text field (id: "customClass") with a "set" property that adds the field value as a CSS class to the block. The extension should use extend: true in the blockstudio key. Show the complete JSON file.',
      scorers: [
        jsonParse(),
        extensionStructure("core/paragraph", 1),
        (() => {
          return (output: string) => {
            const hasSet =
              output.includes('"set"') && output.includes('"class"');
            return {
              name: "SetProp",
              score: hasSet ? 1 : 0,
              details: hasSet
                ? "set with class found"
                : "Missing set/class config",
            };
          };
        })(),
      ],
    },
    {
      name: "Extend core/image, multiple fields",
      prompt:
        'Create a Blockstudio block extension that extends core/image. Add 3 fields in a group titled "Image Settings": a toggle (id: "lazyLoad"), a select (id: "aspectRatio") with options like 16:9/4:3/1:1, and a text field (id: "caption"). Show the complete JSON.',
      scorers: [
        jsonParse(),
        extensionStructure("core/image", 3),
      ],
    },
    {
      name: "Extension with conditions",
      prompt:
        'Create a Blockstudio block extension that extends core/heading. Add a toggle field (id: "enableAnimation") and a select field (id: "animationType") with options fade/slide/bounce. The animationType field should only be visible when enableAnimation is true, using the conditions property. Show the complete JSON.',
      scorers: [
        jsonParse(),
        extensionStructure("core/heading", 2),
        hasConditions(),
      ],
    },
    {
      name: "set with style",
      prompt:
        'Create a Blockstudio block extension that extends core/group. Add a color field (id: "overlayColor") with a "set" property that applies the value as an inline style (style attribute) for background-color on the block wrapper. Show the complete JSON.',
      scorers: [
        jsonParse(),
        extensionStructure("core/group", 1),
        (() => {
          return (output: string) => {
            const hasSet = output.includes('"set"');
            const hasStyle =
              output.includes('"style"') || output.includes("background");
            const score = (hasSet ? 0.5 : 0) + (hasStyle ? 0.5 : 0);
            return {
              name: "SetStyle",
              score,
              details: `set: ${hasSet}, style: ${hasStyle}`,
            };
          };
        })(),
      ],
    },
    {
      name: "set with data attribute",
      prompt:
        'Create a Blockstudio block extension that extends core/button. Add a text field (id: "analyticsId") with a "set" property that adds a "data-analytics" attribute to the block element. Show the complete JSON.',
      scorers: [
        jsonParse(),
        extensionStructure("core/button", 1),
        (() => {
          return (output: string) => {
            const hasSet = output.includes('"set"');
            const hasDataAttr = output.includes("data-analytics");
            const score = (hasSet ? 0.5 : 0) + (hasDataAttr ? 0.5 : 0);
            return {
              name: "SetDataAttr",
              score,
              details: `set: ${hasSet}, data-analytics: ${hasDataAttr}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Wildcard matching",
      prompt:
        'Create a Blockstudio block extension that extends ALL core blocks using the wildcard pattern "core/*" as the name value. Add a toggle field (id: "hideOnMobile"). Show the complete JSON.',
      scorers: [
        jsonParse(),
        (() => {
          return (output: string) => {
            const hasWildcard = output.includes('"core/*"') || output.includes('"core/"');
            const hasExtend = output.includes('"extend"');
            const hasField = output.includes('"hideOnMobile"');
            const found = [hasWildcard, hasExtend, hasField].filter(Boolean).length;
            return {
              name: "Wildcard",
              score: found / 3,
              details: `wildcard: ${hasWildcard}, extend: ${hasExtend}, field: ${hasField}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Priority and group",
      prompt:
        'Create a Blockstudio block extension that extends core/cover. Add a select field (id: "overlayPattern") in the "styles" group. Set the extension priority to 20. Show the complete JSON.',
      scorers: [
        jsonParse(),
        extensionStructure("core/cover", 1),
        (() => {
          return (output: string) => {
            const hasPriority = output.includes('"priority"');
            const hasGroup = output.includes('"group"') && output.includes('"styles"');
            const score = (hasPriority ? 0.5 : 0) + (hasGroup ? 0.5 : 0);
            return {
              name: "PriorityGroup",
              score,
              details: `priority: ${hasPriority}, group/styles: ${hasGroup}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Multiple block targets",
      prompt:
        'Create a Blockstudio block extension that extends BOTH core/paragraph and core/heading using an array for the name field. Add a text field (id: "customId") that lets users set a custom HTML ID. Show the complete JSON.',
      scorers: [
        jsonParse(),
        (() => {
          return (output: string) => {
            const hasParagraph = output.includes("core/paragraph");
            const hasHeading = output.includes("core/heading");
            const hasArray =
              output.includes('["core/') || output.includes("[\n");
            const hasExtend = output.includes('"extend"');
            const hasField = output.includes('"customId"');
            const found = [
              hasParagraph && hasHeading,
              hasExtend,
              hasField,
            ].filter(Boolean).length;
            return {
              name: "MultiTarget",
              score: found / 3,
              details: `both blocks: ${hasParagraph && hasHeading}, extend: ${hasExtend}, field: ${hasField}`,
            };
          };
        })(),
      ],
    },
  ],
};

export default suite;

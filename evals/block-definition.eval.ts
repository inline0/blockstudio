import type { EvalSuite } from "./types.ts";
import { jsonParse } from "./scorers/json-parse.ts";
import { jsonSchema } from "./scorers/json-schema.ts";
import {
  hasFieldTypes,
  hasFieldIds,
  requiredFiles,
  hasConditions,
  hasSwitch,
  hasStorage,
} from "./scorers/structure.ts";

const suite: EvalSuite = {
  name: "Block Definition Evals",
  cases: [
    {
      name: "Simple text field",
      prompt:
        'Create a Blockstudio block called "acme/greeting" with a single text field. The field should have id "message", label "Greeting Message", and a default value of "Hello World". Include the block.json and template file.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("text"),
        hasFieldIds("message"),
        requiredFiles("block.json", "index.php"),
      ],
    },
    {
      name: "Multiple field types",
      prompt:
        'Create a Blockstudio block called "acme/card" with these fields: a text field (id: "title"), a number field (id: "count"), a toggle field (id: "featured"), a color field (id: "bgColor"), and a select field (id: "size") with options small/medium/large. Show only the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("text", "number", "toggle", "color", "select"),
        hasFieldIds("title", "count", "featured", "bgColor", "size"),
      ],
    },
    {
      name: "Nested group fields",
      prompt:
        'Create a Blockstudio block.json for "acme/settings-panel". It should have a group field titled "Settings" containing a text field (id: "label") and a toggle field (id: "enabled"). Show the complete block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("group", "text", "toggle"),
        hasFieldIds("label", "enabled"),
      ],
    },
    {
      name: "Repeater with nested fields",
      prompt:
        'Create a Blockstudio block.json for "acme/team-members". It should have a repeater field (id: "members") with min 1 and max 10 items. Each item should have a text field (id: "name") and a files field (id: "photo"). Show the complete block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("repeater", "text", "files"),
        hasFieldIds("members", "name", "photo"),
      ],
    },
    {
      name: "Tabs layout",
      prompt:
        'Create a Blockstudio block.json for "acme/product". Use tabs layout to organize fields: a "Content" tab with text fields (id: "title", "description"), and a "Settings" tab with a toggle (id: "featured") and select (id: "category"). Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("tabs", "text", "toggle", "select"),
        hasFieldIds("title", "description", "featured", "category"),
      ],
    },
    {
      name: "Field conditions",
      prompt:
        'Create a Blockstudio block.json for "acme/conditional". It has a toggle field (id: "showDetails", label: "Show Details"), and a textarea field (id: "details") that should only appear when showDetails is true. Use the conditions property. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("toggle", "textarea"),
        hasFieldIds("showDetails", "details"),
        hasConditions(),
      ],
    },
    {
      name: "Switch visibility",
      prompt:
        'Create a Blockstudio block.json for "acme/hero" with a text field (id: "title") that is always visible, and a textarea field (id: "subtitle") with switch visibility enabled (hidden by default, togglable). Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("text", "textarea"),
        hasFieldIds("title", "subtitle"),
        hasSwitch(),
      ],
    },
    {
      name: "Storage (postMeta, option)",
      prompt:
        'Create a Blockstudio block.json for "acme/meta-block". It has a text field (id: "metaTitle") that stores its value as post meta (meta key "meta_title"), and a text field (id: "siteTagline") that stores as a WordPress option. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("metaTitle", "siteTagline"),
        hasStorage(),
      ],
    },
    {
      name: "Interactivity enabled",
      prompt:
        'Create a Blockstudio block.json for "acme/counter" with interactivity API support enabled (interactivity.enqueue: true). It should have a number field (id: "initialCount"). Show the complete block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("initialCount"),
        (() => {
          return (output: string) => {
            const content = output.toLowerCase();
            const hasInteractivity =
              content.includes('"enqueue"') ||
              (content.includes('"interactivity"') && content.includes("true"));
            return {
              name: "Interactivity",
              score: hasInteractivity ? 1 : 0,
              details: hasInteractivity
                ? "Interactivity config found"
                : "No interactivity config",
            };
          };
        })(),
      ],
    },
    {
      name: "Custom field reference",
      prompt:
        'Create a Blockstudio block.json for "acme/icon-block". It should use a custom field type (type starts with "custom/") for the icon selector field (id: "icon"). Also include a text field (id: "label"). Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("icon", "label"),
        (() => {
          return (output: string) => {
            const hasCustomType = output.includes('"custom/');
            return {
              name: "CustomType",
              score: hasCustomType ? 1 : 0,
              details: hasCustomType
                ? "custom/ type found"
                : "No custom/ type found",
            };
          };
        })(),
      ],
    },
    {
      name: "Textarea, checkbox, radio",
      prompt:
        'Create a Blockstudio block.json for "acme/survey". It should have: a textarea field (id: "bio", rows: 4), a checkbox field (id: "interests") with options hiking/reading/coding, and a radio field (id: "experience") with options beginner/intermediate/advanced. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("textarea", "checkbox", "radio"),
        hasFieldIds("bio", "interests", "experience"),
      ],
    },
    {
      name: "Range and unit",
      prompt:
        'Create a Blockstudio block.json for "acme/spacer". It should have a range field (id: "spacing", min: 0, max: 100, step: 5) and a unit field (id: "width") with units ["px", "em", "rem", "%"]. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("range", "unit"),
        hasFieldIds("spacing", "width"),
      ],
    },
    {
      name: "Date and datetime",
      prompt:
        'Create a Blockstudio block.json for "acme/event". It should have a date field (id: "startDate") and a datetime field (id: "eventTime") with is12Hour set to true. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("date", "datetime"),
        hasFieldIds("startDate", "eventTime"),
      ],
    },
    {
      name: "Gradient, icon, link",
      prompt:
        'Create a Blockstudio block.json for "acme/promo". It should have a gradient field (id: "bgGradient"), an icon field (id: "promoIcon"), and a link field (id: "ctaLink"). Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("gradient", "icon", "link"),
        hasFieldIds("bgGradient", "promoIcon", "ctaLink"),
      ],
    },
    {
      name: "Code, wysiwyg, richtext",
      prompt:
        'Create a Blockstudio block.json for "acme/content-editor". It should have a code field (id: "customCss", language: "css"), a wysiwyg field (id: "body"), and a richtext field (id: "excerpt"). Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("code", "wysiwyg", "richtext"),
        hasFieldIds("customCss", "body", "excerpt"),
      ],
    },
    {
      name: "Classes, attributes, message",
      prompt:
        'Create a Blockstudio block.json for "acme/styled". It should have a classes field (id: "wrapperClasses") for Tailwind classes, an attributes field (id: "customAttrs"), and a message field (id: "info") with content "This block is {block.title}". Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldTypes("classes", "attributes", "message"),
        hasFieldIds("wrapperClasses", "customAttrs", "info"),
      ],
    },
    {
      name: "Fallback and help",
      prompt:
        'Create a Blockstudio block.json for "acme/profile". It should have a text field (id: "name") with a fallback value of "Anonymous" and help text "Enter the display name". Also include a number field (id: "age") with a fallback of 25. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("name", "age"),
        (() => {
          return (output: string) => {
            const hasFallback = output.includes('"fallback"');
            const hasHelp = output.includes('"help"');
            const score = (hasFallback ? 0.5 : 0) + (hasHelp ? 0.5 : 0);
            return {
              name: "FallbackHelp",
              score,
              details: `fallback: ${hasFallback}, help: ${hasHelp}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Populate options",
      prompt:
        'Create a Blockstudio block.json for "acme/post-picker". It should have a select field (id: "selectedPost") that uses the populate property with type "query" to populate options from posts, and another select field (id: "dynamicOptions") with populate type "function" referencing "acme_get_options". Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("selectedPost", "dynamicOptions"),
        (() => {
          return (output: string) => {
            const hasPopulate = output.includes('"populate"');
            const hasQuery = output.includes('"query"');
            const hasFunction = output.includes('"function"');
            const score =
              (hasPopulate ? 0.34 : 0) +
              (hasQuery ? 0.33 : 0) +
              (hasFunction ? 0.33 : 0);
            return {
              name: "Populate",
              score: Math.min(score, 1),
              details: `populate: ${hasPopulate}, query: ${hasQuery}, function: ${hasFunction}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Hidden field",
      prompt:
        'Create a Blockstudio block.json for "acme/internal". It should have a text field (id: "trackingId") with hidden set to true, and a visible text field (id: "label"). Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("trackingId", "label"),
        (() => {
          return (output: string) => {
            const hasHidden = output.includes('"hidden"');
            return {
              name: "Hidden",
              score: hasHidden ? 1 : 0,
              details: hasHidden ? "hidden property found" : "No hidden property",
            };
          };
        })(),
      ],
    },
    {
      name: "Variations",
      prompt:
        'Create a Blockstudio block.json for "acme/alert" with a text field (id: "message") and a select field (id: "type"). Define two variations: one called "Success" with type defaulting to "success", and one called "Error" with type defaulting to "error". Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("message", "type"),
        (() => {
          return (output: string) => {
            const hasVariations = output.includes('"variations"');
            const hasSuccess = output.includes('"success"') || output.includes("Success");
            const hasError = output.includes('"error"') || output.includes("Error");
            const score =
              (hasVariations ? 0.5 : 0) +
              (hasSuccess && hasError ? 0.5 : 0);
            return {
              name: "Variations",
              score,
              details: `variations: ${hasVariations}, success: ${hasSuccess}, error: ${hasError}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Transforms",
      prompt:
        'Create a Blockstudio block.json for "acme/callout" with a text field (id: "content"). Add transforms that allow: a prefix transform using "!" character, and a block-to-block transform from core/paragraph. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("content"),
        (() => {
          return (output: string) => {
            const hasTransforms = output.includes('"transforms"');
            const hasPrefix = output.includes('"prefix"');
            const hasBlock = output.includes('"block"') || output.includes("core/paragraph");
            const score =
              (hasTransforms ? 0.34 : 0) +
              (hasPrefix ? 0.33 : 0) +
              (hasBlock ? 0.33 : 0);
            return {
              name: "Transforms",
              score: Math.min(score, 1),
              details: `transforms: ${hasTransforms}, prefix: ${hasPrefix}, block: ${hasBlock}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Block-level conditions",
      prompt:
        'Create a Blockstudio block.json for "acme/product-cta" with a text field (id: "buttonText"). Add a blockstudio.conditions array that restricts this block to only appear on the "product" post type. Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("buttonText"),
        (() => {
          return (output: string) => {
            const hasConditions = output.includes('"conditions"');
            const hasPostType = output.includes('"postType"') || output.includes('"post_type"');
            const hasProduct = output.includes('"product"');
            const score =
              (hasConditions ? 0.34 : 0) +
              (hasPostType ? 0.33 : 0) +
              (hasProduct ? 0.33 : 0);
            return {
              name: "BlockConditions",
              score: Math.min(score, 1),
              details: `conditions: ${hasConditions}, postType: ${hasPostType}, product: ${hasProduct}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Custom SVG icon",
      prompt:
        'Create a Blockstudio block.json for "acme/diamond". Use a custom inline SVG icon in the blockstudio.icon property (a simple diamond shape SVG). Include a text field (id: "label"). Show the block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("label"),
        (() => {
          return (output: string) => {
            const hasSvg = output.includes("<svg") && output.includes("</svg>");
            const hasIcon = output.includes('"icon"');
            const score = (hasIcon ? 0.5 : 0) + (hasSvg ? 0.5 : 0);
            return {
              name: "SvgIcon",
              score,
              details: `icon key: ${hasIcon}, SVG markup: ${hasSvg}`,
            };
          };
        })(),
      ],
    },
    {
      name: "Initialization file",
      prompt:
        'Create a Blockstudio block with an init.php file that registers a custom post type "portfolio" using register_post_type. Also include a basic block.json for "acme/portfolio-grid" with a number field (id: "columns"). Show both init.php and block.json.',
      scorers: [
        jsonParse(),
        jsonSchema("block"),
        hasFieldIds("columns"),
        requiredFiles("block.json", "init.php"),
        (() => {
          return (output: string) => {
            const hasRegister = output.includes("register_post_type");
            const hasPortfolio = output.includes("portfolio");
            const score = (hasRegister ? 0.5 : 0) + (hasPortfolio ? 0.5 : 0);
            return {
              name: "InitFile",
              score,
              details: `register_post_type: ${hasRegister}, portfolio: ${hasPortfolio}`,
            };
          };
        })(),
      ],
    },
  ],
};

export default suite;

import type { EvalSuite } from "./types.ts";
import { jsonParse } from "./scorers/json-parse.ts";
import { patternStructure, requiredFiles } from "./scorers/structure.ts";
import { templateVars } from "./scorers/php-syntax.ts";

const suite: EvalSuite = {
  name: "Pattern Evals",
  cases: [
    {
      name: "Simple pattern",
      prompt:
        'Create a Blockstudio pattern called "hero-section" with title "Hero Section". Include the pattern.json and an index.php template with a heading, paragraph, and button using Blockstudio page HTML syntax.',
      scorers: [
        jsonParse(),
        patternStructure("name", "title"),
        requiredFiles("pattern.json", "index.php"),
      ],
    },
    {
      name: "Categories and keywords",
      prompt:
        'Create the complete pattern.json file for a Blockstudio pattern called "Feature Grid". It must include categories ["featured", "layout"] and keywords ["features", "grid", "cards"]. Show the complete pattern.json file.',
      scorers: [
        jsonParse(),
        patternStructure("name", "title", "categories", "keywords"),
        (() => {
          return (output: string) => {
            const hasFeatured = output.includes('"featured"');
            const hasLayout = output.includes('"layout"');
            const categoriesOk = hasFeatured && hasLayout;
            return {
              name: "CatKeywords",
              score: categoriesOk ? 1 : 0.5,
              details: categoriesOk
                ? "Categories correct"
                : "Missing expected categories",
            };
          };
        })(),
      ],
    },
    {
      name: "Multiple core blocks in HTML",
      prompt:
        'Create a Blockstudio pattern with an index.php template that contains: two columns with a heading and paragraph in each column, and a buttons group with two buttons below. Use Blockstudio page HTML syntax (e.g. <block name="core/columns">). Show both pattern.json and index.php.',
      scorers: [
        jsonParse(),
        patternStructure("name", "title"),
        requiredFiles("pattern.json", "index.php"),
        templateVars("core/columns", "core/column", "core/button"),
      ],
    },
    {
      name: "viewportWidth",
      prompt:
        'Create a Blockstudio pattern.json for a "Wide Banner" pattern with viewportWidth set to 1400 so it previews at 1400px width in the inserter. Show only the pattern.json.',
      scorers: [
        jsonParse(),
        patternStructure("name", "title", "viewportWidth"),
        (() => {
          return (output: string) => {
            const has1400 = output.includes("1400");
            return {
              name: "ViewportWidth",
              score: has1400 ? 1 : 0,
              details: has1400 ? "1400 value found" : "No 1400 value",
            };
          };
        })(),
      ],
    },
    {
      name: "blockTypes and postTypes",
      prompt:
        'Create a Blockstudio pattern.json for a "Product Hero" pattern. Restrict it to only the "product" post type using postTypes, and associate it with the "acme/product-layout" block using blockTypes. Show the pattern.json.',
      scorers: [
        jsonParse(),
        patternStructure("name", "title"),
        (() => {
          return (output: string) => {
            const hasBlockTypes = output.includes('"blockTypes"');
            const hasPostTypes = output.includes('"postTypes"');
            const score = (hasBlockTypes ? 0.5 : 0) + (hasPostTypes ? 0.5 : 0);
            return {
              name: "TypeRestrictions",
              score,
              details: `blockTypes: ${hasBlockTypes}, postTypes: ${hasPostTypes}`,
            };
          };
        })(),
      ],
    },
    {
      name: "inserter: false",
      prompt:
        'Create a Blockstudio pattern.json for a "Hidden Helper" pattern that should not appear in the block inserter. Set inserter to false. Show the pattern.json.',
      scorers: [
        jsonParse(),
        patternStructure("name", "title"),
        (() => {
          return (output: string) => {
            const hasInserter =
              output.includes('"inserter"') &&
              (output.includes(": false") || output.includes(":false"));
            return {
              name: "InserterFalse",
              score: hasInserter ? 1 : 0,
              details: hasInserter
                ? "inserter: false found"
                : "No inserter: false",
            };
          };
        })(),
      ],
    },
    {
      name: "HTML-to-block elements",
      prompt:
        'Create a Blockstudio pattern with an index.php template that uses native HTML elements that map to blocks: a <details> element with a <summary>, a <table> with rows and cells, and an <hr> element. Show both pattern.json and index.php.',
      scorers: [
        jsonParse(),
        patternStructure("name", "title"),
        requiredFiles("pattern.json", "index.php"),
        templateVars("<details", "<table", "<hr"),
      ],
    },
  ],
};

export default suite;

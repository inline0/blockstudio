import { readFileSync } from "fs";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";
import Ajv from "ajv";
import type { Scorer } from "../types.ts";
import { extractByLanguage, extractByFilename } from "../utils/extract.ts";

function stripJsonComments(text: string): string {
  return text
    .split("\n")
    .filter((line) => !line.trimStart().startsWith("//"))
    .join("\n");
}

const __dirname = dirname(fileURLToPath(import.meta.url));
const ajv = new Ajv({ allErrors: true, strict: false });

// Load page schema from docs source of truth (static object, no dependencies)
function loadPageSchema(): Record<string, unknown> {
  const raw = readFileSync(
    resolve(__dirname, "../../docs/src/schemas/page.ts"),
    "utf-8"
  );
  const match = raw.match(/export const page = (\{[\s\S]*\});?\s*$/);
  if (!match) throw new Error("Could not parse page schema from docs");
  const schema = new Function(`return ${match[1]}`)() as Record<string, unknown>;
  // AJV doesn't support draft-04, strip the $schema property
  delete schema.$schema;
  return schema;
}

// Simplified block schema for eval validation.
// The full schema in docs/src/schemas/schema.ts is async (fetches WP block.json
// from GitHub at runtime) so we use a minimal version here.
const blockSchema = {
  type: "object",
  required: ["name", "title", "blockstudio"],
  properties: {
    name: {
      type: "string",
      pattern: "^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$",
    },
    title: { type: "string", minLength: 1 },
    category: { type: "string" },
    icon: { type: "string" },
    description: { type: "string" },
    keywords: { type: "array", items: { type: "string" } },
    blockstudio: {
      type: "object",
      required: ["attributes"],
      properties: {
        attributes: {
          type: "array",
          items: {
            type: "object",
            properties: {
              id: { type: "string" },
              type: { type: "string" },
              label: { type: "string" },
            },
          },
        },
        interactivity: {
          oneOf: [
            { type: "boolean" },
            {
              type: "object",
              properties: {
                enqueue: { type: "boolean" },
              },
            },
          ],
        },
      },
    },
  },
};

// No pattern schema exists in docs
const patternSchema = {
  type: "object",
  required: ["name", "title"],
  properties: {
    name: { type: "string", minLength: 1 },
    title: { type: "string", minLength: 1 },
    description: { type: "string" },
    categories: { type: "array", items: { type: "string" } },
    keywords: { type: "array", items: { type: "string" } },
  },
};

const validators = {
  block: ajv.compile(blockSchema),
  page: ajv.compile(loadPageSchema()),
  pattern: ajv.compile(patternSchema),
};

type SchemaType = keyof typeof validators;

export function jsonSchema(schemaType: SchemaType): Scorer {
  const schemaNames: Record<SchemaType, string> = {
    block: "BlockSchema",
    page: "PageSchema",
    pattern: "PatternSchema",
  };

  const fileHints: Record<SchemaType, string> = {
    block: "block.json",
    page: "page.json",
    pattern: "pattern.json",
  };

  return (output: string) => {
    const name = schemaNames[schemaType];
    const validate = validators[schemaType];

    let blocks = extractByFilename(output, fileHints[schemaType]);
    if (blocks.length === 0) {
      blocks = extractByLanguage(output, "json");
    }

    if (blocks.length === 0) {
      return { name, score: 0, details: "No JSON blocks found" };
    }

    for (const block of blocks) {
      let parsed;
      try {
        parsed = JSON.parse(stripJsonComments(block.content));
      } catch {
        continue;
      }

      if (validate(parsed)) {
        return { name, score: 1, details: "Valid" };
      }
    }

    for (const block of blocks) {
      let parsed;
      try {
        parsed = JSON.parse(stripJsonComments(block.content));
      } catch {
        continue;
      }

      validate(parsed);
      const errors = validate.errors
        ?.map((e) => `${e.instancePath} ${e.message}`)
        .join("; ");
      return { name, score: 0, details: errors || "Validation failed" };
    }

    return { name, score: 0, details: "No parseable JSON blocks" };
  };
}

import { readFileSync, writeFileSync, existsSync } from "fs";
import { dirname, join } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const DATA_DIR = join(__dirname, "../data");
const CONTENT_DIR = join(__dirname, "../content/docs");

interface SchemaProperty {
  type: string | string[];
  description?: string;
  descriptionFilter?: string;
  default?: unknown;
  example?: unknown;
  properties?: Record<string, SchemaProperty>;
  items?: { anyOf?: SchemaProperty[] };
  anyOf?: SchemaProperty[];
  const?: unknown;
  enum?: string[];
}

interface Schema {
  title: string;
  properties: Record<string, SchemaProperty>;
  definitions?: Record<string, SchemaProperty>;
}

function loadSchema(name: string): Schema {
  const filePath = join(DATA_DIR, `${name}.schema.json`);
  if (!existsSync(filePath)) {
    console.error(`Schema not found: ${filePath}`);
    console.error("Run 'npm run fetch-schemas' first");
    process.exit(1);
  }
  return JSON.parse(readFileSync(filePath, "utf-8"));
}

function formatExample(value: unknown, type: string | string[]): string {
  if (value === undefined) {
    if (type === "boolean" || (Array.isArray(type) && type.includes("boolean"))) {
      return "true";
    }
    return "// your value here";
  }

  if (typeof value === "boolean") {
    return value ? "true" : "false";
  }

  if (typeof value === "string") {
    return `'${value}'`;
  }

  if (Array.isArray(value)) {
    if (value.length === 0) return "[]";
    if (typeof value[0] === "string") {
      return `['${value.join("', '")}']`;
    }
    return JSON.stringify(value);
  }

  if (typeof value === "object" && value !== null) {
    // Convert to PHP array syntax
    return formatPhpArray(value, 0);
  }

  return String(value);
}

function formatPhpArray(obj: object, indent: number): string {
  const spaces = "  ".repeat(indent + 1);
  const closingSpaces = "  ".repeat(indent);
  const entries = Object.entries(obj).map(([key, val]) => {
    const formattedVal =
      typeof val === "object" && val !== null
        ? formatPhpArray(val, indent + 1)
        : typeof val === "string"
          ? `'${val}'`
          : val;
    return `${spaces}'${key}' => ${formattedVal}`;
  });
  return `[\n${entries.join(",\n")}\n${closingSpaces}]`;
}

function generateSettingsFilters(schema: Schema): string {
  const lines: string[] = [];

  function processProperty(
    prop: SchemaProperty,
    path: string[],
    parentKey?: string
  ) {
    const currentPath = path.join("/");

    // If this property has nested properties, recurse
    if (prop.properties) {
      for (const [key, value] of Object.entries(prop.properties)) {
        processProperty(value, [...path, key], key);
      }
      return;
    }

    // Only output leaf nodes with descriptionFilter
    if (prop.descriptionFilter) {
      const example = formatExample(prop.example, prop.type);

      lines.push(`### ${currentPath}`);
      lines.push("");
      lines.push(prop.descriptionFilter);
      lines.push("");
      lines.push("```php title=\"functions.php\"");
      lines.push(`add_filter('blockstudio/settings/${currentPath}', function() {`);
      lines.push(`  return ${example};`);
      lines.push("});");
      lines.push("```");
      lines.push("");
    }
  }

  for (const [key, value] of Object.entries(schema.properties)) {
    processProperty(value, [key], key);
  }

  return lines.join("\n");
}

function getFieldTypes(schema: Schema): SchemaProperty[] {
  const blockstudioProp = schema.properties?.blockstudio;
  if (!blockstudioProp?.properties?.attributes?.items?.anyOf) {
    return [];
  }
  return blockstudioProp.properties.attributes.items.anyOf;
}

function generateFieldTypeDocs(fieldTypes: SchemaProperty[]): string {
  const lines: string[] = [];

  for (const fieldType of fieldTypes) {
    const typeConst = fieldType.properties?.type?.const;
    if (!typeConst || typeof typeConst !== "string") continue;

    const typeName = typeConst as string;
    const description = fieldType.description || "";

    lines.push(`## ${typeName}`);
    lines.push("");
    if (description) {
      lines.push(description);
      lines.push("");
    }

    // Generate example JSON
    const exampleObj: Record<string, unknown> = { type: typeName };
    const properties = fieldType.properties || {};

    // Add common properties to example
    if (properties.id) exampleObj.id = "myField";
    if (properties.label) exampleObj.label = `My ${typeName} field`;

    // Add type-specific example properties
    if (properties.options) {
      exampleObj.options = [
        { value: "option1", label: "Option 1" },
        { value: "option2", label: "Option 2" },
      ];
    }
    if (properties.min) exampleObj.min = 0;
    if (properties.max) exampleObj.max = 100;
    if (properties.step) exampleObj.step = 1;
    if (properties.rows && typeName === "textarea") exampleObj.rows = 4;
    if (properties.multiple) exampleObj.multiple = false;

    lines.push("```json");
    lines.push(JSON.stringify(exampleObj, null, 2));
    lines.push("```");
    lines.push("");

    // Generate properties table
    const propEntries = Object.entries(properties).filter(
      ([key]) => !["type"].includes(key)
    );

    if (propEntries.length > 0) {
      lines.push("### Properties");
      lines.push("");
      lines.push("| Property | Type | Description |");
      lines.push("|----------|------|-------------|");

      for (const [propName, propDef] of propEntries) {
        const propType = Array.isArray(propDef.type)
          ? propDef.type.join(" \\| ")
          : propDef.type || "any";
        const propDesc = (propDef.description || "").replace(/\|/g, "\\|").replace(/\n/g, " ");
        lines.push(`| \`${propName}\` | \`${propType}\` | ${propDesc} |`);
      }
      lines.push("");
    }
  }

  return lines.join("\n");
}

function updateMdxFile(filePath: string, startMarker: string, endMarker: string, content: string): boolean {
  if (!existsSync(filePath)) {
    console.log(`  File not found: ${filePath}`);
    return false;
  }

  const fileContent = readFileSync(filePath, "utf-8");
  const startIndex = fileContent.indexOf(startMarker);
  const endIndex = fileContent.indexOf(endMarker);

  if (startIndex === -1 || endIndex === -1) {
    console.log(`  Markers not found in ${filePath}`);
    return false;
  }

  const newContent =
    fileContent.substring(0, startIndex + startMarker.length) +
    "\n\n" +
    content +
    "\n" +
    fileContent.substring(endIndex);

  writeFileSync(filePath, newContent);
  return true;
}

function generateSettingsDoc() {
  console.log("Generating settings filters documentation...");

  const schema = loadSchema("blockstudio");
  const content = generateSettingsFilters(schema);

  // Save to data folder for reference
  const outputPath = join(DATA_DIR, "generated-settings-filters.mdx");
  writeFileSync(outputPath, content);
  console.log(`  Generated: ${outputPath}`);

  // Update the actual MDX file
  const mdxPath = join(CONTENT_DIR, "hooks/php.mdx");
  if (updateMdxFile(mdxPath, "{/* GENERATED_SETTINGS_START */}", "{/* GENERATED_SETTINGS_END */}", content)) {
    console.log(`  Updated: ${mdxPath}`);
  }
}

function generateFieldTypesDoc() {
  console.log("Generating field types documentation...");

  const schema = loadSchema("block");
  const fieldTypes = getFieldTypes(schema);

  if (fieldTypes.length === 0) {
    console.log("  No field types found in schema");
    return;
  }

  const content = generateFieldTypeDocs(fieldTypes);

  // Save to data folder for reference
  const outputPath = join(DATA_DIR, "generated-field-types.mdx");
  writeFileSync(outputPath, content);
  console.log(`  Generated: ${outputPath}`);
  console.log(`  Found ${fieldTypes.length} field types`);

  // Update the actual MDX file
  const mdxPath = join(CONTENT_DIR, "attributes/field-types.mdx");
  if (updateMdxFile(mdxPath, "{/* GENERATED_FIELD_TYPES_START */}", "{/* GENERATED_FIELD_TYPES_END */}", content)) {
    console.log(`  Updated: ${mdxPath}`);
  }
}

function main() {
  console.log("Generating documentation from schemas...\n");

  generateSettingsDoc();
  generateFieldTypesDoc();

  console.log("\nDone!");
}

main();

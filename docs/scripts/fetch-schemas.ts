import { writeFileSync, mkdirSync } from "fs";
import { dirname, join } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const DATA_DIR = join(__dirname, "../data");

const SCHEMAS = {
  block: "https://app.blockstudio.dev/schema/block",
  blockstudio: "https://app.blockstudio.dev/schema/blockstudio",
  extend: "https://app.blockstudio.dev/schema/extend",
} as const;

async function fetchSchemas() {
  console.log("Fetching schemas...\n");

  mkdirSync(DATA_DIR, { recursive: true });

  for (const [name, url] of Object.entries(SCHEMAS)) {
    try {
      console.log(`Fetching ${name} from ${url}`);
      const response = await fetch(url);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const schema = await response.json();
      const filePath = join(DATA_DIR, `${name}.schema.json`);

      writeFileSync(filePath, JSON.stringify(schema, null, 2));
      console.log(`  Saved to ${filePath}\n`);
    } catch (error) {
      console.error(`  Error fetching ${name}:`, error);
      process.exit(1);
    }
  }

  console.log("All schemas fetched successfully!");
}

fetchSchemas();

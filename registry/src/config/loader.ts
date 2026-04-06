import { readFile } from "node:fs/promises";
import { dirname, join, resolve } from "node:path";
import { configSchema, type Config } from "./schema.js";

const CONFIG_FILE = "blocks.json";
const MAX_DEPTH = 20;

export async function findConfig(from: string): Promise<string> {
  let dir = resolve(from);

  for (let i = 0; i < MAX_DEPTH; i++) {
    const candidate = join(dir, CONFIG_FILE);
    try {
      await readFile(candidate, "utf-8");
      return candidate;
    } catch {
      const parent = dirname(dir);
      if (parent === dir) break;
      dir = parent;
    }
  }

  throw new Error(
    `Could not find ${CONFIG_FILE}. Run \`blockstudio init\` to create one.`,
  );
}

export async function loadConfig(from: string): Promise<{
  config: Config;
  configPath: string;
  configDir: string;
}> {
  const configPath = await findConfig(from);
  const raw = await readFile(configPath, "utf-8");

  let json: unknown;
  try {
    json = JSON.parse(raw);
  } catch {
    throw new Error(`Invalid JSON in ${configPath}`);
  }

  const result = configSchema.safeParse(json);
  if (!result.success) {
    const issues = result.error.issues
      .map((i) => `  ${i.path.join(".")}: ${i.message}`)
      .join("\n");
    throw new Error(`Invalid ${CONFIG_FILE}:\n${issues}`);
  }

  return {
    config: result.data,
    configPath,
    configDir: dirname(configPath),
  };
}

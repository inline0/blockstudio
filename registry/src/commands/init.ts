import { writeFile } from "node:fs/promises";
import { join } from "node:path";
import chalk from "chalk";
import { exists } from "../utils/fs.js";
import type { Config } from "../config/schema.js";

export type InitOptions = {
  cwd: string;
  directory?: string;
  yes?: boolean;
};

export async function runInit(options: InitOptions): Promise<void> {
  const configPath = join(options.cwd, "blocks.json");

  if (await exists(configPath)) {
    if (!options.yes) {
      throw new Error(
        `blocks.json already exists at ${configPath}. Use --yes to overwrite.`,
      );
    }
  }

  const config: Config = {
    $schema: "https://blockstudio.dev/schema/blocks.json",
    directory: options.directory ?? "blockstudio",
    registries: {},
  };

  await writeFile(configPath, JSON.stringify(config, null, 2) + "\n", "utf-8");

  console.log(chalk.green("Created"), configPath);
}

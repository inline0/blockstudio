import chalk from "chalk";
import { loadConfig } from "../config/loader.js";
import { fetchRegistry, clearCache } from "../registry/fetcher.js";

export type ListOptions = {
  cwd: string;
  namespace?: string;
};

export async function runList(options: ListOptions): Promise<void> {
  clearCache();

  const { config } = await loadConfig(options.cwd);

  const names = options.namespace
    ? [options.namespace]
    : Object.keys(config.registries);

  if (names.length === 0) {
    console.log("No registries configured.");
    return;
  }

  for (const name of names) {
    const url = config.registries[name];
    if (!url) {
      throw new Error(
        `Registry "${name}" not found. Available: ${Object.keys(config.registries).join(", ")}`,
      );
    }

    const registry = await fetchRegistry(url);

    console.log(chalk.bold(`\n${name}`) + chalk.dim(` (${registry.blocks.length} blocks)`));
    console.log();

    const nameWidth = Math.max(...registry.blocks.map((b) => b.name.length), 4);
    const titleWidth = Math.max(
      ...registry.blocks.map((b) => (b.title ?? "").length),
      5,
    );

    console.log(
      chalk.dim(
        "  " +
          "Name".padEnd(nameWidth + 2) +
          "Title".padEnd(titleWidth + 2) +
          "Category".padEnd(14) +
          "Type",
      ),
    );

    for (const block of registry.blocks) {
      console.log(
        "  " +
          block.name.padEnd(nameWidth + 2) +
          (block.title ?? "").padEnd(titleWidth + 2) +
          (block.category ?? "").padEnd(14) +
          (block.type ?? ""),
      );
    }
  }

  console.log();
}

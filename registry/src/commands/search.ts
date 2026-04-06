import chalk from "chalk";
import { loadConfig } from "../config/loader.js";
import { fetchRegistry, clearCache } from "../registry/fetcher.js";
import type { BlockEntry } from "../config/schema.js";

type SearchResult = {
  block: BlockEntry;
  registryName: string;
  score: number;
};

function scoreMatch(query: string, block: BlockEntry): number {
  const q = query.toLowerCase();
  const name = block.name.toLowerCase();
  const title = (block.title ?? "").toLowerCase();
  const desc = (block.description ?? "").toLowerCase();

  if (name === q) return 100;
  if (name.startsWith(q)) return 80;
  if (name.includes(q)) return 60;
  if (title.includes(q)) return 40;
  if (desc.includes(q)) return 20;
  return 0;
}

export type SearchOptions = {
  cwd: string;
};

export async function runSearch(
  query: string,
  options: SearchOptions,
): Promise<void> {
  clearCache();

  const { config } = await loadConfig(options.cwd);
  const results: SearchResult[] = [];

  for (const [name, url] of Object.entries(config.registries)) {
    const registry = await fetchRegistry(url);

    for (const block of registry.blocks) {
      const score = scoreMatch(query, block);
      if (score > 0) {
        results.push({ block, registryName: name, score });
      }
    }
  }

  results.sort((a, b) => b.score - a.score);

  if (results.length === 0) {
    console.log(`No blocks matching "${query}".`);
    return;
  }

  console.log(chalk.bold(`\nResults for "${query}"\n`));

  for (const { block, registryName } of results) {
    console.log(
      `  ${chalk.green(registryName + "/" + block.name)}` +
        (block.title ? chalk.dim(` - ${block.title}`) : ""),
    );
    if (block.description) {
      console.log(chalk.dim(`    ${block.description}`));
    }
  }

  console.log();
}

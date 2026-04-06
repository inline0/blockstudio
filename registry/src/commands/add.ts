import chalk from "chalk";
import { loadConfig } from "../config/loader.js";
import type { Registry } from "../config/schema.js";
import { resolveRegistryRef } from "../config/schema.js";
import { fetchRegistry, clearCache } from "../registry/fetcher.js";
import {
  resolveBlock,
  findBlockAcrossRegistries,
  type RegistryMatch,
} from "../registry/resolver.js";
import { downloadBlocks } from "../registry/downloader.js";
import { writeBlocks, exists } from "../utils/fs.js";
import { getTargetDir, getBlockDir } from "../utils/paths.js";

export type AddOptions = {
  cwd: string;
  yes?: boolean;
  dryRun?: boolean;
  onConflict?: (matches: RegistryMatch[]) => Promise<RegistryMatch>;
};

export function parseBlockArg(arg: string): { namespace?: string; name: string } {
  const parts = arg.split("/");
  if (parts.length === 2) {
    return { namespace: parts[0], name: parts[1] };
  }
  return { name: arg };
}

export async function runAdd(
  blockArgs: string[],
  options: AddOptions,
): Promise<void> {
  clearCache();

  const { config, configDir } = await loadConfig(options.cwd);
  const targetDir = getTargetDir(config, configDir);

  const registryNames = Object.keys(config.registries);
  if (registryNames.length === 0) {
    throw new Error(
      "No registries configured in blocks.json. Add a registry first.",
    );
  }

  const registries = new Map<string, Registry>();
  const headersMap = new Map<string, Record<string, string>>();

  for (const name of registryNames) {
    const { url, headers } = resolveRegistryRef(config.registries[name]);
    const registry = await fetchRegistry(url, headers);
    registries.set(name, registry);
    if (Object.keys(headers).length > 0) {
      headersMap.set(name, headers);
    }
  }

  for (const arg of blockArgs) {
    const { namespace, name } = parseBlockArg(arg);

    let registryName: string;
    let registry: Registry;
    let headers: Record<string, string> | undefined;

    if (namespace) {
      registry = registries.get(namespace)!;
      if (!registry) {
        throw new Error(
          `Registry "${namespace}" not found. Available: ${registryNames.join(", ")}`,
        );
      }
      registryName = namespace;
      headers = headersMap.get(namespace);

      if (!registry.blocks.find((b) => b.name === name)) {
        throw new Error(
          `Block "${name}" not found in registry "${namespace}".`,
        );
      }
    } else {
      const matches = findBlockAcrossRegistries(name, registries, headersMap);

      if (matches.length === 0) {
        throw new Error(
          `Block "${name}" not found in any registry.`,
        );
      }

      let match: RegistryMatch;
      if (matches.length === 1) {
        match = matches[0];
      } else if (options.onConflict) {
        match = await options.onConflict(matches);
      } else if (options.yes) {
        match = matches[0];
      } else {
        throw new Error(
          `Block "${name}" found in multiple registries: ${matches.map((m) => m.registryName).join(", ")}. Specify one: blockstudio add ${matches[0].registryName}/${name}`,
        );
      }

      registryName = match.registryName;
      registry = registries.get(registryName)!;
      headers = match.headers;
    }

    const resolved = resolveBlock(name, registry, registryName, headers);

    for (const block of resolved) {
      const blockDir = getBlockDir(config, configDir, block.name);
      if (await exists(blockDir)) {
        if (options.dryRun) {
          console.log(chalk.yellow("Would overwrite"), block.name);
          continue;
        }
        if (!options.yes) {
          throw new Error(
            `Block "${block.name}" already exists at ${blockDir}. Use --yes to overwrite.`,
          );
        }
      }
    }

    if (options.dryRun) {
      console.log(chalk.blue("Would install:"));
      for (const block of resolved) {
        console.log(`  ${block.name} (${block.files.length} files)`);
      }
      continue;
    }

    console.log(chalk.blue("Downloading"), `${registryName}/${name}...`);
    const downloaded = await downloadBlocks(resolved);
    const written = await writeBlocks(downloaded, targetDir);

    for (const [blockName, files] of written) {
      console.log(chalk.green("Installed"), `${blockName} (${files.length} files)`);
    }
  }
}

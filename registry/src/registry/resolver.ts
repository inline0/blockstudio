import type { BlockEntry, Registry } from "../config/schema.js";

export type ResolvedBlock = BlockEntry & {
  registryName: string;
  registryBaseUrl: string;
};

export function resolveBlock(
  blockName: string,
  registry: Registry,
  registryName: string,
): ResolvedBlock[] {
  const blockMap = new Map(registry.blocks.map((b) => [b.name, b]));
  const resolved: ResolvedBlock[] = [];
  const visited = new Set<string>();
  const visiting = new Set<string>();

  function visit(name: string): void {
    if (visited.has(name)) return;

    if (visiting.has(name)) {
      throw new Error(`Circular dependency detected: ${name}`);
    }

    const block = blockMap.get(name);
    if (!block) {
      throw new Error(
        `Dependency "${name}" not found in registry "${registryName}".`,
      );
    }

    visiting.add(name);

    for (const dep of block.dependencies ?? []) {
      visit(dep);
    }

    visiting.delete(name);
    visited.add(name);

    resolved.push({
      ...block,
      registryName,
      registryBaseUrl: registry.baseUrl,
    });
  }

  visit(blockName);
  return resolved;
}

export type RegistryMatch = {
  block: BlockEntry;
  registryName: string;
  registryBaseUrl: string;
};

export function findBlockAcrossRegistries(
  blockName: string,
  registries: Map<string, Registry>,
): RegistryMatch[] {
  const matches: RegistryMatch[] = [];

  for (const [name, registry] of registries) {
    const block = registry.blocks.find((b) => b.name === blockName);
    if (block) {
      matches.push({
        block,
        registryName: name,
        registryBaseUrl: registry.baseUrl,
      });
    }
  }

  return matches;
}

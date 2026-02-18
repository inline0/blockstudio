import { cloneDeep } from 'lodash-es';
import { replaceEmptyStringsWithFalse } from '@/blocks/utils/replace-empty-strings-with-false';

const cache = new Map<string, string>();
const cacheByBlock = new Map<string, Set<string>>();
const preloadQueues = new Map<string, string[]>();

const sortedStringify = (value: unknown): string => {
  if (value === null || value === undefined) return JSON.stringify(value);
  if (typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) {
    return '[' + value.map(sortedStringify).join(',') + ']';
  }
  const obj = value as Record<string, unknown>;
  const keys = Object.keys(obj).sort();
  const pairs = keys.map((k) => JSON.stringify(k) + ':' + sortedStringify(obj[k]));
  return '{' + pairs.join(',') + '}';
};

export const computeHash = (
  blockName: string,
  attributes: unknown,
): string => {
  const cloned = cloneDeep(attributes) as Record<string, unknown>;
  Object.keys(cloned).forEach((key) => {
    if (key.startsWith('BLOCKSTUDIO_RICH_TEXT')) {
      delete cloned[key];
    }
  });

  const attrs = replaceEmptyStringsWithFalse(
    cloned as Parameters<typeof replaceEmptyStringsWithFalse>[0],
  );

  return sortedStringify({ blockName, attrs })
    .replaceAll('{', '_')
    .replaceAll('}', '_')
    .replaceAll('[', '_')
    .replaceAll(']', '_')
    .replaceAll('"', '_')
    .replaceAll('/', '__')
    .replaceAll(' ', '_')
    .replaceAll(',', '_')
    .replaceAll(':', '_')
    .replaceAll('\\', '_');
};

export const renderCache = {
  initFromPreload() {
    const preloaded = window.blockstudio?.blockstudioBlocks;
    if (!preloaded) return;

    const entries = Array.isArray(preloaded)
      ? preloaded
      : Object.values(preloaded);

    entries.forEach((data) => {
      if (data.rendered && data.blockName) {
        const queue = preloadQueues.get(data.blockName) || [];
        queue.push(data.rendered);
        preloadQueues.set(data.blockName, queue);
      }
    });
  },

  claimPreloaded(blockName: string): string | undefined {
    const queue = preloadQueues.get(blockName);
    if (!queue || queue.length === 0) return undefined;
    return queue.shift();
  },

  get(hash: string): string | undefined {
    return cache.get(hash);
  },

  set(hash: string, rendered: string, blockName?: string) {
    cache.set(hash, rendered);
    if (blockName) {
      const hashes = cacheByBlock.get(blockName) || new Set();
      hashes.add(hash);
      cacheByBlock.set(blockName, hashes);
    }
  },

  addPreloads(entries: Array<{ rendered: string; blockName: string }>) {
    entries.forEach((data) => {
      if (data.rendered && data.blockName) {
        const queue = preloadQueues.get(data.blockName) || [];
        queue.push(data.rendered);
        preloadQueues.set(data.blockName, queue);
      }
    });
  },

  replacePreloads(entries: Array<{ rendered: string; blockName: string }>) {
    const affectedTypes = new Set<string>();
    entries.forEach((data) => {
      if (data.blockName) affectedTypes.add(data.blockName);
    });

    for (const blockName of affectedTypes) {
      preloadQueues.delete(blockName);

      const hashes = cacheByBlock.get(blockName);
      if (hashes) {
        for (const hash of hashes) cache.delete(hash);
        cacheByBlock.delete(blockName);
      }
    }

    entries.forEach((data) => {
      if (data.rendered && data.blockName) {
        const queue = preloadQueues.get(data.blockName) || [];
        queue.push(data.rendered);
        preloadQueues.set(data.blockName, queue);
      }
    });
  },
};

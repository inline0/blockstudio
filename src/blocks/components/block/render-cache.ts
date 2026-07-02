import { cloneDeep } from 'lodash-es';
import { replaceEmptyStringsWithFalse } from '@/blocks/utils/replace-empty-strings-with-false';
import type { PreloadEntry } from '../../../canvas/types';

const cache = new Map<string, string>();
const cacheByBlock = new Map<string, Set<string>>();
const preloadQueues = new Map<string, string[]>();
const claimedByClient = new Map<string, string | undefined>();

const getClientClaimKey = (clientId: string, mode: string): string =>
  `${mode}:${clientId}`;

const getQueueKey = (blockName: string, mode: string): string =>
  `${mode}:${blockName}`;

const getBlockstudioFieldDefaults = (
  blockName: string,
): Record<string, unknown> => {
  if (typeof window === 'undefined') return {};

  const blocks = window.blockstudioAdmin?.data?.blocksNative as unknown as
    | Record<
        string,
        { attributes?: Record<string, { id?: string; default?: unknown }> }
      >
    | undefined;
  const block = blocks?.[blockName];

  if (!block?.attributes) return {};

  return Object.values(block.attributes).reduce<Record<string, unknown>>(
    (defaults, attribute) => {
      if (attribute.id) {
        defaults[attribute.id] = attribute.default;
      }
      return defaults;
    },
    {},
  );
};

const getRegisteredAttributeDefaults = (
  blockName: string,
): Record<string, unknown> => {
  if (typeof window === 'undefined') return {};

  const getBlockType = (
    window as unknown as {
      wp?: {
        blocks?: {
          getBlockType?: (
            name: string,
          ) => {
            attributes?: Record<string, { default?: unknown }>;
          };
        };
      };
    }
  ).wp?.blocks?.getBlockType;

  const attributes = getBlockType?.(blockName)?.attributes;
  if (!attributes) return {};

  return Object.entries(attributes).reduce<Record<string, unknown>>(
    (defaults, [key, attribute]) => {
      if (key !== 'blockstudio' && 'default' in attribute) {
        defaults[key] = attribute.default;
      }
      return defaults;
    },
    {},
  );
};

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
  mode: string = 'editor',
): string => {
  let cloned = cloneDeep(attributes) as Record<string, unknown> | unknown[];
  if (Array.isArray(cloned)) {
    cloned = {};
  }

  const clonedRecord = cloned as Record<string, unknown>;
  const nestedAttributes =
    clonedRecord.blockstudio &&
    typeof clonedRecord.blockstudio === 'object' &&
    (clonedRecord.blockstudio as Record<string, unknown>).attributes &&
    typeof (clonedRecord.blockstudio as Record<string, unknown>).attributes ===
      'object' &&
    !Array.isArray(
      (clonedRecord.blockstudio as Record<string, unknown>).attributes,
    )
      ? ((clonedRecord.blockstudio as Record<string, unknown>)
          .attributes as Record<string, unknown>)
      : undefined;

  const fieldDefaults = getBlockstudioFieldDefaults(blockName);
  const registeredDefaults = getRegisteredAttributeDefaults(blockName);
  const fieldIds = new Set([...Object.keys(fieldDefaults)]);
  const canonicalFieldAttributes: Record<string, unknown> = {};
  const isMatchingDefault = (
    defaults: Record<string, unknown>,
    key: string,
    value: unknown,
  ): boolean =>
    Object.prototype.hasOwnProperty.call(defaults, key) &&
    sortedStringify(value) === sortedStringify(defaults[key]);
  const isFieldDefaultValue = (key: string, value: unknown): boolean =>
    isMatchingDefault(fieldDefaults, key, value);

  if (nestedAttributes) {
    Object.entries(nestedAttributes).forEach(([key, value]) => {
      if (!isFieldDefaultValue(key, value)) {
        canonicalFieldAttributes[key] = value;
      }
    });

    delete (clonedRecord.blockstudio as Record<string, unknown>).attributes;

    if (Object.keys(clonedRecord.blockstudio as object).length === 0) {
      delete clonedRecord.blockstudio;
    }
  } else {
    fieldIds.forEach((key) => {
      if (key in clonedRecord && !isFieldDefaultValue(key, clonedRecord[key])) {
        canonicalFieldAttributes[key] = clonedRecord[key];
      }
    });
  }

  fieldIds.forEach((key) => {
    if (key in clonedRecord) {
      delete clonedRecord[key];
    }
  });

  if (Object.keys(canonicalFieldAttributes).length > 0) {
    clonedRecord.__blockstudioAttributes = canonicalFieldAttributes;
  }

  Object.entries(registeredDefaults).forEach(([key, defaultValue]) => {
    if (
      key in clonedRecord &&
      sortedStringify(clonedRecord[key]) === sortedStringify(defaultValue)
    ) {
      delete clonedRecord[key];
    }
  });

  Object.keys(clonedRecord).forEach((key) => {
    if (key.startsWith('BLOCKSTUDIO_RICH_TEXT')) {
      delete clonedRecord[key];
    }
  });
  if (clonedRecord.metadata && typeof clonedRecord.metadata === 'object') {
    delete (clonedRecord.metadata as Record<string, unknown>).blockVisibility;
    if (Object.keys(clonedRecord.metadata as object).length === 0) {
      delete clonedRecord.metadata;
    }
  }

  const attrs = replaceEmptyStringsWithFalse(
    clonedRecord as Parameters<typeof replaceEmptyStringsWithFalse>[0],
  );

  return sortedStringify({ blockName, attrs, mode })
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

    this.addPreloads(entries);
  },

  claimPreloaded(
    blockName: string,
    clientId?: string,
    attributes?: unknown,
    mode: string = 'editor',
  ): string | undefined {
    const clientClaimKey = clientId
      ? getClientClaimKey(clientId, mode)
      : undefined;

    if (clientClaimKey && claimedByClient.has(clientClaimKey)) {
      return claimedByClient.get(clientClaimKey);
    }

    if (attributes) {
      const cached = this.get(computeHash(blockName, attributes, mode));
      if (cached) {
        if (clientClaimKey) {
          claimedByClient.set(clientClaimKey, cached);
        }
        return cached;
      }
    }

    const queue = preloadQueues.get(getQueueKey(blockName, mode));
    if (!queue || queue.length === 0) return undefined;
    const rendered = queue.shift();
    if (clientClaimKey) {
      claimedByClient.set(clientClaimKey, rendered);
    }
    return rendered;
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

  addPreloads(entries: PreloadEntry[]) {
    entries.forEach((data) => {
      if (!data.rendered || !data.blockName) {
        return;
      }

      if (
        data.attributes &&
        typeof data.attributes === 'object' &&
        !Array.isArray(data.attributes)
      ) {
        const hash = computeHash(
          data.blockName,
          data.attributes,
          data.mode || 'editor',
        );
        this.set(hash, data.rendered, data.blockName);
        return;
      }

      const queueKey = getQueueKey(data.blockName, data.mode || 'editor');
      const queue = preloadQueues.get(queueKey) || [];
      queue.push(data.rendered);
      preloadQueues.set(queueKey, queue);
    });
  },

  replacePreloads(entries: PreloadEntry[]) {
    const affectedTypes = new Set<string>();
    entries.forEach((data) => {
      if (data.blockName) affectedTypes.add(data.blockName);
    });

    for (const blockName of affectedTypes) {
      preloadQueues.delete(getQueueKey(blockName, 'editor'));
      preloadQueues.delete(getQueueKey(blockName, 'preview'));

      const hashes = cacheByBlock.get(blockName);
      if (hashes) {
        for (const hash of hashes) cache.delete(hash);
        cacheByBlock.delete(blockName);
      }
    }

    this.addPreloads(entries);
  },
};

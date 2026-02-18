interface Page {
  title: string;
  slug: string;
  name: string;
  content: string;
}

interface BlockItem {
  title: string;
  name: string;
  content: string;
}

interface PreloadEntry {
  rendered: string;
  blockName: string;
}

export interface QueueEntry {
  fingerprint: string;
  pages: Page[];
  blocks: BlockItem[];
  blockstudioBlocks: PreloadEntry[];
  changedBlocks: string[];
  changedPages: string[];
  blocksNative?: Record<string, unknown>;
  tailwindCss?: string;
}

export interface UpdateQueueCallbacks {
  onFlush: (entry: QueueEntry, pendingSwaps: Set<string>) => void;
  onAllSwapsComplete: (entry: QueueEntry) => void;
}

interface UpdateQueue {
  enqueue: (entry: QueueEntry) => void;
  reportSwapComplete: (slug: string) => void;
  destroy: () => void;
}

const DEBOUNCE_MS = 50;
const SWAP_TIMEOUT_MS = 8000;

function mergeByKey<T>(a: T[], b: T[], key: (item: T) => string): T[] {
  const map = new Map<string, T>();
  for (const item of a) map.set(key(item), item);
  for (const item of b) map.set(key(item), item);
  return Array.from(map.values());
}

function coalesce(existing: QueueEntry, incoming: QueueEntry): QueueEntry {
  const changedBlocks = new Set([
    ...existing.changedBlocks,
    ...incoming.changedBlocks,
  ]);
  const changedPages = new Set([
    ...existing.changedPages,
    ...incoming.changedPages,
  ]);

  const mergedBsBlocks = new Map<string, PreloadEntry>();
  for (const entry of existing.blockstudioBlocks) {
    mergedBsBlocks.set(entry.blockName, entry);
  }
  for (const entry of incoming.blockstudioBlocks) {
    mergedBsBlocks.set(entry.blockName, entry);
  }

  return {
    fingerprint: incoming.fingerprint,
    pages: mergeByKey(existing.pages, incoming.pages, (p) => p.slug),
    blocks: mergeByKey(existing.blocks, incoming.blocks, (b) => b.name),
    blockstudioBlocks: Array.from(mergedBsBlocks.values()),
    changedBlocks: Array.from(changedBlocks),
    changedPages: Array.from(changedPages),
    blocksNative: incoming.blocksNative
      ? { ...(existing.blocksNative || {}), ...incoming.blocksNative }
      : existing.blocksNative,
    tailwindCss:
      incoming.tailwindCss !== undefined
        ? incoming.tailwindCss
        : existing.tailwindCss,
  };
}

export function createUpdateQueue(
  callbacks: UpdateQueueCallbacks,
): UpdateQueue {
  let pending: QueueEntry | null = null;
  let processing: QueueEntry | null = null;
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;
  let swapTimer: ReturnType<typeof setTimeout> | null = null;
  let pendingSwaps = new Set<string>();
  let destroyed = false;

  const completeBatch = (): void => {
    if (!processing) return;

    if (swapTimer !== null) {
      clearTimeout(swapTimer);
      swapTimer = null;
    }

    const completed = processing;
    processing = null;
    pendingSwaps = new Set();

    callbacks.onAllSwapsComplete(completed);

    if (pending && !destroyed) {
      flush();
    }
  };

  const flush = (): void => {
    if (processing || !pending || destroyed) return;

    const entry = pending;
    pending = null;
    processing = entry;

    pendingSwaps = new Set<string>();

    callbacks.onFlush(entry, pendingSwaps);

    if (pendingSwaps.size === 0) {
      completeBatch();
      return;
    }

    swapTimer = setTimeout(() => {
      console.warn(
        '[update-queue] swap timeout, forcing completion. Pending:',
        Array.from(pendingSwaps),
      );
      completeBatch();
    }, SWAP_TIMEOUT_MS);
  };

  const enqueue = (entry: QueueEntry): void => {
    if (destroyed) return;

    if (pending) {
      pending = coalesce(pending, entry);
    } else {
      pending = entry;
    }

    if (debounceTimer !== null) {
      clearTimeout(debounceTimer);
    }

    if (processing) return;

    debounceTimer = setTimeout(() => {
      debounceTimer = null;
      flush();
    }, DEBOUNCE_MS);
  };

  const reportSwapComplete = (slug: string): void => {
    if (!processing) return;

    pendingSwaps.delete(slug);

    if (pendingSwaps.size === 0) {
      completeBatch();
    }
  };

  const destroy = (): void => {
    destroyed = true;
    if (debounceTimer !== null) clearTimeout(debounceTimer);
    if (swapTimer !== null) clearTimeout(swapTimer);
    pending = null;
    processing = null;
    pendingSwaps = new Set();
  };

  return { enqueue, reportSwapComplete, destroy };
}

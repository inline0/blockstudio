import { select, subscribe } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';

import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlock } from '@/types/types';

declare global {
  interface Window {
    YoastSEO?: {
      app?: {
        registerPlugin: (
          name: string,
          opts: { status: string },
        ) => void;
        registerModification: (
          type: string,
          cb: (c: string) => string,
          name: string,
          priority: number,
        ) => void;
        refresh: () => void;
      };
    };
    rankMath?: unknown;
    rankMathEditor?: unknown;
  }
}

const PLUGIN_NAME = 'blockstudioSeoIntegration';
const TEXT_TYPES = new Set(['text', 'textarea', 'richtext', 'wysiwyg']);
const blocks = window.blockstudioAdmin.data
  .blocksNative as unknown as Record<string, BlockstudioBlock>;

let cachedContent = '';
let seoPluginDetected = false;

function stripHtml(html: string): string {
  const div = document.createElement('div');
  div.innerHTML = html;
  return div.textContent || '';
}

function extractText(
  values: Record<string, unknown>,
  fieldDefs: BlockstudioAttribute[],
  disabled: string[],
): string {
  const parts: string[] = [];

  for (const def of fieldDefs) {
    if (!def.id || !def.type || disabled.includes(def.id)) continue;

    const value = values?.[def.id];
    if (!value) continue;

    if (TEXT_TYPES.has(def.type)) {
      const text = typeof value === 'string' ? stripHtml(value) : '';
      if (text) parts.push(text);
    } else if (
      def.type === 'link' &&
      typeof value === 'object' &&
      (value as Record<string, unknown>)?.title
    ) {
      parts.push((value as Record<string, string>).title);
    } else if (
      def.type === 'repeater' &&
      Array.isArray(value) &&
      (def as unknown as { attributes?: BlockstudioAttribute[] }).attributes
    ) {
      for (const row of value) {
        if (row && typeof row === 'object') {
          parts.push(
            extractText(
              row as Record<string, unknown>,
              (def as unknown as { attributes: BlockstudioAttribute[] })
                .attributes,
              disabled,
            ),
          );
        }
      }
    }
  }

  return parts.filter(Boolean).join(' ');
}

interface EditorBlock {
  name: string;
  attributes: {
    blockstudio?: {
      attributes?: Record<string, unknown>;
      disabled?: string[];
    };
  };
  innerBlocks: EditorBlock[];
}

function collectContent(): string {
  const editorBlocks: EditorBlock[] =
    (
      select('core/block-editor') as {
        getBlocks: () => EditorBlock[];
      }
    )?.getBlocks() || [];
  const parts: string[] = [];

  function walk(blockList: EditorBlock[]) {
    for (const block of blockList) {
      const def = blocks[block.name];
      if (def?.blockstudio?.attributes) {
        const attrs = block.attributes?.blockstudio?.attributes;
        const disabled = block.attributes?.blockstudio?.disabled || [];
        if (attrs && typeof attrs === 'object') {
          parts.push(
            extractText(attrs, def.blockstudio.attributes, disabled),
          );
        }
      }
      if (block.innerBlocks?.length) walk(block.innerBlocks);
    }
  }

  walk(editorBlocks);
  return parts.filter(Boolean).join(' ');
}

function initYoast() {
  if (!window.YoastSEO?.app?.registerPlugin) return false;
  seoPluginDetected = true;
  try {
    window.YoastSEO.app.registerPlugin(PLUGIN_NAME, { status: 'ready' });
    window.YoastSEO.app.registerModification(
      'content',
      (content: string) => content + ' ' + cachedContent,
      PLUGIN_NAME,
      10,
    );
  } catch {
    seoPluginDetected = false;
    return false;
  }
  return true;
}

function initRankMath() {
  if (!window.rankMath && !window.rankMathEditor) return false;
  seoPluginDetected = true;
  addFilter(
    'rank_math_content',
    'blockstudio/seo',
    (content: string) => content + ' ' + cachedContent,
  );
  return true;
}

let debounceTimer: ReturnType<typeof setTimeout>;

function waitForSeoPlugin(
  init: () => boolean,
  attempts = 0,
) {
  if (seoPluginDetected || init()) return;
  if (attempts < 50) {
    setTimeout(() => waitForSeoPlugin(init, attempts + 1), 200);
  }
}

waitForSeoPlugin(initYoast);
waitForSeoPlugin(initRankMath);

subscribe(() => {
  if (!seoPluginDetected) return;
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    const newContent = collectContent();
    if (newContent !== cachedContent) {
      cachedContent = newContent;
      if (window.YoastSEO?.app) {
        window.YoastSEO.app.refresh();
      }
    }
  }, 1000);
});

import { subscribe, select, dispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { addFilter } from '@wordpress/hooks';
import { registerPageBlock } from './component';

interface Block {
  clientId: string;
  name: string;
  attributes: Record<string, unknown>;
  innerBlocks: Block[];
}

interface BlockSettings {
  attributes: Record<string, { type: string }>;
}

interface EditorSettings {
  blockstudioBlockEditingMode?: string;
}

interface EditorStore {
  getEditorSettings: () => EditorSettings;
}

interface BlockEditorStore {
  getBlocks: () => Block[];
  getBlockEditingMode?: (clientId: string) => string | undefined;
}

interface BlockEditorActions {
  setBlockEditingMode: (clientId: string, mode: string) => void;
}

// Register metadata before parsing so block attributes are preserved.
addFilter(
  'blocks.registerBlockType',
  'blockstudio/page-metadata-attributes',
  (settings: BlockSettings) => {
    settings.attributes = {
      ...settings.attributes,
      blockEditingMode: { type: 'string' },
      __BLOCKSTUDIO_KEY: { type: 'string' },
    };
    return settings;
  },
);

registerPageBlock();

function findAncestorsOfOverrides(
  blocks: Block[],
  parentIds: string[],
): Set<string> {
  const ancestors = new Set<string>();

  for (const block of blocks) {
    const currentPath = [...parentIds, block.clientId];

    if (block.attributes.blockEditingMode) {
      for (const id of parentIds) {
        ancestors.add(id);
      }
    }

    if (block.innerBlocks && block.innerBlocks.length) {
      const childAncestors = findAncestorsOfOverrides(
        block.innerBlocks,
        currentPath,
      );
      childAncestors.forEach((id) => ancestors.add(id));
    }
  }

  return ancestors;
}

function getAllBlocks(blocks: Block[]): Block[] {
  const result: Block[] = [];

  blocks.forEach((block) => {
    result.push(block);

    if (block.innerBlocks && block.innerBlocks.length) {
      result.push(...getAllBlocks(block.innerBlocks));
    }
  });

  return result;
}

domReady(() => {
  subscribe(() => {
    const editor = select('core/editor') as EditorStore | undefined;

    if (!editor) {
      return;
    }

    const settings = editor.getEditorSettings();
    const defaultMode = settings.blockstudioBlockEditingMode;

    if (!defaultMode) {
      return;
    }

    const blockEditor = select('core/block-editor') as
      | BlockEditorStore
      | undefined;
    const blockEditorActions = dispatch('core/block-editor') as unknown as
      | BlockEditorActions
      | undefined;

    if (!blockEditor || !blockEditorActions) {
      return;
    }

    const topBlocks = blockEditor.getBlocks();
    const ancestorIds = findAncestorsOfOverrides(topBlocks, []);
    const blocks = getAllBlocks(topBlocks);

    blocks.forEach((block) => {
      let mode = (block.attributes.blockEditingMode as string) || defaultMode;

      // Keep overridden descendants interactive without changing other containers.
      if (
        !block.attributes.blockEditingMode &&
        ancestorIds.has(block.clientId)
      ) {
        mode = 'contentOnly';
      }

      const currentMode = blockEditor.getBlockEditingMode?.(block.clientId);

      if (mode && currentMode !== mode) {
        blockEditorActions.setBlockEditingMode(block.clientId, mode);
      }
    });
  });
});

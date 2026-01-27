import {
  BlockstudioEditorBlock,
  BlockstudioEditorFileStructure,
  BlockstudioEditorStore,
  BlockstudioAdmin,
} from '@/type/types';

export const selectors = {
  getBlock(state: BlockstudioEditorStore = null) {
    const { block } = state;
    return block as BlockstudioEditorBlock;
  },

  getBlocks(state: BlockstudioEditorStore = null) {
    const { blocks } = state;
    return blocks as BlockstudioEditorFileStructure;
  },

  getBlocksData(state: BlockstudioEditorStore = null) {
    const { blocksData } = state;
    return blocksData;
  },

  getBlocksNative(state: BlockstudioEditorStore = null) {
    const { blocksNative } = state;
    return blocksNative;
  },

  getBlockstudio(state: BlockstudioEditorStore = null) {
    const { blockstudio } = state;
    return blockstudio as BlockstudioAdmin;
  },

  getBlockResets(state: BlockstudioEditorStore = null) {
    const { blockResets } = state;
    return blockResets;
  },

  getContextMenu(state: BlockstudioEditorStore = null) {
    const { contextMenu } = state;
    return contextMenu as {
      x: number;
      y: number;
      path: string;
      right: string;
      type: string;
      allowDelete: boolean;
      block: BlockstudioEditorBlock;
    };
  },

  getConsole(state: BlockstudioEditorStore = null) {
    const { console } = state;
    return console;
  },

  getEditor(state: BlockstudioEditorStore = null) {
    const { editor } = state;
    return editor;
  },

  getEditorMounted(state: BlockstudioEditorStore = null) {
    const { editorMounted } = state;
    return editorMounted;
  },

  getErrors(state: BlockstudioEditorStore = null) {
    const { errors } = state;
    return errors as string[];
  },

  getFiles(state: BlockstudioEditorStore = null) {
    const { files } = state;
    return files as BlockstudioEditorBlock[];
  },

  getFilesChanged(state: BlockstudioEditorStore = null) {
    const { filesChanged } = state;
    return filesChanged as {
      [key: string]: string;
    }[];
  },

  getFolders(state: BlockstudioEditorStore = null) {
    const { folders } = state;
    return folders;
  },

  getFormatCode(state: BlockstudioEditorStore = null) {
    const { formatCode } = state;
    return formatCode;
  },

  getMove(state: BlockstudioEditorStore = null) {
    const { move } = state;
    return move;
  },

  getNextBlock(state: BlockstudioEditorStore = null) {
    const { nextBlock } = state;
    return nextBlock as unknown as
      | {
          block: BlockstudioEditorBlock;
          path: string;
        }
      | string;
  },

  getOptions(state: BlockstudioEditorStore = null) {
    const { options } = state;
    return options;
  },

  getPath(state: BlockstudioEditorStore = null) {
    const { path } = state;
    return path;
  },

  getPaths(state: BlockstudioEditorStore = null) {
    const { paths } = state;
    return paths;
  },

  getPluginsPath(state: BlockstudioEditorStore = null) {
    const { pluginsPath } = state;
    return pluginsPath;
  },

  getPlugins(state: BlockstudioEditorStore = null) {
    const { plugins } = state;
    return plugins;
  },

  getPreview(state: BlockstudioEditorStore = null) {
    const { preview } = state;
    return preview;
  },

  getRename(state: BlockstudioEditorStore = null) {
    const { rename } = state;
    return rename;
  },

  getSearchedBlocks(state: BlockstudioEditorStore = null) {
    const { searchedBlocks } = state;
    return searchedBlocks;
  },

  getSettings(state: BlockstudioEditorStore = null) {
    const { settings } = state;
    return settings;
  },

  getTree(state: BlockstudioEditorStore = null) {
    const { tree } = state;
    return tree;
  },

  isEditor(state: BlockstudioEditorStore = null) {
    const { isEditor } = state;
    return isEditor;
  },

  isImport(state: BlockstudioEditorStore = null) {
    const { isImport } = state;
    return isImport;
  },

  isGutenberg(state: BlockstudioEditorStore = null) {
    const { isGutenberg } = state;
    return isGutenberg;
  },

  isModalSave(state: BlockstudioEditorStore = null) {
    const { isModalSave } = state;
    return isModalSave;
  },

  isStatic(state: BlockstudioEditorStore = null) {
    const { isStatic } = state;
    return isStatic;
  },

  isTreeOpen(state: BlockstudioEditorStore = null) {
    const { treeOpen } = state;
    return treeOpen;
  },

  newBlock(state: BlockstudioEditorStore = null) {
    const { newBlock } = state;
    return newBlock;
  },

  newFile(state: BlockstudioEditorStore = null) {
    const { newFile } = state;
    return newFile;
  },

  newFolder(state: BlockstudioEditorStore = null) {
    const { newFolder } = state;
    return newFolder as boolean | string;
  },

  newInstance(state: BlockstudioEditorStore = null) {
    const { newInstance } = state;
    return newInstance;
  },
};

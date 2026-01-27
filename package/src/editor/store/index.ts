import { createReduxStore } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { actions } from './actions';
import { selectors } from './selectors';
import { router } from '@/admin/router';
import { isArray } from 'lodash-es';
import { __ } from '@/utils/__';
import { BlockstudioEditorStore } from '@/type/types';

const DEFAULT_STATE = <BlockstudioEditorStore>{
  block: {},
  blocks: window.blockstudioAdmin.data.blocksSorted || {},
  blocksData: window.blockstudioAdmin.data.blocks || {},
  blocksNative: window.blockstudioAdmin.data.blocksNative || {},
  blockstudio: window.blockstudioAdmin || {},
  blockResets: [],
  contextMenu: { x: 0, y: 0 },
  console: [
    {
      emoji: '🔮',
      text: `Running Blockstudio ${window.blockstudioAdmin.pluginVersion}`,
    },
  ],
  editor: {
    colorDecorators: true,
    padding: { top: 20 },
    scrollbar: { useShadows: false },
    lineHeight: 20,
    fontSize: 12,
    minimap: { enabled: false },
    automaticLayout: true,
    quickSuggestions: true,
    ...{
      ...(JSON.parse(
        Object.values(window?.blockstudioAdmin?.settings).length === 0
          ? '{}'
          : window?.blockstudioAdmin?.settings
      )?.editor
        ? JSON.parse(window.blockstudioAdmin.settings).editor
        : {}),
    },
  },
  editorMounted: false,
  errors: [],
  files: window.blockstudioAdmin.data.files || {},
  filesChanged: {},
  folders: [],
  formatCode: 0,
  isEditor: false,
  isGutenberg: false,
  isImport: false,
  isModalSave: false,
  isStatic: false,
  move: {
    oldPath: '',
    newPath: '',
  },
  newBlock: '',
  newFile: '',
  newFolder: '',
  newInstance: false,
  nextBlock: false,
  options: window?.blockstudioAdmin?.options,
  path: '',
  paths: window.blockstudioAdmin.data.paths || [],
  plugins: window.blockstudioAdmin?.plugins || {},
  pluginsPath: window.blockstudioAdmin?.pluginsPath || '',
  preview: 0,
  rename: '',
  searchedBlocks: [],
  settings: {
    ...(JSON.parse(
      Object.values(window?.blockstudioAdmin?.settings).length === 0
        ? '{}'
        : window?.blockstudioAdmin?.settings
    )?.editor
      ? JSON.parse(window.blockstudioAdmin.settings).settings
      : {}),
    userId: window.blockstudioAdmin.userId,
  },
  tree: localStorage.getItem('BLOCKSTUDIO_TREE')
    ? JSON.parse(localStorage.getItem('BLOCKSTUDIO_TREE'))
    : [],
  treeOpen: false,
};

const updateLocalStorage = (k: string, v: object) => {
  localStorage.setItem(`BLOCKSTUDIO_${k.toUpperCase()}`, JSON.stringify(v));
};

const updateSettings = (
  state: BlockstudioEditorStore,
  action: BlockstudioEditorStore
) => {
  const obj = {
    editor: {
      ...state.editor,
      ...action.editor,
    },
    settings: {
      ...state.settings,
      ...action.settings,
    },
  };

  apiFetch({
    path: '/blockstudio/v1/editor/settings/save',
    method: 'POST',
    data: {
      userId: state.settings.userId,
      settings: encodeURIComponent(JSON.stringify(obj)),
    },
  }).then(() => {});
};

export const store = createReduxStore('blockstudio/editor', {
  reducer(state = DEFAULT_STATE, action) {
    switch (action.type) {
      case 'CLOSE_EDITOR': {
        router.navigate('/editor');

        return {
          ...state,
          isEditor: false,
        };
      }

      case 'FORMAT_CODE':
        return {
          ...state,
          formatCode: action.formatCode,
        };

      case 'OPEN_EDITOR':
        return {
          ...state,
          block: action.block,
          errors: [],
          filesChanged: {},
          isEditor: true,
          isStatic: false,
          path: action.block.path,
        };

      case 'SET_BLOCK':
        return {
          ...state,
          block: action.block,
          path: action.block.path,
          isStatic: false,
        };

      case 'SET_BLOCK_DATA':
        return {
          ...state,
          block: action.block,
        };

      case 'SET_BLOCK_RESETS':
        return {
          ...state,
          blockResets: action.blockResets,
        };

      case 'SET_BLOCKS':
        return {
          ...state,
          blocks: action.blocks,
        };

      case 'SET_BLOCKS_NATIVE':
        return {
          ...state,
          blocksNative: action.blocks,
        };

      case 'SET_CONTEXT_MENU':
        return {
          ...state,
          contextMenu: action.contextMenu,
        };

      case 'SET_CONSOLE': {
        const date = new Date();

        const getConsoleObj = (action: BlockstudioEditorStore['errors']) => ({
          text: __(
            action.console?.text
              ? action.console?.text
              : action.console?.type !== 'error' && action.console
          ),
          type: action.console?.type,
          emoji: action.console?.emoji,
          time: `${date.getHours()}:${String(date.getMinutes()).padStart(
            2,
            '0'
          )}`,
        });

        return {
          ...state,
          console: [
            ...state.console,
            ...(isArray(action.console?.text) || isArray(action.console)
              ? (action.console?.text || action.console).map(
                  (e: BlockstudioEditorStore['errors']) =>
                    getConsoleObj({
                      console: {
                        ...action.console,
                        text: e?.text || e,
                      },
                    })
                )
              : [getConsoleObj(action)]),
          ],
        };
      }

      case 'SET_EDITOR':
        updateSettings(state, action);
        return {
          ...state,
          editor: {
            ...state.editor,
            ...action.editor,
          },
        };

      case 'SET_EDITOR_MOUNTED':
        return {
          ...state,
          editorMounted: action.editorMounted,
        };

      case 'SET_ERRORS':
        return {
          ...state,
          errors: action.errors,
        };

      case 'SET_FILES':
        return {
          ...state,
          files: action.files,
        };

      case 'SET_FILES_CHANGED':
        return {
          ...state,
          filesChanged: action.filesChanged,
        };

      case 'SET_FOLDERS':
        return {
          ...state,
          folders: action.folders,
        };

      case 'SET_IS_GUTENBERG':
        return {
          ...state,
          isGutenberg: action.isGutenberg,
        };

      case 'SET_IS_IMPORT':
        return {
          ...state,
          isImport: action.isImport,
        };

      case 'SET_IS_MODAL_SAVE':
        return {
          ...state,
          isModalSave: action.isModalSave,
        };

      case 'SET_IS_STATIC':
        return {
          ...state,
          isStatic: action.isStatic,
        };

      case 'SET_MOVE':
        return {
          ...state,
          move: action.move,
        };

      case 'SET_NEW_BLOCK':
        return {
          ...state,
          newBlock: action.newBlock,
        };

      case 'SET_NEW_FILE':
        return {
          ...state,
          newFile: action.newFile,
        };

      case 'SET_NEW_FOLDER':
        return {
          ...state,
          newFolder: action.newFolder,
        };

      case 'SET_NEW_INSTANCE':
        return {
          ...state,
          newInstance: action.newInstance,
        };

      case 'SET_NEXT_BLOCK':
        return {
          ...state,
          nextBlock: action.nextBlock,
        };

      case 'SET_OPTIONS':
        return {
          ...state,
          options: {
            ...state.options,
            ...action.options,
          },
        };

      case 'SET_PATH':
        return {
          ...state,
          path: action.path,
        };

      case 'SET_PREVIEW':
        return {
          ...state,
          preview: state.preview + 1,
        };

      case 'SET_RENAME':
        return {
          ...state,
          rename: action.rename,
        };

      case 'SET_SEARCHED_BLOCKS':
        return {
          ...state,
          searchedBlocks: action.searchedBlocks,
        };

      case 'SET_SETTINGS':
        updateSettings(state, action);

        return {
          ...state,
          settings: {
            ...state.settings,
            ...action.settings,
          },
        };

      case 'SET_TREE': {
        const val = state.tree.includes(action.tree)
          ? state.tree.filter((e: string) => e !== action.tree)
          : [...state.tree, action.tree];

        updateLocalStorage('tree', val);

        return {
          ...state,
          tree: val,
        };
      }

      case 'SET_TREE_OPEN':
        return {
          ...state,
          treeOpen: action.treeOpen,
        };
    }

    return state;
  },

  actions,
  selectors,
});

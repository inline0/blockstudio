import { ReactNode } from 'react';
import { Block, BlockstudioAttribute, BlockstudioClass } from '@/type/block';

type Any = any;

interface BlockstudioBlock extends Block {
  blockstudio: BlockstudioClass;
}

type BlockstudioBlockStore = {
  editor: Record<string, string>;
  editorFocus: number;
  icons: Record<string, string>;
  initialLoad: Record<string, string>;
  initialLoadRendered: Record<string, string>;
  isLoaded: boolean;
  media: Record<
    string,
    {
      [key: string]: string;
    }
  >;
  repeaters: Record<string, string>;
  richText: Record<string, string>;
};

type BlockstudioBlockAttributes = {
  blockstudio: {
    attributes: {
      [key: string]: any;
    }[];
    context: {
      [key: string]: any;
    };
    contextBlock: {
      [key: string]: any;
    };
    name: string;
    disabled: string[];
  };
};

type BlockstudioBlockContext = {
  id: string;
  index: number;
  parentId: string;
  rootClientId: string;
};

type BlockstudioFieldsOptions = {
  label: string;
  value: string;
};

type BlockstudioFieldsElement = (
  e: BlockstudioAttribute,
  id?: string,
  transform?: boolean
) => ReactNode;

type BlockstudioFieldsChange = (
  value: string | any,
  direct?: boolean,
  suffix?: string
) => void;

type BlockstudioFieldsRepeaterAddRemove = (id: string) => void;

type BlockstudioFieldsRepeaterSort = (
  order: string | string[],
  id: string
) => void;

type BlockstudioFieldsListMove = (
  index: number,
  droppableId?: string,
  ref?: string | null
) => void;

type BlockstudioEditorBlock = {
  directory: boolean;
  example: Record<any, any>;
  file: {
    dirname: string;
    basename: string;
    extension: string;
    filename: string;
  };
  files: string[];
  filesPaths: string[];
  folders: string[];
  init: boolean;
  instance: string;
  instancePath: string;
  level: number;
  library: boolean;
  name: string;
  nameAlt?: string;
  path: string;
  previewAssets: any[];
  scopedClass: string;
  structure: string;
  structureArray: string[];
  twig: boolean;
  value: string;
  assets: {
    [key: string]: {
      type: string;
      path: string;
      url: string;
      editor: boolean;
      instance: string;
      file: {
        dirname: string;
        basename: string;
        extension: string;
        filename: string;
      };
    };
  };
};

type BlockstudioEditorFileStructure = {
  children: BlockstudioEditorFileStructure[];
  instance: string;
  library: boolean;
  path: string;
};

type BlockstudioRestResponse = {
  code: string;
  message: string;
  data: {
    status: number;
    [key: string]: unknown;
  };
};

type BlockstudioTailwindStore = {
  customClasses: {
    className: string;
    value: string;
  }[];
  temporaryClasses: {
    [key: string]: string;
  };
};

type BlockstudioAdmin = {
  adminUrl: string;
  ajax: string;
  allowEditor: string;
  canEdit: string;
  cssClasses: string[];
  cssVariables: string[];
  data: {
    blocks: BlockstudioBlock[];
    blocksNative: BlockstudioBlock[];
    blocksSorted: BlockstudioBlock[];
    editorMarkup: string;
    extensions: any;
    files: BlockstudioEditorBlock[];
    functions: string[];
    imageSizes: any;
    paths: string[];
    scripts: object;
    styles: object;
    templates: { [key: string]: { [key: string]: string } };
  };
  isTailwindActive: string;
  llmTxtUrl: string;
  loader: string;
  logo: string;
  nonce: string;
  nonceRest: string;
  options: Any;
  optionsOptions: Any;
  optionsJson: Any;
  optionsFilters: Any;
  optionsFiltersValues: Any;
  optionsRoles: string[];
  optionsSchema: string[];
  optionsUsers: string[];
  plugin: {
    currentUrl: string;
    licenseCode: string;
    licenseStatus: string;
    name: string;
    pluginUrl: string;
    productId: number;
    shopUrl: string;
  };
  pluginVersion: string;
  plugins: any[];
  pluginsPath: string;
  postId: number;
  postType: string;
  rest: string;
  settings: string;
  site: string;
  styles: {
    [key: string]: any;
  };
  tailwindUrl: string;
  userId: number;
  userRole: string;
};

declare global {
  interface Window {
    blockstudio: {
      blockstudioBlocks: {
        [key: string]: {
          rendered: string;
        };
      };
    };
    blockstudioAdmin: BlockstudioAdmin;
    pagenow?: string;
  }
}

type BlockstudioEditorStore = {
  block: Record<string, any>;
  blocks: Record<string, any>;
  blocksData: Record<string, any>;
  blocksNative: Record<string, any>;
  blockstudio: BlockstudioAdmin;
  blockResets: string[];
  contextMenu: { x: number; y: number };
  console: {
    emoji: string;
    text: string;
    type?: string;
  }[];
  editor: {
    tabSize: number;
    colorDecorators: boolean;
    padding: { top: number };
    scrollbar: { useShadows: boolean };
    lineHeight: number;
    fontSize: number;
    minimap: { enabled: boolean };
    automaticLayout: boolean;
    [key: string]: any;
  };
  editorMounted: boolean;
  errors: Record<string, any>;
  files: Record<string, any>;
  filesChanged: Record<string, any>;
  folders: string[];
  formatCode: number;
  isEditor: boolean;
  isGutenberg: boolean;
  isImport: boolean;
  isModalSave: boolean;
  isStatic: boolean;
  move: {
    oldPath: string;
    newPath: string;
  };
  newBlock: string;
  newFile: string;
  newFolder: string;
  newInstance: boolean;
  nextBlock: boolean;
  options: {
    [key: string]: any;
  };
  path: string;
  paths: string[];
  plugins: Record<string, any>;
  pluginsPath: string;
  preview: number;
  rename: string;
  searchedBlocks: string[];
  settings: {
    [key: string]: any;
  };
  tree: {
    [key: string]: any;
  };
  treeOpen: boolean;
};

export type {
  Any,
  BlockstudioAdmin,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
  BlockstudioBlockContext,
  BlockstudioBlockStore,
  BlockstudioEditorBlock,
  BlockstudioEditorFileStructure,
  BlockstudioEditorStore,
  BlockstudioFieldsChange,
  BlockstudioFieldsElement,
  BlockstudioFieldsListMove,
  BlockstudioFieldsOptions,
  BlockstudioFieldsRepeaterAddRemove,
  BlockstudioFieldsRepeaterSort,
  BlockstudioRestResponse,
  BlockstudioTailwindStore,
};

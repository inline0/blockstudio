import type { Field, View } from '@wordpress/dataviews';
import { __ } from '@/utils/__';
import type {
  AdminBlockRow,
  AdminExtensionRow,
  AdminFieldRow,
  AdminPageRow,
  AdminSchemaRow,
  BlockstudioAdminOverview,
  RegistryTabName,
} from '../types';

export type AdminRegistryConfig<Item extends { id: string }> = {
  emptyMessage: string;
  fields: Field<Item>[];
  label: string;
  searchLabel: string;
  view: View;
};

type RegistryConfigMap = {
  blocks: AdminRegistryConfig<AdminBlockRow>;
  extensions: AdminRegistryConfig<AdminExtensionRow>;
  fields: AdminRegistryConfig<AdminFieldRow>;
  pages: AdminRegistryConfig<AdminPageRow>;
  schemas: AdminRegistryConfig<AdminSchemaRow>;
};

const createTableView = (fields: string[], sortField: string): View => {
  return {
    fields,
    page: 1,
    perPage: 20,
    sort: {
      direction: 'asc',
      field: sortField,
    },
    type: 'table',
  };
};

export const REGISTRY_CONFIG: RegistryConfigMap = {
  blocks: {
    emptyMessage: __('No blocks are registered.'),
    fields: [
      {
        enableGlobalSearch: true,
        id: 'title',
        label: __('Title'),
      },
      {
        enableGlobalSearch: true,
        id: 'name',
        label: __('Name'),
      },
      {
        enableGlobalSearch: true,
        id: 'category',
        label: __('Category'),
      },
      {
        id: 'apiVersion',
        label: __('API'),
        type: 'integer',
      },
      {
        enableGlobalSearch: true,
        id: 'render',
        label: __('Render'),
      },
      {
        id: 'attributesCount',
        label: __('Attributes'),
        type: 'integer',
      },
    ],
    label: __('Blocks'),
    searchLabel: __('Search blocks'),
    view: createTableView(
      ['title', 'name', 'category', 'apiVersion', 'render', 'attributesCount'],
      'title',
    ),
  },
  extensions: {
    emptyMessage: __('No extensions are registered.'),
    fields: [
      {
        enableGlobalSearch: true,
        id: 'targets',
        label: __('Targets'),
      },
      {
        enableGlobalSearch: true,
        id: 'title',
        label: __('Title'),
      },
      {
        enableGlobalSearch: true,
        id: 'category',
        label: __('Category'),
      },
      {
        enableGlobalSearch: true,
        id: 'priority',
        label: __('Priority'),
      },
      {
        id: 'apiVersion',
        label: __('API'),
        type: 'integer',
      },
      {
        enableGlobalSearch: true,
        id: 'render',
        label: __('Render'),
      },
      {
        id: 'attributesCount',
        label: __('Attributes'),
        type: 'integer',
      },
    ],
    label: __('Extensions'),
    searchLabel: __('Search extensions'),
    view: createTableView(
      [
        'targets',
        'title',
        'category',
        'priority',
        'apiVersion',
        'render',
        'attributesCount',
      ],
      'title',
    ),
  },
  fields: {
    emptyMessage: __('No fields are registered.'),
    fields: [
      {
        enableGlobalSearch: true,
        id: 'name',
        label: __('Name'),
      },
      {
        enableGlobalSearch: true,
        id: 'title',
        label: __('Title'),
      },
      {
        id: 'attributesCount',
        label: __('Attributes'),
        type: 'integer',
      },
    ],
    label: __('Fields'),
    searchLabel: __('Search fields'),
    view: createTableView(['name', 'title', 'attributesCount'], 'title'),
  },
  pages: {
    emptyMessage: __('No pages are registered.'),
    fields: [
      {
        enableGlobalSearch: true,
        id: 'name',
        label: __('Name'),
      },
      {
        enableGlobalSearch: true,
        id: 'title',
        label: __('Title'),
      },
      {
        enableGlobalSearch: true,
        id: 'slug',
        label: __('Slug'),
      },
      {
        enableGlobalSearch: true,
        id: 'postType',
        label: __('Post Type'),
      },
      {
        enableGlobalSearch: true,
        id: 'postStatus',
        label: __('Status'),
      },
      {
        enableGlobalSearch: true,
        id: 'sync',
        label: __('Sync'),
      },
      {
        enableGlobalSearch: true,
        id: 'postId',
        label: __('Post ID'),
      },
      {
        enableGlobalSearch: true,
        id: 'template',
        label: __('Template'),
      },
      {
        enableGlobalSearch: true,
        id: 'templateFor',
        label: __('Template For'),
      },
    ],
    label: __('Pages'),
    searchLabel: __('Search pages'),
    view: createTableView(
      [
        'name',
        'title',
        'slug',
        'postType',
        'postStatus',
        'sync',
        'postId',
        'template',
      ],
      'title',
    ),
  },
  schemas: {
    emptyMessage: __('No schemas are registered.'),
    fields: [
      {
        enableGlobalSearch: true,
        id: 'block',
        label: __('Block'),
      },
      {
        enableGlobalSearch: true,
        id: 'name',
        label: __('Schema'),
      },
      {
        enableGlobalSearch: true,
        id: 'storage',
        label: __('Storage'),
      },
      {
        id: 'fieldsCount',
        label: __('Fields'),
        type: 'integer',
      },
      {
        enableGlobalSearch: true,
        id: 'userScoped',
        label: __('User Scoped'),
      },
      {
        enableGlobalSearch: true,
        id: 'capabilities',
        label: __('Capabilities'),
      },
    ],
    label: __('Schemas'),
    searchLabel: __('Search schemas'),
    view: createTableView(
      ['block', 'name', 'storage', 'fieldsCount', 'userScoped', 'capabilities'],
      'block',
    ),
  },
};

export const REGISTRY_ORDER: RegistryTabName[] = [
  'blocks',
  'extensions',
  'fields',
  'pages',
  'schemas',
];

export const createDefaultViews = (): Record<RegistryTabName, View> => {
  return {
    blocks: { ...REGISTRY_CONFIG.blocks.view },
    extensions: { ...REGISTRY_CONFIG.extensions.view },
    fields: { ...REGISTRY_CONFIG.fields.view },
    pages: { ...REGISTRY_CONFIG.pages.view },
    schemas: { ...REGISTRY_CONFIG.schemas.view },
  };
};

export const getRegistryTabs = (
  overview: BlockstudioAdminOverview,
): Array<{ name: RegistryTabName; title: string }> => {
  return REGISTRY_ORDER.map((name) => {
    return {
      name,
      title: `${REGISTRY_CONFIG[name].label} (${overview[name].length})`,
    };
  });
};

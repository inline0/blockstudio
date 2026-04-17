export type AdminBlockRow = {
  apiVersion: number;
  attributesCount: number;
  category: string;
  id: string;
  name: string;
  render: string;
  title: string;
};

export type AdminExtensionRow = {
  apiVersion: number;
  attributesCount: number;
  category: string;
  id: string;
  priority: string;
  render: string;
  targets: string;
  title: string;
};

export type AdminFieldRow = {
  attributesCount: number;
  id: string;
  name: string;
  title: string;
};

export type AdminPageRow = {
  id: string;
  name: string;
  postId: string;
  postStatus: string;
  postType: string;
  slug: string;
  sync: string;
  template: string;
  templateFor: string;
  title: string;
};

export type AdminSchemaRow = {
  block: string;
  capabilities: string;
  fieldsCount: number;
  id: string;
  name: string;
  storage: string;
  userScoped: string;
};

export type AdminDatabaseDefinition = {
  block: string;
  fields: string[];
  id: string;
  label: string;
  name: string;
  storage: string;
  userScoped: boolean;
};

export type BlockstudioAdminOverview = {
  blocks: AdminBlockRow[];
  extensions: AdminExtensionRow[];
  fields: AdminFieldRow[];
  pages: AdminPageRow[];
  schemas: AdminSchemaRow[];
};

export type RegistryTabName = keyof BlockstudioAdminOverview;

export type AdminRegistryBlockRow = {
  category: string;
  description: string;
  id: string;
  name: string;
  registry: string;
  status: 'installed' | 'available';
  title: string;
  type: string;
};

export type AdminDatabaseRow = Record<string, unknown> & {
  id?: number | string;
};

export type AdminDatabaseRecordsResponse = {
  items: AdminDatabaseRow[];
  limit: number;
  offset: number;
  total: number;
};

export type AdminPageName = 'overview' | 'databases' | 'registry';

export type BlockstudioAdminPageData = {
  adminUrl: string;
  databases: AdminDatabaseDefinition[];
  logo: string;
  nonce: string;
  overview: BlockstudioAdminOverview;
  registryEnabled: boolean;
  restUrl: string;
  version: string;
};

declare global {
  interface Window {
    blockstudioAdminPage?: BlockstudioAdminPageData;
  }
}

export {};

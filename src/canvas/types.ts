export interface Page {
  title: string;
  slug: string;
  name: string;
  content: string;
}

export interface BlockItem {
  title: string;
  name: string;
  content: string;
}

export interface PreloadEntry {
  rendered: string;
  blockName: string;
  attributes?: unknown;
  mode?: string;
}

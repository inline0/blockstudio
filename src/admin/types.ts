export type BlockstudioAdminPageData = {
  logo: string;
};

declare global {
  interface Window {
    blockstudioAdminPage?: BlockstudioAdminPageData;
  }
}

export {};

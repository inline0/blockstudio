import { HomeLayout } from "onedocs";
import { DocsLayout } from "fumadocs-ui/layouts/docs";
import type { ReactNode } from "react";
import config from "../../../onedocs.config";
import { createBaseOptions } from "onedocs";

const emptyTree = { name: "Blog", children: [] };

export default function BlogLayout({ children }: { children: ReactNode }) {
  return (
    <HomeLayout config={config}>
      <DocsLayout
        {...createBaseOptions(config)}
        tree={emptyTree}
        sidebar={{ enabled: false }}
        nav={{ enabled: false }}
      >
        {children}
      </DocsLayout>
    </HomeLayout>
  );
}

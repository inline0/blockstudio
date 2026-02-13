import { DocsLayout as FumaDocsLayout } from "fumadocs-ui/layouts/docs";
import { createBaseOptions } from "onedocs";
import type { ReactNode } from "react";
import type { LinkItemType } from "@fumadocs/ui/link-item";
import { source } from "@/lib/source";
import { PlusBadge } from "@/components/plus-badge";
import config from "../../../onedocs.config";

const plusBadgeNav: LinkItemType = {
  type: "custom",
  on: "nav",
  children: <PlusBadge />,
};

function getLayoutOptions() {
  const base = createBaseOptions(config);
  return {
    ...base,
    links: [...(base.links ?? []), plusBadgeNav],
  };
}

export default function Layout({ children }: { children: ReactNode }) {
  return (
    <FumaDocsLayout {...getLayoutOptions()} tree={source.pageTree}>
      {children}
    </FumaDocsLayout>
  );
}

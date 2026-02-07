import { HomeLayout } from "onedocs";
import type { ReactNode } from "react";
import config from "../../../onedocs.config";

export default function BlogLayout({ children }: { children: ReactNode }) {
  return <HomeLayout config={config}>{children}</HomeLayout>;
}

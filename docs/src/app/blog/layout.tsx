import { HomeLayout } from "onedocs";
import type { ReactNode } from "react";
import config from "../../../onedocs.config";

export default function BlogLayout({ children }: { children: ReactNode }) {
  return (
    <HomeLayout config={config}>
      <main className="relative mx-auto w-full flex-1 max-w-(--fd-layout-width)">
        <div className="absolute inset-0 border-x pointer-events-none" />
        <div className="relative">{children}</div>
      </main>
    </HomeLayout>
  );
}

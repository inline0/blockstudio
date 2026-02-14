import { SiteLayout } from "@/components/site-layout";
import type { ReactNode } from "react";

export default function AiLayout({ children }: { children: ReactNode }) {
  return (
    <SiteLayout>
      <main className="relative mx-auto w-full flex-1 max-w-(--fd-layout-width)">
        <div className="absolute inset-0 border-x pointer-events-none" />
        <div className="relative">{children}</div>
      </main>
    </SiteLayout>
  );
}

import { SiteLayout } from "@/components/site-layout";
import { SiteFooter } from "@/components/site-footer";
import type { ReactNode } from "react";

export default function BlogLayout({ children }: { children: ReactNode }) {
  return (
    <SiteLayout>
      <main className="relative mx-auto w-full flex-1 max-w-(--fd-layout-width)">
        <div className="absolute inset-0 border-x pointer-events-none" />
        <div className="relative">{children}</div>
      </main>
      <SiteFooter />
    </SiteLayout>
  );
}

import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/site-footer';
import { SiteLayout } from '@/components/site-layout';

export default function FeaturesLayout({ children }: { children: ReactNode }) {
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

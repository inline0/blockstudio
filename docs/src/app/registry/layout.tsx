import type { ReactNode } from 'react';
import { DocsLayout as FumaDocsLayout } from 'fumadocs-ui/layouts/docs';
import { getLayoutOptions } from '@/lib/layout-options';
import { registrySource } from '@/lib/source';

export default function Layout({ children }: { children: ReactNode }) {
  return (
    <FumaDocsLayout {...getLayoutOptions()} tree={registrySource.pageTree}>
      {children}
    </FumaDocsLayout>
  );
}

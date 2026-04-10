import type { ReactNode } from 'react';
import { DocsLayout as FumaDocsLayout } from 'fumadocs-ui/layouts/docs';
import { getLayoutOptions } from '@/lib/layout-options';

export default function BlogLayout({ children }: { children: ReactNode }) {
  return (
    <FumaDocsLayout {...getLayoutOptions()} tree={{ name: '', children: [] }}>
      {children}
    </FumaDocsLayout>
  );
}

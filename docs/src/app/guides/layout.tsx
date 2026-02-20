import type { ReactNode } from 'react';
import type { LinkItemType } from '@fumadocs/ui/link-item';
import { DocsLayout as FumaDocsLayout } from 'fumadocs-ui/layouts/docs';
import { BookOpen, Compass, Newspaper } from 'lucide-react';
import { createBaseOptions } from 'onedocs';
import { PlusBadge } from '@/components/plus-badge';
import { guidesSource } from '@/lib/source';
import config from '../../../onedocs.config';

const plusBadgeNav: LinkItemType = {
  type: 'custom',
  on: 'nav',
  children: <PlusBadge />,
};

function getLayoutOptions() {
  const base = createBaseOptions(config);
  return {
    ...base,
    links: [
      { text: 'Docs', url: '/docs', icon: <BookOpen /> },
      { text: 'Guides', url: '/guides', icon: <Compass /> },
      { text: 'Blog', url: '/blog', icon: <Newspaper /> },
      plusBadgeNav,
    ],
  };
}

export default function Layout({ children }: { children: ReactNode }) {
  return (
    <FumaDocsLayout {...getLayoutOptions()} tree={guidesSource.pageTree}>
      {children}
    </FumaDocsLayout>
  );
}

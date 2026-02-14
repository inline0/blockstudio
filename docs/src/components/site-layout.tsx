import type { ReactNode } from 'react';
import type { LinkItemType } from '@fumadocs/ui/link-item';
import { HomeLayout as FumaHomeLayout } from 'fumadocs-ui/layouts/home';
import {
  Blocks,
  BookOpen,
  Bot,
  FileText,
  LayoutGrid,
  Newspaper,
  Puzzle,
} from 'lucide-react';
import { createBaseOptions } from 'onedocs';
import config from '../../onedocs.config';
import { FeaturesMenu } from './features-menu';
import { PlusBadge } from './plus-badge';

const featuresMenuDesktop: LinkItemType = {
  type: 'custom',
  on: 'nav',
  children: <FeaturesMenu />,
};

const plusBadgeNav: LinkItemType = {
  type: 'custom',
  on: 'nav',
  children: <PlusBadge />,
};

const featuresMenuMobile: LinkItemType = {
  type: 'menu',
  on: 'menu',
  text: 'Features',
  items: [
    {
      text: 'Blocks',
      url: '/features/blocks',
      icon: <Blocks className="size-4" />,
    },
    {
      text: 'Pages',
      url: '/features/pages',
      icon: <FileText className="size-4" />,
    },
    {
      text: 'Patterns',
      url: '/features/patterns',
      icon: <LayoutGrid className="size-4" />,
    },
    {
      text: 'Extensions',
      url: '/features/extensions',
      icon: <Puzzle className="size-4" />,
    },
  ],
};

function getLayoutOptions() {
  const base = createBaseOptions(config);
  return {
    ...base,
    links: [
      featuresMenuDesktop,
      featuresMenuMobile,
      { text: 'Build with AI', url: '/ai', icon: <Bot /> },
      { text: 'Docs', url: '/docs', icon: <BookOpen /> },
      { text: 'Blog', url: '/blog', icon: <Newspaper /> },
      plusBadgeNav,
    ],
  };
}

export function SiteLayout({ children }: { children: ReactNode }) {
  return <FumaHomeLayout {...getLayoutOptions()}>{children}</FumaHomeLayout>;
}

import type { LinkItemType } from '@fumadocs/ui/link-item';
import { BookOpen, Compass, Newspaper, Package } from 'lucide-react';
import { createBaseOptions } from 'onedocs';
import { PlusBadge } from '@/components/plus-badge';
import config from '../../onedocs.config';

const plusBadgeNav: LinkItemType = {
  type: 'custom',
  on: 'nav',
  children: <PlusBadge />,
};

const sidebarLinks: LinkItemType[] = [
  { text: 'Docs', url: '/docs', icon: <BookOpen /> },
  { text: 'Registry', url: '/registry', icon: <Package /> },
  { text: 'Guides', url: '/guides', icon: <Compass /> },
  { text: 'Blog', url: '/blog', icon: <Newspaper /> },
  plusBadgeNav,
];

export function getLayoutOptions() {
  return {
    ...createBaseOptions(config),
    links: sidebarLinks,
  };
}

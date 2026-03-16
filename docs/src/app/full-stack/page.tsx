import type { Metadata } from 'next';
import { FullStackPage } from '@/components/full-stack/full-stack-page';

export const metadata: Metadata = {
  title: 'Full-Stack Blocks',
  description:
    'Build complete applications inside a block folder. Database, RPC, cron, CLI, and more.',
};

export default function Page() {
  return <FullStackPage />;
}

import {
  Database,
  Zap,
  Clock,
  Terminal,
  Shield,
  Globe,
  FolderOpen,
  Server,
  Layers,
  FileCheck,
  GitBranch,
  Workflow,
} from 'lucide-react';
import Link from 'next/link';
import { Section, SectionIcon } from '../homepage/section';

const sections = [
  {
    title: 'Database',
    description: 'Define schemas, get CRUD endpoints.',
    icon: Database,
    items: [
      {
        icon: Database,
        title: 'Four storage backends',
        description:
          'MySQL tables, SQLite, JSONC files, and post meta. Choose based on portability and query needs.',
        href: '/docs/blocks/block-api/database#storage-modes',
      },
      {
        icon: Globe,
        title: 'User-scoped data',
        description:
          'Set userScoped: true to auto-isolate records per user. No manual filtering needed.',
        href: '/docs/blocks/block-api/database#user-scoped-data',
      },
      {
        icon: FileCheck,
        title: 'Validation and hooks',
        description:
          'Required, type, enum, format, length, and custom callbacks. Lifecycle hooks before and after every write.',
        href: '/docs/blocks/block-api/database#validation',
      },
    ],
  },
  {
    title: 'RPC',
    description: 'Server functions callable from the frontend.',
    icon: Zap,
    items: [
      {
        icon: Zap,
        title: 'bs.fn() client',
        description:
          'Call server functions from JavaScript. Inline scripts auto-detect the block name.',
        href: '/docs/blocks/block-api/rpc#calling-from-javascript',
      },
      {
        icon: Shield,
        title: 'Access control',
        description:
          'Public with CSRF, open for webhooks, or capability-based. Per-function HTTP method control.',
        href: '/docs/blocks/block-api/rpc#access-control',
      },
      {
        icon: Workflow,
        title: 'Cross-block calls',
        description:
          'Call functions on any block from JavaScript or PHP. Compose logic across blocks.',
        href: '/docs/blocks/block-api/rpc#cross-block-calling',
      },
    ],
  },
  {
    title: 'Infrastructure',
    description: 'Cron, CLI, portability, and security.',
    icon: Layers,
    items: [
      {
        icon: Clock,
        title: 'Scheduled tasks',
        description:
          'Define cron jobs in cron.php. Auto-registered with WP Cron, auto-cleaned on deactivation.',
        href: '/docs/blocks/block-api/cron',
      },
      {
        icon: Terminal,
        title: 'CLI',
        description:
          'wp bs commands for blocks, db, rpc, cron, and settings. Full --format=json support for scripting.',
        href: '/docs/blocks/cli',
      },
      {
        icon: FolderOpen,
        title: 'Portability',
        description:
          'SQLite and JSONC storage travel with the code. Copy the folder to deploy the entire app with its data.',
        href: '/guides/full-stack-blocks',
      },
    ],
  },
];

export function Features() {
  return (
    <section className="px-6 pb-16 sm:pb-24 lg:px-16 xl:px-20">
      <div className="flex flex-col gap-16">
        {sections.map((section) => (
          <div key={section.title}>
            <div className="flex items-center gap-3 mb-6">
              <div className="flex items-center justify-center size-8 rounded-lg bg-fd-primary/10">
                <section.icon className="size-4 text-fd-primary" />
              </div>
              <div>
                <h2 className="text-xl font-semibold text-fd-foreground">
                  {section.title}
                </h2>
                <p className="text-sm text-fd-muted-foreground">
                  {section.description}
                </p>
              </div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-8 sm:gap-10">
              {section.items.map((item) => (
                <Link
                  key={item.title}
                  href={item.href}
                  className="group flex flex-col gap-2 text-sm/7"
                >
                  <div className="flex items-start gap-3 text-fd-foreground">
                    <div className="flex items-center h-[1lh]">
                      <item.icon className="h-3.5 w-3.5 text-fd-primary" />
                    </div>
                    <h3 className="font-semibold group-hover:text-fd-primary transition-colors">
                      {item.title}
                    </h3>
                  </div>
                  <p className="text-fd-muted-foreground text-pretty">
                    {item.description}
                  </p>
                </Link>
              ))}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

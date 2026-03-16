import {
  Layers,
  Database,
  Globe,
  Clock,
  Terminal,
  Shield,
  FolderOpen,
  Zap,
  Server,
} from 'lucide-react';
import Link from 'next/link';
import { Button } from 'onedocs';
import { CodeCard } from './code-card';
import { Feature } from './feature';
import { Section, SectionIcon } from './section';

const dbPreview = `<?php
return [
    'storage'    => 'sqlite',
    'userScoped' => true,
    'capability' => [
        'create' => true,
        'read'   => true,
        'update' => true,
        'delete' => true,
    ],
    'fields' => [
        'text' => ['type' => 'string', 'required' => true],
        'done' => ['type' => 'boolean', 'default' => false],
    ],
];`;

const rpcPreview = `<?php
return [
    'toggle' => function (array $params): array {
        $db   = Db::get('my-theme/todo');
        $todo = $db->get_record((int) $params['id']);
        $db->update((int) $params['id'], ['done' => !$todo['done']]);
        return ['success' => true];
    },
];`;

const details = [
  {
    icon: Database,
    title: 'Database',
    description:
      'Define schemas in db.php. CRUD endpoints, validation, and storage are generated automatically. Four backends: MySQL, SQLite, JSONC, post meta.',
    href: '/docs/blocks/block-api/database',
  },
  {
    icon: Zap,
    title: 'RPC',
    description:
      'Server functions in rpc.php callable from the frontend with bs.fn(). Capability checks, HTTP method control, and lifecycle hooks.',
    href: '/docs/blocks/block-api/rpc',
  },
  {
    icon: Clock,
    title: 'Cron',
    description:
      'Scheduled tasks in cron.php. Runs on WordPress Cron with automatic cleanup on deactivation.',
    href: '/docs/blocks/block-api/cron',
  },
  {
    icon: Terminal,
    title: 'CLI',
    description:
      'Full WP-CLI integration via wp bs. Manage blocks, query records, call RPC functions, run cron jobs, and inspect settings.',
    href: '/docs/blocks/cli',
  },
  {
    icon: Shield,
    title: 'CSRF Protection',
    description:
      'Public endpoints are protected by X-BS-Token automatically. Use "open" for webhooks and external APIs.',
    href: '/docs/blocks/block-api/database#csrf-protection',
  },
  {
    icon: Globe,
    title: 'User Scoping',
    description:
      'Set userScoped: true to auto-isolate data per user. No manual filtering, no ownership checks.',
    href: '/docs/blocks/block-api/database#user-scoped-data',
  },
  {
    icon: FolderOpen,
    title: 'Portability',
    description:
      'With SQLite or JSONC storage, the entire app (code + data) is a folder. Copy it to deploy.',
    href: '/guides/full-stack-blocks',
  },
  {
    icon: Server,
    title: 'Components',
    description:
      'Non-editor blocks for programmatic rendering. Build reusable UI with the full Blockstudio pipeline.',
    href: '/docs/blocks/components',
  },
];

export function FullStack() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Layers />
        </SectionIcon>
      }
      title="Full-stack blocks"
      description="Build complete applications inside a block folder. Database, server logic, scheduled tasks, and CLI, all from files."
    >
      <div className="flex flex-col gap-10">
        <Feature
          headline="Drop a file, get an API"
          description={
            <>
              <p>
                Add a{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  db.php
                </code>{' '}
                to define your data model. Blockstudio generates REST endpoints,
                validates input against the schema, manages storage, and provides
                both a JavaScript client and a PHP API. Four storage backends:
                MySQL tables, SQLite, JSONC files, and post meta.
              </p>
              <p>
                Add a{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  rpc.php
                </code>{' '}
                for custom server functions. Add a{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  cron.php
                </code>{' '}
                for scheduled tasks. Each file does one thing. No boilerplate, no
                build step, no REST controller classes.
              </p>
            </>
          }
          cta={
            <Button href="/guides/full-stack-blocks" className="w-max">
              Full-Stack Blocks guide &rarr;
            </Button>
          }
          demo={<CodeCard code={dbPreview} lang="php" />}
        />
        <Feature
          headline="Server functions in one line"
          description={
            <>
              <p>
                Define PHP functions in{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  rpc.php
                </code>{' '}
                and call them from the frontend with{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  bs.fn()
                </code>
                . Blockstudio handles endpoint routing, authentication, CSRF
                protection, and JSON serialization. Inspired by tRPC: define
                procedures server-side, call them from the client.
              </p>
            </>
          }
          demo={<CodeCard code={rpcPreview} lang="php" />}
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
          {details.map((detail) => (
            <Link
              key={detail.title}
              href={detail.href}
              className="group flex flex-col gap-2 text-sm/7"
            >
              <div className="flex items-start gap-3 text-fd-foreground">
                <div className="flex items-center h-[1lh]">
                  <detail.icon className="h-3.5 w-3.5 text-fd-primary" />
                </div>
                <h3 className="font-semibold group-hover:text-fd-primary transition-colors">
                  {detail.title}
                </h3>
              </div>
              <p className="text-fd-muted-foreground text-pretty">
                {detail.description}
              </p>
            </Link>
          ))}
        </div>
      </div>
    </Section>
  );
}

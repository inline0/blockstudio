import { Button } from 'onedocs';
import { CodeCard } from '../homepage/code-card';

const dbCode = `return [
    'storage'    => 'sqlite',
    'userScoped' => true,
    'capability' => [
        'create' => true,
        'read'   => true,
        'update' => true,
        'delete' => true,
    ],
    'fields' => [
        'text' => [
            'type'     => 'string',
            'required' => true,
        ],
        'done' => [
            'type'    => 'boolean',
            'default' => false,
        ],
    ],
];`;

const rpcCode = `return [
    'toggle' => function (array $params): array {
        $db   = Db::get('my-theme/todo');
        $todo = $db->get_record((int) $params['id']);
        $db->update((int) $params['id'], [
            'done' => !$todo['done'],
        ]);
        return ['success' => true];
    },
    'clear_done' => function (array $params): array {
        $db    = Db::get('my-theme/todo');
        $todos = $db->list();
        foreach ($todos as $todo) {
            if ($todo['done']) {
                $db->delete((int) $todo['id']);
            }
        }
        return ['cleared' => true];
    },
];`;

function ComparisonHeader({
  title,
  subtitle,
}: {
  title: string;
  subtitle: string;
}) {
  return (
    <div className="flex items-center justify-between px-1">
      <div>
        <h3 className="text-sm font-semibold text-fd-foreground">{title}</h3>
        <p className="text-xs text-fd-muted-foreground">{subtitle}</p>
      </div>
    </div>
  );
}

export function Hero() {
  return (
    <section className="px-6 pt-16 pb-12 sm:pt-24 sm:pb-16 lg:px-16 xl:px-20">
      <div className="flex flex-wrap items-end justify-between gap-6">
        <div className="max-w-2xl">
          <h1 className="text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl text-balance">
            Full-stack blocks
          </h1>
          <p className="mt-4 text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance">
            Blocks can define their own database, server functions, scheduled
            tasks, and CLI commands. Everything lives in one folder, works
            without a build step, and deploys by copying files.
          </p>
        </div>
        <Button href="/guides/full-stack-blocks" className="w-max shrink-0">
          Read the guide &rarr;
        </Button>
      </div>

      <div className="mt-10 grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div className="flex flex-col gap-3">
          <ComparisonHeader
            title="db.php"
            subtitle="Define schema, get CRUD endpoints"
          />
          <div className="rounded-2xl bg-fd-secondary/50 overflow-hidden">
            <CodeCard code={dbCode} lang="php" />
          </div>
        </div>

        <div className="flex flex-col gap-3">
          <ComparisonHeader
            title="rpc.php"
            subtitle="Custom server functions"
          />
          <div className="rounded-2xl bg-fd-secondary/50 overflow-hidden">
            <CodeCard code={rpcCode} lang="php" />
          </div>
        </div>
      </div>
    </section>
  );
}

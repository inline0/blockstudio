import { Button } from 'onedocs';

export function Hero() {
  return (
    <section className="px-6 pt-16 pb-12 sm:pt-24 sm:pb-16 lg:px-16 xl:px-20">
      <div className="flex flex-wrap items-end justify-between gap-6">
        <div className="max-w-2xl">
          <h1 className="text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl text-balance">
            Full-stack blocks
          </h1>
          <p className="mt-4 text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance">
            Build complete applications inside a block folder. Database, server
            functions, scheduled tasks, and CLI. No separate plugins, no build
            step, no boilerplate. Drop a file, get an API.
          </p>
        </div>
        <Button href="/guides/full-stack-blocks" className="w-max shrink-0">
          Read the guide &rarr;
        </Button>
      </div>

      <div className="mt-10 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Panel title="db.php" badge="CRUD + Validation">
          {`return [
    'storage'    => 'sqlite',
    'userScoped' => true,
    'fields'     => [
        'text' => [
            'type'     => 'string',
            'required' => true,
        ],
        'done' => [
            'type'    => 'boolean',
            'default' => false,
        ],
    ],
];`}
        </Panel>
        <Panel title="rpc.php" badge="Server Functions">
          {`return [
    'toggle' => function (array $params): array {
        $db   = Db::get('my-theme/todo');
        $todo = $db->get_record((int) $params['id']);
        $db->update((int) $params['id'], [
            'done' => !$todo['done'],
        ]);
        return ['success' => true];
    },
];`}
        </Panel>
      </div>
    </section>
  );
}

function Panel({
  title,
  badge,
  children,
}: {
  title: string;
  badge: string;
  children: string;
}) {
  return (
    <div className="rounded-2xl bg-fd-secondary p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
      <div className="flex items-center justify-between mb-4">
        <span className="text-fd-foreground font-sans font-medium text-sm">
          {title}
        </span>
        <span className="rounded bg-fd-primary/10 px-2 py-0.5 text-[11px] font-sans font-medium text-fd-primary">
          {badge}
        </span>
      </div>
      <pre className="text-fd-muted-foreground whitespace-pre overflow-x-auto">
        <code>{children}</code>
      </pre>
    </div>
  );
}

export function FileStructure() {
  return (
    <section className="px-6 pb-12 sm:pb-16 lg:px-16 xl:px-20">
      <div className="grid grid-cols-1 xl:grid-cols-[3fr_7fr] gap-12 xl:gap-16">
        <div className="flex h-full flex-col justify-between gap-8">
          <div>
            <h2 className="text-2xl font-semibold tracking-tight text-fd-foreground sm:text-3xl text-balance">
              Every capability is a file
            </h2>
            <p className="mt-4 text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance">
              Drop a file, it works. Remove a file, the feature disappears. No
              configuration wiring, no registration code.
            </p>
          </div>
        </div>

        <div className="rounded-2xl bg-fd-secondary p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
          <div className="flex items-center gap-2 mb-4">
            <span className="text-fd-muted-foreground text-xs font-sans font-medium tracking-wide uppercase">
              Application Block
            </span>
          </div>
          <div className="flex flex-col gap-0.5 text-fd-muted-foreground">
            <div>blockstudio/</div>
            <div className="pl-4">{'└── '}my-app/</div>
            <FileRow indent={8} name="block.json" label="Block definition" />
            <FileRow indent={8} name="index.php" label="Template + Interactivity API" />
            <FileRow indent={8} name="style.css" label="Scoped styles" />
            <FileRow indent={8} name="script-inline.js" label="Client logic (auto block detection)" />
            <FileRow indent={8} name="db.php" label="Data model → CRUD endpoints" color="emerald" />
            <FileRow indent={8} name="rpc.php" label="Server functions → bs.fn()" color="emerald" />
            <FileRow indent={8} name="cron.php" label="Scheduled tasks → WP Cron" color="emerald" />
            <div className="pl-8">{'└── '}db/</div>
            <FileRow indent={12} name="default.sqlite" label="Portable database" color="blue" last />
          </div>
        </div>
      </div>
    </section>
  );
}

function FileRow({
  indent,
  name,
  label,
  color,
  last,
}: {
  indent: number;
  name: string;
  label: string;
  color?: string;
  last?: boolean;
}) {
  const colorClasses = {
    emerald: 'bg-emerald-600/15 dark:bg-emerald-400/15 text-emerald-600 dark:text-emerald-400',
    blue: 'bg-blue-600/15 dark:bg-blue-400/15 text-blue-600 dark:text-blue-400',
  };

  return (
    <div style={{ paddingLeft: `${indent * 4}px` }}>
      {last ? '└── ' : '├── '}
      <span className="text-fd-foreground">{name}</span>
      <span className="ml-3 text-fd-muted-foreground/60 text-[11px] font-sans">
        {label}
      </span>
      {color && (
        <span
          className={`ml-2 rounded px-1.5 py-0.5 text-[11px] font-sans font-medium ${colorClasses[color as keyof typeof colorClasses]}`}
        >
          NEW
        </span>
      )}
    </div>
  );
}

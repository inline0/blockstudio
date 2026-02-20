import Link from 'next/link';

function ArchiveCardImage({ title }: { title: string }) {
  const repeated = `${title}  `.repeat(20);

  return (
    <div className="relative aspect-[16/9] overflow-hidden rounded-lg bg-fd-secondary/50">
      <div
        className="absolute inset-0 flex flex-col justify-center gap-1.5 -rotate-12 scale-125"
        aria-hidden
      >
        {Array.from({ length: 8 }).map((_, i) => (
          <p
            key={i}
            className="whitespace-nowrap text-2xl font-bold"
            style={{
              color: 'transparent',
              WebkitTextStroke:
                '0.5px color-mix(in srgb, var(--color-fd-primary) 8%, transparent)',
              marginLeft: `${(i % 3) * -40}px`,
            }}
          >
            {repeated}
          </p>
        ))}
      </div>
    </div>
  );
}

export function ArchiveCard({
  href,
  title,
  description,
  date,
}: {
  href: string;
  title: string;
  description?: string;
  date?: Date;
}) {
  return (
    <Link
      href={href}
      className="flex flex-col rounded-2xl border bg-fd-card p-4 shadow-sm transition-colors hover:bg-fd-secondary/50 no-underline"
    >
      <ArchiveCardImage title={title} />
      <p className="mt-4 font-medium text-pretty">{title}</p>
      {description && (
        <p className="mt-1 text-sm text-fd-muted-foreground text-pretty">
          {description}
        </p>
      )}
      {date && (
        <p className="mt-auto pt-4 text-xs text-fd-primary">
          {date.toDateString()}
        </p>
      )}
    </Link>
  );
}

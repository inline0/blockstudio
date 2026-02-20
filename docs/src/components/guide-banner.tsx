import { BookOpenIcon, ArrowRightIcon } from 'lucide-react';
import Link from 'next/link';

export function GuideBanner({
  href,
  title,
  description,
}: {
  href: string;
  title: string;
  description?: string;
}) {
  return (
    <Link
      href={href}
      className="not-prose flex items-center gap-3 rounded-xl border bg-fd-card p-4 no-underline transition-colors hover:bg-fd-secondary/50"
    >
      <BookOpenIcon className="size-5 shrink-0 text-fd-primary" />
      <div className="flex-1">
        <span className="font-medium text-fd-primary">Guide: {title}</span>
        {description && (
          <span className="mt-0.5 block text-sm text-fd-muted-foreground">
            {description}
          </span>
        )}
      </div>
      <ArrowRightIcon className="size-4 shrink-0 text-fd-muted-foreground" />
    </Link>
  );
}

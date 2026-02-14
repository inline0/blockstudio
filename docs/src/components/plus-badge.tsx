import { ExternalLink } from 'lucide-react';

export function PlusBadge() {
  return (
    <a
      href="https://plus.blockstudio.dev"
      target="_blank"
      rel="noopener noreferrer"
      className="ml-3 hidden lg:inline-flex items-center gap-1.5 rounded-full bg-[var(--color-plus-button)] px-2.5 py-1.5 text-xs font-semibold uppercase tracking-tight text-black transition-colors hover:bg-[var(--color-plus-button)]/80"
    >
      Plus
      <ExternalLink className="size-3" />
    </a>
  );
}

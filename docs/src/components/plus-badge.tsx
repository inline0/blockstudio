import { ExternalLink } from "lucide-react";

export function PlusBadge() {
  return (
    <a
      href="https://plus.blockstudio.dev"
      target="_blank"
      rel="noopener noreferrer"
      className="ml-3 inline-flex items-center gap-1.5 rounded-full border border-[oklch(0.85_0.18_85/0.4)] bg-[oklch(0.85_0.18_85/0.1)] px-2.5 py-1 text-sm font-semibold uppercase tracking-tight text-[oklch(0.85_0.18_85)] transition-colors hover:border-[oklch(0.85_0.18_85/0.6)] hover:bg-[oklch(0.85_0.18_85/0.15)]"
    >
      Plus
      <ExternalLink className="size-3" />
    </a>
  );
}

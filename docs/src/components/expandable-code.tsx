'use client';

import { useState, type ReactNode } from 'react';
import { ChevronDown } from 'lucide-react';

export function ExpandableCode({ children }: { children: ReactNode }) {
  const [expanded, setExpanded] = useState(false);

  return (
    <div className="relative">
      <div
        style={!expanded ? { maxHeight: 527, overflow: 'hidden' } : undefined}
      >
        {children}
      </div>
      {!expanded && (
        <button
          onClick={() => setExpanded(true)}
          className="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 rounded-b-2xl pt-20 pb-4 text-xs font-medium text-fd-muted-foreground transition-colors hover:text-fd-foreground cursor-pointer"
          style={{
            background:
              'linear-gradient(to bottom, transparent, var(--color-fd-secondary) 60%)',
          }}
        >
          Show all
          <ChevronDown className="size-3.5" />
        </button>
      )}
    </div>
  );
}

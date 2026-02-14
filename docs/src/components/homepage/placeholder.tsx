import { cn } from '@/lib/cn';

interface PlaceholderProps {
  label?: string;
  aspect?: 'video' | 'wide' | 'square';
  className?: string;
}

export function Placeholder({
  label,
  aspect = 'video',
  className = '',
}: PlaceholderProps) {
  const aspectClass = {
    video: 'aspect-video',
    wide: 'aspect-[2/1]',
    square: 'aspect-square',
  }[aspect];

  return (
    <div
      className={cn(
        'relative w-full overflow-hidden rounded-lg bg-fd-secondary/50',
        aspectClass,
        className,
      )}
    >
      <svg
        className="absolute inset-0 h-full w-full"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <pattern
            id={`grid-${label}`}
            width="32"
            height="32"
            patternUnits="userSpaceOnUse"
          >
            <path
              d="M 32 0 L 0 0 0 32"
              fill="none"
              stroke="currentColor"
              strokeWidth="0.5"
              className="text-fd-muted-foreground/15"
            />
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill={`url(#grid-${label})`} />
      </svg>
      {label && (
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="rounded-md bg-fd-background/80 px-3 py-1.5 text-xs font-medium text-fd-muted-foreground backdrop-blur-sm">
            {label}
          </span>
        </div>
      )}
    </div>
  );
}

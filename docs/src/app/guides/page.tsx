import type { Metadata } from 'next';
import Link from 'next/link';
import { guides, guideSlug } from '@/lib/source';

export const metadata: Metadata = {
  title: 'Guides',
  description:
    'In-depth guides for building with Blockstudio.',
};

function GuideImage({ title }: { title: string }) {
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

export default function GuidesIndex() {
  const posts = [...guides].sort(
    (a, b) => b.date.getTime() - a.date.getTime(),
  );

  return (
    <div className="px-6 py-16 sm:py-20 lg:px-10">
      <h1 className="mb-2 text-3xl font-semibold">Guides</h1>
      <p className="mb-8 text-fd-muted-foreground">
        In-depth guides for building with Blockstudio.
      </p>
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {posts.map((post) => (
          <Link
            key={post.info.path}
            href={`/guides/${guideSlug(post.info.path)}`}
            className="flex flex-col rounded-2xl border bg-fd-card p-4 shadow-sm transition-colors hover:bg-fd-secondary/50 no-underline"
          >
            <GuideImage title={post.title} />
            <p className="mt-4 font-medium text-pretty">{post.title}</p>
            {post.description && (
              <p className="mt-1 text-sm text-fd-muted-foreground text-pretty">
                {post.description}
              </p>
            )}
            <p className="mt-auto pt-4 text-xs text-fd-primary">
              {post.date.toDateString()}
            </p>
          </Link>
        ))}
      </div>
    </div>
  );
}

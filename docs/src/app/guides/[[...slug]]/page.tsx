import type { MDXComponents } from 'mdx/types';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { DocsPage, mdxComponents } from 'onedocs';
import { guidesSource } from '@/lib/source';

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

function GuidesIndex() {
  const pages = guidesSource.getPages().filter((p) => p.slugs.length > 0);

  return (
    <DocsPage>
      <h1>Guides</h1>
      <p className="text-fd-muted-foreground">
        In-depth guides for building with Blockstudio.
      </p>
      <div className="mt-6 grid grid-cols-1 gap-2 sm:grid-cols-2">
        {pages.map((page) => (
          <Link
            key={page.url}
            href={page.url}
            className="flex flex-col rounded-2xl border bg-fd-card p-4 shadow-sm transition-colors hover:bg-fd-secondary/50 no-underline"
          >
            <GuideImage title={page.data.title} />
            <p className="mt-4 font-medium text-pretty">{page.data.title}</p>
            {page.data.description && (
              <p className="mt-1 text-sm text-fd-muted-foreground text-pretty">
                {page.data.description}
              </p>
            )}
          </Link>
        ))}
      </div>
    </DocsPage>
  );
}

export default async function Page(props: {
  params: Promise<{ slug?: string[] }>;
}) {
  const params = await props.params;

  if (!params.slug) {
    return <GuidesIndex />;
  }

  const page = guidesSource.getPage(params.slug);
  if (!page) notFound();

  const MDX = page.data.body;

  return (
    <DocsPage toc={page.data.toc}>
      <MDX components={mdxComponents as MDXComponents} />
    </DocsPage>
  );
}

export async function generateStaticParams() {
  return guidesSource.generateParams();
}

export async function generateMetadata(props: {
  params: Promise<{ slug?: string[] }>;
}) {
  const params = await props.params;

  if (!params.slug) {
    return {
      title: 'Guides',
      description: 'In-depth guides for building with Blockstudio.',
    };
  }

  const page = guidesSource.getPage(params.slug);
  if (!page) notFound();

  return {
    title: page.data.title,
    description: page.data.description,
  };
}

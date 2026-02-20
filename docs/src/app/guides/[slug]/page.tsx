import { TOCItems } from 'fumadocs-ui/components/toc/default';
import { TOCProvider, TOCScrollArea } from 'fumadocs-ui/components/toc/index';
import { ChevronLeft } from 'lucide-react';
import type { MDXComponents } from 'mdx/types';
import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { mdxComponents } from 'onedocs';
import { guides, guideSlug } from '@/lib/source';

function getGuide(slug: string) {
  return guides.find((guide) => guideSlug(guide.info.path) === slug);
}

export default async function GuidePage(props: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await props.params;
  const guide = getGuide(slug);
  if (!guide) notFound();

  const MDX = guide.body;

  return (
    <TOCProvider toc={guide.toc}>
      <div className="grid grid-cols-1 xl:grid-cols-[1fr_268px]">
        <article className="min-w-0 max-w-3xl mx-auto px-6 pt-8 pb-16 lg:px-10">
          <Link
            href="/guides"
            className="mb-6 inline-flex items-center gap-1 text-sm text-fd-muted-foreground no-underline transition-colors hover:text-fd-foreground"
          >
            <ChevronLeft className="size-4" />
            Back to Guides
          </Link>
          <div className="mb-8 flex flex-row gap-4 text-sm">
            <div>
              <p className="text-fd-muted-foreground">Written by</p>
              <p className="font-medium">{guide.author}</p>
            </div>
            <div>
              <p className="text-fd-muted-foreground">At</p>
              <p className="font-medium">{guide.date.toDateString()}</p>
            </div>
          </div>

          <h1 className="mb-4 text-3xl font-semibold">{guide.title}</h1>
          {guide.description && (
            <p className="mb-8 text-fd-muted-foreground">
              {guide.description}
            </p>
          )}

          <div className="prose prose-fd">
            <MDX components={mdxComponents as MDXComponents} />
          </div>
        </article>

        <aside className="sticky top-16 hidden h-[calc(100dvh-4rem)] flex-col pt-8 pe-6 xl:flex">
          <p className="mb-3 shrink-0 text-sm font-medium text-fd-muted-foreground">
            On this page
          </p>
          <TOCScrollArea className="flex min-h-0 flex-1 flex-col overflow-y-auto pb-8">
            <TOCItems />
          </TOCScrollArea>
        </aside>
      </div>
    </TOCProvider>
  );
}

export function generateStaticParams() {
  return guides.map((guide) => ({ slug: guideSlug(guide.info.path) }));
}

export async function generateMetadata(props: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await props.params;
  const guide = getGuide(slug);
  if (!guide) notFound();

  return {
    title: guide.title,
    description: guide.description,
  };
}

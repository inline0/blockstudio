import type { MDXComponents } from 'mdx/types';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { DocsPage, mdxComponents } from 'onedocs';
import { guidesSource } from '@/lib/source';

function GuidesIndex() {
  const pages = guidesSource.getPages().filter((p) => p.slugs.length > 0);

  return (
    <DocsPage>
      <h1>Guides</h1>
      <p className="text-fd-muted-foreground">
        In-depth guides for building with Blockstudio.
      </p>
      <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        {pages.map((page) => (
          <Link
            key={page.url}
            href={page.url}
            className="flex flex-col rounded-lg border bg-fd-card p-4 transition-colors hover:bg-fd-accent no-underline"
          >
            <p className="font-medium">{page.data.title}</p>
            {page.data.description && (
              <p className="mt-1 text-sm text-fd-muted-foreground">
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

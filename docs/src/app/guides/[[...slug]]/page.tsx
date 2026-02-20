import type { MDXComponents } from 'mdx/types';
import { notFound } from 'next/navigation';
import { DocsPage, mdxComponents } from 'onedocs';
import { ArchiveCard } from '@/components/archive-card';
import { guidesSource } from '@/lib/source';

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
          <ArchiveCard
            key={page.url}
            href={page.url}
            title={page.data.title}
            description={page.data.description}
          />
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

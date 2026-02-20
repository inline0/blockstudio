import type { MDXComponents } from 'mdx/types';
import { notFound } from 'next/navigation';
import { DocsPage, mdxComponents } from 'onedocs';
import { guidesSource } from '@/lib/source';

export default async function Page(props: {
  params: Promise<{ slug?: string[] }>;
}) {
  const params = await props.params;
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
  const page = guidesSource.getPage(params.slug);
  if (!page) notFound();

  return {
    title: page.data.title,
    description: page.data.description,
  };
}

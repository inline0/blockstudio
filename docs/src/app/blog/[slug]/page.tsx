import { blog, blogSlug } from "@/lib/source";
import { mdxComponents } from "onedocs";
import { InlineTOC } from "fumadocs-ui/components/inline-toc";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import type { MDXComponents } from "mdx/types";

function getPost(slug: string) {
  return blog.find((post) => blogSlug(post.info.path) === slug);
}

export default async function BlogPost(props: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await props.params;
  const post = getPost(slug);
  if (!post) notFound();

  const MDX = post.body;

  return (
    <article className="mx-auto flex w-full max-w-[800px] flex-col px-4 py-8">
      <div className="mb-8 flex flex-row gap-4 text-sm">
        <div>
          <p className="mb-1 text-fd-muted-foreground">Written by</p>
          <p className="font-medium">{post.author}</p>
        </div>
        <div>
          <p className="mb-1 text-fd-muted-foreground">At</p>
          <p className="font-medium">{post.date.toDateString()}</p>
        </div>
      </div>

      <h1 className="mb-4 text-3xl font-semibold">{post.title}</h1>
      {post.description && (
        <p className="mb-8 text-fd-muted-foreground">{post.description}</p>
      )}

      <div className="prose min-w-0 flex-1">
        <InlineTOC items={post.toc} />
        <MDX components={mdxComponents as MDXComponents} />
      </div>
    </article>
  );
}

export function generateStaticParams() {
  return blog.map((post) => ({ slug: blogSlug(post.info.path) }));
}

export async function generateMetadata(props: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await props.params;
  const post = getPost(slug);
  if (!post) notFound();

  return {
    title: post.title,
    description: post.description,
  };
}

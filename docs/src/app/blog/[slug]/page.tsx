import { blog, blogSlug } from "@/lib/source";
import { DocsPage, mdxComponents } from "onedocs";
import { notFound } from "next/navigation";
import Link from "next/link";
import { ChevronLeft } from "lucide-react";
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
    <DocsPage toc={post.toc}>
      <Link
        href="/blog"
        className="mb-6 inline-flex items-center gap-1 text-sm text-fd-muted-foreground no-underline transition-colors hover:text-fd-foreground"
      >
        <ChevronLeft className="size-4" />
        Back to Blog
      </Link>
      <div className="mb-8 flex flex-row gap-4 text-sm">
        <div>
          <p className="text-fd-muted-foreground">Written by</p>
          <p className="font-medium">{post.author}</p>
        </div>
        <div>
          <p className="text-fd-muted-foreground">At</p>
          <p className="font-medium">{post.date.toDateString()}</p>
        </div>
      </div>

      <h1 className="mb-4 text-3xl font-semibold">{post.title}</h1>
      {post.description && (
        <p className="mb-8 text-fd-muted-foreground">{post.description}</p>
      )}

      <MDX components={mdxComponents as MDXComponents} />
    </DocsPage>
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

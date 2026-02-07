import { blog, blogSlug } from "@/lib/source";
import Link from "next/link";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Blog",
  description: "News and updates from the Blockstudio team.",
};

export default function BlogIndex() {
  const posts = [...blog].sort(
    (a, b) => b.date.getTime() - a.date.getTime(),
  );

  return (
    <main className="mx-auto w-full max-w-[var(--fd-layout-width)] px-4 py-8 md:py-12">
      <h1 className="mb-2 text-3xl font-semibold">Blog</h1>
      <p className="mb-8 text-fd-muted-foreground">
        News and updates from the Blockstudio team.
      </p>
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {posts.map((post) => (
          <Link
            key={post.info.path}
            href={`/blog/${blogSlug(post.info.path)}`}
            className="flex flex-col rounded-2xl border bg-fd-card p-4 shadow-sm transition-colors hover:bg-fd-accent hover:text-fd-accent-foreground"
          >
            <p className="font-medium">{post.title}</p>
            {post.description && (
              <p className="mt-1 text-sm text-fd-muted-foreground">
                {post.description}
              </p>
            )}
            <p className="mt-auto pt-4 text-xs text-fd-primary">
              {post.date.toDateString()}
            </p>
          </Link>
        ))}
      </div>
    </main>
  );
}

import type { Metadata } from 'next';
import { DocsPage } from 'onedocs';
import { ArchiveCard } from '@/components/archive-card';
import { blog, blogSlug } from '@/lib/source';

export const metadata: Metadata = {
  title: 'Blog',
  description: 'News and updates from the Blockstudio team.',
};

export default function BlogIndex() {
  const posts = [...blog].sort((a, b) => b.date.getTime() - a.date.getTime());

  return (
    <DocsPage className="archive-page">
      <h1 className="mb-2 text-3xl font-semibold">Blog</h1>
      <p className="mb-8 text-fd-muted-foreground">
        News and updates from the Blockstudio team.
      </p>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {posts.map((post) => (
          <ArchiveCard
            key={post.info.path}
            href={`/blog/${blogSlug(post.info.path)}`}
            title={post.title}
            description={post.description}
            date={post.date}
          />
        ))}
      </div>
    </DocsPage>
  );
}

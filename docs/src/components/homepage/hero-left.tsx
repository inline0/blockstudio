import Link from 'next/link';
import { Download } from 'lucide-react';
import { CodeBlock, Button } from 'onedocs';

async function getLatestVersion(): Promise<string | null> {
  try {
    const res = await fetch(
      'https://api.github.com/repos/inline0/blockstudio/releases/latest',
      { next: { revalidate: 3600 } },
    );
    if (!res.ok) return null;
    const data = await res.json();
    return data.tag_name ?? null;
  } catch {
    return null;
  }
}

export async function HeroLeft() {
  const version = await getLatestVersion();

  return (
    <>
      <Link
        href="/blog/introducing-blockstudio-7"
        className="inline-flex items-center gap-1.5 rounded-full border border-fd-border bg-fd-secondary/50 pl-1 pr-1 py-1 text-xs text-fd-muted-foreground transition-colors hover:bg-fd-secondary hover:text-fd-foreground w-fit mb-4"
      >
        <span className="rounded-full bg-fd-primary px-1.5 py-0.5 text-xs font-medium text-fd-primary-foreground">
          New
        </span>
        <span className="px-3">
          Introducing Blockstudio 7, now free and open source
        </span>
      </Link>
      <h1 className="text-left text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl">
        The block framework
        <br />
        for WordPress
      </h1>
      <p className="text-left max-w-xl leading-normal text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance mt-4">
        The fastest way to build custom blocks. Define fields in JSON, write
        templates in PHP, Twig, or Blade. No build step required.
      </p>
      <div className="mt-8 w-full">
        <CodeBlock
          lang="bash"
          code="composer require blockstudio/blockstudio"
          className="!my-0"
        />
        <div className="flex items-center gap-3 mt-4">
          <Button href="/docs">Get Started</Button>
          <a
            href="https://github.com/inline0/blockstudio/releases"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center justify-center gap-2 rounded-full border border-fd-border px-4 py-2 text-sm font-medium text-fd-foreground transition-colors hover:bg-fd-secondary whitespace-nowrap"
          >
            <Download className="size-4" />
            Download{version ? ` ${version}` : ''}
          </a>
        </div>
      </div>
    </>
  );
}

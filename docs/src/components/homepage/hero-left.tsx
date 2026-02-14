import Link from 'next/link';
import { CodeBlock, Button } from 'onedocs';

export async function HeroLeft() {
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
        The fastest way to build custom blocks. Write templates in PHP, Twig, or
        Blade with 25+ field types and zero JavaScript.
      </p>
      <div className="flex flex-wrap items-end gap-x-8 gap-y-6 mt-8 w-full">
        <div className="flex-1">
          <CodeBlock
            lang="bash"
            code="composer require blockstudio/blockstudio"
            className="!my-0"
          />
        </div>
        <Button href="/docs">Get Started</Button>
      </div>
    </>
  );
}

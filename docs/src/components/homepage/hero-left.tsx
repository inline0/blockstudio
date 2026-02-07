import { CodeBlock, Button } from "onedocs";

export async function HeroLeft() {
  return (
    <>
      <h1 className="text-left text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl">
        The block framework<br />for WordPress
      </h1>
      <p className="text-left max-w-xl leading-normal text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance mt-4">
        The fastest way to build custom blocks. Write templates in PHP, Twig, or Blade with 25+ field types and zero JavaScript.
      </p>
      <div className="flex flex-wrap items-end gap-x-8 gap-y-6 mt-8 w-full">
        <div className="flex-1">
          <CodeBlock
            lang="bash"
            code="composer require blockstudio/blockstudio"
            className="!my-0"
          />
        </div>
        <Button href="/docs/getting-started">Get Started</Button>
      </div>
    </>
  );
}

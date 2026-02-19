import type { ReactNode } from 'react';
import { Button } from 'onedocs';
import { cn } from '@/lib/cn';
import { ClickToPlayVideo } from './click-to-play-video';

function Panel({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('rounded-2xl bg-fd-secondary', className)}>
      {children}
    </div>
  );
}

function TerminalGraphic() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <Panel className="p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
        <div className="flex items-center gap-2 mb-4">
          <div className="size-3 rounded-full bg-fd-muted-foreground/20" />
          <div className="size-3 rounded-full bg-fd-muted-foreground/20" />
          <div className="size-3 rounded-full bg-fd-muted-foreground/20" />
        </div>
        <div className="text-fd-muted-foreground">~/starter-theme</div>
        <div className="mt-1">
          <span className="text-emerald-600 dark:text-emerald-400">{'❯'}</span>{' '}
          <span className="text-fd-foreground">
            Create a pricing block with three tiers,
          </span>
        </div>
        <div className="text-fd-foreground pl-4">
          a toggle for monthly/annual billing, and
        </div>
        <div className="text-fd-foreground pl-4">
          a CTA button for each tier.
        </div>
        <div className="mt-4 flex flex-col gap-1.5">
          <div>
            <span className="text-emerald-600 dark:text-emerald-400">
              {'✓'}
            </span>{' '}
            <span className="text-fd-muted-foreground">Created</span>{' '}
            <span className="text-fd-foreground">
              blockstudio/pricing/block.json
            </span>
          </div>
          <div>
            <span className="text-emerald-600 dark:text-emerald-400">
              {'✓'}
            </span>{' '}
            <span className="text-fd-muted-foreground">Created</span>{' '}
            <span className="text-fd-foreground">
              blockstudio/pricing/index.php
            </span>
          </div>
          <div>
            <span className="text-emerald-600 dark:text-emerald-400">
              {'✓'}
            </span>{' '}
            <span className="text-fd-muted-foreground">Created</span>{' '}
            <span className="text-fd-foreground">
              blockstudio/pricing/style.css
            </span>
          </div>
        </div>

        <div className="mt-6 pt-5 border-t border-fd-border/50">
          <span className="text-emerald-600 dark:text-emerald-400">{'❯'}</span>{' '}
          <span className="text-fd-foreground">
            Now create a pricing page with a hero
          </span>
          <div className="text-fd-foreground pl-4">and FAQ section.</div>
          <div className="mt-4 flex flex-col gap-1.5">
            <div>
              <span className="text-emerald-600 dark:text-emerald-400">
                {'✓'}
              </span>{' '}
              <span className="text-fd-muted-foreground">Created</span>{' '}
              <span className="text-fd-foreground">
                pages/pricing/page.json
              </span>
            </div>
            <div>
              <span className="text-emerald-600 dark:text-emerald-400">
                {'✓'}
              </span>{' '}
              <span className="text-fd-muted-foreground">Created</span>{' '}
              <span className="text-fd-foreground">
                pages/pricing/pricing.php
              </span>
            </div>
          </div>
          <div className="mt-4 text-fd-muted-foreground">5 files written</div>
        </div>
      </Panel>

      <Panel className="p-5 sm:p-6 font-mono text-[13px] leading-relaxed">
        <div className="flex items-center gap-2 mb-4">
          <span className="text-fd-muted-foreground text-xs font-sans font-medium tracking-wide uppercase">
            Files
          </span>
        </div>
        <div className="flex flex-col gap-0.5 text-fd-muted-foreground">
          <div>blockstudio/</div>
          <div className="pl-4">
            {'├── '}
            <span className="text-fd-foreground">pricing/</span>
            <span className="ml-2 rounded bg-emerald-600/15 dark:bg-emerald-400/15 px-1.5 py-0.5 text-[11px] font-sans font-medium text-emerald-600 dark:text-emerald-400">
              NEW
            </span>
          </div>
          <div className="pl-8">
            {'├── '}
            <span className="text-fd-foreground">block.json</span>
          </div>
          <div className="pl-8">
            {'├── '}
            <span className="text-fd-foreground">index.php</span>
          </div>
          <div className="pl-8">
            {'└── '}
            <span className="text-fd-foreground">style.css</span>
          </div>
          <div className="pl-4">{'├── '}hero/</div>
          <div className="pl-8">{'├── '}block.json</div>
          <div className="pl-8">{'└── '}index.php</div>
          <div className="pl-4">{'└── '}testimonials/</div>

          <div className="mt-4">pages/</div>
          <div className="pl-4">
            {'├── '}
            <span className="text-fd-foreground">pricing/</span>
            <span className="ml-2 rounded bg-emerald-600/15 dark:bg-emerald-400/15 px-1.5 py-0.5 text-[11px] font-sans font-medium text-emerald-600 dark:text-emerald-400">
              NEW
            </span>
          </div>
          <div className="pl-8">
            {'├── '}
            <span className="text-fd-foreground">page.json</span>
          </div>
          <div className="pl-8">
            {'└── '}
            <span className="text-fd-foreground">pricing.php</span>
          </div>
          <div className="pl-4">{'├── '}about/</div>
          <div className="pl-4">{'└── '}contact/</div>
        </div>
      </Panel>
    </div>
  );
}

export function AiHero() {
  return (
    <>
      <section className="px-6 pt-16 pb-12 sm:pt-24 sm:pb-16 lg:px-16 xl:px-20">
        <div className="flex flex-wrap items-end justify-between gap-6">
          <div className="max-w-2xl">
            <h1 className="text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl text-balance">
              AI-native block development
            </h1>
            <p className="mt-4 text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance">
              Blockstudio&apos;s file-based architecture and zero build tooling
              create the ideal environment for AI coding agents. The video
              below shows a complete WordPress site with 10 custom blocks and 6
              pages built from a single prompt.
            </p>
          </div>
          <Button href="/docs/dev/ai" className="w-max shrink-0">
            Read the docs &rarr;
          </Button>
        </div>
        <div className="mt-10 overflow-hidden rounded-2xl border border-fd-border bg-fd-secondary">
          <ClickToPlayVideo
            src="https://blockstudio-cdn.hi-3dc.workers.dev/blockstudio7_v1_web.mp4"
            poster="https://blockstudio-cdn.hi-3dc.workers.dev/blockstudio7_v1_web_poster.jpg"
          />
        </div>
      </section>

      <section className="px-6 pb-12 sm:pb-16 lg:px-16 xl:px-20">
        <div className="grid grid-cols-1 xl:grid-cols-[3fr_7fr] gap-12 xl:gap-16">
          <div className="flex h-full flex-col justify-between gap-8">
            <div>
              <h2 className="text-2xl font-semibold tracking-tight text-fd-foreground sm:text-3xl text-balance">
                Describe a block, get a block
              </h2>
              <p className="mt-4 text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance">
                Blockstudio&apos;s file-based architecture means AI coding
                agents can scaffold blocks, pages, and patterns from a single
                prompt. No GUI, no database, just files.
              </p>
            </div>
          </div>
          <TerminalGraphic />
        </div>
      </section>
    </>
  );
}

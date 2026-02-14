import type { ReactNode } from 'react';
import { ArrowDownRight, ShieldCheck, Zap, Gauge } from 'lucide-react';
import { cn } from '@/lib/cn';
import { ExpandableCode } from '../expandable-code';
import { CodeCard } from '../homepage/code-card';
import { Section, SectionIcon } from '../homepage/section';

function Panel({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('rounded-2xl bg-fd-secondary/50', className)}>
      {children}
    </div>
  );
}

function ComparisonHeader({
  title,
  subtitle,
  tokens,
  highlight,
}: {
  title: string;
  subtitle: string;
  tokens: string;
  highlight?: boolean;
}) {
  return (
    <div className="flex items-center justify-between px-1">
      <div>
        <h3 className="text-sm font-semibold text-fd-foreground">{title}</h3>
        <p className="text-xs text-fd-muted-foreground">{subtitle}</p>
      </div>
      <span
        className={cn(
          'rounded-full px-2.5 py-1 text-xs font-medium',
          highlight
            ? 'bg-emerald-600/15 dark:bg-emerald-400/15 text-emerald-600 dark:text-emerald-400'
            : 'bg-fd-muted-foreground/15 text-fd-muted-foreground',
        )}
      >
        {tokens}
      </span>
    </div>
  );
}

const blockstudioPageCode = `<h1>Build Websites That Convert</h1>
<p>The all-in-one platform for creating
beautiful, high-performance landing pages.</p>

<block name="core/columns">
  <block name="core/column">
    <h3>Lightning Fast</h3>
    <p>Pages load in under one second.</p>
  </block>
  <block name="core/column">
    <h3>Easy to Use</h3>
    <p>No technical skills required.</p>
  </block>
  <block name="core/column">
    <h3>SEO Optimized</h3>
    <p>Built-in best practices from day one.</p>
  </block>
</block>

<img src="/dashboard.jpg" alt="Dashboard" />

<ul>
  <li>Drag-and-drop editor</li>
  <li>A/B testing built in</li>
  <li>Analytics dashboard</li>
</ul>`;

const wordpressPageCode = `<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Build Websites
That Convert</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The all-in-one platform for creating
beautiful, high-performance landing pages.</p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Lightning Fast</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Pages load in under one second.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Easy to Use</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>No technical skills required.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">SEO Optimized</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Built-in best practices from day one.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:image {"url":"/dashboard.jpg",
"alt":"Dashboard"} -->
<figure class="wp-block-image">
<img src="/dashboard.jpg" alt="Dashboard"/>
</figure>
<!-- /wp:image -->

<!-- wp:list {"ordered":false} -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li>Drag-and-drop editor</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>A/B testing built in</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Analytics dashboard</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->`;

const benefits = [
  {
    icon: ArrowDownRight,
    title: 'Less output to generate',
    description:
      'Agents write plain HTML without comment delimiters, duplicated classes, or wrapper markup. The parser handles the rest.',
  },
  {
    icon: ShieldCheck,
    title: 'Less to get wrong',
    description:
      'Block validation fails on a single mismatched attribute. With HTML input, the parser guarantees valid output every time.',
  },
  {
    icon: Zap,
    title: 'Faster and cheaper',
    description:
      'Fewer output tokens means faster responses and lower API costs across every model and provider.',
  },
];

export function TokenComparison() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Gauge />
        </SectionIcon>
      }
      title="3x fewer tokens per page"
      description="Agents write plain HTML. Blockstudio's parser automatically converts it to serialized WordPress block markup on sync. Less to generate, less to get wrong."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div className="flex flex-col gap-3">
          <ComparisonHeader
            title="Blockstudio"
            subtitle="What agents write"
            tokens="~370 tokens"
            highlight
          />
          <Panel className="overflow-hidden">
            <CodeCard code={blockstudioPageCode} lang="html" />
          </Panel>
        </div>

        <div className="flex flex-col gap-3">
          <ComparisonHeader
            title="WordPress"
            subtitle="What the parser generates"
            tokens="~1,100 tokens"
          />
          <ExpandableCode>
            <Panel className="overflow-hidden">
              <CodeCard code={wordpressPageCode} lang="html" />
            </Panel>
          </ExpandableCode>
        </div>
      </div>

      <div className="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8">
        {benefits.map((card) => (
          <div key={card.title} className="flex flex-col gap-2 text-sm/7">
            <div className="flex items-start gap-3 text-fd-foreground">
              <div className="flex items-center h-[1lh]">
                <card.icon className="h-3.5 w-3.5 text-fd-primary" />
              </div>
              <h3 className="font-semibold">{card.title}</h3>
            </div>
            <p className="text-fd-muted-foreground text-pretty">
              {card.description}
            </p>
          </div>
        ))}
      </div>
    </Section>
  );
}

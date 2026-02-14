import { FileText, Lock, RefreshCw, Key, GitMerge } from 'lucide-react';
import { Button } from 'onedocs';
import { CodeCard } from './code-card';
import { Feature } from './feature';
import { Section, SectionIcon } from './section';

const templateCode = `<div>
  <h1 blockEditingMode="contentOnly">About Us</h1>
  <p>We build tools for WordPress developers.</p>
  <img src="/team.jpg" alt="Our team" />
</div>

<block name="core/columns">
  <block name="core/column">
    <h2>Our Mission</h2>
    <p>Making block development fast and simple.</p>
  </block>
  <block name="core/column">
    <block name="blockstudio/features" layout="grid">
      <h2>Our Stack</h2>
      <ul>
        <li>PHP and WordPress</li>
        <li>Zero JavaScript</li>
      </ul>
    </block>
  </block>
</block>`;

const details = [
  {
    icon: RefreshCw,
    title: 'Automatic sync',
    description:
      'Pages sync to WordPress on every admin load. Change your template, the editor updates instantly.',
  },
  {
    icon: Lock,
    title: 'Template locking',
    description:
      'Lock the entire page so clients can only edit content, not structure. Perfect for landing pages and marketing sites.',
  },
  {
    icon: Key,
    title: 'Keyed blocks',
    description:
      'Assign keys to individual blocks so user edits persist across template updates. Sync structure, keep content.',
  },
  {
    icon: GitMerge,
    title: 'Version controlled',
    description:
      'Pages live in your theme or plugin as files. Track changes in Git, deploy across environments, review in PRs.',
  },
];

export async function Pages() {
  return (
    <Section
      icon={
        <SectionIcon>
          <FileText />
        </SectionIcon>
      }
      title="Full pages, defined in code"
      description="Create complete WordPress pages from template files. HTML maps to core blocks automatically. Blockstudio keeps the editor in sync."
    >
      <div className="flex flex-col gap-10">
        <Feature
          headline="Write HTML, get blocks"
          description={
            <>
              <p>
                Standard elements like{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  {'<h1>'}
                </code>
                ,{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  {'<p>'}
                </code>
                , and{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  {'<ul>'}
                </code>{' '}
                map to core blocks automatically. Use the{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  {'<block>'}
                </code>{' '}
                tag for everything else. Any block name, any attributes.
              </p>
              <p>
                Define entire page layouts in your codebase. Blockstudio parses
                your templates and creates real WordPress pages with real blocks
                , fully editable in the block editor.
              </p>
            </>
          }
          cta={
            <Button href="/docs/pages-and-patterns/pages" className="w-max">
              Learn more about pages &rarr;
            </Button>
          }
          demo={<CodeCard code={templateCode} lang="html" />}
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
          {details.map((detail) => (
            <div key={detail.title} className="flex flex-col gap-2 text-sm/7">
              <div className="flex items-start gap-3 text-fd-foreground">
                <div className="flex items-center h-[1lh]">
                  <detail.icon className="h-3.5 w-3.5 text-fd-primary" />
                </div>
                <h3 className="font-semibold">{detail.title}</h3>
              </div>
              <p className="text-fd-muted-foreground text-pretty">
                {detail.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </Section>
  );
}

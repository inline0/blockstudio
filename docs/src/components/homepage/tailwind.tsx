import { Wind, Server, Gauge, Palette, MonitorSmartphone } from 'lucide-react';
import { Button } from 'onedocs';
import { CodeTabs } from './code-tabs';
import { Feature } from './feature';
import { Section, SectionIcon } from './section';

const templates = {
  config: {
    label: 'functions.php',
    lang: 'php',
    code: `add_filter('blockstudio/tailwind/css', function ($css) {
  return $css . '
    @theme {
      --color-brand: #4f46e5;
    }

    @layer utilities {
      .container-narrow {
        max-width: 48rem;
        margin-inline: auto;
      }
    }
  ';
});`,
  },
  template: {
    label: 'Template',
    lang: 'php',
    code: `<section class="bg-brand px-6 py-20">
  <div class="container-narrow">
    <h2 class="text-4xl font-bold text-white">
      <?= $a['heading'] ?>
    </h2>

    <p class="mt-4 text-lg text-white/80">
      <?= $a['description'] ?>
    </p>

    <a href="<?= $a['url'] ?>"
      class="mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand">
      <?= $a['buttonText'] ?>
    </a>
  </div>
</section>`,
  },
};

const details = [
  {
    icon: Server,
    title: 'Server-side compilation',
    description:
      'Every frontend request is compiled via TailwindPHP. No Node.js, no CLI, no build step required.',
  },
  {
    icon: Gauge,
    title: 'Automatic caching',
    description:
      'Compiled CSS is cached to disk by class names. Same classes, same cache hit, even with dynamic content.',
  },
  {
    icon: Palette,
    title: 'CSS-first config',
    description:
      'Tailwind v4 CSS-first configuration. Define custom themes, utilities, and variants directly in your settings.',
  },
  {
    icon: MonitorSmartphone,
    title: 'Live editor preview',
    description:
      'A bundled Tailwind CDN script provides instant preview in the block editor. The frontend always uses compiled CSS.',
  },
];

export async function TailwindCSS() {
  return (
    <Section
      icon={
        <SectionIcon>
          <Wind />
        </SectionIcon>
      }
      title="Tailwind v4, compiled in PHP"
      description="Write utility classes in your templates. Blockstudio compiles them server-side via TailwindPHP on every request, with automatic file-based caching."
    >
      <div className="flex flex-col gap-10">
        <Feature
          headline="One setting, zero tooling"
          description={
            <>
              <p>
                Set{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  tailwind.enabled
                </code>{' '}
                to{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  true
                </code>{' '}
                in your{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  blockstudio.json
                </code>{' '}
                and start writing utility classes. Blockstudio compiles the CSS
                server-side, caches it to disk, and injects it as an inline
                style tag. No Node.js, no CLI, no build step.
              </p>
              <p>
                Use the{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  blockstudio/tailwind/css
                </code>{' '}
                filter to define custom themes, utility classes, and variants
                using Tailwind v4 CSS-first syntax. The{' '}
                <code className="text-fd-foreground font-mono text-sm">
                  classes
                </code>{' '}
                field type provides autocomplete for all Tailwind utilities in
                the editor.
              </p>
            </>
          }
          cta={
            <Button href="/docs/tailwind" className="w-max">
              Learn more about Tailwind &rarr;
            </Button>
          }
          demo={<CodeTabs items={[templates.config, templates.template]} />}
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

import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Tabs, Tab } from "onedocs/components";
import { Section } from "./section";

const templates = {
  php: {
    lang: "php",
    code: `<section class="hero">
  <h1><?= $a['heading'] ?></h1>
  <p><?= $a['description'] ?></p>

  <?php if ($a['showCta']): ?>
    <a href="<?= $a['ctaUrl'] ?>">
      <?= $a['ctaLabel'] ?>
    </a>
  <?php endif; ?>

  <InnerBlocks />
</section>`,
  },
  twig: {
    lang: "twig",
    code: `<section class="hero">
  <h1>{{ a.heading }}</h1>
  <p>{{ a.description }}</p>

  {% if a.showCta %}
    <a href="{{ a.ctaUrl }}">
      {{ a.ctaLabel }}
    </a>
  {% endif %}

  <InnerBlocks />
</section>`,
  },
  blade: {
    lang: "php",
    code: `<section class="hero">
  <h1>{{ $a['heading'] }}</h1>
  <p>{{ $a['description'] }}</p>

  @if($a['showCta'])
    <a href="{{ $a['ctaUrl'] }}">
      {{ $a['ctaLabel'] }}
    </a>
  @endif

  <InnerBlocks />
</section>`,
  },
};

const variables = [
  {
    name: "$a",
    full: "$attributes",
    description: "All custom field values defined in your block.json schema.",
  },
  {
    name: "$b",
    full: "$block",
    description:
      "Block instance data — name, directory path, post ID, alignment, CSS class, and anchor.",
  },
  {
    name: "$c",
    full: "$context",
    description:
      "Parent block context when using the Context API for parent-child data sharing.",
  },
  {
    name: "$isEditor",
    description:
      "Boolean flag to conditionally render different markup in the editor vs the frontend.",
  },
  {
    name: "$isPreview",
    description:
      "True when the block is rendered as a preview in the block inserter.",
  },
];

export async function TemplateLanguages() {
  return (
    <Section
      title="Your language. Your templates."
      description="Block templates work with PHP, Twig (via Timber), or Blade (via Sage/Acorn). All three share the same variables and components — pick the one your team already uses."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 px-6 lg:px-10 gap-8">
        <div className="flex flex-col gap-6">
          <div>
            <h3 className="text-sm font-medium text-fd-muted-foreground uppercase tracking-wider mb-4">
              Template Variables
            </h3>
            <div className="flex flex-col gap-4">
              {variables.map((v) => (
                <div key={v.name} className="flex flex-col gap-0.5">
                  <div className="flex items-baseline gap-2">
                    <code className="text-sm font-mono text-fd-primary">
                      {v.name}
                    </code>
                    {v.full && (
                      <span className="text-xs text-fd-muted-foreground">
                        {v.full}
                      </span>
                    )}
                  </div>
                  <span className="text-sm text-fd-muted-foreground">
                    {v.description}
                  </span>
                </div>
              ))}
            </div>
          </div>
          <div>
            <h3 className="text-sm font-medium text-fd-muted-foreground uppercase tracking-wider mb-3">
              Components
            </h3>
            <div className="flex flex-col gap-4">
              <div className="flex flex-col gap-0.5">
                <code className="text-sm font-mono text-fd-primary">
                  {"<InnerBlocks />"}
                </code>
                <span className="text-sm text-fd-muted-foreground">
                  Renders a nested blocks zone. Supports allowed block
                  restrictions, template locking, and default block templates.
                </span>
              </div>
              <div className="flex flex-col gap-0.5">
                <code className="text-sm font-mono text-fd-primary">
                  {"<RichText />"}
                </code>
                <span className="text-sm text-fd-muted-foreground">
                  Inline-editable text linked to a richtext field. Renders
                  directly in the editor canvas, not in the inspector panel.
                </span>
              </div>
              <div className="flex flex-col gap-0.5">
                <code className="text-sm font-mono text-fd-primary">
                  {"<MediaPlaceholder />"}
                </code>
                <span className="text-sm text-fd-muted-foreground">
                  Drag-and-drop file upload area linked to a files field.
                  Opens the media library on click.
                </span>
              </div>
            </div>
          </div>
          <Link
            href="/docs/templating"
            className="text-fd-primary hover:underline font-medium text-sm"
          >
            Learn more about templating &rarr;
          </Link>
        </div>
        <div>
          <Tabs items={["PHP", "Twig", "Blade"]}>
            <Tab>
              <CodeBlock code={templates.php.code} lang={templates.php.lang} />
            </Tab>
            <Tab>
              <CodeBlock
                code={templates.twig.code}
                lang={templates.twig.lang}
              />
            </Tab>
            <Tab>
              <CodeBlock
                code={templates.blade.code}
                lang={templates.blade.lang}
              />
            </Tab>
          </Tabs>
        </div>
      </div>
    </Section>
  );
}

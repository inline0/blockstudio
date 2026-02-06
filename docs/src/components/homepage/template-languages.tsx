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
  { name: "$a", description: "Block attributes" },
  { name: "$b", description: "Block instance data" },
  { name: "$isEditor", description: "True in the editor" },
  {
    name: "<InnerBlocks />",
    description: "Nested blocks component",
  },
];

export async function TemplateLanguages() {
  return (
    <Section
      title="Your language. Your templates."
      description="Write block templates in PHP, Twig, or Blade. Same variables, same output."
    >
      <div className="grid grid-cols-1 lg:grid-cols-3 px-6 lg:px-10 gap-8">
        <div>
          <h3 className="text-sm font-medium text-fd-muted-foreground uppercase tracking-wider mb-4">
            Template Variables
          </h3>
          <div className="flex flex-col gap-3">
            {variables.map((v) => (
              <div key={v.name} className="flex items-baseline gap-3">
                <code className="text-sm font-mono text-fd-primary shrink-0">
                  {v.name}
                </code>
                <span className="text-sm text-fd-muted-foreground">
                  {v.description}
                </span>
              </div>
            ))}
          </div>
        </div>
        <div className="lg:col-span-2">
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

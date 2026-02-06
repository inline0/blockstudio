import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Tabs, Tab } from "onedocs/components";
import { Section } from "./section";
import { Feature } from "./feature";

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

export async function TemplateLanguages() {
  return (
    <Section
      title="Your language. Your templates."
      description="Block templates work with PHP, Twig (via Timber), or Blade (via Sage/Acorn). All three share the same variables and components — pick the one your team already uses."
    >
      <div className="px-6 lg:px-10">
        <Feature
          headline="Template variables & components"
          description={
            <>
              <p>
                Every block template gets access to shorthand variables:{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $a
                </code>{" "}
                for attributes,{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $b
                </code>{" "}
                for block instance data,{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $c
                </code>{" "}
                for parent context, and boolean flags like{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $isEditor
                </code>{" "}
                and{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $isPreview
                </code>
                .
              </p>
              <p>
                JSX-like components work directly in your templates.{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<InnerBlocks />"}
                </code>{" "}
                renders a nested blocks zone,{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<RichText />"}
                </code>{" "}
                adds inline-editable text on the editor canvas, and{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<MediaPlaceholder />"}
                </code>{" "}
                creates drag-and-drop upload areas.
              </p>
            </>
          }
          cta={
            <Link
              href="/docs/templating"
              className="text-fd-primary hover:underline font-medium text-sm"
            >
              Learn more about templating &rarr;
            </Link>
          }
          demo={
            <Tabs items={["PHP", "Twig", "Blade"]}>
              <Tab>
                <CodeBlock
                  code={templates.php.code}
                  lang={templates.php.lang}
                />
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
          }
        />
      </div>
    </Section>
  );
}

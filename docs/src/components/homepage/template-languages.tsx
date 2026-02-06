import { Button } from "onedocs";
import { Section } from "./section";
import { Feature } from "./feature";
import { CodeTabs } from "./code-tabs";

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
      title="Write templates in PHP, Twig, or Blade"
      description="Use the templating language you already know. All three share the same variables, components, and features — no JavaScript required."
    >
      <div className="px-6 lg:px-10">
        <Feature
          headline="Shorthand variables & built-in components"
          description={
            <>
              <p>
                Access attributes via{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $a
                </code>
                , block data via{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $b
                </code>
                , and parent context via{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $c
                </code>
                . Use flags like{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  $isEditor
                </code>{" "}
                to tailor output per environment.
              </p>
              <p>
                Use features like{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<InnerBlocks />"}
                </code>
                ,{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<RichText />"}
                </code>
                , and{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  {"<MediaPlaceholder />"}
                </code>{" "}
                directly in your templates — without writing any JavaScript.
              </p>
            </>
          }
          cta={
            <Button href="/docs/registration#template" className="w-max">
              Learn more about templating &rarr;
            </Button>
          }
          demo={
            <CodeTabs
              items={[
                { label: "PHP", ...templates.php },
                { label: "Twig", ...templates.twig },
                { label: "Blade", ...templates.blade },
              ]}
            />
          }
        />
      </div>
    </Section>
  );
}

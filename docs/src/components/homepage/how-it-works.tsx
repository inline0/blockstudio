import { CodeBlock } from "onedocs";
import { Section } from "./section";
import { Placeholder } from "./placeholder";

const steps = [
  {
    number: 1,
    title: "Define your schema",
    filename: "block.json",
    description:
      "Declare fields, labels, and types in a single JSON file. Full IDE autocomplete via JSON Schema.",
    lang: "json",
    code: `{
  "name": "starter/hero",
  "title": "Hero",
  "blockstudio": {
    "attributes": {
      "heading": {
        "type": "text"
      },
      "showCta": {
        "type": "toggle",
        "label": "Show CTA"
      },
      "background": {
        "type": "color"
      }
    }
  }
}`,
    placeholder: "WordPress editor showing block inspector with fields",
  },
  {
    number: 2,
    title: "Write a template",
    filename: "index.php",
    description:
      "Use template variables to render your block. PHP, Twig, or Blade — your choice.",
    lang: "php",
    code: `<section style="background: <?= $a['background'] ?>">
  <h1><?= $a['heading'] ?></h1>

  <?php if ($a['showCta']): ?>
    <a href="/contact">Get Started</a>
  <?php endif; ?>

  <?= $innerBlocks ?>
</section>`,
    placeholder: "Block rendered on the frontend with custom styles",
  },
  {
    number: 3,
    title: "Add styles",
    filename: "style.scss",
    description:
      "Drop in a stylesheet. SCSS is compiled automatically, scoped to your block, zero config.",
    lang: "scss",
    code: `section {
  padding: 4rem 2rem;

  h1 {
    font-size: 3rem;
    font-weight: 700;
  }

  a {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background: currentColor;
  }
}`,
    placeholder: "Styled hero block with compiled SCSS output",
  },
];

export async function HowItWorks() {
  return (
    <Section
      title="Three files. One block."
      description="Define your schema, write a template, add styles. Blockstudio handles everything else."
      border={false}
    >
      <div className="flex flex-col gap-4 px-6 pb-4 lg:px-10">
        {steps.map((step) => (
          <div
            key={step.number}
            className="group grid grid-cols-1 gap-2 rounded-lg bg-fd-secondary/30 p-2 lg:grid-cols-2"
          >
            <div className="flex flex-col justify-between gap-6 p-6 sm:p-8 lg:group-even:col-start-2">
              <div>
                <div className="flex items-center gap-3 mb-3">
                  <span className="flex items-center justify-center w-7 h-7 rounded-full bg-fd-primary text-fd-primary-foreground text-xs font-semibold">
                    {step.number}
                  </span>
                  <span className="text-sm font-mono text-fd-muted-foreground">
                    {step.filename}
                  </span>
                </div>
                <h3 className="text-xl font-medium text-fd-foreground">
                  {step.title}
                </h3>
                <p className="mt-2 text-sm text-fd-muted-foreground text-pretty">
                  {step.description}
                </p>
              </div>
              <CodeBlock code={step.code} lang={step.lang} />
            </div>
            <div className="overflow-hidden rounded-md lg:group-even:col-start-1 lg:group-even:row-start-1">
              <Placeholder label={step.placeholder} />
            </div>
          </div>
        ))}
      </div>
    </Section>
  );
}

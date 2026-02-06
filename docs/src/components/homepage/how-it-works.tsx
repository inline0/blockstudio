import { CodeBlock } from "onedocs";
import { Section } from "./section";

const steps = [
  {
    number: 1,
    filename: "block.json",
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
  },
  {
    number: 2,
    filename: "index.php",
    lang: "php",
    code: `<section style="background: <?= $a['background'] ?>">
  <h1><?= $a['heading'] ?></h1>

  <?php if ($a['showCta']): ?>
    <a href="/contact">Get Started</a>
  <?php endif; ?>

  <?= $innerBlocks ?>
</section>`,
  },
  {
    number: 3,
    filename: "style.scss",
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
  },
];

export async function HowItWorks() {
  return (
    <Section
      title="Three files. One block."
      description="Define your schema, write a template, add styles. Blockstudio handles everything else."
      border={false}
    >
      <div className="grid grid-cols-1 lg:grid-cols-3">
        {steps.map((step) => (
          <div
            key={step.number}
            className="flex flex-col gap-y-3 py-8 px-6 border-b lg:border-b-0 lg:border-r lg:[&:last-child]:border-r-0 [&:last-child]:border-b-0"
          >
            <div className="flex items-center gap-3">
              <span className="flex items-center justify-center w-6 h-6 rounded-full bg-fd-primary text-fd-primary-foreground text-xs font-medium">
                {step.number}
              </span>
              <span className="text-sm font-mono text-fd-muted-foreground">
                {step.filename}
              </span>
            </div>
            <CodeBlock code={step.code} lang={step.lang} />
          </div>
        ))}
      </div>
    </Section>
  );
}

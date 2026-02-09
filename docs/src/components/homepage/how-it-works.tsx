import { Blocks } from "lucide-react";
import { Button } from "onedocs";
import { Section, SectionIcon } from "./section";
import { CodeCard } from "./code-card";

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
    code: `<section
  style="background: <?= $a['background'] ?>"
>
  <h1><?= $a['heading'] ?></h1>

  <?php if ($a['showCta']): ?>
    <a href="/contact">Get Started</a>
  <?php endif; ?>

  <InnerBlocks />
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
      icon={<SectionIcon><Blocks /></SectionIcon>}
      title="Custom blocks in 3 files"
      description="Define fields in block.json, render them in a template, style with CSS or SCSS. No JavaScript, no build step, no boilerplate."
      border={false}
      cta={
        <Button href="/docs/general/getting-started" className="w-max">
          Get started &rarr;
        </Button>
      }
    >
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {steps.map((step) => (
          <div key={step.number} className="flex flex-col gap-y-3">
            <div className="flex items-center gap-3">
              <span className="flex items-center justify-center w-6 h-6 rounded-full bg-fd-primary text-fd-primary-foreground text-xs font-medium">
                {step.number}
              </span>
              <span className="text-sm font-mono text-fd-muted-foreground">
                {step.filename}
              </span>
            </div>
            <CodeCard code={step.code} lang={step.lang} />
          </div>
        ))}
      </div>
    </Section>
  );
}

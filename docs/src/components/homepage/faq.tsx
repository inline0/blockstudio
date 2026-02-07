import { CircleHelp } from "lucide-react";
import { Accordions, Accordion } from "onedocs/components";
import { Section, SectionIcon } from "./section";

const faqs = [
  {
    question: "What is Blockstudio?",
    answer:
      "A WordPress plugin that lets you build custom blocks with just JSON and a template file. No JavaScript, no build tools, no React. Just define your fields and write your markup.",
  },
  {
    question: "Do I need to know JavaScript?",
    answer:
      "Not at all. If you can write HTML and basic PHP, you can build blocks. Blockstudio handles all the editor internals for you.",
  },
  {
    question: "Does it work with my theme?",
    answer:
      "Yes. Blocks are registered through the native WordPress block API, so they work with any block-compatible theme. No lock-in.",
  },
  {
    question: "How is this different from ACF blocks?",
    answer:
      "Everything is file-based. No admin UI, no PHP registration boilerplate. You also get built-in SCSS compilation, ES modules, scoped assets, and nested InnerBlocks out of the box.",
  },
  {
    question: "What template languages are supported?",
    answer:
      "PHP, Twig (via Timber), and Blade (via Sage/Acorn). All three share the same variables and components.",
  },
  {
    question: "Can I extend existing blocks?",
    answer:
      "Yes. Extensions let you add custom fields to any block, whether core, third-party, or your own. Use wildcards like core/* to target entire namespaces at once.",
  },
  {
    question: "How does the asset pipeline work?",
    answer:
      "Name your files following the convention (style.scss, script.js, editor.css) and Blockstudio handles the rest. SCSS compilation, ES modules, minification. Zero config.",
  },
  {
    question: "Is there IDE support?",
    answer:
      "Yes. Add a $schema property to your block.json and you get full autocomplete, inline docs, and validation in VS Code.",
  },
  {
    question: "What are the requirements?",
    answer:
      "WordPress 6.7+ and PHP 8.2+. Blockstudio follows 100% WordPress Coding Standards.",
  },
  {
    question: "Is Blockstudio free?",
    answer:
      "Yes. Blockstudio is 100% free and open source. Every feature is included: extensions, all field types, asset processing, pages, patterns. No paid tiers, no feature gates.",
  },
];

export function FAQ() {
  return (
    <Section border>
      <div className="grid grid-cols-1 gap-x-2 gap-y-8 lg:grid-cols-2 px-6 pt-16 sm:pt-20 lg:px-10">
        <div className="flex flex-col gap-4">
          <SectionIcon>
            <CircleHelp />
          </SectionIcon>
          <h2 className="text-2xl font-semibold tracking-tight text-fd-foreground sm:text-3xl">
            Questions & Answers
          </h2>
          <p className="text-fd-muted-foreground max-w-md text-pretty">
            Everything you need to know about Blockstudio. Can&apos;t find what
            you&apos;re looking for? Reach out to our support team.
          </p>
        </div>
        <Accordions
          type="single"
          className="rounded-none border-0 bg-transparent divide-y-0"
        >
          {faqs.map((faq) => (
            <Accordion key={faq.question} title={faq.question}>
              <p className="text-fd-muted-foreground">{faq.answer}</p>
            </Accordion>
          ))}
        </Accordions>
      </div>
    </Section>
  );
}

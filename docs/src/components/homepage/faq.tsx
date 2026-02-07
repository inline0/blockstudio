import { CircleHelp } from "lucide-react";
import { Accordions, Accordion } from "onedocs/components";
import { Section, SectionIcon } from "./section";

const faqs = [
  {
    question: "What is Blockstudio?",
    answer:
      "Blockstudio is a WordPress plugin that lets you create custom blocks, pages, patterns, and extensions using template files. You write JSON for the schema and PHP (or Twig/Blade) for the template — no JavaScript or build tools required.",
  },
  {
    question: "Do I need to know JavaScript or React?",
    answer:
      "No. Blockstudio handles all the JavaScript and React internals. You define your block schema in JSON and write templates in PHP, Twig, or Blade. If you can write HTML and basic PHP, you can build blocks.",
  },
  {
    question: "Does it work with any WordPress theme?",
    answer:
      "Yes. Blockstudio registers blocks through the native WordPress block API, so they work with any block-compatible theme. There's no proprietary runtime or framework lock-in.",
  },
  {
    question: "How are blocks different from ACF or Meta Box blocks?",
    answer:
      "Blockstudio blocks are defined entirely in template files — no PHP registration code, no admin UI configuration. You get the simplicity of file-based development with features like SCSS compilation, ES modules, scoped assets, and nested InnerBlocks out of the box.",
  },
  {
    question: "What field types are available?",
    answer:
      "26 field types including text, textarea, richtext, WYSIWYG, code editor, number, range, unit, select, radio, checkbox, toggle, color, gradient, icon, group, tabs, repeater, files, link, date, and more. Each supports conditional logic, storage options, and validation.",
  },
  {
    question: "Can I use Blockstudio with existing blocks?",
    answer:
      "Yes. Extensions let you add custom fields to any existing block — core WordPress blocks, third-party blocks, or your own. You can even use wildcards like core/* to extend all blocks in a namespace at once.",
  },
  {
    question: "How does the asset pipeline work?",
    answer:
      "Name your files following the convention (style.scss, script.js, editor.css) and Blockstudio handles everything: SCSS compilation, ES module resolution, automatic minification, and scoped loading. No webpack, Vite, or any build tools needed.",
  },
  {
    question: "What template languages are supported?",
    answer:
      "PHP (default), Twig (via Timber), and Blade (via Sage/Acorn). All three share the same template variables: $a for attributes, $b for block data, $isEditor for editor context, and $innerBlocks for nested content.",
  },
  {
    question: "Can I store field values outside the block markup?",
    answer:
      "Yes. The storage system lets you save field values to post meta, options, or custom locations. You can assign multiple storage targets to a single field for cross-context access — for example, storing a value both in the block and as post meta.",
  },
  {
    question: "Is there IDE support?",
    answer:
      "Full IDE support via JSON Schema. When you add the $schema property to your block.json, you get autocomplete, inline documentation, and validation for all Blockstudio properties in VS Code and other editors.",
  },
  {
    question: "What WordPress versions are supported?",
    answer:
      "Blockstudio 7 requires WordPress 6.7 or later and PHP 8.1 or later. It follows 100% WordPress Coding Standards and is built for modern WordPress development.",
  },
  {
    question: "Is Blockstudio free?",
    answer:
      "Blockstudio offers a free tier for basic block development. Pro features like advanced field types, extensions, and priority support are available with a paid license.",
  },
];

export function FAQ() {
  return (
    <Section icon={<SectionIcon><CircleHelp /></SectionIcon>} title="Questions & Answers">
      <div className="px-6 pb-4 lg:px-10 max-w-3xl">
        <Accordions type="single">
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

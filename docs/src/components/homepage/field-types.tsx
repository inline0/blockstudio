import { SlidersHorizontal } from "lucide-react";
import Link from "next/link";
import { Button } from "onedocs";
import { Section, SectionIcon } from "./section";
import { Marquee } from "./marquee";

const fields = [
  { type: "text", description: "Single line text input" },
  { type: "textarea", description: "Multi-line text input" },
  { type: "richtext", description: "Inline rich text editing" },
  { type: "wysiwyg", description: "Full WYSIWYG editor" },
  { type: "code", description: "Code editor with syntax highlighting" },
  { type: "number", description: "Number input" },
  { type: "range", description: "Range slider" },
  { type: "unit", description: "Number with unit dropdown" },
  { type: "select", description: "Single or multi select" },
  { type: "radio", description: "Radio button group" },
  { type: "checkbox", description: "Checkbox group" },
  { type: "toggle", description: "True/false toggle" },
  { type: "color", description: "Color palette and picker" },
  { type: "gradient", description: "Gradient palette and picker" },
  { type: "icon", description: "SVG icon selector" },
  { type: "classes", description: "CSS class selector" },
  { type: "group", description: "Collapsible field container" },
  { type: "tabs", description: "Tabbed field groups" },
  { type: "repeater", description: "Repeatable field sets" },
  { type: "files", description: "Media library upload" },
  { type: "link", description: "Internal or external links" },
  { type: "date", description: "Date picker" },
  { type: "datetime", description: "Date and time picker" },
  { type: "attributes", description: "Data attribute inputs" },
  { type: "message", description: "Custom message display" },
  { type: "custom", description: "Custom field reference" },
];

function shuffle<T>(arr: T[], seed: number): T[] {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor((Math.abs(Math.sin(seed + i) * 10000)) % (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

const row1 = shuffle(fields, 1);
const row2 = shuffle(fields, 2);
const row3 = shuffle(fields, 3);
const row4 = shuffle(fields, 4);

function FieldCard({ type, description }: { type: string; description: string }) {
  return (
    <Link
      href="/docs/attributes/field-types"
      className="flex flex-col gap-1 rounded-lg border bg-fd-card px-4 py-3 transition-colors hover:bg-fd-secondary/50 hover:border-fd-primary/30"
    >
      <span className="text-sm font-medium text-fd-foreground font-mono">
        {type}
      </span>
      <span className="text-xs text-fd-muted-foreground whitespace-nowrap">
        {description}
      </span>
    </Link>
  );
}

export function FieldTypes() {
  return (
    <Section
      icon={<SectionIcon><SlidersHorizontal /></SectionIcon>}
      title="26 different field types at your disposal"
      description="Blockstudio provides a versatile array of field types, from basic text fields to complex structures like repeaters and tabs, as well as DOM-focused fields such as classes and data attributes."
      cta={
        <Button href="/docs/attributes/field-types" className="w-max">
          Explore field types &rarr;
        </Button>
      }
    >
      <div className="flex flex-col gap-0">
        <Marquee pauseOnHover>
          {row1.map((field) => (
            <FieldCard key={field.type} {...field} />
          ))}
        </Marquee>
        <Marquee pauseOnHover reverse>
          {row2.map((field) => (
            <FieldCard key={field.type} {...field} />
          ))}
        </Marquee>
        <Marquee pauseOnHover>
          {row3.map((field) => (
            <FieldCard key={field.type} {...field} />
          ))}
        </Marquee>
        <Marquee pauseOnHover reverse>
          {row4.map((field) => (
            <FieldCard key={field.type} {...field} />
          ))}
        </Marquee>
      </div>
    </Section>
  );
}

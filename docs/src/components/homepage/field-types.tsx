import Link from "next/link";
import { Section } from "./section";

const categories = [
  {
    name: "Input",
    types: [
      "text",
      "textarea",
      "richtext",
      "wysiwyg",
      "code",
      "number",
      "range",
      "unit",
    ],
  },
  {
    name: "Selection",
    types: [
      "select",
      "radio",
      "checkbox",
      "toggle",
      "color",
      "gradient",
      "icon",
      "classes",
    ],
  },
  {
    name: "Complex",
    types: [
      "group",
      "tabs",
      "repeater",
      "files",
      "link",
      "date",
      "datetime",
    ],
  },
  {
    name: "Special",
    types: ["attributes", "message", "custom"],
  },
];

export function FieldTypes() {
  return (
    <Section
      title="26 field types"
      description="Everything you need to build rich editing experiences, from simple text inputs to nested repeaters."
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 border-t">
        {categories.map((category) => (
          <div
            key={category.name}
            className="flex flex-col gap-y-3 py-8 px-6 border-b sm:border-r sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r lg:[&:nth-child(4n)]:border-r-0 lg:border-b-0"
          >
            <h3 className="text-sm font-medium text-fd-muted-foreground uppercase tracking-wider">
              {category.name}
            </h3>
            <div className="flex flex-wrap gap-2">
              {category.types.map((type) => (
                <Link
                  key={type}
                  href={`/docs/attributes/field-types/${type}`}
                  className="inline-flex items-center rounded-md border px-2.5 py-1 text-sm text-fd-foreground transition-colors hover:bg-fd-secondary/50 hover:border-fd-primary/30"
                >
                  {type}
                </Link>
              ))}
            </div>
          </div>
        ))}
      </div>
    </Section>
  );
}

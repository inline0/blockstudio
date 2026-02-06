import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Section } from "./section";

const extensionCode = `{
  "blockstudio": {
    "extend": "core/*",
    "attributes": {
      "animation": {
        "type": "select",
        "label": "Animation",
        "set": "style",
        "options": ["none", "fade", "slide", "scale"]
      },
      "animationDuration": {
        "type": "range",
        "label": "Duration",
        "min": 0.1,
        "max": 2,
        "step": 0.1,
        "condition": {
          "animation": "!= none"
        }
      }
    }
  }
}`;

export async function Extensions() {
  return (
    <Section
      title="Extend any block"
      description="Add custom fields to core blocks, third-party blocks, or your own. Use wildcards to extend entire namespaces at once."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 px-6 lg:px-10">
        <div>
          <CodeBlock code={extensionCode} lang="json" />
        </div>
        <div className="flex flex-col gap-3 text-sm text-fd-muted-foreground">
          <p>
            Extensions let you add fields to any existing block without
            modifying its source code. Target a single block, a list of
            blocks, or use <code className="text-fd-foreground font-mono">core/*</code> to
            extend every core block at once.
          </p>
          <p>
            Fields added via extensions support the same features as block
            fields — conditional logic, storage, validation, and all 26
            field types. Values are injected into the
            block&apos;s <code className="text-fd-foreground font-mono">style</code> or <code className="text-fd-foreground font-mono">class</code> attributes,
            or stored in post meta.
          </p>
          <Link
            href="/docs/extensions"
            className="text-fd-primary hover:underline font-medium"
          >
            Learn more about extensions &rarr;
          </Link>
        </div>
      </div>
    </Section>
  );
}

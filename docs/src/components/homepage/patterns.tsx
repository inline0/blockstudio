import Link from "next/link";
import { CodeBlock } from "onedocs";
import { Files, Folder, File } from "onedocs/components";
import { Section } from "./section";

const templateCode = `<section class="pricing">
  <div class="pricing__grid">
    <?php foreach ($a['plans'] as $plan): ?>
      <div class="pricing__card">
        <h3><?= $plan['name'] ?></h3>
        <p class="pricing__price"><?= $plan['price'] ?></p>
        <a href="<?= $plan['url'] ?>">Get Started</a>
      </div>
    <?php endforeach; ?>
  </div>
</section>`;

const jsonCode = `{
  "title": "Pricing Table",
  "categories": ["content"],
  "blockstudio": {
    "attributes": {
      "plans": {
        "type": "repeater",
        "fields": {
          "name": { "type": "text" },
          "price": { "type": "text" },
          "url": { "type": "text" }
        }
      }
    }
  }
}`;

export async function Patterns() {
  return (
    <Section
      title="Template-based patterns"
      description="Reusable block patterns defined as template files. Registered automatically in the block inserter, always in sync with your code."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 px-6 lg:px-10">
        <div className="flex flex-col gap-4">
          <Files>
            <Folder name="patterns" defaultOpen>
              <File name="pricing.php" />
              <File name="pricing.json" />
              <File name="testimonials.php" />
              <File name="testimonials.json" />
            </Folder>
          </Files>
          <CodeBlock code={jsonCode} lang="json" />
        </div>
        <div className="flex flex-col gap-4">
          <CodeBlock code={templateCode} lang="php" />
          <div className="flex flex-col gap-3 text-sm text-fd-muted-foreground">
            <p>
              Patterns work like blocks — JSON schema for fields, template
              file for markup. They show up in the block inserter and can
              be inserted into any page or post.
            </p>
            <p>
              Unlike WordPress&apos;s built-in pattern registration, Blockstudio
              patterns support field types, conditional logic, and
              repeaters. Build complex, editable patterns without
              hardcoding content.
            </p>
            <Link
              href="/docs/patterns"
              className="text-fd-primary hover:underline font-medium"
            >
              Learn more about patterns &rarr;
            </Link>
          </div>
        </div>
      </div>
    </Section>
  );
}

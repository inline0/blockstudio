import { Zap } from "lucide-react";
import { CodeBlock } from "onedocs";
import { Tabs, Tab, Files, Folder, File } from "onedocs/components";
import { Section, SectionIcon } from "./section";

const esModules = {
  lang: "js",
  code: `import gsap from "gsap";

const heading = block.querySelector("h1");

gsap.from(heading, {
  opacity: 0,
  y: 20,
  duration: 0.6,
});`,
};

const scss = {
  lang: "scss",
  code: `$accent: var(--wp--preset--color--primary);

.hero {
  padding: 4rem 2rem;

  h1 {
    color: $accent;
    font-size: clamp(2rem, 5vw, 4rem);
  }

  &--dark {
    background: #0a0a0a;
  }
}`,
};

export async function AssetProcessing() {
  return (
    <Section
      icon={<SectionIcon><Zap /></SectionIcon>}
      title="No build tools. Ever."
      description="SCSS compilation, ES modules, and scoped styles — all by naming convention."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 px-6 lg:px-10 gap-8">
        <div>
          <Tabs items={["ES Modules", "SCSS"]}>
            <Tab>
              <CodeBlock code={esModules.code} lang={esModules.lang} />
            </Tab>
            <Tab>
              <CodeBlock code={scss.code} lang={scss.lang} />
            </Tab>
          </Tabs>
        </div>
        <div>
          <h3 className="text-sm font-medium text-fd-muted-foreground uppercase tracking-wider mb-4">
            Naming Convention
          </h3>
          <Files>
            <Folder name="hero" defaultOpen>
              <File name="block.json" />
              <File name="index.php" />
              <File name="style.css" />
              <File name="style.scss" />
              <File name="script.js" />
              <File name="script.inline.js" />
              <File name="editor.css" />
              <File name="editor.js" />
            </Folder>
          </Files>
          <div className="mt-4 flex flex-col gap-2 text-sm text-fd-muted-foreground">
            <p>
              <code className="text-fd-foreground font-mono">style.*</code>{" "}
              Frontend styles
            </p>
            <p>
              <code className="text-fd-foreground font-mono">script.*</code>{" "}
              Frontend scripts
            </p>
            <p>
              <code className="text-fd-foreground font-mono">editor.*</code>{" "}
              Editor only
            </p>
            <p>
              <code className="text-fd-foreground font-mono">*.inline.*</code>{" "}
              Inlined in page
            </p>
          </div>
        </div>
      </div>
    </Section>
  );
}

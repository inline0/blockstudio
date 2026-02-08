import { Zap, FileType, FileCode2, Paintbrush, Monitor } from "lucide-react";
import { Button } from "onedocs";
import { Section, SectionIcon } from "./section";
import { Feature } from "./feature";
import { CodeTabs } from "./code-tabs";

const templates = {
  scss: {
    label: "SCSS",
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
  },
  js: {
    label: "ES Modules",
    lang: "js",
    code: `import gsap from "npm:gsap@3.12.5";

const heading = block.querySelector("h1");
const cards = block.querySelectorAll(".card");

gsap.from(heading, {
  opacity: 0,
  y: 20,
  duration: 0.6,
});

gsap.from(cards, {
  opacity: 0, stagger: 0.1,
});`,
  },
};

const details = [
  {
    icon: Paintbrush,
    title: "SCSS compilation",
    description:
      "Write SCSS with nesting, variables, and mixins. Blockstudio compiles and minifies it automatically.",
  },
  {
    icon: FileCode2,
    title: "ES module imports",
    description:
      "Import npm packages directly in your scripts. Blockstudio downloads and serves them locally. No bundler needed.",
  },
  {
    icon: FileType,
    title: "Naming convention",
    description:
      "style.scss for frontend styles, script.js for frontend scripts, editor.css for editor only. Name the file, done.",
  },
  {
    icon: Monitor,
    title: "Scoped & inlined",
    description:
      "Assets load only when the block is on the page. Use *.inline.* to inline scripts and styles directly.",
  },
];

export async function AssetProcessing() {
  return (
    <Section
      icon={<SectionIcon><Zap /></SectionIcon>}
      title="Zero-config asset pipeline"
      description="SCSS compilation, ES module imports, and automatic minification, all by naming convention. No webpack, no Vite."
    >
      <div className="flex flex-col gap-10">
        <Feature
          headline="Name your files, Blockstudio handles the rest"
          description={
            <>
              <p>
                Drop a{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  style.scss
                </code>{" "}
                next to your block and it gets compiled, minified, and enqueued
                automatically. Same for{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  script.js
                </code>{" "}
                with full ES module support. Import from npm, no bundler needed.
              </p>
              <p>
                Assets are scoped per block and only loaded when the block is on
                the page. Use the{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  editor.*
                </code>{" "}
                prefix for editor-only assets or{" "}
                <code className="text-fd-foreground font-mono text-sm">
                  *.inline.*
                </code>{" "}
                to inline them directly in the page.
              </p>
            </>
          }
          cta={
            <Button href="/docs/assets" className="w-max">
              Learn more about assets &rarr;
            </Button>
          }
          demo={
            <CodeTabs items={[templates.scss, templates.js]} />
          }
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
          {details.map((detail) => (
            <div key={detail.title} className="flex flex-col gap-2 text-sm/7">
              <div className="flex items-start gap-3 text-fd-foreground">
                <div className="flex items-center h-[1lh]">
                  <detail.icon className="h-3.5 w-3.5 text-fd-primary" />
                </div>
                <h3 className="font-semibold">{detail.title}</h3>
              </div>
              <p className="text-fd-muted-foreground text-pretty">
                {detail.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </Section>
  );
}

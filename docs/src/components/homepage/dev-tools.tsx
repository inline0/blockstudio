import Link from "next/link";
import { Crosshair, LayoutGrid } from "lucide-react";
import { Section, SectionIcon } from "./section";

const tools = [
  {
    icon: LayoutGrid,
    title: "Canvas",
    description:
      "A Figma-like overview of all your managed pages. Pan, zoom, and inspect your site in a single view.",
    href: "/docs/dev/canvas",
    shortcut: "?blockstudio-canvas",
  },
  {
    icon: Crosshair,
    title: "Element Grabber",
    description:
      "Hold Cmd+C on any element to copy its block template path. Built for pasting context straight into your AI editor.",
    href: "/docs/dev/grab",
    shortcut: "?blockstudio-devtools",
  },
];

export function DevTools() {
  return (
    <Section
      icon={<SectionIcon><Crosshair /></SectionIcon>}
      title="Dev tools"
      description="Built-in tooling for inspecting, debugging, and building with AI."
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-px bg-fd-border rounded-lg overflow-hidden border">
        {tools.map((tool) => (
          <Link
            key={tool.title}
            href={tool.href}
            className="group flex flex-col justify-between gap-8 bg-fd-card p-10 transition-colors hover:bg-fd-secondary/30"
          >
            <div className="flex flex-col gap-4">
              <tool.icon className="h-6 w-6 text-fd-primary" />
              <h3 className="text-xl font-semibold text-fd-foreground group-hover:text-fd-primary transition-colors">
                {tool.title}
              </h3>
              <p className="text-base text-fd-muted-foreground leading-relaxed max-w-md">
                {tool.description}
              </p>
            </div>
            <code className="text-xs text-fd-muted-foreground/50 font-mono">
              {tool.shortcut}
            </code>
          </Link>
        ))}
      </div>
    </Section>
  );
}

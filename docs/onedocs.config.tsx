import { defineConfig } from "onedocs/config";
import { Blocks, Palette, Code, Zap, Settings, FileJson, Cpu, Bot } from "lucide-react";

const iconClass = "h-5 w-5 text-fd-primary";

export default defineConfig({
  title: "Blockstudio",
  description: "The WordPress block framework for developers.",
  logo: {
    light: "/logo-light.svg",
    dark: "/logo-dark.svg",
  },
  icon: "/icon.png",
  nav: {
    github: "inline0/blockstudio",
  },
  homepage: {
    hero: {
      title: "Build WordPress blocks with ease",
      description:
        "Blockstudio is a powerful framework for creating custom WordPress blocks using Twig templates and a simple JSON configuration.",
      cta: { label: "Get Started", href: "/docs/getting-started" },
    },
    features: [
      {
        title: "Block Builder",
        description: "Create custom blocks with a simple JSON schema and Twig templates.",
        icon: <Blocks className={iconClass} />,
      },
      {
        title: "Tailwind Integration",
        description: "Built-in Tailwind CSS support with autocomplete and custom classes.",
        icon: <Palette className={iconClass} />,
      },
      {
        title: "Developer First",
        description: "Full TypeScript support and modern development experience.",
        icon: <Code className={iconClass} />,
      },
      {
        title: "Fast Workflow",
        description: "Hot reload, live preview, and instant feedback during development.",
        icon: <Zap className={iconClass} />,
      },
      {
        title: "Flexible Config",
        description: "Configure blocks, fields, and extensions with JSON schemas.",
        icon: <Settings className={iconClass} />,
      },
      {
        title: "JSON Schema",
        description: "Full schema validation and autocomplete in your IDE.",
        icon: <FileJson className={iconClass} />,
      },
      {
        title: "Asset Processing",
        description: "Built-in SCSS compilation, ES modules, and automatic minification.",
        icon: <Cpu className={iconClass} />,
      },
      {
        title: "AI Integration",
        description: "Generate context files for AI coding assistants like Cursor.",
        icon: <Bot className={iconClass} />,
      },
    ],
  },
});

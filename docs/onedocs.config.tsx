import { defineConfig } from "onedocs/config";
import { Blocks, FileText, LayoutGrid, Puzzle, FormInput, Cpu, FileJson, Code } from "lucide-react";
import { HeroBlocks } from "./src/components/hero-blocks";
import { HeroLeft } from "./src/components/homepage/hero-left";

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
    links: [
      { label: "Build with AI", href: "/ai" },
      { label: "Docs", href: "/docs" },
      { label: "Blog", href: "/blog" },
    ],
    github: "inline0/blockstudio",
  },
  homepage: {
    hero: {
      left: <HeroLeft />,
      right: <HeroBlocks />,
    },
    features: [
      {
        title: "Blocks",
        description: "Create custom blocks with JSON and PHP, Twig, or Blade templates. 30+ field types, zero JavaScript.",
        icon: <Blocks className={iconClass} />,
      },
      {
        title: "Pages",
        description: "Build full WordPress pages as file-based templates that automatically sync to native blocks.",
        icon: <FileText className={iconClass} />,
      },
      {
        title: "Patterns",
        description: "Define reusable block patterns as template files, registered automatically in the inserter.",
        icon: <LayoutGrid className={iconClass} />,
      },
      {
        title: "Extensions",
        description: "Add custom fields to any block — core, third-party, or your own — with a single JSON file.",
        icon: <Puzzle className={iconClass} />,
      },
      {
        title: "Field Types",
        description: "30+ built-in types including repeaters, conditional logic, color pickers, code editors, and media.",
        icon: <FormInput className={iconClass} />,
      },
      {
        title: "Asset Processing",
        description: "SCSS compilation, ES modules, scoped styles, and automatic minification — all by naming convention.",
        icon: <Cpu className={iconClass} />,
      },
      {
        title: "JSON Schema",
        description: "Full IDE autocomplete and validation for block, extension, and settings configurations.",
        icon: <FileJson className={iconClass} />,
      },
      {
        title: "Templating",
        description: "Write templates in PHP, Twig, or Blade with scoped assets, nested InnerBlocks, and template variables.",
        icon: <Code className={iconClass} />,
      },
    ],
  },
});

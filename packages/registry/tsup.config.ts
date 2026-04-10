import { defineConfig } from "tsup";

export default defineConfig({
  entry: ["src/index.ts"],
  format: ["esm"],
  clean: true,
  banner: { js: "#!/usr/bin/env node" },
  external: ["ink", "react", "ink-select-input", "ink-spinner", "ink-text-input"],
  esbuildOptions(options) {
    options.jsx = "automatic";
  },
});

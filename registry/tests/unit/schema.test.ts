import { describe, it, expect } from "vitest";
import {
  configSchema,
  registrySchema,
  blockEntrySchema,
} from "../../src/config/schema.js";

describe("configSchema", () => {
  it("parses a valid config", () => {
    const result = configSchema.parse({
      directory: "blockstudio",
      registries: {
        ui: "https://example.com/registry.json",
      },
    });
    expect(result.directory).toBe("blockstudio");
    expect(result.registries.ui).toBe("https://example.com/registry.json");
  });

  it("accepts optional $schema", () => {
    const result = configSchema.parse({
      $schema: "https://blockstudio.dev/schema/blocks.json",
      directory: "blocks",
      registries: {},
    });
    expect(result.$schema).toBeDefined();
  });

  it("rejects missing directory", () => {
    const result = configSchema.safeParse({ registries: {} });
    expect(result.success).toBe(false);
  });

  it("rejects empty directory", () => {
    const result = configSchema.safeParse({ directory: "", registries: {} });
    expect(result.success).toBe(false);
  });

  it("rejects invalid registry URL", () => {
    const result = configSchema.safeParse({
      directory: "blocks",
      registries: { ui: "not-a-url" },
    });
    expect(result.success).toBe(false);
  });

  it("rejects missing registries", () => {
    const result = configSchema.safeParse({ directory: "blocks" });
    expect(result.success).toBe(false);
  });

  it("accepts empty registries", () => {
    const result = configSchema.parse({ directory: "blocks", registries: {} });
    expect(result.registries).toEqual({});
  });
});

describe("registrySchema", () => {
  const validRegistry = {
    name: "ui",
    baseUrl: "https://example.com/blocks",
    blocks: [
      {
        name: "hero",
        files: ["block.json"],
      },
    ],
  };

  it("parses a valid registry", () => {
    const result = registrySchema.parse(validRegistry);
    expect(result.name).toBe("ui");
    expect(result.blocks).toHaveLength(1);
  });

  it("accepts all optional fields", () => {
    const result = registrySchema.parse({
      ...validRegistry,
      description: "Core UI blocks",
      blocks: [
        {
          name: "tabs",
          title: "Tabs",
          description: "Tabbed content",
          category: "layout",
          type: "blockstudio",
          dependencies: ["tab-item"],
          files: ["block.json", "index.php"],
        },
      ],
    });
    expect(result.blocks[0].dependencies).toEqual(["tab-item"]);
  });

  it("rejects missing name", () => {
    const { name, ...rest } = validRegistry;
    expect(registrySchema.safeParse(rest).success).toBe(false);
  });

  it("rejects missing baseUrl", () => {
    const { baseUrl, ...rest } = validRegistry;
    expect(registrySchema.safeParse(rest).success).toBe(false);
  });

  it("rejects invalid baseUrl", () => {
    expect(
      registrySchema.safeParse({ ...validRegistry, baseUrl: "nope" }).success,
    ).toBe(false);
  });

  it("rejects empty blocks array", () => {
    expect(
      registrySchema.safeParse({ ...validRegistry, blocks: [] }).success,
    ).toBe(false);
  });

  it("rejects blocks with no files", () => {
    expect(
      registrySchema.safeParse({
        ...validRegistry,
        blocks: [{ name: "hero", files: [] }],
      }).success,
    ).toBe(false);
  });

  it("rejects invalid block type", () => {
    expect(
      registrySchema.safeParse({
        ...validRegistry,
        blocks: [{ name: "hero", files: ["a.php"], type: "invalid" }],
      }).success,
    ).toBe(false);
  });
});

describe("blockEntrySchema", () => {
  it("parses minimal block", () => {
    const result = blockEntrySchema.parse({
      name: "hero",
      files: ["block.json"],
    });
    expect(result.name).toBe("hero");
  });

  it("rejects empty name", () => {
    expect(
      blockEntrySchema.safeParse({ name: "", files: ["a"] }).success,
    ).toBe(false);
  });
});

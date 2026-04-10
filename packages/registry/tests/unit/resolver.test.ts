import { describe, it, expect } from "vitest";
import {
  resolveBlock,
  findBlockAcrossRegistries,
} from "../../src/registry/resolver.js";
import type { Registry } from "../../src/config/schema.js";

const makeRegistry = (
  blocks: Array<{ name: string; dependencies?: string[] }>,
): Registry => ({
  name: "test",
  baseUrl: "https://example.com/blocks",
  blocks: blocks.map((b) => ({
    ...b,
    files: ["block.json"],
  })),
});

describe("resolveBlock", () => {
  it("resolves a single block with no dependencies", () => {
    const registry = makeRegistry([{ name: "hero" }]);
    const result = resolveBlock("hero", registry, "test");
    expect(result).toHaveLength(1);
    expect(result[0].name).toBe("hero");
    expect(result[0].registryName).toBe("test");
  });

  it("resolves dependencies in order (deps first)", () => {
    const registry = makeRegistry([
      { name: "tabs", dependencies: ["tab-item"] },
      { name: "tab-item" },
    ]);
    const result = resolveBlock("tabs", registry, "test");
    expect(result.map((b) => b.name)).toEqual(["tab-item", "tabs"]);
  });

  it("resolves diamond dependencies without duplicates", () => {
    const registry = makeRegistry([
      { name: "a", dependencies: ["b", "c"] },
      { name: "b", dependencies: ["d"] },
      { name: "c", dependencies: ["d"] },
      { name: "d" },
    ]);
    const result = resolveBlock("a", registry, "test");
    expect(result.map((b) => b.name)).toEqual(["d", "b", "c", "a"]);
  });

  it("resolves a chain of dependencies", () => {
    const registry = makeRegistry([
      { name: "a", dependencies: ["b"] },
      { name: "b", dependencies: ["c"] },
      { name: "c" },
    ]);
    const result = resolveBlock("a", registry, "test");
    expect(result.map((b) => b.name)).toEqual(["c", "b", "a"]);
  });

  it("throws on circular dependency", () => {
    const registry = makeRegistry([
      { name: "a", dependencies: ["b"] },
      { name: "b", dependencies: ["a"] },
    ]);
    expect(() => resolveBlock("a", registry, "test")).toThrow("Circular");
  });

  it("throws on self-referencing dependency", () => {
    const registry = makeRegistry([{ name: "a", dependencies: ["a"] }]);
    expect(() => resolveBlock("a", registry, "test")).toThrow("Circular");
  });

  it("throws on missing dependency", () => {
    const registry = makeRegistry([
      { name: "a", dependencies: ["missing"] },
    ]);
    expect(() => resolveBlock("a", registry, "test")).toThrow(
      'Dependency "missing" not found',
    );
  });

  it("throws when block itself is not found", () => {
    const registry = makeRegistry([{ name: "hero" }]);
    expect(() => resolveBlock("nonexistent", registry, "test")).toThrow(
      'Dependency "nonexistent" not found',
    );
  });
});

describe("findBlockAcrossRegistries", () => {
  it("finds a block in one registry", () => {
    const registries = new Map<string, Registry>();
    registries.set("ui", makeRegistry([{ name: "hero" }]));
    registries.set("starter", makeRegistry([{ name: "card" }]));

    const matches = findBlockAcrossRegistries("hero", registries);
    expect(matches).toHaveLength(1);
    expect(matches[0].registryName).toBe("ui");
  });

  it("finds a block in multiple registries", () => {
    const registries = new Map<string, Registry>();
    registries.set("ui", makeRegistry([{ name: "hero" }]));
    registries.set("starter", makeRegistry([{ name: "hero" }]));

    const matches = findBlockAcrossRegistries("hero", registries);
    expect(matches).toHaveLength(2);
  });

  it("returns empty when block not found", () => {
    const registries = new Map<string, Registry>();
    registries.set("ui", makeRegistry([{ name: "hero" }]));

    const matches = findBlockAcrossRegistries("missing", registries);
    expect(matches).toHaveLength(0);
  });
});

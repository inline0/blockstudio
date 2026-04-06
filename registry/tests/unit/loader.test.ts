import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { mkdtemp, writeFile, mkdir, rm } from "node:fs/promises";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { findConfig, loadConfig } from "../../src/config/loader.js";

let tempDir: string;

beforeEach(async () => {
  tempDir = await mkdtemp(join(tmpdir(), "bs-loader-test-"));
});

afterEach(async () => {
  await rm(tempDir, { recursive: true, force: true });
});

describe("findConfig", () => {
  it("finds blocks.json in the current directory", async () => {
    await writeFile(join(tempDir, "blocks.json"), "{}");
    const path = await findConfig(tempDir);
    expect(path).toBe(join(tempDir, "blocks.json"));
  });

  it("finds blocks.json in a parent directory", async () => {
    await writeFile(join(tempDir, "blocks.json"), "{}");
    const nested = join(tempDir, "a", "b", "c");
    await mkdir(nested, { recursive: true });
    const path = await findConfig(nested);
    expect(path).toBe(join(tempDir, "blocks.json"));
  });

  it("throws when blocks.json is not found", async () => {
    await expect(findConfig(tempDir)).rejects.toThrow("Could not find");
  });
});

describe("loadConfig", () => {
  it("loads and validates a valid config", async () => {
    const config = {
      directory: "blockstudio",
      registries: { ui: "https://example.com/registry.json" },
    };
    await writeFile(join(tempDir, "blocks.json"), JSON.stringify(config));

    const result = await loadConfig(tempDir);
    expect(result.config.directory).toBe("blockstudio");
    expect(result.configDir).toBe(tempDir);
  });

  it("throws on invalid JSON", async () => {
    await writeFile(join(tempDir, "blocks.json"), "{broken");
    await expect(loadConfig(tempDir)).rejects.toThrow("Invalid JSON");
  });

  it("throws on schema validation failure", async () => {
    await writeFile(
      join(tempDir, "blocks.json"),
      JSON.stringify({ directory: "" }),
    );
    await expect(loadConfig(tempDir)).rejects.toThrow("Invalid blocks.json");
  });
});

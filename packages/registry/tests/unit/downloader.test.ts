import { describe, it, expect, beforeAll, afterAll } from "vitest";
import { downloadBlock, downloadBlocks } from "../../src/registry/downloader.js";
import type { ResolvedBlock } from "../../src/registry/resolver.js";
import { startFixtureServer } from "../helpers/fixtures.js";

let server: Awaited<ReturnType<typeof startFixtureServer>>;

beforeAll(async () => {
  server = await startFixtureServer();
});

afterAll(async () => {
  await server.stop();
});

function makeBlock(
  name: string,
  files: string[],
  baseUrl?: string,
): ResolvedBlock {
  return {
    name,
    files,
    registryName: "ui",
    registryBaseUrl: baseUrl ?? `${server.url}/ui/blocks`,
  };
}

describe("downloadBlock", () => {
  it("downloads all files for a block", async () => {
    const block = makeBlock("hero", ["block.json", "index.php"]);
    const result = await downloadBlock(block);

    expect(result.files).toHaveLength(2);
    expect(result.files[0].path).toBe("block.json");
    expect(result.files[1].path).toBe("index.php");
  });

  it("preserves file content correctly", async () => {
    const block = makeBlock("hero", ["block.json"]);
    const result = await downloadBlock(block);

    const content = JSON.parse(result.files[0].content);
    expect(content.apiVersion).toBe(3);
    expect(content.name).toBe("test/hero");
    expect(content.title).toBe("Hero");
  });

  it("downloads PHP templates with correct content", async () => {
    const block = makeBlock("hero", ["index.php"]);
    const result = await downloadBlock(block);

    expect(result.files[0].content).toContain("<section");
    expect(result.files[0].content).toContain("$a['title']");
  });

  it("downloads SCSS files", async () => {
    const block = makeBlock("tabs", ["style.scss"]);
    const result = await downloadBlock(block);

    expect(result.files[0].content).toContain(".tabs");
    expect(result.files[0].content).toContain("flex");
  });

  it("throws on 404 for missing file", async () => {
    const block = makeBlock("hero", ["nonexistent.php"]);
    await expect(downloadBlock(block)).rejects.toThrow("404");
  });

  it("attaches the original block metadata", async () => {
    const block = makeBlock("hero", ["block.json"]);
    const result = await downloadBlock(block);

    expect(result.block.name).toBe("hero");
    expect(result.block.registryName).toBe("ui");
  });
});

describe("downloadBlocks", () => {
  it("downloads multiple blocks in parallel", async () => {
    const blocks = [
      makeBlock("hero", ["block.json", "index.php"]),
      makeBlock("tab-item", ["block.json", "index.php"]),
    ];
    const results = await downloadBlocks(blocks);

    expect(results).toHaveLength(2);
    expect(results[0].files).toHaveLength(2);
    expect(results[1].files).toHaveLength(2);
  });

  it("downloads from different registries", async () => {
    const blocks: ResolvedBlock[] = [
      makeBlock("hero", ["block.json"], `${server.url}/ui/blocks`),
      {
        name: "hero",
        files: ["block.json"],
        registryName: "starter",
        registryBaseUrl: `${server.url}/starter/blocks`,
      },
    ];
    const results = await downloadBlocks(blocks);

    const uiContent = JSON.parse(results[0].files[0].content);
    const starterContent = JSON.parse(results[1].files[0].content);

    expect(uiContent.name).toBe("test/hero");
    expect(starterContent.name).toBe("starter/hero");
  });
});

import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { mkdtemp, readFile, rm, mkdir, writeFile as nodeWriteFile } from "node:fs/promises";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { exists, writeBlock, writeBlocks, removeBlock } from "../../src/utils/fs.js";
import type { DownloadedBlock } from "../../src/registry/downloader.js";

let tempDir: string;

beforeEach(async () => {
  tempDir = await mkdtemp(join(tmpdir(), "bs-fs-test-"));
});

afterEach(async () => {
  await rm(tempDir, { recursive: true, force: true });
});

describe("exists", () => {
  it("returns true for existing file", async () => {
    await nodeWriteFile(join(tempDir, "test.txt"), "hello");
    expect(await exists(join(tempDir, "test.txt"))).toBe(true);
  });

  it("returns true for existing directory", async () => {
    await mkdir(join(tempDir, "subdir"));
    expect(await exists(join(tempDir, "subdir"))).toBe(true);
  });

  it("returns false for non-existent path", async () => {
    expect(await exists(join(tempDir, "nope"))).toBe(false);
  });
});

describe("writeBlock", () => {
  const makeDownloaded = (name: string, files: Array<{ path: string; content: string }>): DownloadedBlock => ({
    block: {
      name,
      files: files.map((f) => f.path),
      registryName: "test",
      registryBaseUrl: "https://example.com",
    },
    files,
  });

  it("writes files to the correct directory", async () => {
    const block = makeDownloaded("hero", [
      { path: "block.json", content: '{"name":"test/hero"}' },
      { path: "index.php", content: "<div>hero</div>" },
    ]);

    const written = await writeBlock(block, tempDir);

    expect(written).toHaveLength(2);
    expect(await readFile(join(tempDir, "hero", "block.json"), "utf-8")).toBe('{"name":"test/hero"}');
    expect(await readFile(join(tempDir, "hero", "index.php"), "utf-8")).toBe("<div>hero</div>");
  });

  it("creates nested directories for files with paths", async () => {
    const block = makeDownloaded("complex", [
      { path: "src/components/main.tsx", content: "export default Main;" },
      { path: "src/styles/main.css", content: ".main {}" },
    ]);

    await writeBlock(block, tempDir);

    expect(await readFile(join(tempDir, "complex", "src", "components", "main.tsx"), "utf-8")).toBe("export default Main;");
    expect(await readFile(join(tempDir, "complex", "src", "styles", "main.css"), "utf-8")).toBe(".main {}");
  });

  it("overwrites existing files", async () => {
    const blockDir = join(tempDir, "hero");
    await mkdir(blockDir, { recursive: true });
    await nodeWriteFile(join(blockDir, "block.json"), "old content");

    const block = makeDownloaded("hero", [
      { path: "block.json", content: "new content" },
    ]);

    await writeBlock(block, tempDir);
    expect(await readFile(join(tempDir, "hero", "block.json"), "utf-8")).toBe("new content");
  });

  it("returns list of written file paths", async () => {
    const block = makeDownloaded("hero", [
      { path: "block.json", content: "{}" },
      { path: "index.php", content: "" },
    ]);

    const written = await writeBlock(block, tempDir);
    expect(written).toEqual([
      join(tempDir, "hero", "block.json"),
      join(tempDir, "hero", "index.php"),
    ]);
  });
});

describe("writeBlocks", () => {
  it("writes multiple blocks and returns a map", async () => {
    const blocks: DownloadedBlock[] = [
      {
        block: { name: "hero", files: ["block.json"], registryName: "test", registryBaseUrl: "" },
        files: [{ path: "block.json", content: '{"name":"hero"}' }],
      },
      {
        block: { name: "card", files: ["block.json"], registryName: "test", registryBaseUrl: "" },
        files: [{ path: "block.json", content: '{"name":"card"}' }],
      },
    ];

    const result = await writeBlocks(blocks, tempDir);

    expect(result.size).toBe(2);
    expect(result.get("hero")).toHaveLength(1);
    expect(result.get("card")).toHaveLength(1);
    expect(await readFile(join(tempDir, "hero", "block.json"), "utf-8")).toBe('{"name":"hero"}');
    expect(await readFile(join(tempDir, "card", "block.json"), "utf-8")).toBe('{"name":"card"}');
  });
});

describe("removeBlock", () => {
  it("removes a block directory", async () => {
    const blockDir = join(tempDir, "hero");
    await mkdir(blockDir, { recursive: true });
    await nodeWriteFile(join(blockDir, "block.json"), "{}");
    await nodeWriteFile(join(blockDir, "index.php"), "<?php");

    await removeBlock("hero", tempDir);

    expect(await exists(blockDir)).toBe(false);
  });

  it("does not throw for non-existent block", async () => {
    await expect(removeBlock("nope", tempDir)).resolves.toBeUndefined();
  });
});

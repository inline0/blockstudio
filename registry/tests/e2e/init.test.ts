import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { mkdtemp, readFile, rm, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { run } from "../helpers/cli.js";

let cwd: string;

beforeEach(async () => {
  cwd = await mkdtemp(join(tmpdir(), "bs-e2e-init-"));
});

afterEach(async () => {
  await rm(cwd, { recursive: true, force: true });
});

describe("init", () => {
  it("creates blocks.json with default directory", async () => {
    const result = await run(["init"], { cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("Created");

    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.directory).toBe("blockstudio");
    expect(config.registries).toEqual({});
  });

  it("creates blocks.json with custom directory", async () => {
    const result = await run(["init", "--directory", "my-blocks"], { cwd });

    expect(result.exitCode).toBe(0);

    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.directory).toBe("my-blocks");
  });

  it("overwrites when blocks.json already exists in non-interactive mode", async () => {
    await writeFile(join(cwd, "blocks.json"), "{}");

    const result = await run(["init"], { cwd });
    expect(result.exitCode).toBe(0);

    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.directory).toBe("blockstudio");
  });

  it("overwrites with --yes", async () => {
    await writeFile(join(cwd, "blocks.json"), "{}");

    const result = await run(["init", "--yes"], { cwd });
    expect(result.exitCode).toBe(0);

    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.directory).toBe("blockstudio");
  });

  it("includes $schema in output", async () => {
    await run(["init"], { cwd });

    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.$schema).toContain("blockstudio.dev");
  });
});

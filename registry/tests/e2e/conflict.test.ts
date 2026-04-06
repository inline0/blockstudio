import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { readFile, access } from "node:fs/promises";
import { join } from "node:path";
import { run } from "../helpers/cli.js";
import { startFixtureServer } from "../helpers/fixtures.js";
import { createTestProject } from "../helpers/project.js";

let server: Awaited<ReturnType<typeof startFixtureServer>>;

beforeAll(async () => {
  server = await startFixtureServer();
});

afterAll(async () => {
  await server.stop();
});

let project: Awaited<ReturnType<typeof createTestProject>>;

beforeEach(async () => {
  project = await createTestProject({
    registries: {
      ui: `${server.url}/ui/registry.json`,
      starter: `${server.url}/starter/registry.json`,
    },
  });
});

afterEach(async () => {
  await project.cleanup();
});

async function fileExists(path: string): Promise<boolean> {
  try {
    await access(path);
    return true;
  } catch {
    return false;
  }
}

describe("conflict resolution", () => {
  it("errors when block exists in multiple registries without namespace", async () => {
    const result = await run(["add", "hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("multiple registries");
    expect(result.stderr).toContain("ui");
    expect(result.stderr).toContain("starter");
  });

  it("uses first match with --yes when block is in multiple registries", async () => {
    const result = await run(["add", "hero", "--yes"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(await fileExists(join(project.cwd, "blockstudio", "hero", "block.json"))).toBe(true);
  });

  it("resolves with explicit namespace: ui/hero", async () => {
    const result = await run(["add", "ui/hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    const content = JSON.parse(
      await readFile(join(project.cwd, "blockstudio", "hero", "block.json"), "utf-8"),
    );
    expect(content.name).toBe("test/hero");
  });

  it("resolves with explicit namespace: starter/hero", async () => {
    const result = await run(["add", "starter/hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    const content = JSON.parse(
      await readFile(join(project.cwd, "blockstudio", "hero", "block.json"), "utf-8"),
    );
    expect(content.name).toBe("starter/hero");
  });
});

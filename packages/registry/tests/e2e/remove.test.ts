import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { access } from "node:fs/promises";
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

describe("remove", () => {
  it("removes an installed block with --yes", async () => {
    await run(["add", "ui/hero"], { cwd: project.cwd });
    const blockDir = join(project.cwd, "blockstudio", "hero");
    expect(await fileExists(blockDir)).toBe(true);

    const result = await run(["remove", "hero", "--yes"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("Removed");
    expect(await fileExists(blockDir)).toBe(false);
  });

  it("removes without --yes in non-interactive mode", async () => {
    await run(["add", "ui/hero"], { cwd: project.cwd });

    const result = await run(["remove", "hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("Removed");
    expect(await fileExists(join(project.cwd, "blockstudio", "hero"))).toBe(false);
  });

  it("fails when block is not installed", async () => {
    const result = await run(["remove", "hero", "--yes"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("not installed");
  });
});

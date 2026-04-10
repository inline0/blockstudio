import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
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

describe("list", () => {
  it("lists all blocks from all registries", async () => {
    const result = await run(["list"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("ui");
    expect(result.stdout).toContain("starter");
    expect(result.stdout).toContain("tabs");
    expect(result.stdout).toContain("tab-item");
    expect(result.stdout).toContain("hero");
  });

  it("lists blocks from a specific registry", async () => {
    const result = await run(["list", "ui"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("tabs");
    expect(result.stdout).toContain("tab-item");
    expect(result.stdout).toContain("hero");
    expect(result.stdout).not.toContain("Starter Hero");
  });

  it("shows block count", async () => {
    const result = await run(["list", "ui"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("3 blocks");
  });

  it("fails for unknown registry", async () => {
    const result = await run(["list", "nonexistent"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("not found");
  });
});

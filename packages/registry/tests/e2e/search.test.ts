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

describe("search", () => {
  it("finds blocks by exact name", async () => {
    const result = await run(["search", "tabs"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("ui/tabs");
  });

  it("finds blocks by partial name", async () => {
    const result = await run(["search", "tab"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("ui/tabs");
    expect(result.stdout).toContain("ui/tab-item");
  });

  it("finds blocks by description", async () => {
    const result = await run(["search", "keyboard"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("ui/tabs");
  });

  it("finds blocks across registries", async () => {
    const result = await run(["search", "hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("ui/hero");
    expect(result.stdout).toContain("starter/hero");
  });

  it("shows no results for unmatched query", async () => {
    const result = await run(["search", "zzzznothing"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("No blocks matching");
  });
});

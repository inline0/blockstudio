import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { mkdtemp, rm } from "node:fs/promises";
import { join } from "node:path";
import { tmpdir } from "node:os";
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

describe("error: no config", () => {
  let cwd: string;

  beforeEach(async () => {
    cwd = await mkdtemp(join(tmpdir(), "bs-e2e-noconfig-"));
  });

  afterEach(async () => {
    await rm(cwd, { recursive: true, force: true });
  });

  it("add fails without blocks.json", async () => {
    const result = await run(["add", "ui/hero"], { cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("Could not find");
    expect(result.stderr).toContain("init");
  });

  it("list fails without blocks.json", async () => {
    const result = await run(["list"], { cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("Could not find");
  });

  it("search fails without blocks.json", async () => {
    const result = await run(["search", "hero"], { cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("Could not find");
  });

  it("remove fails without blocks.json", async () => {
    const result = await run(["remove", "hero", "--yes"], { cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("Could not find");
  });
});

describe("error: bad registry URL", () => {
  let project: Awaited<ReturnType<typeof createTestProject>>;

  beforeEach(async () => {
    project = await createTestProject({
      registries: {
        bad: `${server.url}/nonexistent/registry.json`,
      },
    });
  });

  afterEach(async () => {
    await project.cleanup();
  });

  it("add reports 404", async () => {
    const result = await run(["add", "bad/hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("404");
  });

  it("list reports 404", async () => {
    const result = await run(["list", "bad"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("404");
  });
});

describe("error: no registries configured", () => {
  let project: Awaited<ReturnType<typeof createTestProject>>;

  beforeEach(async () => {
    project = await createTestProject({ registries: {} });
  });

  afterEach(async () => {
    await project.cleanup();
  });

  it("add reports no registries", async () => {
    const result = await run(["add", "hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("No registries");
  });
});

import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { readFile, rm, access } from "node:fs/promises";
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

describe("add", () => {
  it("installs a block with namespace and correct file contents", async () => {
    const result = await run(["add", "ui/hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("Installed");

    const blockJson = JSON.parse(
      await readFile(join(project.cwd, "blockstudio", "hero", "block.json"), "utf-8"),
    );
    expect(blockJson.name).toBe("test/hero");
    expect(blockJson.apiVersion).toBe(3);
    expect(blockJson.category).toBe("layout");

    const php = await readFile(
      join(project.cwd, "blockstudio", "hero", "index.php"), "utf-8",
    );
    expect(php).toContain("<section class=\"hero\">");
    expect(php).toContain("$a['title']");
  });

  it("installs a block and its dependencies with correct content", async () => {
    const result = await run(["add", "ui/tabs"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);

    const tabItemBlock = JSON.parse(
      await readFile(join(project.cwd, "blockstudio", "tab-item", "block.json"), "utf-8"),
    );
    expect(tabItemBlock.name).toBe("test/tab-item");
    expect(tabItemBlock.apiVersion).toBe(3);

    const tabItemPhp = await readFile(
      join(project.cwd, "blockstudio", "tab-item", "index.php"), "utf-8",
    );
    expect(tabItemPhp).toContain("tab-item");
    expect(tabItemPhp).toContain("$a['content']");

    const tabsBlock = JSON.parse(
      await readFile(join(project.cwd, "blockstudio", "tabs", "block.json"), "utf-8"),
    );
    expect(tabsBlock.name).toBe("test/tabs");

    const tabsPhp = await readFile(
      join(project.cwd, "blockstudio", "tabs", "index.php"), "utf-8",
    );
    expect(tabsPhp).toContain("<div class=\"tabs\">");

    const tabsScss = await readFile(
      join(project.cwd, "blockstudio", "tabs", "style.scss"), "utf-8",
    );
    expect(tabsScss).toContain(".tabs");
    expect(tabsScss).toContain("flex-direction");
  });

  it("installs without namespace when block is unique", async () => {
    const result = await run(["add", "tabs", "--yes"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(await fileExists(join(project.cwd, "blockstudio", "tabs", "block.json"))).toBe(true);
  });

  it("fails when block is in multiple registries without namespace", async () => {
    const result = await run(["add", "hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("multiple registries");
  });

  it("fails when block is not found", async () => {
    const result = await run(["add", "ui/nonexistent"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("not found");
  });

  it("fails when registry namespace is invalid", async () => {
    const result = await run(["add", "fake/hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("not found");
  });

  it("fails when block already exists without --yes", async () => {
    await run(["add", "ui/hero"], { cwd: project.cwd });
    const result = await run(["add", "ui/hero"], { cwd: project.cwd });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("already exists");
  });

  it("overwrites with --yes", async () => {
    await run(["add", "ui/hero"], { cwd: project.cwd });
    const result = await run(["add", "ui/hero", "--yes"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("Installed");
  });

  it("installs multiple blocks in one command", async () => {
    const result = await run(["add", "ui/hero", "ui/tab-item"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(await fileExists(join(project.cwd, "blockstudio", "hero", "block.json"))).toBe(true);
    expect(await fileExists(join(project.cwd, "blockstudio", "tab-item", "block.json"))).toBe(true);
  });

  it("respects custom directory", async () => {
    await project.cleanup();
    project = await createTestProject({
      directory: "my-blocks",
      registries: { ui: `${server.url}/ui/registry.json` },
    });

    await run(["add", "ui/hero"], { cwd: project.cwd });
    expect(await fileExists(join(project.cwd, "my-blocks", "hero", "block.json"))).toBe(true);
  });

  it("downloads correct content from different registries", async () => {
    await run(["add", "ui/hero"], { cwd: project.cwd });
    const uiPhp = await readFile(
      join(project.cwd, "blockstudio", "hero", "index.php"), "utf-8",
    );
    expect(uiPhp).toContain("class=\"hero\"");

    await run(["add", "starter/hero", "--yes"], { cwd: project.cwd });
    const starterPhp = await readFile(
      join(project.cwd, "blockstudio", "hero", "index.php"), "utf-8",
    );
    expect(starterPhp).toContain("class=\"starter-hero\"");
    expect(starterPhp).not.toContain("class=\"hero\"");

    const starterBlock = JSON.parse(
      await readFile(join(project.cwd, "blockstudio", "hero", "block.json"), "utf-8"),
    );
    expect(starterBlock.name).toBe("starter/hero");
  });

  it("dry run does not write files", async () => {
    const result = await run(["add", "ui/hero", "--dry-run"], { cwd: project.cwd });

    expect(result.exitCode).toBe(0);
    expect(result.stdout).toContain("Would install");
    expect(await fileExists(join(project.cwd, "blockstudio", "hero"))).toBe(false);
  });
});

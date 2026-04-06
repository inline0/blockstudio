import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { readFile, access } from "node:fs/promises";
import { join } from "node:path";
import { render } from "ink-testing-library";
import { createElement } from "react";
import { InitApp } from "../../src/ui/init.js";
import { AddApp } from "../../src/ui/add.js";
import { RemoveApp } from "../../src/ui/remove.js";
import { startFixtureServer } from "../helpers/fixtures.js";
import { createTestProject } from "../helpers/project.js";
import { run } from "../helpers/cli.js";
import { mkdtemp, rm } from "node:fs/promises";
import { tmpdir } from "node:os";

async function fileExists(path: string): Promise<boolean> {
  try {
    await access(path);
    return true;
  } catch {
    return false;
  }
}

function wait(ms: number): Promise<void> {
  return new Promise((r) => setTimeout(r, ms));
}

let server: Awaited<ReturnType<typeof startFixtureServer>>;

beforeAll(async () => {
  server = await startFixtureServer();
});

afterAll(async () => {
  await server.stop();
});

describe("interactive: init", () => {
  let cwd: string;

  beforeEach(async () => {
    cwd = await mkdtemp(join(tmpdir(), "bs-ink-init-"));
  });

  afterEach(async () => {
    await rm(cwd, { recursive: true, force: true });
  });

  it("creates blocks.json with default directory", async () => {
    const { stdin, lastFrame, unmount } = render(
      createElement(InitApp, { cwd }),
    );

    await wait(200);
    stdin.write("\r"); // accept default directory
    await wait(200);
    stdin.write("n"); // no registries
    await wait(500);

    expect(await fileExists(join(cwd, "blocks.json"))).toBe(true);
    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.directory).toBe("blockstudio");

    unmount();
  });

  it("creates blocks.json with custom directory", async () => {
    const { stdin, lastFrame, unmount } = render(
      createElement(InitApp, { cwd }),
    );

    await wait(200);
    stdin.write("my-blocks");
    await wait(100);
    stdin.write("\r"); // submit
    await wait(200);
    stdin.write("n"); // no registries
    await wait(500);

    expect(await fileExists(join(cwd, "blocks.json"))).toBe(true);
    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.directory).toBe("my-blocks");

    unmount();
  });

  it("creates blocks.json with a registry", async () => {
    const { stdin, lastFrame, unmount } = render(
      createElement(InitApp, { cwd }),
    );

    await wait(200);
    stdin.write("\r"); // accept default directory
    await wait(200);
    stdin.write("y"); // yes, add registry
    await wait(200);
    stdin.write("ui");
    await wait(100);
    stdin.write("\r"); // submit name
    await wait(200);
    stdin.write(`${server.url}/ui/registry.json`);
    await wait(100);
    stdin.write("\r"); // submit URL
    await wait(200);
    stdin.write("n"); // no more registries
    await wait(800);

    const config = JSON.parse(await readFile(join(cwd, "blocks.json"), "utf-8"));
    expect(config.registries.ui).toContain("registry.json");

    unmount();
  });

  it("shows overwrite prompt for existing config", async () => {
    const { stdin: s1, unmount: u1 } = render(
      createElement(InitApp, { cwd }),
    );
    await wait(200);
    s1.write("\r");
    await wait(200);
    s1.write("n");
    await wait(500);
    u1();

    const { stdin, lastFrame, unmount } = render(
      createElement(InitApp, { cwd }),
    );

    await wait(200);
    const frame = lastFrame();
    expect(frame).toContain("Overwrite");

    stdin.write("y"); // yes overwrite
    await wait(200);
    stdin.write("\r"); // accept directory
    await wait(200);
    stdin.write("n"); // no registries
    await wait(500);

    unmount();
  });
});

describe("interactive: add", () => {
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

  it("installs a block with spinner and success output", async () => {
    const { stdin, lastFrame, unmount } = render(
      createElement(AddApp, { blockArgs: ["ui/hero"], cwd: project.cwd }),
    );

    await wait(2000);

    const frame = lastFrame();
    expect(frame).toContain("Installed");
    expect(await fileExists(join(project.cwd, "blockstudio", "hero", "block.json"))).toBe(true);

    unmount();
  });

  it("installs block with dependencies", async () => {
    const { stdin, lastFrame, unmount } = render(
      createElement(AddApp, { blockArgs: ["ui/tabs"], cwd: project.cwd }),
    );

    await wait(2000);

    const frame = lastFrame();
    expect(frame).toContain("Done");
    expect(await fileExists(join(project.cwd, "blockstudio", "tab-item", "block.json"))).toBe(true);
    expect(await fileExists(join(project.cwd, "blockstudio", "tabs", "block.json"))).toBe(true);

    unmount();
  });

  it("shows select prompt on conflict", async () => {
    const { stdin, lastFrame, unmount } = render(
      createElement(AddApp, { blockArgs: ["hero"], cwd: project.cwd }),
    );

    await wait(1000);

    const frame = lastFrame();
    expect(frame).toContain("multiple registries");

    stdin.write("\r");
    await wait(2000);

    expect(await fileExists(join(project.cwd, "blockstudio", "hero", "block.json"))).toBe(true);

    unmount();
  });

  it("prompts to overwrite existing block", async () => {
    await run(["add", "ui/hero", "--yes"], { cwd: project.cwd });

    const { stdin, lastFrame, unmount } = render(
      createElement(AddApp, { blockArgs: ["ui/hero"], cwd: project.cwd }),
    );

    await wait(1000);

    const frame = lastFrame();
    expect(frame).toContain("Overwrite");

    stdin.write("y"); // confirm
    await wait(2000);

    const finalFrame = lastFrame();
    expect(finalFrame).toContain("Installed");

    unmount();
  });

  it("skips block when overwrite declined", async () => {
    await run(["add", "ui/hero", "--yes"], { cwd: project.cwd });

    const { stdin, lastFrame, unmount } = render(
      createElement(AddApp, { blockArgs: ["ui/hero"], cwd: project.cwd }),
    );

    await wait(1000);
    stdin.write("n"); // decline
    await wait(1000);

    const frame = lastFrame();
    expect(frame).toContain("Skipped");

    unmount();
  });
});

describe("interactive: remove", () => {
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

  it("confirms and removes block", async () => {
    await run(["add", "ui/hero", "--yes"], { cwd: project.cwd });

    const { stdin, lastFrame, unmount } = render(
      createElement(RemoveApp, { blockName: "hero", cwd: project.cwd }),
    );

    await wait(500);
    const frame = lastFrame();
    expect(frame).toContain("Remove");

    stdin.write("y");
    await wait(500);

    expect(lastFrame()).toContain("Removed");
    expect(await fileExists(join(project.cwd, "blockstudio", "hero"))).toBe(false);

    unmount();
  });

  it("aborts when declined", async () => {
    await run(["add", "ui/hero", "--yes"], { cwd: project.cwd });

    const { stdin, lastFrame, unmount } = render(
      createElement(RemoveApp, { blockName: "hero", cwd: project.cwd }),
    );

    await wait(500);
    stdin.write("n");
    await wait(500);

    expect(lastFrame()).toContain("Aborted");
    expect(await fileExists(join(project.cwd, "blockstudio", "hero"))).toBe(true);

    unmount();
  });
});

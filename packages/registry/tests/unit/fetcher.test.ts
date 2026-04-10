import { describe, it, expect, beforeAll, afterAll, beforeEach } from "vitest";
import { fetchRegistry, clearCache } from "../../src/registry/fetcher.js";
import { startFixtureServer } from "../helpers/fixtures.js";

let server: Awaited<ReturnType<typeof startFixtureServer>>;

beforeAll(async () => {
  server = await startFixtureServer();
});

afterAll(async () => {
  await server.stop();
});

beforeEach(() => {
  clearCache();
});

describe("fetchRegistry", () => {
  it("fetches and parses a valid registry", async () => {
    const registry = await fetchRegistry(`${server.url}/ui/registry.json`);

    expect(registry.name).toBe("ui");
    expect(registry.blocks).toHaveLength(3);
    expect(registry.blocks[0].name).toBe("tabs");
    expect(registry.baseUrl).toContain(String(server.url));
  });

  it("returns cached result on second call", async () => {
    const url = `${server.url}/ui/registry.json`;
    const first = await fetchRegistry(url);
    const second = await fetchRegistry(url);
    expect(first).toBe(second); // same reference
  });

  it("cache clears correctly", async () => {
    const url = `${server.url}/ui/registry.json`;
    const first = await fetchRegistry(url);
    clearCache();
    const second = await fetchRegistry(url);
    expect(first).not.toBe(second); // different reference
    expect(first).toEqual(second); // same data
  });

  it("throws on 404", async () => {
    await expect(
      fetchRegistry(`${server.url}/nonexistent/registry.json`),
    ).rejects.toThrow("404");
  });

  it("throws on invalid JSON response", async () => {
    await expect(
      fetchRegistry(`${server.url}/nope/registry.json`),
    ).rejects.toThrow("Registry returned");
  });

  it("parses all block fields correctly", async () => {
    const registry = await fetchRegistry(`${server.url}/ui/registry.json`);

    const tabs = registry.blocks.find((b) => b.name === "tabs")!;
    expect(tabs.title).toBe("Tabs");
    expect(tabs.description).toContain("keyboard");
    expect(tabs.category).toBe("layout");
    expect(tabs.type).toBe("blockstudio");
    expect(tabs.dependencies).toEqual(["tab-item"]);
    expect(tabs.files).toEqual(["block.json", "index.php", "style.scss"]);
  });

  it("parses blocks without optional fields", async () => {
    const registry = await fetchRegistry(`${server.url}/ui/registry.json`);

    const hero = registry.blocks.find((b) => b.name === "hero")!;
    expect(hero.dependencies).toBeUndefined();
  });
});

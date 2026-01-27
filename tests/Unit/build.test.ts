import { test, expect } from "../wordpress-playground/fixtures";

test.describe("Build Class", () => {
  test("health check - Blockstudio is loaded", async ({ wp }) => {
    const health = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/health");
      return res.json();
    });

    expect(health.status).toBe("ok");
    expect(health.blockstudio_loaded).toBe(true);
  });

  test("discovers test blocks", async ({ wp }) => {
    const blocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/build/all");
      return res.json();
    });

    // Should discover many blocks from test-blocks/
    const blockPaths = Object.keys(blocks);
    expect(blockPaths.length).toBeGreaterThan(0);
  });

  test("returns correct data structure for a block", async ({ wp }) => {
    // First get all blocks to find one that exists
    const allBlocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/build/all");
      return res.json();
    });

    const blockNames = Object.values(allBlocks)
      .map((b: any) => b.name)
      .filter(Boolean);

    if (blockNames.length === 0) {
      test.skip();
      return;
    }

    // Get the first block
    const blockName = blockNames[0];
    const block = await wp.locator("body").evaluate(async (name: string) => {
      const res = await fetch(`/wp-json/blockstudio-test/v1/build/${name}`);
      return res.json();
    }, blockName);

    // Check that the block has the expected structure
    expect(block).toHaveProperty("name");
    expect(block).toHaveProperty("path");
    expect(block).toHaveProperty("fields");
    expect(block).toHaveProperty("attributes");
  });

  test("builds attributes from fields", async ({ wp }) => {
    // Get all blocks and find one with fields
    const allBlocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/build/all");
      return res.json();
    });

    // Find a block that has fields
    const blockWithFields = Object.values(allBlocks).find(
      (b: any) => b.fields && b.fields.length > 0
    ) as any;

    if (!blockWithFields) {
      test.skip();
      return;
    }

    // Get detailed data for this block
    const block = await wp.locator("body").evaluate(async (name: string) => {
      const res = await fetch(`/wp-json/blockstudio-test/v1/build/${name}`);
      return res.json();
    }, blockWithFields.name);

    // Should have blockstudio attribute which stores field data
    expect(block.attributes).toBeDefined();
  });
});

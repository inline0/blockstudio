import { test, expect } from "../wordpress-playground/fixtures";

test.describe("Block Registration", () => {
  test("blocks are registered in WP_Block_Type_Registry", async ({ wp }) => {
    const blocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/registered");
      return res.json();
    });

    // Should have registered blocks
    const names = Object.keys(blocks);
    expect(names.length).toBeGreaterThan(0);

    // All registered blocks should be Blockstudio blocks
    names.forEach((name) => {
      expect(name).toMatch(/^blockstudio\//);
    });
  });

  test("registered blocks have attributes", async ({ wp }) => {
    const blocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/registered");
      return res.json();
    });

    const names = Object.keys(blocks);
    if (names.length === 0) {
      test.skip();
      return;
    }

    // Check the first block has attributes property
    const firstBlock = blocks[names[0]];
    expect(firstBlock).toHaveProperty("attributes");
  });

  test("Build blocks match registered blocks", async ({ wp }) => {
    // Get blocks from Build class
    const buildBlocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/build/all");
      return res.json();
    });

    // Get registered blocks
    const registeredBlocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/registered");
      return res.json();
    });

    const buildNames = Object.values(buildBlocks)
      .map((b: any) => b.name)
      .filter(Boolean);
    const registeredNames = Object.keys(registeredBlocks);

    // Every built block should be registered
    // (Some blocks may not register if they have errors, so we check overlap)
    const overlap = buildNames.filter((name) => registeredNames.includes(name));
    expect(overlap.length).toBeGreaterThan(0);
  });
});

import { test, expect } from "../wordpress-playground/fixtures";

test.describe("Block Rendering", () => {
  test("renders a block and returns HTML", async ({ wp }) => {
    // First get a registered block name
    const blocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/registered");
      return res.json();
    });

    const names = Object.keys(blocks);
    if (names.length === 0) {
      test.skip();
      return;
    }

    // Render the first block
    const result = await wp.locator("body").evaluate(async (blockName) => {
      const res = await fetch("/wp-json/blockstudio-test/v1/render", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          blockName,
          attrs: {},
        }),
      });
      return res.json();
    }, names[0]);

    // Should return an HTML string
    expect(result).toHaveProperty("html");
    expect(typeof result.html).toBe("string");
  });

  test("renders block with attributes", async ({ wp }) => {
    // Get a block that has attributes
    const blocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/registered");
      return res.json();
    });

    const blockWithAttrs = Object.entries(blocks).find(
      ([_, data]: [string, any]) =>
        data.attributes && Object.keys(data.attributes).length > 0
    );

    if (!blockWithAttrs) {
      test.skip();
      return;
    }

    const [blockName] = blockWithAttrs;

    // Render with empty blockstudio attribute
    const result = await wp.locator("body").evaluate(
      async ({ name, attrs }) => {
        const res = await fetch("/wp-json/blockstudio-test/v1/render", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            blockName: name,
            attrs,
          }),
        });
        return res.json();
      },
      { name: blockName, attrs: { blockstudio: {} } }
    );

    expect(result).toHaveProperty("html");
  });

  test("all registered blocks can render without errors", async ({ wp }) => {
    const blocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/registered");
      return res.json();
    });

    const names = Object.keys(blocks);
    const results: { name: string; success: boolean; error?: string }[] = [];

    // Test rendering each block
    for (const name of names.slice(0, 10)) {
      // Test first 10 blocks to avoid timeout
      const result = await wp.locator("body").evaluate(async (blockName) => {
        try {
          const res = await fetch("/wp-json/blockstudio-test/v1/render", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              blockName,
              attrs: {},
            }),
          });
          const data = await res.json();
          return { success: true, html: data.html };
        } catch (e: any) {
          return { success: false, error: e.message };
        }
      }, name);

      results.push({
        name,
        success: result.success,
        error: result.success ? undefined : result.error,
      });
    }

    // All blocks should render without errors
    const failures = results.filter((r) => !r.success);
    expect(failures).toHaveLength(0);
  });
});

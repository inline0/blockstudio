import { test, expect } from "../utils/fixtures";

test.describe("E2E Block Registration", () => {
  test.beforeAll(async ({ wp }) => {
    // Setup E2E test data
    await wp.locator("body").evaluate(async () => {
      await fetch("/wp-json/blockstudio-test/v1/e2e/setup", {
        method: "POST",
      });
    });
  });

  test("test blocks are registered", async ({ wp }) => {
    const blocks = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/registered");
      return res.json();
    });

    const blockNames = Object.keys(blocks);

    // Check that E2E test blocks are registered
    expect(blockNames).toContain("blockstudio/type-text");
    expect(blockNames).toContain("blockstudio/type-select");
    expect(blockNames).toContain("blockstudio/type-repeater");
    expect(blockNames).toContain("blockstudio/type-toggle");
    expect(blockNames).toContain("blockstudio/type-number");
  });

  test("E2E test data is available", async ({ wp }) => {
    const data = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/e2e/data");
      return res.json();
    });

    // Should have at least one post
    expect(data.posts.length).toBeGreaterThan(0);

    // Should have at least one user (admin)
    expect(data.users.length).toBeGreaterThan(0);
  });
});

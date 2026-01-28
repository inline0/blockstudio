/**
 * E2E Test Fixtures for WordPress Playground.
 * Uses worker-scoped fixtures to share page across ALL tests in a worker.
 */

import { test as base, Page, FrameLocator, expect, BrowserContext } from "@playwright/test";

type WorkerFixtures = {
  workerPage: Page;
  workerWpFrame: FrameLocator;
};

type TestFixtures = {
  editor: FrameLocator;
  resetBlocks: () => Promise<void>;
};

async function closeModals(frame: FrameLocator) {
  for (let i = 0; i < 10; i++) {
    const modalOverlay = await frame
      .locator(".components-modal__screen-overlay")
      .isVisible()
      .catch(() => false);

    if (!modalOverlay) break;

    const closeSelectors = [
      '.components-modal__header button',
      'button[aria-label="Close"]',
      '.components-modal__frame button:has-text("Close")',
      'button:has-text("Close")',
    ];

    let closed = false;
    for (const selector of closeSelectors) {
      const closeButton = frame.locator(selector).first();
      if (await closeButton.isVisible().catch(() => false)) {
        await closeButton.click();
        await new Promise((r) => setTimeout(r, 500));
        closed = true;
        break;
      }
    }

    if (closed) continue;

    await frame.locator("body").press("Escape");
    await new Promise((r) => setTimeout(r, 500));
  }
}

export const test = base.extend<TestFixtures, WorkerFixtures>({
  // Worker-scoped: shared across ALL tests in the worker
  workerPage: [async ({ browser }, use) => {
    const port = process.env.PLAYGROUND_PORT || "9410";

    console.log("\n  [Worker] Initializing shared Playground page...");

    const page = await browser.newPage();
    await page.goto(`http://localhost:${port}`);
    await page.waitForFunction("window.playgroundReady === true", {
      timeout: 120000,
    });

    const playgroundFrame = page.frameLocator("iframe#playground");
    const wpFrame = playgroundFrame.frameLocator("iframe#wp");

    // Call E2E setup endpoint ONCE to create all required test fixtures
    const setupResult = await wpFrame.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/e2e/setup", {
        method: "POST",
      });
      return res.json();
    });
    console.log("  [Worker] E2E Setup:", setupResult.message || setupResult);

    // Navigate to editor
    await wpFrame
      .locator("body")
      .evaluate(() => (window.location.href = "/wp-admin/post-new.php"));

    await wpFrame
      .locator(".is-root-container")
      .waitFor({ state: "visible", timeout: 60000 });

    await new Promise((r) => setTimeout(r, 2000));

    console.log("  [Worker] Playground ready!\n");

    await use(page);

    await page.close();
  }, { scope: "worker" }],

  // Worker-scoped wpFrame derived from workerPage
  workerWpFrame: [async ({ workerPage }, use) => {
    const playgroundFrame = workerPage.frameLocator("iframe#playground");
    const wpFrame = playgroundFrame.frameLocator("iframe#wp");
    await use(wpFrame);
  }, { scope: "worker" }],

  // Test-scoped: runs for each test
  editor: async ({ workerWpFrame }, use) => {
    await closeModals(workerWpFrame);
    await use(workerWpFrame);
    // Note: Tests call resetBlocks() themselves when needed
  },

  resetBlocks: async ({ workerWpFrame }, use) => {
    const reset = async () => {
      const inserterOpen = await workerWpFrame
        .locator(".block-editor-inserter__block-list")
        .isVisible()
        .catch(() => false);
      if (inserterOpen) {
        await workerWpFrame.locator('button[aria-label="Block Inserter"]').click();
        await new Promise((r) => setTimeout(r, 300));
      }

      await workerWpFrame.locator("body").evaluate(() => {
        (window as any).wp.data.dispatch("core/block-editor").resetBlocks([]);
      });
      await new Promise((r) => setTimeout(r, 300));
    };
    await use(reset);
  },
});

export { expect };

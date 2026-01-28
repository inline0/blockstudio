/**
 * E2E Test Fixtures for WordPress Playground.
 * Provides shared page instance to avoid repeated Playground initialization.
 */

import { test as base, Page, FrameLocator, expect } from "@playwright/test";

let sharedPage: Page | null = null;
let wpFrame: FrameLocator | null = null;

type E2EFixtures = {
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

    // Multiple selectors because button structure varies across WP versions
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

export const test = base.extend<E2EFixtures>({
  editor: async ({ browser }, use) => {
    const port = process.env.PLAYGROUND_PORT || "9410";

    if (!sharedPage) {
      sharedPage = await browser.newPage();
      await sharedPage.goto(`http://localhost:${port}`);
      await sharedPage.waitForFunction("window.playgroundReady === true", {
        timeout: 120000,
      });

      const playgroundFrame = sharedPage.frameLocator("iframe#playground");
      wpFrame = playgroundFrame.frameLocator("iframe#wp");

      // Call E2E setup endpoint to create all required test fixtures
      // (posts, media, users, terms with specific IDs)
      const setupResult = await wpFrame.locator("body").evaluate(async () => {
        const res = await fetch("/wp-json/blockstudio-test/v1/e2e/setup", {
          method: "POST",
        });
        return res.json();
      });
      console.log("E2E Setup:", setupResult.message || setupResult);

      await wpFrame
        .locator("body")
        .evaluate(() => (window.location.href = "/wp-admin/post-new.php"));

      await wpFrame
        .locator(".is-root-container")
        .waitFor({ state: "visible", timeout: 60000 });

      // Editor needs time to fully initialize before interactions
      await new Promise((r) => setTimeout(r, 2000));
    }

    await closeModals(wpFrame!);
    await use(wpFrame!);
  },

  resetBlocks: async ({}, use) => {
    const reset = async () => {
      if (wpFrame) {
        const inserterOpen = await wpFrame
          .locator(".block-editor-inserter__block-list")
          .isVisible()
          .catch(() => false);
        if (inserterOpen) {
          await wpFrame.locator('button[aria-label="Block Inserter"]').click();
          await new Promise((r) => setTimeout(r, 300));
        }

        await wpFrame.locator("body").evaluate(() => {
          (window as any).wp.data.dispatch("core/block-editor").resetBlocks([]);
        });
        await new Promise((r) => setTimeout(r, 300));
      }
    };
    await use(reset);
  },
});

export { expect };

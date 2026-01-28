/**
 * E2E Test Fixtures for WordPress Playground
 *
 * Provides a shared page that navigates to the block editor once,
 * avoiding repeated logins and page reloads.
 */

import { test as base, Page, FrameLocator, expect } from "@playwright/test";

let sharedPage: Page | null = null;
let wpFrame: FrameLocator | null = null;

type E2EFixtures = {
  /** The WordPress iframe inside Playground - already in block editor */
  editor: FrameLocator;
  /** Reset blocks to empty state */
  resetBlocks: () => Promise<void>;
};

async function closeModals(frame: FrameLocator) {
  for (let i = 0; i < 10; i++) {
    const modalOverlay = await frame
      .locator(".components-modal__screen-overlay")
      .isVisible()
      .catch(() => false);

    if (!modalOverlay) break;

    // Try clicking the modal's close button using multiple selectors
    // The button might have aria-label="Close" or just text content "Close"
    const closeSelectors = [
      '.components-modal__header button',  // Close button in modal header
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

    // Try Escape key as fallback
    await frame.locator("body").press("Escape");
    await new Promise((r) => setTimeout(r, 500));
  }
}

export const test = base.extend<E2EFixtures>({
  editor: async ({ browser }, use) => {
    const port = process.env.PLAYGROUND_PORT || "9410";

    // Initialize shared page if needed
    if (!sharedPage) {
      sharedPage = await browser.newPage();
      await sharedPage.goto(`http://localhost:${port}`);
      await sharedPage.waitForFunction("window.playgroundReady === true", {
        timeout: 120000,
      });

      const playgroundFrame = sharedPage.frameLocator("iframe#playground");
      wpFrame = playgroundFrame.frameLocator("iframe#wp");

      // Navigate to editor
      await wpFrame
        .locator("body")
        .evaluate(() => (window.location.href = "/wp-admin/post-new.php"));

      // Wait for editor to load
      await wpFrame
        .locator(".is-root-container")
        .waitFor({ state: "visible", timeout: 60000 });

      // Wait for editor to stabilize
      await new Promise((r) => setTimeout(r, 2000));
    }

    // Always try to close modals before each test
    await closeModals(wpFrame!);

    await use(wpFrame!);
  },

  resetBlocks: async ({}, use) => {
    const reset = async () => {
      if (wpFrame) {
        // Close inserter if open
        const inserterOpen = await wpFrame
          .locator(".block-editor-inserter__block-list")
          .isVisible()
          .catch(() => false);
        if (inserterOpen) {
          await wpFrame.locator('button[aria-label="Block Inserter"]').click();
          await new Promise((r) => setTimeout(r, 300));
        }

        // Reset blocks
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

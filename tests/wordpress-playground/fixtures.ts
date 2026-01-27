import { test as base, Page, FrameLocator } from "@playwright/test";

let sharedPage: Page;
let wpFrame: FrameLocator;

type Fixtures = {
  wp: FrameLocator;
};

export const test = base.extend<Fixtures>({
  wp: async ({ browser }, use) => {
    if (!sharedPage) {
      sharedPage = await browser.newPage();
      await sharedPage.goto("/");
      await sharedPage.waitForFunction("window.playgroundReady === true", {
        timeout: 120000,
      });
      const playgroundFrame = sharedPage.frameLocator("iframe#playground");
      wpFrame = playgroundFrame.frameLocator("iframe#wp");
    }
    await use(wpFrame);
  },
});

export { expect } from "@playwright/test";

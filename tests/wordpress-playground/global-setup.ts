import { chromium, FullConfig } from "@playwright/test";

export interface GlobalSetupOptions {
  baseURL?: string;
  timeout?: number;
}

export function createGlobalSetup(options: GlobalSetupOptions = {}) {
  const { baseURL = "http://localhost:9400", timeout = 120000 } = options;

  return async function globalSetup(_: FullConfig) {
    console.log(
      `\n  Initializing WordPress Playground at ${baseURL}...\n`
    );

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    page.setDefaultTimeout(timeout);

    await page.goto(baseURL, { timeout: 30000 });

    await page.waitForFunction("window.playgroundReady === true", {
      timeout,
    });

    console.log("  WordPress Playground ready!\n");

    await page.close();
    await browser.close();
  };
}

async function globalSetup(config: FullConfig) {
  const port = process.env.PLAYGROUND_PORT || "9400";
  const baseURL =
    (config.projects[0]?.use as any)?.baseURL || `http://localhost:${port}`;
  return createGlobalSetup({ baseURL })(config);
}

export default globalSetup;

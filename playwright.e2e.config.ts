import { createWordPressPlaygroundConfig } from "./tests/wordpress-playground/playwright-wordpress.config";

const port = parseInt(process.env.PLAYGROUND_PORT || "9410", 10);
const isV7 = port === 9411;

export default createWordPressPlaygroundConfig({
  testDir: "./tests/e2e/types",
  globalSetup: "./tests/wordpress-playground/global-setup.ts",
  port,
  testMatch: "**/*.ts",
  timeout: 120000, // E2E tests need more time
  workers: 1,
  extraConfig: {
    webServer: {
      command: isV7
        ? "npx tsx tests/e2e/playground-server-v7.ts"
        : "npx tsx tests/e2e/playground-server.ts",
      url: `http://localhost:${port}`,
      reuseExistingServer: !process.env.CI,
      timeout: 120000,
    },
    use: {
      screenshot: "only-on-failure",
      video: "retain-on-failure",
      trace: "retain-on-failure",
    },
  },
});

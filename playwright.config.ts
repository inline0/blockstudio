import { createWordPressPlaygroundConfig } from "./tests/wordpress-playground/playwright-wordpress.config";

export default createWordPressPlaygroundConfig({
  testDir: "./tests/unit",
  globalSetup: "./tests/wordpress-playground/global-setup.ts",
  port: 9400,
  testMatch: "**/*.test.ts",
  timeout: 60000,
  workers: 1,
  extraConfig: {
    webServer: {
      command: "npx tsx tests/playground-server.ts",
      url: "http://localhost:9400",
      reuseExistingServer: !process.env.CI,
      timeout: 120000,
    },
  },
});

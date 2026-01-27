import { createWordPressPlaygroundConfig } from "./tests/wordpress-playground/playwright-wordpress.config";

const port = parseInt(process.env.PLAYGROUND_PORT || "9400", 10);
const isV7 = port === 9401;

export default createWordPressPlaygroundConfig({
  testDir: "./tests/unit",
  globalSetup: "./tests/wordpress-playground/global-setup.ts",
  port,
  testMatch: "**/*.test.ts",
  timeout: 60000,
  workers: 1,
  extraConfig: {
    webServer: {
      command: isV7
        ? "npx tsx tests/playground-server-v7.ts"
        : "npx tsx tests/playground-server.ts",
      url: `http://localhost:${port}`,
      reuseExistingServer: !process.env.CI,
      timeout: 120000,
    },
  },
});

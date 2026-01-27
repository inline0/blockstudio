import {
  defineConfig,
  devices,
  PlaywrightTestConfig,
} from "@playwright/test";

export interface WordPressPlaygroundConfigOptions {
  testDir: string;
  globalSetup?: string;
  port?: number;
  baseURL?: string;
  workers?: number;
  retries?: number;
  timeout?: number;
  expectTimeout?: number;
  fullyParallel?: boolean;
  testMatch?: string | string[];
  extraConfig?: Partial<PlaywrightTestConfig>;
}

export function createWordPressPlaygroundConfig(
  options: WordPressPlaygroundConfigOptions
): ReturnType<typeof defineConfig> {
  const {
    testDir,
    globalSetup = "./tests/wordpress-playground/global-setup.ts",
    port = 9400,
    baseURL = `http://localhost:${port}`,
    workers = 1,
    retries = 0,
    timeout = 60000,
    expectTimeout = 3000,
    fullyParallel = false,
    testMatch = "**/*.test.ts",
    extraConfig = {},
  } = options;

  const defaultWebServer = {
    url: baseURL,
    reuseExistingServer: true,
    timeout: 30000,
    stdout: "pipe" as const,
    stderr: "pipe" as const,
  };

  const defaultUse = {
    screenshot: "on" as const,
    video: "on" as const,
    trace: "on" as const,
  };

  return defineConfig({
    testDir,
    globalSetup,
    fullyParallel,
    retries,
    workers,
    reporter: "list" as const,
    timeout,
    testMatch,
    expect: {
      timeout: expectTimeout,
    },
    use: {
      baseURL,
      ...defaultUse,
      ...(extraConfig.use || {}),
    },
    projects: [
      {
        name: "chromium",
        use: { ...devices["Desktop Chrome"], baseURL },
      },
    ],
    webServer: extraConfig.webServer
      ? {
          ...defaultWebServer,
          ...extraConfig.webServer,
        }
      : {
          command: "npm run playground",
          ...defaultWebServer,
        },
  });
}

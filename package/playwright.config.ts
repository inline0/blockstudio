import { PlaywrightTestConfig } from '@playwright/test';

// InstaWP API: gmykI9Z6Mhb8tOid4pZ2OJuTo2esRwhlcpP73wmC

const config: PlaywrightTestConfig = {
  testMatch: 'test/**/*.ts',
  retries: 5,
  workers: 1,
  expect: {
    timeout: 20000,
  },
  timeout: 60000,
  use: {
    // headless: false,
  },
};

export default config;

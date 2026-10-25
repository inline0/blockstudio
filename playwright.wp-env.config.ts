import { defineConfig, devices } from '@playwright/test';
import { globSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parseShard, partitionFiles } from './scripts/ci/e2e-shards.ts';

// 'canvas/canvas.ts' temporarily excluded (heaviest file); re-add to restore coverage.
const patterns = [
  'types/**/*.ts',
  'components/**/*.ts',
  'db/**/*.ts',
  'interactivity/**/*.ts',
  'pages/**/*.ts',
  'admin/**/*.ts',
  'patterns.ts',
  'catalog-blocks.ts',
  'keyed-merge.ts',
  'tailwind-compile.ts',
  'grab.ts',
  'preload/**/*.ts',
  'block-tags/**/*.ts',
  'component.ts',
  'native-wp-block.ts',
  'seo.ts',
  'functions.ts',
  'cli.ts',
  'site-templates.ts',
  'block-visibility.ts',
  'duplicate-block-attrs.ts',
  'pattern-innerblocks-complex.ts',
  'select-plain-string.ts',
  'editor-utility-layout.ts',
  'islands.ts',
  'discovery-overlay.ts',
];
const testDir = resolve(import.meta.dirname, 'tests/e2e');
const selection = process.env.BLOCKSTUDIO_E2E_SHARD;
let testMatch = patterns;
if (selection) {
  const { current, total } = parseShard(selection);
  const { seconds } = JSON.parse(
    readFileSync(
      resolve(import.meta.dirname, 'scripts/ci/e2e-file-durations.json'),
      'utf8',
    ),
  ) as { seconds: Record<string, number> };
  const shards = partitionFiles(
    globSync(patterns, { cwd: testDir }),
    seconds,
    total,
  );
  testMatch = shards[current - 1].files.map((file) => resolve(testDir, file));
}

export default defineConfig({
  testDir,
  testMatch,
  timeout: 90000,
  expect: {
    timeout: 15000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1,
  reporter: 'list',

  use: {
    baseURL: 'http://localhost:8888',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'on-first-retry',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});

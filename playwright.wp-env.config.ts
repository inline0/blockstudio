import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
	testDir: './tests/e2e',
	testMatch: ['types/**/*.ts', 'pages.ts', 'patterns.ts', 'catalog-blocks.ts', 'keyed-merge.ts'],
	timeout: 60000,
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

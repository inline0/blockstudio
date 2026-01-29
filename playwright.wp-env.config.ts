import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for E2E tests using wp-env (Docker).
 *
 * This provides a more stable environment than WordPress Playground:
 * - Native PHP execution (no WebAssembly)
 * - Full error logging support
 * - Standard page navigation and reload
 * - No iframe complexity
 */
export default defineConfig({
	testDir: './tests/e2e',
	testMatch: 'types/**/*.ts',
	timeout: 60000,
	expect: {
		timeout: 15000,
	},
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 1,
	workers: 1,
	reporter: [
		['list'],
		['html', { open: 'never' }],
	],

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

	/* No webServer config - wp-env must be started separately with `npm run wp-env:start` */
});

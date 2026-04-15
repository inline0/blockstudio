import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
	testDir: './tests/e2e',
	testMatch: ['types/**/*.ts', 'components/**/*.ts', 'db/**/*.ts', 'interactivity/**/*.ts', 'pages/**/*.ts', 'admin/**/*.ts', 'patterns.ts', 'catalog-blocks.ts', 'keyed-merge.ts', 'tailwind-compile.ts', 'grab.ts', 'canvas/canvas.ts', 'preload/**/*.ts', 'block-tags/**/*.ts', 'component.ts', 'native-wp-block.ts', 'seo.ts', 'functions.ts', 'cli.ts', 'block-visibility.ts', 'duplicate-block-attrs.ts', 'pattern-innerblocks-complex.ts', 'select-plain-string.ts'],
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

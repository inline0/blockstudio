import { test, expect, Page } from '@playwright/test';
import { checkStyle, login } from './utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	const context = await browser.newContext();
	page = await context.newPage();
	page.setViewportSize({ width: 1920, height: 1080 });

	await login(page);

	// Pre-warm: first compile can be slow, do it before tests start
	await page.goto('http://localhost:8888/test-tailwind-on-demand/', {
		timeout: 120000,
	});
	await page.waitForLoadState('networkidle');
});

test.afterAll(async () => {
	await page.close();
});

test.describe('Tailwind Compile', () => {
	// Utility compilation

	test.describe('Utilities', () => {
		test('page has inline tailwind style tag', async () => {
			await page.goto('http://localhost:8888/test-tailwind-on-demand/');
			await page.waitForLoadState('networkidle');

			const styleTag = page.locator('style#blockstudio-tailwind');
			await expect(styleTag).toBeAttached();
		});

		test('bg-red-500 compiles correctly', async () => {
			await checkStyle(
				page,
				'#tw-test-bg',
				'backgroundColor',
				'oklch(0.637 0.237 25.331)',
			);
		});

		test('text-blue-600 compiles correctly', async () => {
			await checkStyle(
				page,
				'#tw-test-text',
				'color',
				'oklch(0.546 0.245 262.881)',
			);
		});

		test('flex layout compiles correctly', async () => {
			await checkStyle(page, '#tw-test-flex', 'display', 'flex');
		});

		test('@layer utilities classes compile from config', async () => {
			await checkStyle(
				page,
				'#tw-test-custom',
				'backgroundColor',
				'oklch(0.637 0.237 25.331)',
			);
		});
	});

	// Config

	test.describe('Config', () => {
		test('CSS config @theme color applies correctly', async () => {
			await checkStyle(
				page,
				'#tw-test-config',
				'backgroundColor',
				'rgb(255, 192, 203)',
			);
		});
	});

	// Style tag content

	test.describe('Style tag', () => {
		test('style tag contains compiled CSS', async () => {
			const content = await page.evaluate(() => {
				const style = document.querySelector(
					'style#blockstudio-tailwind',
				);
				return style?.textContent || '';
			});
			expect(content.length).toBeGreaterThan(100);
		});

		test('style tag includes preflight (box-sizing reset)', async () => {
			const content = await page.evaluate(() => {
				const style = document.querySelector(
					'style#blockstudio-tailwind',
				);
				return style?.textContent || '';
			});
			expect(content).toContain('box-sizing');
		});
	});

	// No old-style enqueues

	test.describe('No old enqueues', () => {
		test('no blockstudio-tailwind link tags', async () => {
			const linkTags = page.locator(
				'link[id*="blockstudio-tailwind"]',
			);
			await expect(linkTags).toHaveCount(0);
		});

		test('no blockstudio-tailwind-preflight link tag', async () => {
			const preflightLink = page.locator(
				'link#blockstudio-tailwind-preflight-css',
			);
			await expect(preflightLink).toHaveCount(0);
		});
	});

	// Cache

	test.describe('Cache', () => {
		test('second visit still has style tag (cache hit)', async () => {
			await page.goto('http://localhost:8888/test-tailwind-on-demand/');
			await page.waitForLoadState('networkidle');

			const styleTag = page.locator('style#blockstudio-tailwind');
			await expect(styleTag).toBeAttached();

			await checkStyle(
				page,
				'#tw-test-bg',
				'backgroundColor',
				'oklch(0.637 0.237 25.331)',
			);
		});
	});

	// Block pages

	test.describe('Blocks', () => {
		test('tailwind style tag injected on block page', async () => {
			await page.goto('http://localhost:8888/native-single/');
			await page.waitForLoadState('networkidle');

			const styleTag = page.locator('style#blockstudio-tailwind');
			await expect(styleTag).toBeAttached();
		});

		test('no blockstudio-tailwind link tags on block page', async () => {
			const linkTags = page.locator(
				'link[id*="blockstudio-tailwind"]',
			);
			await expect(linkTags).toHaveCount(0);
		});
	});
});

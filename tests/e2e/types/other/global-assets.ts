import { test, expect, Page } from '@playwright/test';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	const context = await browser.newContext();
	page = await context.newPage();
});

test.afterAll(async () => {
	await page.close();
});

test.describe('global assets in code-snippet directories', () => {
	test('navigate to frontend page', async () => {
		await page.goto('http://localhost:8888/native-single/');
		await page.waitForLoadState('domcontentloaded');
	});

	test('global CSS loads exactly once', async () => {
		const count = await page.locator('link[href*="functions/global/"]').count();
		expect(count).toBe(1);
	});

	test('global JS loads exactly once', async () => {
		const count = await page.locator('script[src*="functions/global/"]').count();
		expect(count).toBe(1);
	});

	test('inline CSS is rendered as style tag, not link tag', async () => {
		const styleTag = page.locator('style[id$="global-style-inline-css"]');
		const styleCount = await styleTag.count();
		let found = false;

		for (let i = 0; i < styleCount; i++) {
			const text = await styleTag.nth(i).textContent();
			if (text && text.includes('.bs-global-inline-test')) {
				found = true;
				break;
			}
		}
		expect(found).toBe(true);

		const linkTag = page.locator('link[id$="global-style-inline-css"]');
		const linkCount = await linkTag.count();
		let linkFound = false;

		for (let i = 0; i < linkCount; i++) {
			const href = await linkTag.nth(i).getAttribute('href');
			if (href && href.includes('functions/global/')) {
				linkFound = true;
				break;
			}
		}
		expect(linkFound).toBe(false);
	});

	test('inline CSS content is present in style tag', async () => {
		const html = await page.content();
		const match = html.match(/<style[^>]*global-style-inline-css[^>]*>([\s\S]*?)<\/style>/);
		expect(match).not.toBeNull();
		expect(match![1]).toContain('.bs-global-inline-test');
	});
});

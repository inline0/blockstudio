import { Page, test } from '@playwright/test';
import {
	addBlock,
	count,
	getSharedPage,
	resetPageState,
} from '../../../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	page = await getSharedPage(browser);
	await resetPageState(page);
});

test.describe('component-mediaplaceholder-useblockprops-twig', () => {
	test('add block', async () => {
		await addBlock(page, 'component-mediaplaceholder-useblockprops-twig');
		await count(page, '.is-root-container > .wp-block', 1);
	});

	test('check placeholder content', async () => {
		await count(page, 'text=Test title', 1);
		await count(page, 'text=Test instructions', 2);
	});

	test('add image from media library', async () => {
		await page.click('.blockstudio-test__block .components-button.is-secondary');
		await page.waitForSelector('.media-frame', { timeout: 10000 });
		await page.click('[data-id="1604"]');
		await page.click('.media-frame-toolbar button.media-button-select:visible');
		await count(page, '.blockstudio-test__block img', 1);
	});
});

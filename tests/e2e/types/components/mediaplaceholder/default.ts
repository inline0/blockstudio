import { Frame, Page, test } from '@playwright/test';
import {
	addBlock,
	count,
	getEditorCanvas,
	getSharedPage,
	resetPageState,
} from '../../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	page = await getSharedPage(browser);
	await resetPageState(page);
	canvas = await getEditorCanvas(page);
});

test.describe('component-mediaplaceholder-default', () => {
	test('add block', async () => {
		await addBlock(page, 'component-mediaplaceholder-default');
		await count(canvas, '.is-root-container > .wp-block', 1);
	});

	test('check placeholder content', async () => {
		await count(canvas, 'text=Test title', 1);
		await count(canvas, 'text=Test instructions', 1);
	});

	test('add image from media library', async () => {
		await canvas.click('.blockstudio-test__block .components-button.is-secondary');
		await page.waitForSelector('.media-frame', { timeout: 10000 });
		await page.click('[data-id="1604"]');
		await page.click('.media-frame-toolbar button.media-button-select:visible');
		await count(canvas, '.blockstudio-test__block img', 1);
	});
});

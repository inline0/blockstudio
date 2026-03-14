import { Frame, Page, test } from '@playwright/test';
import {
	addBlock,
	checkForLeftoverAttributes,
	count,
	getEditorCanvas,
	getSharedPage,
	resetPageState,
	save,
} from '../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	page = await getSharedPage(browser);
	await resetPageState(page);
	canvas = await getEditorCanvas(page);
});

test.describe('repeater-richtext', () => {
	test('add block', async () => {
		await addBlock(page, 'type-repeater-richtext');
		canvas = await getEditorCanvas(page);
		await count(canvas, '.is-root-container > .wp-block', 1);
	});

	test('repeater item renders with richtext placeholders', async () => {
		canvas = await getEditorCanvas(page);
		await count(canvas, '.repeater-item', 1);
		await count(canvas, '.repeater-heading', 1);
		await count(canvas, '.repeater-content', 1);
	});

	test('mediaplaceholder renders in repeater', async () => {
		canvas = await getEditorCanvas(page);
		await count(canvas, '.repeater-item .components-placeholder', 1);
	});

	test('add image via mediaplaceholder', async () => {
		canvas = await getEditorCanvas(page);
		await canvas.click('.repeater-item .components-button.is-secondary');
		await page.waitForSelector('.media-frame', { timeout: 10000 });
		await page.click('[data-id="1604"]');
		await page.click('.media-frame-toolbar button.media-button-select:visible');
		canvas = await getEditorCanvas(page);
		await count(canvas, '.repeater-item .components-placeholder', 0);
	});

	test('type into heading richtext', async () => {
		canvas = await getEditorCanvas(page);
		const heading = canvas.locator('.repeater-heading').first();
		await heading.click();
		await page.keyboard.type('Hello Repeater');
		await count(canvas, 'text=Hello Repeater', 1);
	});

	test('type into content richtext', async () => {
		canvas = await getEditorCanvas(page);
		const content = canvas.locator('.repeater-content').first();
		await content.click();
		await page.keyboard.type('Body text here');
		await count(canvas, 'text=Body text here', 1);
	});

	test('save and check frontend', async () => {
		await save(page);
		await page.goto('http://localhost:8888/native-single/');
		await checkForLeftoverAttributes(page);
		await count(page, '.repeater-item', 1);
		await count(page, 'h2.repeater-heading', 1);
		await count(page, 'p.repeater-content', 1);
		await count(page, 'text=Hello Repeater', 1);
		await count(page, 'text=Body text here', 1);
	});

	test('richtext persists after reload', async () => {
		await page.goto('http://localhost:8888/wp-admin/post.php?post=1483&action=edit');
		canvas = await getEditorCanvas(page);
		await canvas.waitForSelector('.repeater-heading', { timeout: 30000 });
		await count(canvas, 'text=Hello Repeater', 1);
		await count(canvas, 'text=Body text here', 1);
	});
});

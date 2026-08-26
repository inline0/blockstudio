import { expect, Frame, Page, test } from '@playwright/test';
import {
	addBlock,
	checkForLeftoverAttributes,
	count,
	getEditorCanvas,
	getSharedPage,
	openSidebar,
	resetPageState,
	save,
} from '../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

const repeaterHeadings = async () =>
	page.evaluate(() => {
		const block = (window as any).wp.data
			.select('core/block-editor')
			.getBlocks()
			.find(
				(item: { name: string }) =>
					item.name === 'blockstudio/type-repeater-richtext',
			);

		return (
			block?.attributes?.blockstudio?.attributes?.items?.map(
				(item: { heading?: string }) => item.heading,
			) ?? []
		);
	});

const savedRepeaterHeadings = async () =>
	page.evaluate(async () => {
		const post = await (window as any).wp.apiFetch({
			path: '/wp/v2/posts/1483?context=edit',
		});
		const block = (window as any).wp.blocks
			.parse(post.content.raw)
			.find(
				(item: { name: string }) =>
					item.name === 'blockstudio/type-repeater-richtext',
			);

		return (
			block?.attributes?.blockstudio?.attributes?.items?.map(
				(item: { heading?: string }) => item.heading,
			) ?? []
		);
	});

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

	test('delete repeater item with richtext before save', async () => {
		await openSidebar(page);
		await page.getByRole('button', { name: 'Add item' }).click();

		canvas = await getEditorCanvas(page);
		await count(canvas, '.repeater-item', 2);

		const deletedContent = canvas.locator('.repeater-content').nth(1);
		await deletedContent.click();
		await page.keyboard.type('Cannot delete this');
		await count(canvas, 'text=Cannot delete this', 1);

		await page
			.locator(
				'[data-rfd-draggable-id="items[1]"] .blockstudio-repeater__remove',
			)
			.click();

		await count(page, '[data-rfd-draggable-id^="items["]', 1);
		canvas = await getEditorCanvas(page);
		await count(canvas, '.repeater-item', 1);
		await count(canvas, 'text=Cannot delete this', 0);
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
		await count(page, 'text=Cannot delete this', 0);
	});

	test('richtext persists after reload', async () => {
		await page.goto(
			'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
		);
		canvas = await getEditorCanvas(page);
		await canvas.waitForSelector('.repeater-heading', { timeout: 30000 });
		await count(canvas, 'text=Hello Repeater', 1);
		await count(canvas, 'text=Body text here', 1);
		await count(canvas, 'text=Cannot delete this', 0);
	});

	test('reordering saved rows with richtext persists', async () => {
		await canvas.locator('.repeater-heading').first().click();
		await openSidebar(page);

		await page.getByRole('button', { name: 'Add item' }).click();
		await expect.poll(async () => (await repeaterHeadings()).length).toBe(2);
		await page.getByRole('button', { name: 'Add item' }).click();
		await expect.poll(async () => (await repeaterHeadings()).length).toBe(3);

		canvas = await getEditorCanvas(page);
		await count(canvas, '.repeater-heading', 3);
		for (const [index, value] of ['A', 'B', 'C'].entries()) {
			const heading = canvas.locator('.repeater-heading').nth(index);
			await heading.click();
			await page.keyboard.press('ControlOrMeta+A');
			await page.keyboard.type(value);
			await expect(heading).toHaveText(value);
		}

		await save(page);
		await page.goto(
			'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
		);
		canvas = await getEditorCanvas(page);
		await canvas.waitForSelector('.repeater-heading', { timeout: 30000 });
		await expect.poll(repeaterHeadings).toEqual(['A', 'B', 'C']);

		await canvas.locator('.repeater-heading').first().click();
		await openSidebar(page);
		await page.focus('[data-rfd-draggable-id="items[2]"]');
		await page.keyboard.press('ArrowUp');
		await page.keyboard.press('ArrowUp');
		await expect.poll(repeaterHeadings).toEqual(['C', 'A', 'B']);

		await save(page);
		await page.goto(
			'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
		);
		canvas = await getEditorCanvas(page);
		await canvas.waitForSelector('.repeater-heading', { timeout: 30000 });

		await expect.poll(repeaterHeadings).toEqual(['C', 'A', 'B']);
		await expect.poll(savedRepeaterHeadings).toEqual(['C', 'A', 'B']);
	});
});

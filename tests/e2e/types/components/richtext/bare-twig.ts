import { Frame, Page, test } from '@playwright/test';
import {
	addBlock,
	checkForLeftoverAttributes,
	count,
	getEditorCanvas,
	getSharedPage,
	openSidebar,
	resetPageState,
	save,
} from '../../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	page = await getSharedPage(browser);
	await resetPageState(page);
	canvas = await getEditorCanvas(page);
});

test.describe('component-richtext-bare-twig', () => {
	test('add block', async () => {
		await addBlock(page, 'component-richtext-bare-twig');
		await count(canvas, '.is-root-container > .wp-block', 1);
	});

	test.describe('editor', () => {
		test('placeholder', async () => {
			await count(canvas, '[aria-label="Enter text here"]', 2);
		});

		test('classes', async () => {
			await count(canvas, '.blockstudio-test__block.test.test2.test3', 2);
		});

		test('content', async () => {
			await canvas.locator('[aria-label="Enter text here"]').nth(0).click();
			await page.keyboard.type('Test text');
			await canvas.locator('[aria-label="Enter text here"]').nth(1).click();
			await page.keyboard.type('Test text');
			await count(canvas, 'text=Test text', 2);
		});

		test('color', async () => {
			await openSidebar(page);
			await page.click('[aria-label="blue"]');
			await save(page);
			await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
		});
	});

	test('check content', async () => {
		await page.goto('http://localhost:8888/native-single/');
		await checkForLeftoverAttributes(page);
		await count(page, 'text=Test text', 2);
	});

	test('check content in editor', async () => {
		await page.goto('http://localhost:8888/wp-admin/post.php?post=1483&action=edit');
		canvas = await getEditorCanvas(page);
		await canvas.click('[data-type="blockstudio/component-richtext-bare-twig"]');
		await count(canvas, 'text=Test text', 2);
		await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
	});
});

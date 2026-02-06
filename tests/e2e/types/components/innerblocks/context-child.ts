import { Frame, Page, test } from '@playwright/test';
import {
	addBlock,
	count,
	getEditorCanvas,
	getSharedPage,
	openSidebar,
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

test.describe('component-innerblocks-context-child', () => {
	test('add block', async () => {
		await addBlock(page, 'component-innerblocks-context-child');
		await count(canvas, '.is-root-container > .wp-block', 1);
	});

	test('classes in editor', async () => {
		await count(canvas, '.blockstudio-test__block.test.test2.test3', 1);
	});

	test('context working', async () => {
		await openSidebar(page);
		await canvas.click('.wp-block-post-title');
		await page.keyboard.press('ArrowDown');
		await canvas.waitForSelector('#blockstudio-component-innerblocks-context-child');
		await openSidebar(page);
		await page.click('.blockstudio-fields__field--text input');
		await page.keyboard.type('$CONTEXT', { delay: 100 });
		await count(canvas, 'text=$CONTEXT', 1);
		await page.click('.editor-post-publish-button');
		await count(page, '.components-snackbar', 1);
	});

	test('check content', async () => {
		await page.goto('http://localhost:8888/native-single/');
		await count(page, 'text=$CONTEXT', 1);
		await count(page, '.blockstudio-test__block.test.test2.test3', 1);
		await count(page, '[data-attr]', 1);
	});
});

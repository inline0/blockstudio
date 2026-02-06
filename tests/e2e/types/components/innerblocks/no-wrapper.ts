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

test.describe('component-innerblocks-no-wrapper', () => {
	test('add block', async () => {
		await addBlock(page, 'component-innerblocks-no-wrapper');
		await count(canvas, '.is-root-container > .wp-block', 1);
	});

	test('classes in editor', async () => {
		await count(canvas, '.blockstudio-test__block.test.test2.test3', 1);
	});

	test('add content', async () => {
		await canvas.click('text=Type / to choose a block');
		await page.keyboard.type('TEST$');
		await page.keyboard.press('Enter');
		await page.keyboard.type('TEST$');
		await page.keyboard.press('Enter');
		await page.keyboard.press('/');
		await openSidebar(page);
		await save(page);
	});

	test('check content', async () => {
		await page.goto('http://localhost:8888/native-single/');
		await checkForLeftoverAttributes(page);
		await count(page, 'text=TEST$', 2);
		await count(page, '.blockstudio-test__block.test.test2.test3', 1);
	});
});

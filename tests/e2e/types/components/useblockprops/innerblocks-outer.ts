import { Frame, Page, test } from '@playwright/test';
import {
	addBlock,
	checkForLeftoverAttributes,
	count,
	getEditorCanvas,
	getSharedPage,
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

test.describe('component-useblockprops-innerblocks-outer', () => {
	test('add block', async () => {
		await addBlock(page, 'component-useblockprops-innerblocks-outer');
		await count(canvas, '.is-root-container > .wp-block', 1);
	});

	test('check wrapper', async () => {
		await page.click('.editor-document-tools__document-overview-toggle');
		await page.click('.block-editor-list-view-block__contents-container a');
		await count(canvas, '.is-root-container > .wp-block', 1);
		await count(canvas, '.is-root-container > .wp-block > [data-is-drop-zone]', 1);
		await count(canvas, '.blockstudio-test__block.test.test2.test3', 1);
		await count(canvas, '.blockstudio-test__block.test.test2.test3.test4', 0);
	});

	test('check content', async () => {
		await save(page);
		await page.goto('http://localhost:8888/native-single/');
		await checkForLeftoverAttributes(page);
		await count(page, '.blockstudio-test__block.test.test2.test3', 1);
		await count(page, '.blockstudio-test__block.test.test2.test3.test4', 0);
		await count(page, '.wp-block-blockstudio-component-useblockprops-innerblocks-outer', 1);
	});
});

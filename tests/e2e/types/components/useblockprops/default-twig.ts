import { Page, test } from '@playwright/test';
import {
	addBlock,
	checkForLeftoverAttributes,
	count,
	getSharedPage,
	resetPageState,
	save,
} from '../../../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	page = await getSharedPage(browser);
	await resetPageState(page);
});

test.describe('component-useblockprops-default-twig', () => {
	test('add block', async () => {
		await addBlock(page, 'component-useblockprops-default-twig');
		await count(page, '.is-root-container > .wp-block', 1);
	});

	test('check wrapper', async () => {
		await page.click('.editor-document-tools__document-overview-toggle');
		await page.click('.block-editor-list-view-block__contents-container a');
		await count(page, '.is-root-container > .wp-block > *', 2);
		await count(page, '.blockstudio-test__block.test.test2.test3', 1);
		await count(page, '.blockstudio-test__block.test.test2.test3.test4', 0);
	});

	test('check content', async () => {
		await save(page);
		await page.goto('http://localhost:8888/native-single/');
		await checkForLeftoverAttributes(page);
		await count(page, '.blockstudio-test__block.test.test2.test3', 1);
		await count(page, '.blockstudio-test__block.test.test2.test3.test4', 0);
		await count(page, '.wp-block-blockstudio-component-useblockprops-default-twig', 1);
	});
});

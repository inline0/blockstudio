import { Page, test } from '@playwright/test';
import {
	addBlock,
	checkForLeftoverAttributes,
	count,
	getSharedPage,
	openSidebar,
	resetPageState,
	save,
} from '../../../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
	page = await getSharedPage(browser);
	await resetPageState(page);
});

test.describe('component-innerblocks-bare-spaceless-twig', () => {
	test('add block', async () => {
		await addBlock(page, 'component-innerblocks-bare-spaceless-twig');
		await count(page, '.is-root-container > .wp-block', 1);
	});

	test('add content', async () => {
		await page.click('text=Type / to choose a block');
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
		await count(page, '.blockstudio-test__block.test.test2.test3', 0);
	});
});

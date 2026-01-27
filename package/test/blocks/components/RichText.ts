import { Page, test } from '@playwright/test';
import {
  addBlock,
  checkForLeftoverAttributes,
  count,
  openSidebar,
  pBlocks,
  removeBlocks,
  save,
} from '../../../playwright-utils';

let page: Page;

test.beforeAll(async ({ browser }) => {
  page = await pBlocks(browser);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('RichText', () => {
  [
    'bare',
    'bare-twig',
    'default',
    'default-twig',
    'spaceless',
    'spaceless-twig',
  ].forEach((item) => {
    test.describe(`${item}`, () => {
      test.describe.configure({ mode: 'serial' });

      test('add block', async () => {
        await page.goto(
          'https://fabrikat.local/blockstudio/wp-admin/edit.php?post_type=post'
        );
        await page.locator('.row-title').first().click();
        await removeBlocks(page);
        await addBlock(page, `component-richtext-${item}`);
      });

      test.describe('editor', () => {
        test('placeholder', async () => {
          await count(page, '[aria-label="Enter text here"]', 2);
        });
        test('classes', async () => {
          await count(page, '.blockstudio-test__block.test.test2.test3', 2);
        });
        test('content', async () => {
          await page.locator(`[aria-label="Enter text here"]`).nth(0).click();
          await page.keyboard.type('Test text');
          await page.locator(`[aria-label="Enter text here"]`).nth(1).click();
          await page.keyboard.type('Test text');
          await count(page, 'text=Test text', 2);
        });
        if (item.includes('bare')) {
          test('color', async () => {
            await openSidebar(page);
            await page.click('[aria-label="blue"]');
            await save(page);
            await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
          });
        }
      });

      test('check content', async () => {
        await save(page);
        await page.goto(`https://fabrikat.local/blockstudio/native-single`);
        await checkForLeftoverAttributes(page);
        await count(page, 'text=Test text', 2);
      });

      test('check content in editor', async () => {
        await page.goto(
          'https://fabrikat.local/blockstudio/wp-admin/edit.php?post_type=post'
        );
        await page.locator('.row-title').first().click();
        await page.click(
          `[data-type="blockstudio/component-richtext-${item}"]`
        );
        await count(page, 'text=Test text', 2);
        if (item.includes('bare')) {
          await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
        }
      });
    });
  });
});

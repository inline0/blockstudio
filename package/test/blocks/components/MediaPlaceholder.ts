import { Page, test } from '@playwright/test';
import {
  addBlock,
  count,
  pBlocks,
  removeBlocks,
} from '../../../playwright-utils';

test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
  page = await pBlocks(browser);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('MediaPlaceholder', () => {
  ['default', 'default-twig', 'useblockprops'].forEach((item) => {
    test.describe(`${item}`, () => {
      test.describe.configure({ mode: 'serial' });

      test('add block', async () => {
        await page.goto(
          'https://fabrikat.local/blockstudio/wp-admin/edit.php?post_type=post'
        );
        await page.locator('.row-title').first().click();
        await removeBlocks(page);
        await addBlock(page, `component-mediaplaceholder-${item}`);
      });

      test('add image', async () => {
        await count(page, 'text=Test title', 1);
        await count(page, 'text=Test instructions', 2);
        await page.click(
          '.blockstudio-test__block .components-button.is-secondary'
        );
        await page.click('[data-id="80"]');
        await page.click('.media-frame-toolbar button:visible');
        await count(page, '.blockstudio-test__block img', 1);
      });
    });
  });
});

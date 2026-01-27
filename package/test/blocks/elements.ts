import { Page, test } from '@playwright/test';
import { count, delay, openSidebar, pBlocks } from '../../playwright-utils';

test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
  page = await pBlocks(browser);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('elements', () => {
  ['Code'].forEach((item) => {
    test.describe(`${item}`, () => {
      test('open block inserter', async () => {
        await page.click('.editor-document-tools__inserter-toggle');
        await count(page, '.block-editor-inserter__block-list', 1);
      });

      test('add block', async () => {
        if (item === 'Code') {
          await page.type('[placeholder="Search"]', 'code block');
        }
        await delay(2000);
        await page.click(`text=${item}`);
        await delay(5000);
        await count(page, '.is-root-container > .wp-block', 1);
        await page.click(`.wp-block:not(.wp-block-post-title)`);
        await openSidebar(page);
      });

      if (item === 'Code') {
        test('check attribute api', async () => {
          await page.fill(
            '.blockstudio-fields__field--textarea textarea',
            '<div>test</div>'
          );
          await count(page, 'text=Line Numbers', 0);
          await page.click('.editor-post-publish-button');
          await count(page, '.components-snackbar', 1);
        });

        test('check attribute render api', async () => {
          await page.goto(`https://fabrikat.local/blockstudio/native-single`);
          await count(page, 'text=Copy', 1);
          await page.goto(
            `https://fabrikat.local/blockstudio/wp-admin/post.php?post=1483&action=edit`
          );
          await page
            .locator('.wp-block-post-title')
            .waitFor({ state: 'visible' });
        });
      }
    });
  });
});

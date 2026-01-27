import { Page, test } from '@playwright/test';
import { checkStyle, pEditor } from '../../playwright-utils';

test.describe('admin assets', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    page = await pEditor(browser);
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('check admin scripts', async () => {
    await checkStyle(
      page,
      '[href*="blockstudio.dev"].is-tertiary',
      'color',
      'rgb(0, 0, 0)'
    );
  });

  test('check block-editor styles', async () => {
    await page.goto(
      'https://fabrikat.local/blockstudio/wp-admin/post.php?post=1483&action=edit'
    );
    await checkStyle(
      page,
      '.editor-post-publish-button',
      'background',
      'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
    );
  });
});

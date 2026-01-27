import { expect, Page, test } from '@playwright/test';
import { count, pBlocks } from '../../playwright-utils';

test.describe.configure({ mode: 'serial' });

test.describe('context', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    page = await pBlocks(
      browser,
      'https://fabrikat.local/blockstudio/wp-admin/widgets.php',
      '.edit-widgets-main-block-list',
      false
    );

    const modal = await page.$('text=Welcome to block Widgets');
    if (modal) {
      await page.click(
        '.components-modal__screen-overlay .components-button.has-icon'
      );
    }
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('widgets', async () => {
    await page.click('.wp-block-widget-area button');
    await page.click('.edit-widgets-header-toolbar__inserter-toggle');
    await page.click('[placeholder="Search"]');
    await page.fill('[placeholder="Search"]', 'native block');
    await page.click('.editor-block-list-item-blockstudio-native');
    await count(page, '.blockstudio-test__block', 1);
  });

  test('customizer', async () => {
    await page.goto(
      'https://fabrikat.local/blockstudio/wp-admin/customize.php'
    );
    await expect(
      page
        .frameLocator('iframe')
        .locator('#blockstudio-blockstudio-element-gallery-script-inline-js')
    ).toHaveCount(1);
  });
});

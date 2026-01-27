import { Page, test } from '@playwright/test';
import {
  checkStyle,
  count,
  delay,
  openSidebar,
  pBlocks,
} from '../../playwright-utils';

test.describe.configure({ mode: 'serial' });

test.describe('overrides', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    page = await pBlocks(browser);
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('open block inserter', async () => {
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);
  });

  test('add gallery', async () => {
    await page.fill('[placeholder="Search"]', 'gallery override');
    await page.click('.editor-block-list-item-blockstudio-type-override');
    await page.click('[data-type="blockstudio-type/override"]');
    await count(page, '.blockstudio-element__placeholder', 1);
  });

  test('add images', async () => {
    await openSidebar(page);
    await page.locator(`text=Open Media Library`).click();
    await page.locator('#menu-item-browse:visible').click();
    await page.keyboard.down('Meta');
    await page.click('li[data-id="1604"]:visible');
    await page.click('li[data-id="1605"]:visible');
    await page.keyboard.up('Meta');
    await delay(1000);
    await page.click('.media-frame-toolbar button:visible');
    await count(page, '.blockstudio-element-gallery__content', 2);
    await count(page, '[data-test]', 2);
    await checkStyle(
      page,
      '.blockstudio-element-gallery',
      'gridAutoRows',
      '30px'
    );
    await checkStyle(page, '.blockstudio-element-gallery', 'margin', '12px');
  });
});

import { Page, test } from '@playwright/test';
import { count, pEditor } from '../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('instance', () => {
  test.beforeAll(async () => {
    await page.goto(
      'https://fabrikat.local/streamline/wp-admin/admin.php?page=blockstudio'
    );
    await page.locator('[aria-controls="tab-panel-0-editor-view"]').click();
    await page
      .locator('text=No blocks found, click here to get started')
      .waitFor({ state: 'visible' });
  });

  test('open modal', async () => {
    await page
      .locator('text=No blocks found, click here to get started')
      .click();
    await count(page, '.components-modal__screen-overlay', 1);
  });

  test('create', async () => {
    await page.locator(`text=Edit plugin details`).click();
    await page
      .locator('.components-text-control__input')
      .first()
      .fill('blockstudio');
    await count(page, '.components-button.is-primary[disabled]', 1);
    await count(page, 'text=Plugin folder already exists', 1);
    await page
      .locator('.components-text-control__input')
      .first()
      .fill('blockstudio-blocks');
    await count(page, '.components-button.is-primary[disabled]', 0);
    await count(page, 'text=Plugin folder already exists', 0);
    await page.locator(`text=Instance`).click();
    await page.locator('text=Create plugin').click();
    await count(page, '.components-modal__screen-overlay', 0);
    await count(page, 'text=plugins/blockstudio-blocks/blocks', 1);
  });

  test('delete instance', async () => {
    await page.goto(`https://fabrikat.local/wp-admin/network/plugins.php`);
    await page.click('#delete-blockstudio-blocks');
    await count(page, 'text=Blockstudio Blocks was successfully deleted.', 1);
  });
});

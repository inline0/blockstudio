import { test, expect, Page } from '@playwright/test';
import { login, count, delay } from '../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('Native WP Block', () => {
  test('appears in editor inserter', async () => {
    await page.goto(
      'http://localhost:8888/wp-admin/post-new.php?post_type=page',
      { waitUntil: 'domcontentloaded' }
    );

    await page.waitForSelector('iframe[name="editor-canvas"]', {
      timeout: 30000,
    });
    const frame = page.frame('editor-canvas');
    await frame!.waitForLoadState('domcontentloaded');

    const modalOverlay = await page.$('.components-modal__screen-overlay');
    if (modalOverlay) {
      await page.click(
        '.components-modal__header .components-button.has-icon'
      );
      await page.waitForSelector('.components-modal__screen-overlay', {
        state: 'hidden',
        timeout: 5000,
      });
    }

    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    await page.fill('[placeholder="Search"]', 'Native WP Block Test');
    await delay(1000);

    const result = page.locator(
      '.block-editor-block-types-list__list-item:has-text("Native WP Block Test")'
    );
    await expect(result.first()).toBeVisible();
  });
});

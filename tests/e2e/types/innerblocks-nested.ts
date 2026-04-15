import { test, expect, Page } from '@playwright/test';
import {
  addBlock,
  getEditorCanvas,
  login,
  openBlockInserter,
  delay,
} from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';

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

test.describe('InnerBlocks nested template (#29)', () => {
  test('nested children render inside group', async () => {
    await page.goto(`${BASE}/wp-admin/post-new.php`);
    const closeBtn = page.locator('.components-modal__screen-overlay button[aria-label="Close"]');
    if (await closeBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await closeBtn.click();
    }
    const canvas = await getEditorCanvas(page);

    await openBlockInserter(page);
    await addBlock(page, 'type-innerblocks-nested');
    await delay(2000);

    await expect(canvas.locator('[data-type="core/heading"]')).toBeVisible({ timeout: 10000 });

    const group = canvas.locator('[data-type="core/group"]');
    await expect(group).toBeVisible({ timeout: 10000 });

    const nestedParagraph = group.locator('[data-type="core/paragraph"]');
    await expect(nestedParagraph).toBeVisible({ timeout: 10000 });
  });
});

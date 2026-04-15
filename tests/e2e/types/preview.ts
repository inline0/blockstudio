import { test, expect, Page } from '@playwright/test';
import {
  addBlock,
  getEditorCanvas,
  login,
  openBlockInserter,
  save,
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

test.describe('isPreview render cache', () => {
  test('place block and save', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=1483&action=edit`);
    const closeBtn = page.locator('.components-modal__screen-overlay button[aria-label="Close"]');
    if (await closeBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await closeBtn.click();
    }
    const canvas = await getEditorCanvas(page);
    await openBlockInserter(page);
    await addBlock(page, 'type-preview');
    await canvas.waitForSelector('[data-preview="false"]', { timeout: 15000 });
    await expect(canvas.locator('[data-preview="false"]')).toBeVisible();
    await save(page);
  });

  test('reload and verify editor renders with isPreview=false', async () => {
    await page.reload();
    const canvas = await getEditorCanvas(page);
    await canvas.waitForSelector('[data-preview]', { timeout: 15000 });
    await expect(canvas.locator('[data-preview="false"]')).toBeVisible();
  });

  test('inserter preview renders with isPreview=true', async () => {
    await openBlockInserter(page);
    await page.fill('[placeholder="Search"]', 'Preview Test');
    await delay(1000);

    const inserterItem = page.locator(
      '.block-editor-block-types-list__list-item',
      { hasText: 'Preview Test' }
    );
    await inserterItem.hover();
    await delay(3000);

    let found = false;
    for (const frame of page.frames()) {
      const el = frame.locator('[data-preview="true"]');
      if (await el.isVisible({ timeout: 1000 }).catch(() => false)) {
        found = true;
        break;
      }
    }

    expect(found).toBe(true);
  });
});

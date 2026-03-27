import { test, expect, Page, Frame } from '@playwright/test';
import { login, getEditorCanvas, resetPageState } from './utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await resetPageState(page);
  canvas = await getEditorCanvas(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('Pattern with complex InnerBlocks (issue #28)', () => {
  test('inner blocks render immediately on pattern insert', async () => {
    await page.click('button[aria-label="Block Inserter"]');
    await page.click('role=tab[name="Patterns"]');
    await page.fill('input[placeholder="Search"]', 'InnerBlocks Complex');
    await page.waitForTimeout(1000);

    const pattern = page.locator('[class*="block-editor-block-patterns-list"] [role="option"]').first();
    await expect(pattern).toBeVisible({ timeout: 10000 });
    await pattern.click();
    await page.waitForTimeout(3000);

    const heading = canvas.locator('text=Get Involved');
    await expect(heading).toBeVisible({ timeout: 15000 });

    const paragraph = canvas.locator('text=A description.');
    await expect(paragraph).toBeVisible({ timeout: 5000 });
  });
});

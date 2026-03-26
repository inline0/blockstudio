import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';

const BASE = 'http://localhost:8888';
const POST_ID = 3700;

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

test.describe('Block visibility (issue #26)', () => {
  test('block that starts hidden renders after showing via List View', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`, {
      waitUntil: 'domcontentloaded',
    });

    const canvas = await getEditorCanvas(page);
    await canvas.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });

    const modal = await page.$('text=Welcome to the block editor');
    if (modal) {
      await page.click('.components-modal__screen-overlay .components-button.has-icon');
    }

    await page.waitForTimeout(2000);

    await page.locator('button[aria-label="Document Overview"]').click();
    await page.waitForTimeout(500);

    const listBlock = page.locator('.block-editor-list-view-block-select-button').first();
    await listBlock.click();
    await page.waitForTimeout(500);

    const hiddenToggle = page.locator(
      '.block-editor-block-toolbar button[aria-label="Hidden"]',
    );
    const hasToggle = await hiddenToggle.isVisible({ timeout: 3000 }).catch(() => false);

    if (hasToggle) {
      await hiddenToggle.click();
    } else {
      await page.keyboard.press('ControlOrMeta+Shift+h');
    }

    await page.waitForTimeout(5000);

    await page.locator('button[aria-label="Document Overview"]').click();
    await page.waitForTimeout(500);

    const codeElement = canvas.locator(
      '.blockstudio-block.wp-block-blockstudio-type-text code',
    ).first();

    await expect(codeElement).toBeVisible({ timeout: 15000 });

    const attrs = await codeElement.textContent();
    expect(attrs).toContain('"Visibility Test"');
  });
});

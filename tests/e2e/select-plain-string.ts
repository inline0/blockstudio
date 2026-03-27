import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';

const BASE = 'http://localhost:8888';
const POST_ID = 3900;

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

test.describe('Select with plain string value (issue #27)', () => {
  test('editor sidebar shows correct value when attribute is a plain string', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`, {
      waitUntil: 'domcontentloaded',
    });

    const modalClose = page.locator('.components-modal__header button[aria-label="Close"]');
    if (await modalClose.isVisible({ timeout: 2000 }).catch(() => false)) {
      await modalClose.click();
    }

    const canvas = await getEditorCanvas(page);
    await canvas.click('[data-type^="blockstudio/type-select"]');
    await page.waitForTimeout(1000);

    const blockTab = page.locator('role=tab[name="Block"]');
    if (await blockTab.isVisible()) {
      await blockTab.click();
    }

    const rendered = canvas.locator('code').first();
    const text = await rendered.textContent();
    expect(text).toContain('"defaultValueLabel":"Two"');
  });
});

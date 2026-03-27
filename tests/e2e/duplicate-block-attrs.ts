import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';

const BASE = 'http://localhost:8888';
const POST_ID = 3800;

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

test.describe('Duplicate block attributes (issue #22)', () => {
  test('three blocks of same type with different attrs render correctly in editor', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`, {
      waitUntil: 'domcontentloaded',
    });

    const modalClose = page.locator('.components-modal__header button[aria-label="Close"]');
    if (await modalClose.isVisible({ timeout: 2000 }).catch(() => false)) {
      await modalClose.click();
    }

    const canvas = await getEditorCanvas(page);

    await canvas
      .locator('.blockstudio-block.wp-block-blockstudio-type-text')
      .nth(2)
      .waitFor({ timeout: 30000 });

    const attrs = canvas.locator(
      '.blockstudio-block.wp-block-blockstudio-type-text code:first-of-type',
    );

    const block1 = await attrs.nth(0).textContent();
    const block2 = await attrs.nth(1).textContent();
    const block3 = await attrs.nth(2).textContent();

    expect(block1).toContain('"First"');
    expect(block2).toContain('"First"');
    expect(block3).toContain('"Third"');
    expect(block3).not.toContain('"First"');
  });
});

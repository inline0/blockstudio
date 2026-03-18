import { test, expect, Page } from '@playwright/test';
import { login } from '../utils/playwright-utils';

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

test.describe('Block Tags in Templates', () => {
  test('bs: tag renders Blockstudio block in template', async () => {
    await page.goto('http://localhost:8888/block-tags-template-test/', {
      waitUntil: 'domcontentloaded',
    });

    const host = page.locator('.bs-block-tags-template');
    await expect(host).toBeVisible({ timeout: 10000 });
    await expect(host.locator('.btt-label')).toHaveText('Template Host');

    const embedded = host.locator('.btt-embedded .bs-block-tags');
    await expect(embedded).toBeVisible({ timeout: 5000 });
    await expect(embedded.locator('.bt-title')).toHaveText('From Template');
    await expect(embedded.locator('.bt-count')).toHaveText('99');
  });

  test('bs: tag renders core paragraph in template', async () => {
    const coreBs = page.locator('.btt-core-bs p');
    await expect(coreBs).toBeVisible();
    await expect(coreBs).toHaveText('Core paragraph via bs tag');
  });

  test('block element renders core paragraph in template', async () => {
    const coreBlock = page.locator('.btt-core-block p');
    await expect(coreBlock).toBeVisible();
    await expect(coreBlock).toHaveText('Core paragraph via block tag');
  });
});

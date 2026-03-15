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

test.describe('Native WP Block', () => {
  test('renders on frontend via auto-registration', async () => {
    await page.goto('http://localhost:8888/native-wp-block-test/', {
      waitUntil: 'domcontentloaded',
    });

    const block = page.locator('.wp-block-create-block-blockstudio-test-native');
    await expect(block).toBeVisible();
    await expect(block).toContainText('hello from a dynamic block');
  });
});

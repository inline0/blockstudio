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

    const block = page.locator('.native-wp-block-test');
    await expect(block).toBeVisible();
    await expect(block.locator('.native-wp-block-text')).toHaveText(
      'Native WP block registered by Blockstudio'
    );
  });

  test('has WordPress block wrapper class', async () => {
    const block = page.locator('.wp-block-blockstudio-test-native-wp-block');
    await expect(block).toBeVisible();
  });
});

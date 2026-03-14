import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await page.goto('http://localhost:8888/string-renderer-test/');
  await page.waitForLoadState('domcontentloaded');
});

test.afterAll(async () => {
  await page.close();
});

test.describe('String Renderer', () => {
  test('renders a self-closing tag with attributes', async () => {
    const blocks = page.locator('.bs-string-renderer');
    const first = blocks.first();
    await expect(first).toBeVisible();
    await expect(first.locator('.sr-title')).toHaveText('Self Closing');
    await expect(first.locator('.sr-count')).toHaveText('42');
  });

  test('renders a paired tag', async () => {
    const block = page.locator('.bs-string-renderer', { hasText: 'Paired Tag' });
    await expect(block).toBeVisible();
    await expect(block.locator('.sr-count')).toHaveText('7');
  });

  test('renders with default attribute values', async () => {
    const block = page.locator('.bs-string-renderer', { hasText: 'Default Title' });
    await expect(block).toBeVisible();
    await expect(block.locator('.sr-count')).toHaveText('0');
  });

  test('renders multiple tags', async () => {
    const first = page.locator('.bs-string-renderer', { hasText: 'First' });
    const second = page.locator('.bs-string-renderer', { hasText: 'Second' });
    await expect(first).toBeVisible();
    await expect(second).toBeVisible();
    await expect(first.locator('.sr-count')).toHaveText('1');
    await expect(second.locator('.sr-count')).toHaveText('2');
  });

  test('renders nested tags', async () => {
    const outer = page.locator('.bs-string-renderer', { hasText: 'Outer' });
    const inner = page.locator('.bs-string-renderer', { hasText: 'Inner' });
    await expect(outer).toBeVisible();
    await expect(inner).toBeVisible();
    await expect(outer.locator('.sr-count').first()).toHaveText('100');
    await expect(inner.locator('.sr-count')).toHaveText('200');
  });

  test('leaves unresolvable tags untouched', async () => {
    await expect(page.locator('text=Unknown tag:')).toBeVisible();
  });
});

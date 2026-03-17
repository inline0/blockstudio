import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await page.goto('http://localhost:8888/block-tags-test/');
  await page.waitForLoadState('domcontentloaded');
});

test.afterAll(async () => {
  await page.close();
});

test.describe('Block Tags', () => {
  test('renders a self-closing tag with attributes', async () => {
    const blocks = page.locator('.bs-block-tags');
    const first = blocks.first();
    await expect(first).toBeVisible();
    await expect(first.locator('.bt-title')).toHaveText('Self Closing');
    await expect(first.locator('.bt-count')).toHaveText('42');
  });

  test('renders a paired tag', async () => {
    const block = page.locator('.bs-block-tags', { hasText: 'Paired Tag' });
    await expect(block).toBeVisible();
    await expect(block.locator('.bt-count')).toHaveText('7');
  });

  test('renders with default attribute values', async () => {
    const block = page.locator('.bs-block-tags', { hasText: 'Default Title' });
    await expect(block).toBeVisible();
    await expect(block.locator('.bt-count')).toHaveText('0');
  });

  test('renders multiple tags', async () => {
    const first = page.locator('.bs-block-tags', { hasText: 'First' });
    const second = page.locator('.bs-block-tags', { hasText: 'Second' });
    await expect(first).toBeVisible();
    await expect(second).toBeVisible();
    await expect(first.locator('.bt-count')).toHaveText('1');
    await expect(second.locator('.bt-count')).toHaveText('2');
  });

  test('renders nested tags', async () => {
    const outer = page.locator('.bs-block-tags', { hasText: 'Outer' });
    const inner = page.locator('.bs-block-tags', { hasText: 'Inner' });
    await expect(outer).toBeVisible();
    await expect(inner).toBeVisible();
    await expect(outer.locator('.bt-count').first()).toHaveText('100');
    await expect(inner.locator('.bt-count')).toHaveText('200');
  });

  test('leaves unresolvable tags untouched', async () => {
    await expect(page.locator('text=Unknown tag:')).toBeVisible();
  });
});

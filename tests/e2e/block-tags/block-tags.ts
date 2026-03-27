import { test, expect, Page } from '@playwright/test';
import { login } from '../utils/playwright-utils';

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
  // Blockstudio blocks via bs: syntax
  test('renders a self-closing bs: tag with attributes', async () => {
    const blocks = page.locator('.bs-block-tags');
    const first = blocks.first();
    await expect(first).toBeVisible();
    await expect(first.locator('.bt-title')).toHaveText('Self Closing');
    await expect(first.locator('.bt-count')).toHaveText('42');
  });

  test('renders a paired bs: tag', async () => {
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
    const outer = page.locator('.bs-block-tags', { hasText: 'Outer' }).first();
    await expect(outer).toBeVisible();
    await expect(outer.locator('.bt-count').first()).toHaveText('100');
    const inner = outer.locator('.bs-block-tags');
    await expect(inner).toBeVisible();
    await expect(inner.locator('.bt-count')).toHaveText('200');
  });

  test('leaves unresolvable tags untouched', async () => {
    await expect(page.locator('text=Unknown tag:')).toBeVisible();
  });

  // Core blocks via <block> element
  test('core/paragraph via block element', async () => {
    const p = page.locator('p:has-text("Core via block element")');
    await expect(p).toBeVisible();
  });

  test('core/heading via block element', async () => {
    const h2 = page.locator('h2:has-text("Core heading")');
    await expect(h2).toBeVisible();
  });

  // Core blocks via bs: syntax
  test('core/paragraph via bs: tag', async () => {
    const p = page.locator('p:has-text("Core via bs tag")');
    await expect(p).toBeVisible();
  });

  test('core/heading via bs: tag', async () => {
    const h3 = page.locator('h3:has-text("Core heading via bs")');
    await expect(h3).toBeVisible();
  });

  // Core separator
  test('core/separator via block element', async () => {
    const hr = page.locator('hr.wp-block-separator');
    await expect(hr).toBeAttached();
  });

  // Nested core blocks
  test('nested block elements: group with paragraph', async () => {
    const group = page.locator('.wp-block-group');
    await expect(group.first()).toBeVisible();
    const p = group.first().locator('p:has-text("Inside group")');
    await expect(p).toBeVisible();
  });

  // Passthrough in page content
  test('passthrough attributes on core block in page content', async () => {
    const p = page.locator('p.page-passthrough');
    await expect(p).toBeVisible();
    await expect(p).toHaveAttribute('data-testid', 'page-pt');
    await expect(p).toHaveText('Passthrough test');
  });
});

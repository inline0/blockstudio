import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

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

test.describe('File-based Pages', () => {
  test('test page exists with correct title', async () => {
    await page.goto('http://localhost:8888/blockstudio-e2e-test/');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: 'Core Blocks Test Page' })).toBeVisible();
  });

  test('has text content', async () => {
    await expect(page.getByText('This page tests all supported HTML')).toBeVisible();
    await expect(page.getByText('bold')).toBeVisible();
    await expect(page.getByText('italic')).toBeVisible();
  });

  test('has all heading levels', async () => {
    await expect(page.getByRole('heading', { name: 'Heading Level 1' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Heading Level 2' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Heading Level 3' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Heading Level 4' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Heading Level 5' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Heading Level 6' })).toBeVisible();
  });

  test('has unordered list', async () => {
    await expect(page.getByText('Unordered item one', { exact: true })).toBeVisible();
    await expect(page.getByText('Unordered item two', { exact: true })).toBeVisible();
    await expect(page.getByText('Unordered item three', { exact: true })).toBeVisible();
  });

  test('has ordered list', async () => {
    await expect(page.getByText('Ordered item one', { exact: true })).toBeVisible();
    await expect(page.getByText('Ordered item two', { exact: true })).toBeVisible();
    await expect(page.getByText('Ordered item three', { exact: true })).toBeVisible();
  });

  test('has blockquote', async () => {
    await expect(page.locator('.wp-block-quote').first()).toBeVisible();
  });

  test('has pullquote', async () => {
    await expect(page.locator('.wp-block-pullquote').first()).toBeVisible();
  });

  test('has code block', async () => {
    await expect(page.locator('.wp-block-code').first()).toBeVisible();
    await expect(page.getByText('const hello')).toBeVisible();
  });

  test('has preformatted block', async () => {
    await expect(page.locator('.wp-block-preformatted').first()).toBeVisible();
    await expect(page.getByText('preserved whitespace')).toBeVisible();
  });

  test('has verse block', async () => {
    await expect(page.locator('.wp-block-verse').first()).toBeVisible();
    await expect(page.getByText('Roses are red')).toBeVisible();
  });

  test('has image', async () => {
    await expect(page.locator('.wp-block-image').first()).toBeVisible();
  });

  test('has gallery', async () => {
    await expect(page.locator('.wp-block-gallery').first()).toBeVisible();
  });

  test('has audio block', async () => {
    await expect(page.locator('.wp-block-audio').first()).toBeVisible();
  });

  test('has video block', async () => {
    await expect(page.locator('.wp-block-video').first()).toBeVisible();
  });

  test('has cover block', async () => {
    await expect(page.locator('.wp-block-cover').first()).toBeVisible();
    await expect(page.getByText('Cover Block Title')).toBeVisible();
  });

  test('has embed block', async () => {
    await expect(page.locator('.wp-block-embed').first()).toBeVisible();
  });

  test('has group block', async () => {
    await expect(page.locator('.wp-block-group').first()).toBeVisible();
  });

  test('has columns', async () => {
    await expect(page.locator('.wp-block-columns').first()).toBeVisible();
    await expect(page.locator('.wp-block-column').first()).toBeVisible();
    await expect(page.getByText('Column 1')).toBeVisible();
    await expect(page.getByText('Column 2')).toBeVisible();
    await expect(page.getByText('Column 3')).toBeVisible();
  });

  test('has separator', async () => {
    await expect(page.locator('.wp-block-separator').first()).toBeVisible();
  });

  test('has spacer', async () => {
    await expect(page.locator('.wp-block-spacer').first()).toBeVisible();
  });

  test('has buttons', async () => {
    await expect(page.locator('.wp-block-buttons').first()).toBeVisible();
    await expect(page.locator('.wp-block-button').first()).toBeVisible();
    await expect(page.getByText('Primary Button')).toBeVisible();
  });

  test('has details', async () => {
    await expect(page.locator('.wp-block-details').first()).toBeVisible();
    await expect(page.getByText('Click to expand')).toBeVisible();
  });

  test('has table', async () => {
    await expect(page.locator('.wp-block-table').first()).toBeVisible();
    await expect(page.getByText('Header 1')).toBeVisible();
    await expect(page.getByText('Row 1, Cell 1')).toBeVisible();
  });

  test('editor shows correct blocks', async () => {
    await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
    await page.waitForLoadState('networkidle');

    // Exact match to avoid matching the Twig/Blade page titles
    await page.locator('a.row-title', { hasText: /^Blockstudio E2E Test Page$/ }).click();
    await page.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });

    await expect(page.locator('.wp-block-heading').first()).toBeVisible();
  });

  test('template lock prevents unlocking', async () => {
    await page.click('.wp-block-heading >> nth=0');

    const moreButton = page.locator('[aria-label="Options"]').first();
    if (await moreButton.isVisible()) {
      await moreButton.click();

      const unlockOption = page.locator('button:has-text("Unlock")');
      await expect(unlockOption).toHaveCount(0);
    }
  });
});

test.describe('File-based Pages (Twig)', () => {
  test('twig page exists with correct title', async () => {
    await page.goto('http://localhost:8888/blockstudio-e2e-test-twig/');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: 'Twig Template Test Page' })).toBeVisible();
  });

  test('twig template processed correctly', async () => {
    // Verify |upper filter was applied
    await expect(page.getByText('This page uses a Twig template with TWIG support.')).toBeVisible();

    const currentYear = new Date().getFullYear().toString();
    await expect(page.getByText(`Current year: ${currentYear}`)).toBeVisible();
  });

  test('twig loop generates list items', async () => {
    await expect(page.getByText('Twig item 1', { exact: true })).toBeVisible();
    await expect(page.getByText('Twig item 2', { exact: true })).toBeVisible();
    await expect(page.getByText('Twig item 3', { exact: true })).toBeVisible();
  });

  test('twig page has block syntax elements', async () => {
    await expect(page.locator('.wp-block-buttons').first()).toBeVisible();
    await expect(page.getByText('Twig Button')).toBeVisible();
    await expect(page.locator('.wp-block-columns').first()).toBeVisible();
    await expect(page.getByText('Column A')).toBeVisible();
    await expect(page.getByText('Column B')).toBeVisible();
  });

  test('twig page editor shows correct blocks', async () => {
    await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
    await page.waitForLoadState('networkidle');

    await page.locator('a.row-title:has-text("Blockstudio E2E Test Page (Twig)")').click();
    await page.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });

    await expect(page.locator('.wp-block-heading').first()).toBeVisible();
  });
});

test.describe('File-based Pages (Blade)', () => {
  test('blade page exists with correct title', async () => {
    await page.goto('http://localhost:8888/blockstudio-e2e-test-blade/');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: 'Blade Template Test Page' })).toBeVisible();
  });

  test('blade template processed correctly', async () => {
    // Verify strtoupper() was applied
    await expect(page.getByText('This page uses a Blade template with BLADE support.')).toBeVisible();

    const currentYear = new Date().getFullYear().toString();
    await expect(page.getByText(`Current year: ${currentYear}`)).toBeVisible();
  });

  test('blade loop generates list items', async () => {
    await expect(page.getByText('Blade item 1', { exact: true })).toBeVisible();
    await expect(page.getByText('Blade item 2', { exact: true })).toBeVisible();
    await expect(page.getByText('Blade item 3', { exact: true })).toBeVisible();
  });

  test('blade page has block syntax elements', async () => {
    await expect(page.locator('.wp-block-buttons').first()).toBeVisible();
    await expect(page.getByText('Blade Button')).toBeVisible();
    await expect(page.locator('.wp-block-columns').first()).toBeVisible();
    await expect(page.getByText('Column A')).toBeVisible();
    await expect(page.getByText('Column B')).toBeVisible();
  });

  test('blade page editor shows correct blocks', async () => {
    await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
    await page.waitForLoadState('networkidle');

    await page.locator('a.row-title:has-text("Blockstudio E2E Test Page (Blade)")').click();
    await page.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });

    await expect(page.locator('.wp-block-heading').first()).toBeVisible();
  });
});

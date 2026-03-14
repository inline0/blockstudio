import { test, expect, Page } from '@playwright/test';
import { login, count, delay } from '../utils/playwright-utils';

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

test.describe('Component', () => {
  test('does not appear in editor inserter', async () => {
    await page.goto(
      'http://localhost:8888/wp-admin/post-new.php?post_type=page',
      { waitUntil: 'domcontentloaded' }
    );

    await page.waitForSelector('iframe[name="editor-canvas"]', {
      timeout: 30000,
    });
    const frame = page.frame('editor-canvas');
    await frame!.waitForLoadState('domcontentloaded');

    // Dismiss welcome modal if present.
    const modalOverlay = await page.$('.components-modal__screen-overlay');
    if (modalOverlay) {
      await page.click(
        '.components-modal__header .components-button.has-icon'
      );
      await page.waitForSelector('.components-modal__screen-overlay', {
        state: 'hidden',
        timeout: 5000,
      });
    }

    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    await page.fill('[placeholder="Search"]', 'Component Test');
    await delay(1000);

    const result = page.locator(
      '.block-editor-block-types-list__list-item:has-text("Component Test")'
    );
    await expect(result).toHaveCount(0);
  });

  test('renders via string renderer', async () => {
    await page.goto('http://localhost:8888/component-test/', {
      waitUntil: 'domcontentloaded',
    });

    const first = page.locator('.bs-component').first();
    await expect(first).toBeVisible();
    await expect(first.locator('.comp-heading')).toHaveText('String Rendered');
    await expect(first.locator('.comp-content')).toHaveText('Via bs tag');
  });

  test('renders via paired bs: tag', async () => {
    const block = page.locator('.bs-component', { hasText: 'Paired Tag' });
    await expect(block).toBeVisible();
    await expect(block.locator('.comp-content')).toHaveText('Via paired tag');
  });

  test('renders via bs_render_block()', async () => {
    const block = page.locator('.bs-component', { hasText: 'PHP Rendered' });
    await expect(block).toBeVisible();
    await expect(block.locator('.comp-content')).toHaveText(
      'Via bs_render_block'
    );
  });

  test('loads component CSS asset', async () => {
    const styleTag = page.locator(
      'link[id*="type-component"][rel="stylesheet"]'
    );
    await expect(styleTag.first()).toBeAttached();
  });
});

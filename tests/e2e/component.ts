import { test, expect, Page } from '@playwright/test';
import { login, count, delay } from './utils/playwright-utils';

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

  test('renders via block tags', async () => {
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

  test('renders nested bs_render_block() output on the frontend', async () => {
    const block = page.locator('.nested-render-helper');
    await expect(block).toBeVisible();
    await expect(block).toContainText('Nested shortcode label');
    await expect(block).not.toContainText('<RichText');
  });

  test('renders nested bs_render_block() output in editor preview', async () => {
    await page.goto(
      'http://localhost:8888/wp-admin/post-new.php?post_type=page',
      { waitUntil: 'domcontentloaded' }
    );
    await page.waitForFunction(() => Boolean((window as any).blockstudioAdmin?.nonceRest));

    const result = await page.evaluate(async () => {
      const res = await fetch(
        '/wp-json/blockstudio/v1/gutenberg/block/render/blockstudio/function-nested-render?blockstudioMode=editor',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (window as any).blockstudioAdmin.nonceRest,
          },
          body: JSON.stringify({
            context: 'edit',
            attributes: {
              blockstudio: {
                attributes: {
                  label: 'Nested editor E2E label',
                },
              },
            },
          }),
        }
      );

      return { status: res.status, body: await res.json() };
    });

    expect(result.status).toBe(200);
    expect(result.body.rendered).toContain('Nested editor E2E label');
    expect(result.body.rendered).not.toContain('<RichText');
    expect(result.body.rendered).not.toContain('<InnerBlocks');
    expect(result.body.rendered).not.toContain('useblockprops="true"');
  });

  test('expands allowedBlocks tokens before editor handoff', async () => {
    await page.goto(
      'http://localhost:8888/wp-admin/post-new.php?post_type=page',
      { waitUntil: 'domcontentloaded' }
    );
    await page.waitForFunction(() => Boolean((window as any).blockstudioAdmin?.nonceRest));

    const result = await page.evaluate(async () => {
      const res = await fetch(
        '/wp-json/blockstudio/v1/gutenberg/block/render/blockstudio/function-allowed-blocks-tokens?blockstudioMode=editor',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (window as any).blockstudioAdmin.nonceRest,
          },
          body: JSON.stringify({
            context: 'edit',
            attributes: {},
          }),
        }
      );

      return { status: res.status, body: await res.json() };
    });

    expect(result.status).toBe(200);
    expect(result.body.rendered).toContain('allowedBlocks');
    expect(result.body.rendered).toMatch(/core\\?\/paragraph/);
    expect(result.body.rendered).toMatch(/blockstudio\\?\/type-text/);
    expect(result.body.rendered).not.toContain('category:blockstudio-test-native');
  });

  test('loads component CSS asset', async () => {
    await page.goto('http://localhost:8888/component-test/', {
      waitUntil: 'domcontentloaded',
    });

    const styleTag = page.locator(
      'link[id*="type-component"][rel="stylesheet"]'
    );
    await expect(styleTag.first()).toBeAttached();
  });
});

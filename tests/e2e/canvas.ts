import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

let page: Page;
const canvasUrl = 'http://localhost:8888/wp-admin/admin.php?page=blockstudio-canvas';

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

test.describe('Canvas', () => {
  test.describe('admin page gating', () => {
    test('canvas script enqueued on admin page', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      const script = page.locator('script[id="blockstudio-canvas-js"]');
      await expect(script).toHaveCount(1);
    });

    test('canvas script NOT enqueued on other admin pages', async () => {
      await page.goto('http://localhost:8888/wp-admin/', { waitUntil: 'domcontentloaded' });

      const script = page.locator('script[id="blockstudio-canvas-js"]');
      await expect(script).toHaveCount(0);
    });
  });

  test.describe('logged-out users', () => {
    test('redirects to login page', async ({ browser }) => {
      const anonContext = await browser.newContext();
      const anonPage = await anonContext.newPage();

      await anonPage.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      expect(anonPage.url()).toContain('wp-login.php');

      await anonPage.close();
      await anonContext.close();
    });
  });

  test.describe('canvas UI', () => {
    test('renders canvas root element', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      const root = page.locator('#blockstudio-canvas');
      await expect(root).toBeVisible();
    });

    test('canvas surface has transform applied', async () => {
      const surface = page.locator('[data-canvas-surface]');
      await expect(surface).toBeVisible();

      const transform = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );
      expect(transform).not.toBe('none');
    });

    test('shows all published Blockstudio pages as artboards', async () => {
      const iframes = page.locator('#blockstudio-canvas iframe');
      await expect(iframes.first()).toBeVisible({ timeout: 15000 });
      const count = await iframes.count();
      expect(count).toBeGreaterThanOrEqual(4);
    });

    test('artboard titles are visible', async () => {
      await expect(
        page.locator('#blockstudio-canvas', { hasText: 'Blockstudio E2E Test Page' }),
      ).toBeVisible();
      await expect(
        page.locator('#blockstudio-canvas', { hasText: 'Blockstudio E2E Test Page (Blade)' }),
      ).toBeVisible();
      await expect(
        page.locator('#blockstudio-canvas', { hasText: 'Blockstudio E2E Test Page (Twig)' }),
      ).toBeVisible();
      await expect(
        page.locator('#blockstudio-canvas', { hasText: 'Tailwind On-Demand Test' }),
      ).toBeVisible();
    });

    test('artboard iframes use blob URLs', async () => {
      const iframes = page.locator('#blockstudio-canvas iframe');
      const count = await iframes.count();

      for (let i = 0; i < count; i++) {
        const src = await iframes.nth(i).getAttribute('src');
        expect(src).toMatch(/^blob:/);
      }
    });

    test('artboards have pointer events disabled', async () => {
      const disabled = page.locator('#blockstudio-canvas .components-disabled').first();
      await expect(disabled).toBeVisible();
    });

    test('all artboards are in a single row', async () => {
      const surface = page.locator('[data-canvas-surface]');
      const columns = await surface.evaluate(
        (el) => window.getComputedStyle(el).gridTemplateColumns,
      );
      const columnCount = columns.split(' ').length;
      const iframes = page.locator('#blockstudio-canvas iframe');
      const iframeCount = await iframes.count();
      expect(columnCount).toBe(iframeCount);
    });
  });

  test.describe('interactions', () => {
    test('trackpad scroll pans the canvas', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });
      await page.locator('[data-canvas-surface]').waitFor({ state: 'visible' });

      const surface = page.locator('[data-canvas-surface]');
      const before = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );

      await page.mouse.move(960, 540);
      await page.mouse.wheel(0, -200);
      await page.waitForTimeout(100);

      const after = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );
      expect(after).not.toBe(before);
    });

    test('ctrl+wheel zooms the canvas', async () => {
      const surface = page.locator('[data-canvas-surface]');
      const before = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );

      await page.mouse.move(960, 540);
      await page.keyboard.down('Control');
      await page.mouse.wheel(0, -100);
      await page.keyboard.up('Control');
      await page.waitForTimeout(100);

      const after = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );
      expect(after).not.toBe(before);
    });

  });
});

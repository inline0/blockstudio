import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

let page: Page;
const canvasUrl = 'http://localhost:8888/blockstudio-e2e-test/?blockstudio-canvas';
const noCanvasUrl = 'http://localhost:8888/blockstudio-e2e-test/';

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
  test.describe('query param gating', () => {
    test('canvas script enqueued with ?blockstudio-canvas', async () => {
      await page.goto(canvasUrl);
      await page.waitForLoadState('networkidle');

      const script = page.locator('script[id="blockstudio-canvas-js"]');
      await expect(script).toHaveCount(1);
    });

    test('canvas script NOT enqueued without query param', async () => {
      await page.goto(noCanvasUrl);
      await page.waitForLoadState('networkidle');

      const script = page.locator('script[id="blockstudio-canvas-js"]');
      await expect(script).toHaveCount(0);
    });
  });

  test.describe('logged-out users', () => {
    test('no canvas script even with query param', async ({ browser }) => {
      const anonContext = await browser.newContext();
      const anonPage = await anonContext.newPage();

      await anonPage.goto(canvasUrl);
      await anonPage.waitForLoadState('networkidle');

      const script = anonPage.locator('script[id="blockstudio-canvas-js"]');
      await expect(script).toHaveCount(0);

      await anonPage.close();
      await anonContext.close();
    });
  });

  test.describe('canvas UI', () => {
    test('renders canvas root and hides page content', async () => {
      await page.goto(canvasUrl);
      await page.waitForLoadState('networkidle');

      const root = page.locator('#blockstudio-canvas-root');
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
      const iframes = page.locator('#blockstudio-canvas-root iframe');
      const count = await iframes.count();
      expect(count).toBeGreaterThanOrEqual(4);
    });

    test('artboard titles are visible', async () => {
      await expect(
        page.locator('#blockstudio-canvas-root', { hasText: 'Blockstudio E2E Test Page' }),
      ).toBeVisible();
      await expect(
        page.locator('#blockstudio-canvas-root', { hasText: 'Blockstudio E2E Test Page (Blade)' }),
      ).toBeVisible();
      await expect(
        page.locator('#blockstudio-canvas-root', { hasText: 'Blockstudio E2E Test Page (Twig)' }),
      ).toBeVisible();
      await expect(
        page.locator('#blockstudio-canvas-root', { hasText: 'Tailwind On-Demand Test' }),
      ).toBeVisible();
    });

    test('iframe src URLs do not include blockstudio-canvas param', async () => {
      const iframes = page.locator('#blockstudio-canvas-root iframe');
      const count = await iframes.count();

      for (let i = 0; i < count; i++) {
        const src = await iframes.nth(i).getAttribute('src');
        expect(src).not.toMatch(/[?&]blockstudio-canvas(?!-frame)\b/);
      }
    });

    test('iframes have pointerEvents none', async () => {
      const iframe = page.locator('#blockstudio-canvas-root iframe').first();
      const pointerEvents = await iframe.evaluate(
        (el) => window.getComputedStyle(el).pointerEvents,
      );
      expect(pointerEvents).toBe('none');
    });

    test('all artboards are in a single row', async () => {
      const surface = page.locator('[data-canvas-surface]');
      const columns = await surface.evaluate(
        (el) => window.getComputedStyle(el).gridTemplateColumns,
      );
      const columnCount = columns.split(' ').length;
      const iframes = page.locator('#blockstudio-canvas-root iframe');
      const iframeCount = await iframes.count();
      expect(columnCount).toBe(iframeCount);
    });

    test('admin bar is hidden when adminBar setting is false', async () => {
      const adminBar = page.locator('#wpadminbar');
      await expect(adminBar).toBeHidden();
    });
  });

  test.describe('interactions', () => {
    test('trackpad scroll pans the canvas', async () => {
      await page.goto(canvasUrl);
      await page.waitForLoadState('networkidle');

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

    test('Escape key navigates away from canvas', async () => {
      await page.goto(canvasUrl);
      await page.waitForLoadState('networkidle');

      await expect(page.locator('#blockstudio-canvas-root')).toBeVisible();

      await page.keyboard.press('Escape');
      await page.waitForURL((url) => !url.search.includes('blockstudio-canvas'));
      await page.waitForLoadState('networkidle');

      expect(page.url()).not.toContain('blockstudio-canvas');
      await expect(page.locator('#blockstudio-canvas-root')).toHaveCount(0);
    });
  });
});

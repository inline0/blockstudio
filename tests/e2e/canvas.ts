import * as fs from 'fs';
import * as path from 'path';

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

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );
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

  test.describe('block content rendering', () => {
    test('artboard iframes contain no unsupported block warnings', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      // Wait for BlockPreview iframes to finish rendering.
      await page.waitForTimeout(5000);

      const result = await page.evaluate(() => {
        const iframes = document.querySelectorAll('#blockstudio-canvas iframe');
        const warnings: string[] = [];

        iframes.forEach((f) => {
          const doc = (f as HTMLIFrameElement).contentDocument;
          if (!doc) return;

          doc.querySelectorAll('.wp-block-missing').forEach((el) => {
            const text = el.textContent || '';
            const match = text.match(/"([^"]+)"/);
            if (match) {
              warnings.push(match[1]);
            }
          });
        });

        return warnings;
      });

      expect(result).toEqual([]);
    });

    test('blockstudio blocks render PHP output in artboard iframe', async () => {
      const result = await page.evaluate(() => {
        const artboard = document.querySelector(
          '[data-canvas-slug="blockstudio-e2e-test"]',
        );
        if (!artboard) return { error: 'artboard not found' };

        const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
        if (!iframe) return { error: 'iframe not found' };

        const doc = iframe.contentDocument;
        if (!doc) return { error: 'contentDocument not accessible' };

        const preloadSimple = doc.querySelector('.preload-simple');
        const preloadTitle = doc.querySelector('.preload-simple .preload-title');

        return {
          hasPreloadSimple: preloadSimple !== null,
          preloadTitleText: preloadTitle?.textContent || null,
          bodyText: doc.body?.textContent?.substring(0, 500) || null,
        };
      });

      expect(result).not.toHaveProperty('error');
      expect((result as any).hasPreloadSimple).toBe(true);
      expect((result as any).preloadTitleText).toBeTruthy();
    });
  });

  test.describe('dropdown menu', () => {
    test('opens menu when clicking the button', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await expect(menuButton).toBeVisible();
      await menuButton.click();

      await expect(page.locator('role=menu')).toBeVisible();
    });

    test('has Fit to view menu item', async () => {
      await expect(page.locator('role=menuitem', { hasText: 'Fit to view' })).toBeVisible();
    });

    test('has Zoom to 100% menu item', async () => {
      await expect(page.locator('role=menuitem', { hasText: 'Zoom to 100%' })).toBeVisible();
    });

    test('has Live mode menu item', async () => {
      await expect(page.locator('role=menuitem', { hasText: 'Live mode' })).toBeVisible();
    });

    test('Fit to view resets the transform', async () => {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(200);

      await page.mouse.move(960, 540);
      await page.mouse.wheel(200, 200);
      await page.waitForTimeout(100);

      const surface = page.locator('[data-canvas-surface]');
      const before = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Fit to view' }).click();
      await page.waitForTimeout(200);

      const after = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );
      expect(after).not.toBe(before);
    });

    test('Zoom to 100% sets scale to 1', async () => {
      await page.mouse.click(10, 10);
      await page.waitForTimeout(300);

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Zoom to 100%' }).click();
      await page.waitForTimeout(200);

      const surface = page.locator('[data-canvas-surface]');
      const matrix = await surface.evaluate(
        (el) => window.getComputedStyle(el).transform,
      );

      // CSS transform matrix(a, b, c, d, tx, ty) where a = scaleX.
      const match = matrix.match(/matrix\(([^,]+)/);
      expect(match).toBeTruthy();
      const scaleX = parseFloat(match![1]);
      expect(scaleX).toBeCloseTo(1, 1);
    });
  });

  test.describe('live mode', () => {
    test('toggling live mode shows pulsing indicator', async () => {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const indicator = page.locator('.blockstudio-canvas-menu div[style*="border-radius: 50%"]');
      await expect(indicator).toHaveCount(0);

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();
      await page.waitForTimeout(300);

      await expect(indicator).toHaveCount(1);
    });

    test('live mode state persists in localStorage', async () => {
      const stored = await page.evaluate(() => {
        const raw = localStorage.getItem('blockstudio-canvas-settings');
        return raw ? JSON.parse(raw) : null;
      });

      expect(stored).toBeTruthy();
      expect(stored.liveMode).toBe(true);
    });

    test('live mode check icon is shown in menu', async () => {
      await page.mouse.click(10, 10);
      await page.waitForTimeout(200);

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });

      const liveMenuItem = page.locator('role=menuitem', { hasText: 'Live mode' });
      const icon = liveMenuItem.locator('svg');
      await expect(icon).toBeVisible();

      await page.keyboard.press('Escape');
    });

    test('toggling live mode off hides indicator', async () => {
      await page.waitForTimeout(200);

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();
      await page.waitForTimeout(300);

      const indicator = page.locator('.blockstudio-canvas-menu div[style*="border-radius: 50%"]');
      await expect(indicator).toHaveCount(0);
    });
  });

  test.describe('REST endpoints', () => {
    test('refresh endpoint returns pages and blocks', async () => {
      const result = await page.evaluate(async () => {
        const apiFetch = (window as any).wp.apiFetch;
        return apiFetch({ path: '/blockstudio/v1/canvas/refresh' });
      });

      expect(result).toHaveProperty('pages');
      expect(result).toHaveProperty('blockstudioBlocks');
      expect(Array.isArray(result.pages)).toBe(true);
      expect(result.pages.length).toBeGreaterThanOrEqual(4);

      const firstPage = result.pages[0];
      expect(firstPage).toHaveProperty('title');
      expect(firstPage).toHaveProperty('slug');
      expect(firstPage).toHaveProperty('name');
      expect(firstPage).toHaveProperty('content');
    });

    test('targeted refresh with empty blocks skips block data entirely', async () => {
      const result = await page.evaluate(async () => {
        const apiFetch = (window as any).wp.apiFetch;
        return apiFetch({ path: '/blockstudio/v1/canvas/refresh?blocks=' });
      });

      expect(result).toHaveProperty('pages');
      expect(result).toHaveProperty('blockstudioBlocks');
      expect(result.blockstudioBlocks).toEqual([]);
      expect(result.pages.length).toBeGreaterThanOrEqual(4);
      expect(result.blocks).toEqual([]);
    });

    test('targeted refresh with specific block only renders that block', async () => {
      const result = await page.evaluate(async () => {
        const apiFetch = (window as any).wp.apiFetch;
        return apiFetch({ path: '/blockstudio/v1/canvas/refresh?blocks=blockstudio/init' });
      });

      expect(result.blockstudioBlocks.length).toBeGreaterThan(0);
      for (const entry of result.blockstudioBlocks) {
        expect(entry.blockName).toBe('blockstudio/init');
      }
      expect(result.changedBlocks).toEqual(['blockstudio/init']);

      expect(result.blocks.length).toBe(1);
      expect(result.blocks[0].name).toBe('blockstudio/init');
    });

    test('targeted refresh with specific page only returns that page', async () => {
      const result = await page.evaluate(async () => {
        const apiFetch = (window as any).wp.apiFetch;
        return apiFetch({ path: '/blockstudio/v1/canvas/refresh?blocks=&pages=test-page' });
      });

      expect(result.pages.length).toBe(1);
      expect(result.pages[0].name).toBe('blockstudio-e2e-test');
      expect(result.blocks).toEqual([]);
      expect(result.blockstudioBlocks).toEqual([]);
    });

    test('targeted refresh with empty pages returns no pages', async () => {
      const result = await page.evaluate(async () => {
        const apiFetch = (window as any).wp.apiFetch;
        return apiFetch({ path: '/blockstudio/v1/canvas/refresh?blocks=blockstudio/init&pages=' });
      });

      expect(result.pages).toEqual([]);
      expect(result.blocks.length).toBe(1);
      expect(result.blocks[0].name).toBe('blockstudio/init');
    });

    test('stream endpoint requires authentication', async ({ browser }) => {
      const anonContext = await browser.newContext();
      const anonPage = await anonContext.newPage();

      const response = await anonPage.evaluate(async () => {
        const res = await fetch('http://localhost:8888/wp-json/blockstudio/v1/canvas/stream');
        return { status: res.status };
      });

      expect(response.status).toBe(401);

      await anonPage.close();
      await anonContext.close();
    });
  });

  test.describe('selective updates', () => {
    const templatePath = path.join(
      process.cwd(),
      'tests/theme/pages/test-page/index.php',
    );
    let originalContent: string;

    test.afterAll(async () => {
      if (originalContent) {
        fs.writeFileSync(templatePath, originalContent);
      }
    });

    test('artboards have revision data attributes', async () => {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const artboards = page.locator('[data-canvas-slug]');
      const count = await artboards.count();
      expect(count).toBeGreaterThanOrEqual(4);

      for (let i = 0; i < count; i++) {
        const rev = await artboards.nth(i).getAttribute('data-canvas-revision');
        expect(rev).toBe('0');
      }
    });

    test('SSE stream sends targeted change data on page edit', async () => {
      originalContent = fs.readFileSync(templatePath, 'utf-8');

      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      await page.evaluate(() => {
        const canvasData = (window as any).blockstudioCanvas;
        const url =
          canvasData.restRoot +
          'blockstudio/v1/canvas/stream?_wpnonce=' +
          encodeURIComponent(canvasData.restNonce);

        const es = new EventSource(url);
        (window as any).__sseTestPromise = new Promise<void>((resolve) => {
          es.addEventListener('fingerprint', () => resolve());
        });
        (window as any).__sseTestChangedPromise = new Promise<Record<string, unknown>>(
          (resolve) => {
            es.addEventListener('changed', (e: MessageEvent) => {
              es.close();
              resolve(JSON.parse(e.data));
            });
          },
        );
        (window as any).__sseTestES = es;
      });

      await page.evaluate(() => (window as any).__sseTestPromise);

      fs.writeFileSync(
        templatePath,
        originalContent + '\n<!-- sse-targeted-test -->',
      );

      const sseData = await page.evaluate(
        () => (window as any).__sseTestChangedPromise,
      );

      fs.writeFileSync(templatePath, originalContent);

      expect(sseData).toHaveProperty('fingerprint');
      expect(sseData).toHaveProperty('changedBlocks');
      expect(sseData).toHaveProperty('changedPages');
      expect(sseData.changedBlocks).toEqual([]);
      expect(sseData.changedPages).toEqual(['test-page']);

      expect(sseData).toHaveProperty('pages');
      expect(sseData).toHaveProperty('blocks');
      expect(sseData).toHaveProperty('blockstudioBlocks');
      expect(Array.isArray(sseData.pages)).toBe(true);
      expect((sseData.pages as any[]).length).toBe(1);
      expect((sseData.pages as any[])[0].name).toBe('blockstudio-e2e-test');
      expect(sseData.blocks).toEqual([]);
      expect(Array.isArray(sseData.blockstudioBlocks)).toBe(true);
    });

    test('only changed page revision increments after live refresh', async () => {
      originalContent = fs.readFileSync(templatePath, 'utf-8');

      // Navigate fresh to avoid stale state from previous tests.
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

      await page.waitForFunction(
        () => {
          const select = (window as any).wp?.data?.select;
          if (!select) return false;
          const store = select('blockstudio/canvas');
          return store && store.getFingerprint() !== '';
        },
        null,
        { timeout: 10000 },
      );

      const artboards = page.locator('[data-canvas-slug]');
      const count = await artboards.count();
      const initialRevisions: Record<string, string> = {};
      for (let i = 0; i < count; i++) {
        const slug = await artboards.nth(i).getAttribute('data-canvas-slug');
        const rev = await artboards.nth(i).getAttribute('data-canvas-revision');
        initialRevisions[slug!] = rev!;
      }

      fs.writeFileSync(
        templatePath,
        originalContent + '\n<p>selective update test marker</p>',
      );

      await page.waitForFunction(
        (slug: string) => {
          const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
          return artboard && artboard.getAttribute('data-canvas-revision') !== '0';
        },
        'blockstudio-e2e-test',
        { timeout: 20000 },
      );

      for (let i = 0; i < count; i++) {
        const slug = await artboards.nth(i).getAttribute('data-canvas-slug');
        const rev = await artboards.nth(i).getAttribute('data-canvas-revision');
        if (slug === 'blockstudio-e2e-test') {
          expect(parseInt(rev!, 10)).toBeGreaterThan(
            parseInt(initialRevisions[slug!], 10),
          );
        } else {
          expect(rev).toBe(initialRevisions[slug!]);
        }
      }
    });
  });

  test.describe('new page detection in live mode', () => {
    const newPageDir = path.join(
      process.cwd(),
      'tests/theme/pages/test-canvas-new-page',
    );
    const orderStabilityBlockDir = path.join(
      process.cwd(),
      'tests/theme/blockstudio/single/canvas-order-stability-test',
    );

    const cleanupNewPage = async (): Promise<void> => {
      if (fs.existsSync(newPageDir)) {
        fs.rmSync(newPageDir, { recursive: true });
      }
      if (fs.existsSync(orderStabilityBlockDir)) {
        fs.rmSync(orderStabilityBlockDir, { recursive: true });
      }

      await page.evaluate(async () => {
        const apiFetch = (window as any).wp?.apiFetch;
        if (!apiFetch) return;
        try {
          const pages = await apiFetch({
            path: '/wp/v2/pages?slug=canvas-new-page-test&status=any&per_page=100',
          });
          for (const p of pages) {
            await apiFetch({ path: `/wp/v2/pages/${p.id}?force=true`, method: 'DELETE' });
          }
        } catch {
          // Post may not exist.
        }
      });
    };

    test.afterAll(async () => {
      await cleanupNewPage();
    });

    test('new page appears as artboard during live session', async () => {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await cleanupNewPage();
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const initialCount = await page.locator('[data-canvas-slug]').count();

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

      await page.waitForFunction(
        () => {
          const select = (window as any).wp?.data?.select;
          if (!select) return false;
          const store = select('blockstudio/canvas');
          return store && store.getFingerprint() !== '';
        },
        null,
        { timeout: 10000 },
      );

      fs.mkdirSync(newPageDir, { recursive: true });
      fs.writeFileSync(
        path.join(newPageDir, 'page.json'),
        JSON.stringify({
          name: 'canvas-new-page-test',
          title: 'Canvas New Page Test',
          slug: 'canvas-new-page-test',
          postType: 'page',
          postStatus: 'publish',
          templateLock: 'all',
        }),
      );
      fs.writeFileSync(
        path.join(newPageDir, 'index.php'),
        '<p>New page detected by live mode.</p>',
      );

      await page.waitForFunction(
        (prevCount: number) => {
          return document.querySelectorAll('[data-canvas-slug]').length > prevCount;
        },
        initialCount,
        { timeout: 20000 },
      );

      await expect(
        page.locator('[data-canvas-slug="canvas-new-page-test"]'),
      ).toBeVisible({ timeout: 5000 });

      await expect(
        page.locator('#blockstudio-canvas', { hasText: 'Canvas New Page Test' }),
      ).toBeVisible();

      const slugs = await page.locator('[data-canvas-slug]').evaluateAll((nodes) =>
        nodes.map((node) => node.getAttribute('data-canvas-slug')),
      );
      expect(slugs[slugs.length - 1]).toBe('canvas-new-page-test');

      // Trigger a block-only refresh and ensure page order does not re-sort.
      fs.mkdirSync(orderStabilityBlockDir, { recursive: true });
      fs.writeFileSync(
        path.join(orderStabilityBlockDir, 'block.json'),
        JSON.stringify({
          $schema: 'https://app.blockstudio.dev/schema',
          name: 'blockstudio/canvas-order-stability-test',
          title: 'Canvas Order Stability Test',
          category: 'blockstudio-test-native',
          icon: 'star-filled',
          blockstudio: true,
        }),
      );
      fs.writeFileSync(
        path.join(orderStabilityBlockDir, 'index.php'),
        '<?php\necho \'<div class="canvas-order-stability-marker">Canvas Order Stability Test</div>\';',
      );

      await page.waitForFunction(
        () => {
          const getBlockType = (window as any).wp?.blocks?.getBlockType;
          return getBlockType && getBlockType('blockstudio/canvas-order-stability-test');
        },
        null,
        { timeout: 30000 },
      );

      await page.waitForTimeout(2000);

      const slugsAfterBlockRefresh = await page.locator('[data-canvas-slug]').evaluateAll(
        (nodes) => nodes.map((node) => node.getAttribute('data-canvas-slug')),
      );
      expect(slugsAfterBlockRefresh).toEqual(slugs);
    });

    test('SSE changed event includes new page in changedPages', async () => {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await cleanupNewPage();

      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      await page.evaluate(() => {
        const canvasData = (window as any).blockstudioCanvas;
        const url =
          canvasData.restRoot +
          'blockstudio/v1/canvas/stream?_wpnonce=' +
          encodeURIComponent(canvasData.restNonce);

        const es = new EventSource(url);
        (window as any).__sseNewPagePromise = new Promise<void>((resolve) => {
          es.addEventListener('fingerprint', () => resolve());
        });
        (window as any).__sseNewPageChangedPromise = new Promise<Record<string, unknown>>(
          (resolve) => {
            es.addEventListener('changed', (e: MessageEvent) => {
              es.close();
              resolve(JSON.parse(e.data));
            });
          },
        );
        (window as any).__sseNewPageES = es;
      });

      await page.evaluate(() => (window as any).__sseNewPagePromise);

      fs.mkdirSync(newPageDir, { recursive: true });
      fs.writeFileSync(
        path.join(newPageDir, 'page.json'),
        JSON.stringify({
          name: 'canvas-new-page-test',
          title: 'Canvas New Page Test',
          slug: 'canvas-new-page-test',
          postType: 'page',
          postStatus: 'publish',
          templateLock: 'all',
        }),
      );
      fs.writeFileSync(
        path.join(newPageDir, 'index.php'),
        '<p>SSE new page detection test.</p>',
      );

      const sseData = await page.evaluate(
        () => (window as any).__sseNewPageChangedPromise,
      );

      expect(sseData).toHaveProperty('changedPages');
      expect((sseData.changedPages as string[]).length).toBeGreaterThan(0);
      expect(sseData).toHaveProperty('pages');
      expect((sseData.pages as any[]).length).toBeGreaterThan(0);

      const newPage = (sseData.pages as any[]).find(
        (p: any) => p.name === 'canvas-new-page-test',
      );
      expect(newPage).toBeTruthy();
      expect(newPage.title).toBe('Canvas New Page Test');
    });
  });

  test.describe('new block registration in live mode', () => {
    const newBlockDir = path.join(
      process.cwd(),
      'tests/theme/blockstudio/single/canvas-live-test',
    );
    const templatePath = path.join(
      process.cwd(),
      'tests/theme/pages/test-page/index.php',
    );
    const originalTemplate = fs.readFileSync(templatePath, 'utf-8');

    const cleanup = async (): Promise<void> => {
      if (fs.existsSync(newBlockDir)) {
        fs.rmSync(newBlockDir, { recursive: true });
      }
      fs.writeFileSync(templatePath, originalTemplate);
    };

    test.afterAll(async () => {
      await cleanup();
    });

    test('new block created and added to page renders without unsupported warning', async () => {
      await cleanup();

      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

      await page.waitForFunction(
        () => {
          const select = (window as any).wp?.data?.select;
          if (!select) return false;
          const store = select('blockstudio/canvas');
          return store && store.getFingerprint() !== '';
        },
        null,
        { timeout: 10000 },
      );

      // Create a new block on disk.
      fs.mkdirSync(newBlockDir, { recursive: true });
      fs.writeFileSync(
        path.join(newBlockDir, 'block.json'),
        JSON.stringify({
          $schema: 'https://app.blockstudio.dev/schema',
          name: 'blockstudio/canvas-live-test',
          title: 'Canvas Live Test',
          category: 'blockstudio-test-native',
          icon: 'star-filled',
          blockstudio: true,
        }),
      );
      fs.writeFileSync(
        path.join(newBlockDir, 'index.php'),
        '<?php\necho \'<div class="canvas-live-test-marker">Canvas Live Test Block</div>\';',
      );

      // Wait for the block to be registered client-side via SSE.
      await page.waitForFunction(
        () => {
          const getBlockType = (window as any).wp?.blocks?.getBlockType;
          return getBlockType && getBlockType('blockstudio/canvas-live-test');
        },
        null,
        { timeout: 20000 },
      );

      // Wait for the SSE stream to settle after the first event.
      await page.waitForTimeout(3000);

      // Add the new block to the test page template.
      fs.writeFileSync(
        templatePath,
        originalTemplate + '\n<block name="blockstudio/canvas-live-test" />',
      );

      // Wait for the page artboard revision to change.
      await page.waitForFunction(
        (slug: string) => {
          const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
          return artboard && artboard.getAttribute('data-canvas-revision') !== '0';
        },
        'blockstudio-e2e-test',
        { timeout: 30000 },
      );

      // Wait until the new block render is actually present in the artboard iframe.
      await page.waitForFunction(
        ({ slug, markerClass, markerText }) => {
          const artboard = document.querySelector(
            `[data-canvas-slug="${slug}"]`,
          );
          if (!artboard) return false;
          const iframe = artboard.querySelector(
            'iframe',
          ) as HTMLIFrameElement | null;
          if (!iframe) return false;
          const doc = iframe.contentDocument;
          if (!doc || !doc.body) return false;

          const bodyText = doc.body.textContent || '';
          const missingBlocks = doc.querySelectorAll('.wp-block-missing').length;
          const hasUnsupportedText = bodyText.includes("doesn't include support");
          const hasMarker =
            doc.querySelector(`.${markerClass}`) !== null ||
            bodyText.includes(markerText);

          return missingBlocks === 0 && !hasUnsupportedText && hasMarker;
        },
        {
          slug: 'blockstudio-e2e-test',
          markerClass: 'canvas-live-test-marker',
          markerText: 'Canvas Live Test Block',
        },
        { timeout: 60000 },
      );

      // Verify the artboard rendered the block's PHP output and has no unsupported warnings.
      const result = await page.evaluate((slug: string) => {
        const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
        if (!artboard) return { error: 'artboard not found' };
        const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
        if (!iframe) return { error: 'iframe not found' };
        const doc = iframe.contentDocument;
        if (!doc) return { error: 'contentDocument not accessible' };

        const bodyText = doc.body?.textContent || '';
        const missingBlocks: string[] = [];
        doc.querySelectorAll('.wp-block-missing').forEach((el) => {
          missingBlocks.push(el.textContent || '');
        });

        return {
          hasMarkerElement: doc.querySelector('.canvas-live-test-marker') !== null,
          hasMarkerText: bodyText.includes('Canvas Live Test Block'),
          missingBlocks,
          hasUnsupportedText: bodyText.includes("doesn't include support"),
        };
      }, 'blockstudio-e2e-test');

      expect(result).not.toHaveProperty('error');
      const r = result as {
        hasMarkerElement: boolean;
        hasMarkerText: boolean;
        missingBlocks: string[];
        hasUnsupportedText: boolean;
      };
      expect(r.missingBlocks).toEqual([]);
      expect(r.hasUnsupportedText).toBe(false);
      expect(r.hasMarkerElement).toBe(true);
      expect(r.hasMarkerText).toBe(true);
    });

    test('simultaneous block creation and page update renders without unsupported warning', async () => {
      await cleanup();

      const simultaneousBlockDir = path.join(
        process.cwd(),
        'tests/theme/blockstudio/single/canvas-simultaneous-test',
      );
      if (fs.existsSync(simultaneousBlockDir)) {
        fs.rmSync(simultaneousBlockDir, { recursive: true });
      }

      try {
        await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
        await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

        await page.waitForFunction(
          () => {
            const el = document.querySelector('[data-canvas-surface]');
            return el && window.getComputedStyle(el).transform !== 'none';
          },
          null,
          { timeout: 15000 },
        );

        const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
        await menuButton.click();
        await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
        await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

        await page.waitForFunction(
          () => {
            const select = (window as any).wp?.data?.select;
            if (!select) return false;
            const store = select('blockstudio/canvas');
            return store && store.getFingerprint() !== '';
          },
          null,
          { timeout: 10000 },
        );

        // Create block AND update page template simultaneously (no delay).
        fs.mkdirSync(simultaneousBlockDir, { recursive: true });
        fs.writeFileSync(
          path.join(simultaneousBlockDir, 'block.json'),
          JSON.stringify({
            $schema: 'https://app.blockstudio.dev/schema',
            name: 'blockstudio/canvas-simultaneous-test',
            title: 'Canvas Simultaneous Test',
            category: 'blockstudio-test-native',
            icon: 'star-filled',
            blockstudio: true,
          }),
        );
        fs.writeFileSync(
          path.join(simultaneousBlockDir, 'index.php'),
          '<?php\necho \'<div class="canvas-simultaneous-marker">Simultaneous Block Works</div>\';',
        );
        fs.writeFileSync(
          templatePath,
          originalTemplate + '\n<block name="blockstudio/canvas-simultaneous-test" />',
        );

        // Wait for the block to be registered client-side.
        await page.waitForFunction(
          () => {
            const getBlockType = (window as any).wp?.blocks?.getBlockType;
            return getBlockType && getBlockType('blockstudio/canvas-simultaneous-test');
          },
          null,
          { timeout: 30000 },
        );

        // Wait for the page artboard revision to change.
        await page.waitForFunction(
          (slug: string) => {
            const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
            return artboard && artboard.getAttribute('data-canvas-revision') !== '0';
          },
          'blockstudio-e2e-test',
          { timeout: 30000 },
        );

        // Wait until the new block render is actually present in the artboard iframe.
        await page.waitForFunction(
          ({ slug, markerClass, markerText }) => {
            const artboard = document.querySelector(
              `[data-canvas-slug="${slug}"]`,
            );
            if (!artboard) return false;
            const iframe = artboard.querySelector(
              'iframe',
            ) as HTMLIFrameElement | null;
            if (!iframe) return false;
            const doc = iframe.contentDocument;
            if (!doc || !doc.body) return false;

            const bodyText = doc.body.textContent || '';
            const missingBlocks = doc.querySelectorAll('.wp-block-missing').length;
            const hasUnsupportedText = bodyText.includes("doesn't include support");
            const hasMarker =
              doc.querySelector(`.${markerClass}`) !== null ||
              bodyText.includes(markerText);

            return missingBlocks === 0 && !hasUnsupportedText && hasMarker;
          },
          {
            slug: 'blockstudio-e2e-test',
            markerClass: 'canvas-simultaneous-marker',
            markerText: 'Simultaneous Block Works',
          },
          { timeout: 60000 },
        );

        // Verify the block rendered correctly with no unsupported warnings.
        const result = await page.evaluate((slug: string) => {
          const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
          if (!artboard) return { error: 'artboard not found' };
          const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
          if (!iframe) return { error: 'iframe not found' };
          const doc = iframe.contentDocument;
          if (!doc) return { error: 'contentDocument not accessible' };

          const bodyText = doc.body?.textContent || '';
          const missingBlocks: string[] = [];
          doc.querySelectorAll('.wp-block-missing').forEach((el) => {
            missingBlocks.push(el.textContent || '');
          });

          return {
            hasMarkerElement: doc.querySelector('.canvas-simultaneous-marker') !== null,
            hasMarkerText: bodyText.includes('Simultaneous Block Works'),
            missingBlocks,
            hasUnsupportedText: bodyText.includes("doesn't include support"),
          };
        }, 'blockstudio-e2e-test');

        expect(result).not.toHaveProperty('error');
        const r = result as {
          hasMarkerElement: boolean;
          hasMarkerText: boolean;
          missingBlocks: string[];
          hasUnsupportedText: boolean;
        };
        expect(r.missingBlocks).toEqual([]);
        expect(r.hasUnsupportedText).toBe(false);
        expect(r.hasMarkerElement).toBe(true);
        expect(r.hasMarkerText).toBe(true);
      } finally {
        if (fs.existsSync(simultaneousBlockDir)) {
          fs.rmSync(simultaneousBlockDir, { recursive: true });
        }
        fs.writeFileSync(templatePath, originalTemplate);
      }
    });

    test('block.json written before index.php still registers after template appears', async () => {
      await cleanup();

      const staggeredBlockDir = path.join(
        process.cwd(),
        'tests/theme/blockstudio/single/canvas-staggered-test',
      );
      if (fs.existsSync(staggeredBlockDir)) {
        fs.rmSync(staggeredBlockDir, { recursive: true });
      }

      try {
        await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
        await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

        await page.waitForFunction(
          () => {
            const el = document.querySelector('[data-canvas-surface]');
            return el && window.getComputedStyle(el).transform !== 'none';
          },
          null,
          { timeout: 15000 },
        );

        const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
        await menuButton.click();
        await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
        await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

        await page.waitForFunction(
          () => {
            const select = (window as any).wp?.data?.select;
            if (!select) return false;
            const store = select('blockstudio/canvas');
            return store && store.getFingerprint() !== '';
          },
          null,
          { timeout: 10000 },
        );

        // Write block.json FIRST, without index.php.
        fs.mkdirSync(staggeredBlockDir, { recursive: true });
        fs.writeFileSync(
          path.join(staggeredBlockDir, 'block.json'),
          JSON.stringify({
            $schema: 'https://app.blockstudio.dev/schema',
            name: 'blockstudio/canvas-staggered-test',
            title: 'Canvas Staggered Test',
            category: 'blockstudio-test-native',
            icon: 'star-filled',
            blockstudio: true,
          }),
        );

        // Wait for SSE to detect the partial block (at least 2 poll cycles).
        await page.waitForTimeout(3000);

        // Now write the template file.
        fs.writeFileSync(
          path.join(staggeredBlockDir, 'index.php'),
          '<?php\necho \'<div class="canvas-staggered-marker">Staggered Block</div>\';',
        );

        // Block should register client-side once the template appears.
        await page.waitForFunction(
          () => {
            const getBlockType = (window as any).wp?.blocks?.getBlockType;
            return getBlockType && getBlockType('blockstudio/canvas-staggered-test');
          },
          null,
          { timeout: 30000 },
        );

        // Add the block to the test page.
        fs.writeFileSync(
          templatePath,
          originalTemplate + '\n<block name="blockstudio/canvas-staggered-test" />',
        );

        await page.waitForFunction(
          (slug: string) => {
            const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
            return artboard && artboard.getAttribute('data-canvas-revision') !== '0';
          },
          'blockstudio-e2e-test',
          { timeout: 30000 },
        );

        // Wait until the new block render is actually present in the artboard iframe.
        await page.waitForFunction(
          ({ slug, markerClass, markerText }) => {
            const artboard = document.querySelector(
              `[data-canvas-slug="${slug}"]`,
            );
            if (!artboard) return false;
            const iframe = artboard.querySelector(
              'iframe',
            ) as HTMLIFrameElement | null;
            if (!iframe) return false;
            const doc = iframe.contentDocument;
            if (!doc || !doc.body) return false;

            const bodyText = doc.body.textContent || '';
            const missingBlocks = doc.querySelectorAll('.wp-block-missing').length;
            const hasUnsupportedText = bodyText.includes("doesn't include support");
            const hasMarker =
              doc.querySelector(`.${markerClass}`) !== null ||
              bodyText.includes(markerText);

            return missingBlocks === 0 && !hasUnsupportedText && hasMarker;
          },
          {
            slug: 'blockstudio-e2e-test',
            markerClass: 'canvas-staggered-marker',
            markerText: 'Staggered Block',
          },
          { timeout: 60000 },
        );

        const result = await page.evaluate((slug: string) => {
          const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
          if (!artboard) return { error: 'artboard not found' };
          const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
          if (!iframe) return { error: 'iframe not found' };
          const doc = iframe.contentDocument;
          if (!doc) return { error: 'contentDocument not accessible' };

          const bodyText = doc.body?.textContent || '';
          const missingBlocks: string[] = [];
          doc.querySelectorAll('.wp-block-missing').forEach((el) => {
            missingBlocks.push(el.textContent || '');
          });

          return {
            hasMarkerElement: doc.querySelector('.canvas-staggered-marker') !== null,
            hasMarkerText: bodyText.includes('Staggered Block'),
            missingBlocks,
            hasUnsupportedText: bodyText.includes("doesn't include support"),
          };
        }, 'blockstudio-e2e-test');

        expect(result).not.toHaveProperty('error');
        const r = result as {
          hasMarkerElement: boolean;
          hasMarkerText: boolean;
          missingBlocks: string[];
          hasUnsupportedText: boolean;
        };
        expect(r.missingBlocks).toEqual([]);
        expect(r.hasUnsupportedText).toBe(false);
        expect(r.hasMarkerElement).toBe(true);
        expect(r.hasMarkerText).toBe(true);
      } finally {
        if (fs.existsSync(staggeredBlockDir)) {
          fs.rmSync(staggeredBlockDir, { recursive: true });
        }
      }
    });
  });

  test.describe('block styles in iframes', () => {
    test('BlockPreview iframes contain blockstudio CSS', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const iframes = page.locator('#blockstudio-canvas iframe');
      await expect(iframes.first()).toBeVisible({ timeout: 15000 });

      const hasBlockstudioCSS = await page.evaluate(() => {
        const iframe = document.querySelector(
          '#blockstudio-canvas iframe',
        ) as HTMLIFrameElement | null;
        if (!iframe) return false;
        const doc = iframe.contentDocument;
        if (!doc) return false;
        return doc.documentElement.innerHTML.includes('blockstudio');
      });

      expect(hasBlockstudioCSS).toBe(true);
    });

    test('extension style tags persist after live refresh', async () => {
      const tplPath = path.join(
        process.cwd(),
        'tests/theme/pages/test-page/index.php',
      );
      let original: string | undefined;

      try {
        await page.evaluate(() =>
          localStorage.removeItem('blockstudio-canvas-settings'),
        );
        await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

        await page.waitForFunction(
          () => {
            const el = document.querySelector('[data-canvas-surface]');
            return el && window.getComputedStyle(el).transform !== 'none';
          },
          null,
          { timeout: 15000 },
        );

        const iframes = page.locator('#blockstudio-canvas iframe');
        await expect(iframes.first()).toBeVisible({ timeout: 15000 });

        // Verify extension style tags exist on initial render.
        const initialCount = await page.evaluate(() => {
          const frames = document.querySelectorAll(
            '#blockstudio-canvas iframe',
          );
          let count = 0;
          frames.forEach((f) => {
            const doc = (f as HTMLIFrameElement).contentDocument;
            if (!doc) return;
            count += doc.querySelectorAll('style[id^="blockstudio-"]').length;
          });
          return count;
        });

        expect(initialCount).toBeGreaterThan(0);

        // Enable live mode.
        const menuButton = page
          .locator('.blockstudio-canvas-menu .components-button')
          .first();
        await menuButton.click();
        await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
        await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

        // Wait for the initial fingerprint.
        await page.waitForFunction(
          () => {
            const select = (window as any).wp?.data?.select;
            if (!select) return false;
            const store = select('blockstudio/canvas');
            return store && store.getFingerprint() !== '';
          },
          null,
          { timeout: 10000 },
        );

        // Modify the template to trigger a refresh.
        original = fs.readFileSync(tplPath, 'utf-8');
        fs.writeFileSync(
          tplPath,
          original + '\n<p>extension style persistence marker</p>',
        );

        // Wait for the test page artboard revision to change.
        await page.waitForFunction(
          (slug: string) => {
            const ab = document.querySelector(
              `[data-canvas-slug="${slug}"]`,
            );
            return ab && ab.getAttribute('data-canvas-revision') !== '0';
          },
          'blockstudio-e2e-test',
          { timeout: 20000 },
        );

        // Give the new iframe time to render extension filters.
        await page.waitForTimeout(2000);

        // Verify extension style tags still exist after refresh.
        const afterCount = await page.evaluate(() => {
          const frames = document.querySelectorAll(
            '#blockstudio-canvas iframe',
          );
          let count = 0;
          frames.forEach((f) => {
            const doc = (f as HTMLIFrameElement).contentDocument;
            if (!doc) return;
            count += doc.querySelectorAll('style[id^="blockstudio-"]').length;
          });
          return count;
        });

        expect(afterCount).toBeGreaterThan(0);
      } finally {
        if (original) {
          fs.writeFileSync(tplPath, original);
        }
      }
    });
  });

  test.describe('tailwind CSS in live mode', () => {
    const blockTemplatePath = path.join(
      process.cwd(),
      'tests/theme/blockstudio/types/preload/simple/index.php',
    );
    let originalBlockTemplate: string;

    test.afterAll(async () => {
      if (originalBlockTemplate) {
        fs.writeFileSync(blockTemplatePath, originalBlockTemplate);
      }
    });

    test('SSE includes recompiled tailwind CSS when block template gains new classes', async () => {
      originalBlockTemplate = fs.readFileSync(blockTemplatePath, 'utf-8');

      try {
        await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

        await page.waitForFunction(
          () => {
            const el = document.querySelector('[data-canvas-surface]');
            return el && window.getComputedStyle(el).transform !== 'none';
          },
          null,
          { timeout: 15000 },
        );

        await page.evaluate(() => {
          const canvasData = (window as any).blockstudioCanvas;
          const url =
            canvasData.restRoot +
            'blockstudio/v1/canvas/stream?_wpnonce=' +
            encodeURIComponent(canvasData.restNonce);

          const es = new EventSource(url);
          (window as any).__twSSEFingerprintPromise = new Promise<void>((resolve) => {
            es.addEventListener('fingerprint', () => resolve());
          });
          (window as any).__twSSEChangedPromise = new Promise<Record<string, unknown>>(
            (resolve) => {
              es.addEventListener('changed', (e: MessageEvent) => {
                es.close();
                resolve(JSON.parse(e.data));
              });
            },
          );
        });

        await page.evaluate(() => (window as any).__twSSEFingerprintPromise);

        fs.writeFileSync(
          blockTemplatePath,
          originalBlockTemplate.replace(
            'class="preload-simple"',
            'class="preload-simple bg-fuchsia-700"',
          ),
        );

        const sseData: any = await page.evaluate(
          () => (window as any).__twSSEChangedPromise,
        );

        expect(sseData).toHaveProperty('tailwindCss');
        expect(typeof sseData.tailwindCss).toBe('string');
        expect(sseData.tailwindCss.length).toBeGreaterThan(0);
        expect(sseData.tailwindCss).toContain('fuchsia');
      } finally {
        fs.writeFileSync(blockTemplatePath, originalBlockTemplate);
      }
    });

    test('recompiled tailwind CSS applies styles in artboard iframe', async () => {
      originalBlockTemplate = fs.readFileSync(blockTemplatePath, 'utf-8');

      // Write the modified template BEFORE navigating so the initial page load
      // picks it up (avoids OPcache staleness in long-running SSE processes).
      fs.writeFileSync(
        blockTemplatePath,
        originalBlockTemplate.replace(
          'class="preload-simple"',
          'class="preload-simple bg-fuchsia-700"',
        ),
      );

      try {
        await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

        await page.waitForFunction(
          () => {
            const el = document.querySelector('[data-canvas-surface]');
            return el && window.getComputedStyle(el).transform !== 'none';
          },
          null,
          { timeout: 15000 },
        );

        await page.waitForTimeout(5000);

        const result = await page.evaluate(() => {
          const artboard = document.querySelector('[data-canvas-slug="blockstudio-e2e-test"]');
          if (!artboard) return { error: 'artboard not found' };
          const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
          if (!iframe) return { error: 'iframe not found' };
          const doc = iframe.contentDocument;
          if (!doc) return { error: 'contentDocument not accessible' };

          const el = doc.querySelector('.bg-fuchsia-700');
          if (!el) return { error: 'element with bg-fuchsia-700 not found' };

          const bg = iframe.contentWindow!.getComputedStyle(el).backgroundColor;

          return { backgroundColor: bg };
        });

        expect(result).not.toHaveProperty('error');
        // Tailwind v4 uses oklch color format.
        expect((result as any).backgroundColor).toContain('oklch');
      } finally {
        fs.writeFileSync(blockTemplatePath, originalBlockTemplate);
      }
    });
  });

  test.describe('interactions', () => {
    test('trackpad scroll pans the canvas', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

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

  test.describe('focus mode', () => {
    test('clicking label enters focus mode', async () => {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const firstLabel = page.locator('[data-canvas-label]').first();
      await expect(firstLabel).toBeVisible({ timeout: 15000 });
      await firstLabel.click();

      await expect(page.locator('[data-canvas-focus]')).toBeVisible();
    });

    test('focus overlay contains artboard iframe', async () => {
      const iframe = page.locator('[data-canvas-focus] iframe');
      await expect(iframe).toBeVisible({ timeout: 15000 });
    });

    test('focus overlay is scrollable', async () => {
      const overflowY = await page.locator('[data-canvas-focus]').evaluate(
        (el) => window.getComputedStyle(el).overflowY,
      );
      expect(overflowY).toBe('auto');
    });

    test('close button visible during focus', async () => {
      const closeButton = page.locator('.blockstudio-canvas-menu button[aria-label="Close focus mode"]');
      await expect(closeButton).toBeVisible();

      const dropdownMenu = page.locator('.blockstudio-canvas-menu button[aria-label="Canvas options"]');
      await expect(dropdownMenu).toHaveCount(0);
    });

    test('close button exits focus mode', async () => {
      const closeButton = page.locator('.blockstudio-canvas-menu button[aria-label="Close focus mode"]');
      await closeButton.click();

      await expect(page.locator('[data-canvas-focus]')).toHaveCount(0);
    });

    test('escape closes focus mode', async () => {
      const firstLabel = page.locator('[data-canvas-label]').first();
      await firstLabel.click();
      await expect(page.locator('[data-canvas-focus]')).toBeVisible();

      await page.keyboard.press('Escape');

      await expect(page.locator('[data-canvas-focus]')).toHaveCount(0);
    });

    test('dropdown menu returns after close', async () => {
      const dropdownMenu = page.locator('.blockstudio-canvas-menu button[aria-label="Canvas options"]');
      await expect(dropdownMenu).toBeVisible();
    });

    test('labels have pointer cursor', async () => {
      const cursor = await page.locator('[data-canvas-label]').first().evaluate(
        (el) => window.getComputedStyle(el).cursor,
      );
      expect(cursor).toBe('pointer');
    });
  });

  test.describe('blocks view', () => {
    test('blocks data is passed to JS', async () => {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      const blocks = await page.evaluate(() => (window as any).blockstudioCanvas?.blocks);

      expect(Array.isArray(blocks)).toBe(true);
      expect(blocks.length).toBeGreaterThan(0);

      const first = blocks[0];
      expect(first).toHaveProperty('title');
      expect(first).toHaveProperty('name');
      expect(first).toHaveProperty('content');
    });

    test('switching to blocks view shows block artboards', async () => {
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Blocks' }).click();
      await page.waitForTimeout(300);

      await expect(page.locator('[data-canvas-view="blocks"]')).toBeVisible();

      const artboards = page.locator('[data-canvas-slug]');
      await page.waitForFunction(
        () => {
          const nodes = Array.from(
            document.querySelectorAll('[data-canvas-slug]'),
          ) as HTMLElement[];
          if (nodes.length === 0) return false;
          return nodes.some((node) => {
            const style = window.getComputedStyle(node);
            return style.display !== 'none' && style.visibility !== 'hidden';
          });
        },
        null,
        { timeout: 30000 },
      );
      const count = await artboards.count();
      expect(count).toBeGreaterThan(0);
    });

    test('block labels have violet color', async () => {
      const labels = page.locator('[data-canvas-label]');
      await expect(labels.first()).toBeVisible({ timeout: 5000 });

      const color = await labels.first().evaluate(
        (el) => window.getComputedStyle(el).color,
      );
      expect(color).toBe('rgb(168, 85, 247)');
    });

    test('blocks view uses multi-column grid', async () => {
      const surface = page.locator('[data-canvas-surface]');
      const columns = await surface.evaluate(
        (el) => window.getComputedStyle(el).gridTemplateColumns,
      );
      const columnCount = columns.split(' ').length;
      expect(columnCount).toBeGreaterThan(1);
    });

    test('blocks artboard width is 800px', async () => {
      const artboard = page.locator('[data-canvas-slug]').first();
      const width = await artboard.evaluate((el) => (el as HTMLElement).offsetWidth);
      expect(width).toBe(800);
    });

    test('switching back to pages view restores page artboards', async () => {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(300);

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Pages' }).click();
      await page.waitForTimeout(300);

      await expect(page.locator('[data-canvas-view="pages"]')).toBeVisible();

      const artboards = page.locator('[data-canvas-slug]');
      await expect(artboards.first()).toBeVisible({ timeout: 15000 });

      const width = await artboards.first().evaluate((el) => (el as HTMLElement).offsetWidth);
      expect(width).toBe(1440);
    });

    test('view state persists in localStorage', async () => {
      await page.evaluate(() => {
        const dispatch = (window as any).wp?.data?.dispatch;
        if (dispatch) dispatch('blockstudio/canvas').setView('blocks');
      });
      await page.waitForTimeout(300);

      const stored = await page.evaluate(() => {
        const raw = localStorage.getItem('blockstudio-canvas-settings');
        return raw ? JSON.parse(raw) : null;
      });

      expect(stored).toBeTruthy();
      expect(stored.view).toBe('blocks');
    });

    test('refresh endpoint returns blocks data', async () => {
      const result = await page.evaluate(async () => {
        const apiFetch = (window as any).wp.apiFetch;
        return apiFetch({ path: '/blockstudio/v1/canvas/refresh' });
      });

      expect(result).toHaveProperty('blocks');
      expect(Array.isArray(result.blocks)).toBe(true);
      expect(result.blocks.length).toBeGreaterThan(0);

      const first = result.blocks[0];
      expect(first).toHaveProperty('title');
      expect(first).toHaveProperty('name');
      expect(first).toHaveProperty('content');
    });

    test('Pages menu item shows check icon in pages view', async () => {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });

      const pagesItem = page.locator('role=menuitem', { hasText: 'Pages' });
      const icon = pagesItem.locator('svg');
      await expect(icon).toBeVisible();

      const blocksItem = page.locator('role=menuitem', { hasText: 'Blocks' });
      const blocksIcon = blocksItem.locator('svg');
      await expect(blocksIcon).toHaveCount(0);
    });
  });

  test.describe('update queue', () => {
    const blockTemplatePath = path.join(
      process.cwd(),
      'tests/theme/blockstudio/types/preload/simple/index.php',
    );
    const pageTemplatePath = path.join(
      process.cwd(),
      'tests/theme/pages/test-page/index.php',
    );
    let originalBlockTemplate: string;
    let originalPageTemplate: string;

    test.beforeAll(() => {
      originalBlockTemplate = fs.readFileSync(blockTemplatePath, 'utf-8');
      originalPageTemplate = fs.readFileSync(pageTemplatePath, 'utf-8');
    });

    test.afterAll(() => {
      if (originalBlockTemplate) {
        fs.writeFileSync(blockTemplatePath, originalBlockTemplate);
      }
      if (originalPageTemplate) {
        fs.writeFileSync(pageTemplatePath, originalPageTemplate);
      }
    });

    async function setupLiveMode(): Promise<void> {
      await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

      await page.waitForFunction(
        () => {
          const select = (window as any).wp?.data?.select;
          if (!select) return false;
          const store = select('blockstudio/canvas');
          return store && store.getFingerprint() !== '';
        },
        null,
        { timeout: 10000 },
      );
    }

    test('block template update does not flash white on artboard', async () => {
      await setupLiveMode();

      await page.evaluate(() => {
        (window as any).__flashDetected = false;
        (window as any).__flashCheckDone = false;
        const check = (): void => {
          if ((window as any).__flashCheckDone) return;
          const artboard = document.querySelector('[data-canvas-slug="blockstudio-e2e-test"]');
          if (artboard) {
            artboard.querySelectorAll('iframe').forEach((iframe) => {
              const computed = window.getComputedStyle(iframe);
              if (computed.visibility !== 'visible') return;
              const doc = (iframe as HTMLIFrameElement).contentDocument;
              if (doc && doc.body && doc.body.children.length === 0) {
                (window as any).__flashDetected = true;
              }
            });
          }
          requestAnimationFrame(check);
        };
        requestAnimationFrame(check);
      });

      const initialRev = await page.locator('[data-canvas-slug="blockstudio-e2e-test"]')
        .getAttribute('data-canvas-revision');

      fs.writeFileSync(
        blockTemplatePath,
        originalBlockTemplate.replace(
          'class="preload-simple"',
          'class="preload-simple queue-flash-test"',
        ),
      );

      await page.waitForFunction(
        (args: { slug: string; prevRev: string }) => {
          const ab = document.querySelector(`[data-canvas-slug="${args.slug}"]`);
          return ab && ab.getAttribute('data-canvas-revision') !== args.prevRev;
        },
        { slug: 'blockstudio-e2e-test', prevRev: initialRev! },
        { timeout: 20000 },
      );

      await page.waitForTimeout(1000);

      const flashDetected = await page.evaluate(() => {
        (window as any).__flashCheckDone = true;
        return (window as any).__flashDetected;
      });

      expect(flashDetected).toBe(false);

      fs.writeFileSync(blockTemplatePath, originalBlockTemplate);
    });

    test('rapid block template edits coalesce into single artboard swap', async () => {
      await setupLiveMode();

      const initialRev = await page.locator('[data-canvas-slug="blockstudio-e2e-test"]')
        .getAttribute('data-canvas-revision');

      for (let i = 1; i <= 3; i++) {
        fs.writeFileSync(
          blockTemplatePath,
          originalBlockTemplate.replace(
            'class="preload-simple"',
            `class="preload-simple rapid-edit-${i}"`,
          ),
        );
        if (i < 3) {
          await page.waitForTimeout(100);
        }
      }

      await page.waitForFunction(
        (args: { slug: string; prevRev: string }) => {
          const ab = document.querySelector(`[data-canvas-slug="${args.slug}"]`);
          return ab && ab.getAttribute('data-canvas-revision') !== args.prevRev;
        },
        { slug: 'blockstudio-e2e-test', prevRev: initialRev! },
        { timeout: 20000 },
      );

      await page.waitForTimeout(5000);

      const finalRev = await page.locator('[data-canvas-slug="blockstudio-e2e-test"]')
        .getAttribute('data-canvas-revision');

      const revDiff = parseInt(finalRev!, 10) - parseInt(initialRev!, 10);
      // Allow up to 2 if the first event arrived before debounce could coalesce.
      expect(revDiff).toBeLessThanOrEqual(2);

      fs.writeFileSync(blockTemplatePath, originalBlockTemplate);
    });

    test('page update with block data in same SSE event renders correctly', async () => {
      await setupLiveMode();

      fs.writeFileSync(
        pageTemplatePath,
        originalPageTemplate + '\n<p class="combined-page-marker">Combined page update</p>',
      );

      await page.waitForFunction(
        (slug: string) => {
          const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
          if (!artboard) return false;
          const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
          if (!iframe) return false;
          const doc = iframe.contentDocument;
          if (!doc) return false;
          return (doc.body?.textContent || '').includes('Combined page update');
        },
        'blockstudio-e2e-test',
        { timeout: 20000 },
      );

      const result = await page.evaluate((slug: string) => {
        const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
        if (!artboard) return { error: 'artboard not found' };
        const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
        if (!iframe) return { error: 'iframe not found' };
        const doc = iframe.contentDocument;
        if (!doc) return { error: 'contentDocument not accessible' };

        const missingBlocks: string[] = [];
        doc.querySelectorAll('.wp-block-missing').forEach((el) => {
          missingBlocks.push(el.textContent || '');
        });

        return {
          hasPageMarker: (doc.body?.textContent || '').includes('Combined page update'),
          hasPreloadSimple: doc.querySelector('.preload-simple') !== null,
          missingBlocks,
        };
      }, 'blockstudio-e2e-test');

      expect(result).not.toHaveProperty('error');
      const r = result as {
        hasPageMarker: boolean;
        hasPreloadSimple: boolean;
        missingBlocks: string[];
      };
      expect(r.missingBlocks).toEqual([]);
      expect(r.hasPageMarker).toBe(true);
      expect(r.hasPreloadSimple).toBe(true);

      fs.writeFileSync(pageTemplatePath, originalPageTemplate);
    });

    test('second update while first is still swapping applies after first completes', async () => {
      await setupLiveMode();

      const initialRev = await page.locator('[data-canvas-slug="blockstudio-e2e-test"]')
        .getAttribute('data-canvas-revision');

      fs.writeFileSync(
        pageTemplatePath,
        originalPageTemplate + '\n<p class="sequential-edit-1">First sequential edit</p>',
      );

      await page.waitForFunction(
        (args: { slug: string; prevRev: string }) => {
          const ab = document.querySelector(`[data-canvas-slug="${args.slug}"]`);
          return ab && ab.getAttribute('data-canvas-revision') !== args.prevRev;
        },
        { slug: 'blockstudio-e2e-test', prevRev: initialRev! },
        { timeout: 20000 },
      );

      const midRev = await page.locator('[data-canvas-slug="blockstudio-e2e-test"]')
        .getAttribute('data-canvas-revision');

      fs.writeFileSync(
        pageTemplatePath,
        originalPageTemplate + '\n<p class="sequential-edit-2">Second sequential edit</p>',
      );

      await page.waitForFunction(
        (slug: string) => {
          const artboard = document.querySelector(`[data-canvas-slug="${slug}"]`);
          if (!artboard) return false;
          const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
          if (!iframe) return false;
          const doc = iframe.contentDocument;
          if (!doc) return false;
          return (doc.body?.textContent || '').includes('Second sequential edit');
        },
        'blockstudio-e2e-test',
        { timeout: 30000 },
      );

      const finalRev = await page.locator('[data-canvas-slug="blockstudio-e2e-test"]')
        .getAttribute('data-canvas-revision');
      expect(parseInt(finalRev!, 10)).toBeGreaterThan(parseInt(midRev!, 10));

      fs.writeFileSync(pageTemplatePath, originalPageTemplate);
    });

    test('adding a block tag with custom attrs renders with those attrs', async () => {
      await setupLiveMode();
      await page.waitForTimeout(2000);

      fs.writeFileSync(
        pageTemplatePath,
        originalPageTemplate + '\n<block name="blockstudio/type-preload-simple" title="Custom Attrs Test" />',
      );

      await page.waitForFunction(
        (slug: string) => {
          const ab = document.querySelector(`[data-canvas-slug="${slug}"]`);
          return ab && ab.getAttribute('data-canvas-revision') !== '0';
        },
        'blockstudio-e2e-test',
        { timeout: 20000 },
      );

      await page.waitForTimeout(3000);

      const result = await page.evaluate((slug: string) => {
        const ab = document.querySelector(`[data-canvas-slug="${slug}"]`);
        const iframe = ab?.querySelector('iframe') as HTMLIFrameElement | null;
        const doc = iframe?.contentDocument;
        if (!doc) return { error: 'no doc' };

        const titles: string[] = [];
        doc.querySelectorAll('.preload-title').forEach((el) => {
          titles.push(el.textContent || '');
        });

        return {
          preloadSimpleCount: doc.querySelectorAll('.preload-simple').length,
          preloadTitles: titles,
        };
      }, 'blockstudio-e2e-test');

      const r = result as any;
      expect(r.preloadTitles).toContain('Custom Attrs Test');
      expect(r.preloadSimpleCount).toBe(3);

      fs.writeFileSync(pageTemplatePath, originalPageTemplate);
    });

    test('adding a block tag with default attrs renders correctly', async () => {
      await setupLiveMode();
      await page.waitForTimeout(2000);

      fs.writeFileSync(
        pageTemplatePath,
        originalPageTemplate + '\n<block name="blockstudio/type-preload-simple" />',
      );

      await page.waitForFunction(
        (slug: string) => {
          const ab = document.querySelector(`[data-canvas-slug="${slug}"]`);
          return ab && ab.getAttribute('data-canvas-revision') !== '0';
        },
        'blockstudio-e2e-test',
        { timeout: 20000 },
      );

      await page.waitForTimeout(3000);

      const result = await page.evaluate((slug: string) => {
        const ab = document.querySelector(`[data-canvas-slug="${slug}"]`);
        const iframe = ab?.querySelector('iframe') as HTMLIFrameElement | null;
        const doc = iframe?.contentDocument;
        if (!doc) return { error: 'no doc' };

        const titles: string[] = [];
        doc.querySelectorAll('.preload-title').forEach((el) => {
          titles.push(el.textContent || '');
        });

        return {
          preloadSimpleCount: doc.querySelectorAll('.preload-simple').length,
          preloadTitles: titles,
        };
      }, 'blockstudio-e2e-test');

      const r = result as any;
      expect(r.preloadSimpleCount).toBe(3);

      fs.writeFileSync(pageTemplatePath, originalPageTemplate);
    });

    test('first blockstudio block added to page with none renders immediately', async () => {
      const newPageDir = path.join(
        process.cwd(),
        'tests/theme/pages/test-first-block',
      );

      const cleanupFirstBlockPage = async (): Promise<void> => {
        if (fs.existsSync(newPageDir)) {
          fs.rmSync(newPageDir, { recursive: true });
        }
        await page.evaluate(async () => {
          const apiFetch = (window as any).wp?.apiFetch;
          if (!apiFetch) return;
          try {
            const pages = await apiFetch({
              path: '/wp/v2/pages?slug=first-block-test&status=any&per_page=100',
            });
            for (const p of pages) {
              await apiFetch({ path: `/wp/v2/pages/${p.id}?force=true`, method: 'DELETE' });
            }
          } catch {
            // Post may not exist.
          }
        });
      };

      try {
        await page.evaluate(() => localStorage.removeItem('blockstudio-canvas-settings'));
        await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });
        await cleanupFirstBlockPage();
        await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

        await page.waitForFunction(
          () => {
            const el = document.querySelector('[data-canvas-surface]');
            return el && window.getComputedStyle(el).transform !== 'none';
          },
          null,
          { timeout: 15000 },
        );

        const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
        await menuButton.click();
        await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
        await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

        await page.waitForFunction(
          () => {
            const select = (window as any).wp?.data?.select;
            if (!select) return false;
            const store = select('blockstudio/canvas');
            return store && store.getFingerprint() !== '';
          },
          null,
          { timeout: 10000 },
        );

        fs.mkdirSync(newPageDir, { recursive: true });
        fs.writeFileSync(
          path.join(newPageDir, 'page.json'),
          JSON.stringify({
            name: 'first-block-test',
            title: 'First Block Test',
            slug: 'first-block-test',
            postType: 'page',
            postStatus: 'publish',
            templateLock: 'all',
          }),
        );
        fs.writeFileSync(
          path.join(newPageDir, 'index.php'),
          '<p>Page with no blocks yet.</p>',
        );

        await page.waitForFunction(
          () => !!document.querySelector('[data-canvas-slug="first-block-test"]'),
          null,
          { timeout: 20000 },
        );

        fs.writeFileSync(
          path.join(newPageDir, 'index.php'),
          '<p>Page with first block.</p>\n<block name="blockstudio/type-preload-simple" title="First Ever Block" />',
        );

        await page.waitForFunction(
          (slug: string) => {
            const ab = document.querySelector(`[data-canvas-slug="${slug}"]`);
            if (!ab) return false;
            const iframe = ab.querySelector('iframe') as HTMLIFrameElement | null;
            if (!iframe) return false;
            const doc = iframe.contentDocument;
            if (!doc) return false;
            return (doc.body?.textContent || '').includes('First Ever Block');
          },
          'first-block-test',
          { timeout: 25000 },
        );
      } finally {
        await cleanupFirstBlockPage();
      }
    });
  });
});

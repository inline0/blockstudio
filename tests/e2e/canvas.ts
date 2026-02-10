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
    test('poll endpoint returns fingerprint', async () => {
      const result = await page.evaluate(async () => {
        const apiFetch = (window as any).wp.apiFetch;
        return apiFetch({ path: '/blockstudio/v1/canvas/poll' });
      });

      expect(result).toHaveProperty('fingerprint');
      expect(typeof result.fingerprint).toBe('string');
      expect(result.fingerprint.length).toBeGreaterThan(0);
    });

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

    test('poll endpoint requires authentication', async ({ browser }) => {
      const anonContext = await browser.newContext();
      const anonPage = await anonContext.newPage();

      const response = await anonPage.evaluate(async () => {
        const res = await fetch('http://localhost:8888/wp-json/blockstudio/v1/canvas/poll');
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

    test('only changed page revision increments after live refresh', async () => {
      originalContent = fs.readFileSync(templatePath, 'utf-8');

      const menuButton = page.locator('.blockstudio-canvas-menu .components-button').first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

      // Wait for the initial fingerprint to be stored by the first poll.
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
      await expect(artboards.first()).toBeVisible({ timeout: 15000 });
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
});

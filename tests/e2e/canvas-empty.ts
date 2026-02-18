import * as fs from 'fs';
import * as path from 'path';

import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

let page: Page;
const canvasUrl = 'http://localhost:8890/wp-admin/admin.php?page=blockstudio-canvas';
const projectRoot = process.cwd();
const emptyThemePath = path.join(projectRoot, 'tests/theme-empty');

const cleanupDynamicFiles = (): void => {
  for (const dir of ['blockstudio', 'pages']) {
    const fullDir = path.join(emptyThemePath, dir);
    if (!fs.existsSync(fullDir)) continue;

    for (const entry of fs.readdirSync(fullDir)) {
      if (entry === '.gitkeep') continue;
      const entryPath = path.join(fullDir, entry);
      if (fs.statSync(entryPath).isDirectory()) {
        fs.rmSync(entryPath, { recursive: true });
      }
    }
  }
};

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });

  await login(page, 'http://localhost:8890');

  cleanupDynamicFiles();
});

test.afterAll(async () => {
  cleanupDynamicFiles();
  await page.close();
});

test.describe('Canvas - empty theme', () => {
  test('canvas loads with no artboards on empty theme', async () => {
    await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

    const root = page.locator('#blockstudio-canvas');
    await expect(root).toBeVisible({ timeout: 15000 });

    await expect(
      page.locator('text=No Blockstudio pages found.'),
    ).toBeVisible({ timeout: 15000 });

    const artboards = page.locator('[data-canvas-slug]');
    await expect(artboards).toHaveCount(0);
  });

  test('first block and page created from scratch register and render', async () => {
    const blockDir = path.join(emptyThemePath, 'blockstudio/empty-test-block');
    const pageDir = path.join(emptyThemePath, 'pages/empty-test-page');

    await page.evaluate(() =>
      localStorage.removeItem('blockstudio-canvas-settings'),
    );
    await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

    const root = page.locator('#blockstudio-canvas');
    await expect(root).toBeVisible({ timeout: 15000 });

    // Enable live mode.
    const menuButton = page
      .locator('.blockstudio-canvas-menu .components-button')
      .first();
    await menuButton.click();
    await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
    await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

    // Open a manual SSE stream to wait for the fingerprint.
    await page.evaluate(() => {
      const canvasData = (window as any).blockstudioCanvas;
      const url =
        canvasData.restRoot +
        'blockstudio/v1/canvas/stream?_wpnonce=' +
        encodeURIComponent(canvasData.restNonce);

      const es = new EventSource(url);
      (window as any).__emptyTestFingerprintPromise = new Promise<void>(
        (resolve) => {
          es.addEventListener('fingerprint', () => resolve());
        },
      );
      (window as any).__emptyTestES = es;
    });

    await page.evaluate(() => (window as any).__emptyTestFingerprintPromise);

    // Write block.json first, then template (staggered).
    fs.mkdirSync(blockDir, { recursive: true });
    fs.writeFileSync(
      path.join(blockDir, 'block.json'),
      JSON.stringify({
        $schema: 'https://app.blockstudio.dev/schema',
        name: 'blockstudio/empty-test-block',
        title: 'Empty Test Block',
        category: 'text',
        icon: 'star-filled',
        blockstudio: true,
      }),
    );

    // Wait for SSE to detect the incomplete block.
    await page.waitForTimeout(3000);

    // Now write the template file.
    fs.writeFileSync(
      path.join(blockDir, 'index.php'),
      '<?php\necho \'<div class="empty-test-marker">Empty Theme Block Works</div>\';',
    );

    // Block should register client-side once the template appears.
    await page.waitForFunction(
      () => {
        const getBlockType = (window as any).wp?.blocks?.getBlockType;
        return getBlockType && getBlockType('blockstudio/empty-test-block');
      },
      null,
      { timeout: 30000 },
    );

    await page.waitForTimeout(3000);

    // Create a page that uses the block.
    fs.mkdirSync(pageDir, { recursive: true });
    fs.writeFileSync(
      path.join(pageDir, 'page.json'),
      JSON.stringify({
        name: 'empty-test-page',
        title: 'Empty Test Page',
        slug: 'empty-test-page',
        postType: 'page',
        postStatus: 'publish',
        templateLock: 'all',
      }),
    );
    fs.writeFileSync(
      path.join(pageDir, 'index.php'),
      '<block name="blockstudio/empty-test-block" />',
    );

    // Wait for the new artboard to appear.
    await page.waitForFunction(
      () => document.querySelectorAll('[data-canvas-slug]').length > 0,
      null,
      { timeout: 30000 },
    );

    // Wait for the iframe to finish rendering.
    await page.waitForTimeout(5000);

    const result = await page.evaluate(() => {
      const artboard = document.querySelector(
        '[data-canvas-slug="empty-test-page"]',
      );
      if (!artboard) return { error: 'artboard not found' };
      const iframe = artboard.querySelector(
        'iframe',
      ) as HTMLIFrameElement | null;
      if (!iframe) return { error: 'iframe not found' };
      const doc = iframe.contentDocument;
      if (!doc) return { error: 'contentDocument not accessible' };

      const bodyText = doc.body?.textContent || '';
      const missingBlocks: string[] = [];
      doc.querySelectorAll('.wp-block-missing').forEach((el) => {
        missingBlocks.push(el.textContent || '');
      });

      return {
        hasMarkerElement: doc.querySelector('.empty-test-marker') !== null,
        hasMarkerText: bodyText.includes('Empty Theme Block Works'),
        missingBlocks,
        hasUnsupportedText: bodyText.includes("doesn't include support"),
      };
    });

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

  test('block created from scratch with no prior blockstudio dir renders without unsupported warning', async () => {
    const blockstudioDir = path.join(emptyThemePath, 'blockstudio');
    const pagesDir = path.join(emptyThemePath, 'pages');
    const blockDir = path.join(blockstudioDir, 'no-dir-test-block');
    const pageDir = path.join(pagesDir, 'no-dir-test-page');

    // Remove the blockstudio directory entirely so Build::init() has no
    // instance to register. This simulates a brand-new theme.
    if (fs.existsSync(blockstudioDir)) {
      fs.rmSync(blockstudioDir, { recursive: true });
    }

    try {
      await page.evaluate(() =>
        localStorage.removeItem('blockstudio-canvas-settings'),
      );
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      const root = page.locator('#blockstudio-canvas');
      await expect(root).toBeVisible({ timeout: 15000 });

      // Enable live mode.
      const menuButton = page
        .locator('.blockstudio-canvas-menu .components-button')
        .first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

      // Wait for SSE to establish.
      await page.evaluate(() => {
        const canvasData = (window as any).blockstudioCanvas;
        const url =
          canvasData.restRoot +
          'blockstudio/v1/canvas/stream?_wpnonce=' +
          encodeURIComponent(canvasData.restNonce);

        const es = new EventSource(url);
        (window as any).__noDirTestFpPromise = new Promise<void>((resolve) => {
          es.addEventListener('fingerprint', () => resolve());
        });
        (window as any).__noDirTestES = es;
      });

      await page.evaluate(() => (window as any).__noDirTestFpPromise);

      // Create block and page simultaneously (like Claude Code does).
      fs.mkdirSync(blockDir, { recursive: true });
      fs.writeFileSync(
        path.join(blockDir, 'block.json'),
        JSON.stringify({
          $schema: 'https://app.blockstudio.dev/schema',
          name: 'blockstudio/no-dir-test-block',
          title: 'No Dir Test Block',
          category: 'text',
          icon: 'star-filled',
          blockstudio: true,
        }),
      );
      fs.writeFileSync(
        path.join(blockDir, 'index.php'),
        "<?php\necho '<div class=\"no-dir-test-marker\">No Dir Block Works</div>';",
      );

      fs.mkdirSync(pageDir, { recursive: true });
      fs.writeFileSync(
        path.join(pageDir, 'page.json'),
        JSON.stringify({
          name: 'no-dir-test-page',
          title: 'No Dir Test Page',
          slug: 'no-dir-test-page',
          postType: 'page',
          postStatus: 'publish',
          templateLock: 'all',
        }),
      );
      fs.writeFileSync(
        path.join(pageDir, 'index.php'),
        '<block name="blockstudio/no-dir-test-block" />',
      );

      // Wait for the artboard.
      await page.waitForFunction(
        () => document.querySelectorAll('[data-canvas-slug]').length > 0,
        null,
        { timeout: 30000 },
      );

      await page.waitForTimeout(5000);

      const result = await page.evaluate(() => {
        const artboard = document.querySelector(
          '[data-canvas-slug="no-dir-test-page"]',
        );
        if (!artboard) return { error: 'artboard not found' };
        const iframe = artboard.querySelector(
          'iframe',
        ) as HTMLIFrameElement | null;
        if (!iframe) return { error: 'iframe not found' };
        const doc = iframe.contentDocument;
        if (!doc) return { error: 'contentDocument not accessible' };

        const bodyText = doc.body?.textContent || '';
        const missingBlocks: string[] = [];
        doc.querySelectorAll('.wp-block-missing').forEach((el) => {
          missingBlocks.push(el.textContent || '');
        });

        return {
          hasMarkerElement: doc.querySelector('.no-dir-test-marker') !== null,
          hasMarkerText: bodyText.includes('No Dir Block Works'),
          missingBlocks,
          hasUnsupportedText: bodyText.includes("doesn't include support"),
        };
      });

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
      // Restore the blockstudio directory with .gitkeep.
      if (!fs.existsSync(blockstudioDir)) {
        fs.mkdirSync(blockstudioDir, { recursive: true });
      }
      fs.writeFileSync(path.join(blockstudioDir, '.gitkeep'), '');

      // Clean up page.
      if (fs.existsSync(pageDir)) {
        fs.rmSync(pageDir, { recursive: true });
      }
    }
  });
});

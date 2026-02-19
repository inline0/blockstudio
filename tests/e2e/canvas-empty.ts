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

  await fetch('http://localhost:8890/wp-json/blockstudio-test/v1/cleanup', {
    method: 'POST',
  });
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

  test('existing page updated to use a brand new block renders without second edit', async () => {
    const blockstudioDir = path.join(emptyThemePath, 'blockstudio');
    const blockDir = path.join(blockstudioDir, 'replace-test-block');
    const pageDir = path.join(emptyThemePath, 'pages/replace-test-page');

    if (fs.existsSync(blockstudioDir)) {
      fs.rmSync(blockstudioDir, { recursive: true });
    }

    try {
      // Create a page with plain HTML first.
      fs.mkdirSync(pageDir, { recursive: true });
      fs.writeFileSync(
        path.join(pageDir, 'page.json'),
        JSON.stringify({
          name: 'replace-test-page',
          title: 'Replace Test Page',
          slug: 'replace-test-page',
          postType: 'page',
          postStatus: 'publish',
          templateLock: 'all',
        }),
      );
      fs.writeFileSync(
        path.join(pageDir, 'index.php'),
        '<h1>Hello World</h1>',
      );

      await page.evaluate(() =>
        localStorage.removeItem('blockstudio-canvas-settings'),
      );
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      const root = page.locator('#blockstudio-canvas');
      await expect(root).toBeVisible({ timeout: 15000 });

      await page.waitForFunction(
        () => {
          const ab = document.querySelector('[data-canvas-slug="replace-test-page"]');
          if (!ab) return false;
          const iframe = ab.querySelector('iframe') as HTMLIFrameElement | null;
          if (!iframe) return false;
          const doc = iframe.contentDocument;
          if (!doc) return false;
          return (doc.body?.textContent || '').includes('Hello World');
        },
        null,
        { timeout: 30000 },
      );

      const menuButton = page
        .locator('.blockstudio-canvas-menu .components-button')
        .first();
      await menuButton.click();
      await expect(page.locator('role=menu')).toBeVisible({ timeout: 5000 });
      await page.locator('role=menuitem', { hasText: 'Live mode' }).click();

      await page.evaluate(() => {
        const canvasData = (window as any).blockstudioCanvas;
        const url =
          canvasData.restRoot +
          'blockstudio/v1/canvas/stream?_wpnonce=' +
          encodeURIComponent(canvasData.restNonce);

        const es = new EventSource(url);
        (window as any).__replaceTestFpPromise = new Promise<void>(
          (resolve) => {
            es.addEventListener('fingerprint', () => resolve());
          },
        );
        (window as any).__replaceTestES = es;
      });

      await page.evaluate(() => (window as any).__replaceTestFpPromise);

      // Simulate a real agent: write block.json first (SSE may poll and see
      // an incomplete block), then after a delay write index.php and the page
      // update back-to-back. The SSE poll that picks up the complete block may
      // miss the page change if prev_mtimes already captured the new page mtime.
      fs.mkdirSync(blockDir, { recursive: true });
      fs.writeFileSync(
        path.join(blockDir, 'block.json'),
        JSON.stringify({
          $schema: 'https://app.blockstudio.dev/schema',
          name: 'blockstudio/replace-test-block',
          title: 'Replace Test Block',
          category: 'text',
          icon: 'star-filled',
          blockstudio: true,
        }),
      );

      // Wait for the SSE to poll and see block.json (incomplete block).
      await page.waitForTimeout(1500);

      // Write index.php and page update back-to-back (same write batch).
      fs.writeFileSync(
        path.join(blockDir, 'index.php'),
        '<?php\necho \'<div class="replace-test-marker">Replaced Content Works</div>\';',
      );
      fs.writeFileSync(
        path.join(pageDir, 'index.php'),
        '<block name="blockstudio/replace-test-block" />',
      );

      // The block should register AND the page should update.
      await page.waitForFunction(
        () => {
          const ab = document.querySelector(
            '[data-canvas-slug="replace-test-page"]',
          );
          if (!ab) return false;
          const iframe = ab.querySelector('iframe') as HTMLIFrameElement | null;
          if (!iframe) return false;
          const doc = iframe.contentDocument;
          if (!doc) return false;
          return (doc.body?.textContent || '').includes(
            'Replaced Content Works',
          );
        },
        null,
        { timeout: 30000 },
      );
    } finally {
      if (!fs.existsSync(blockstudioDir)) {
        fs.mkdirSync(blockstudioDir, { recursive: true });
      }
      fs.writeFileSync(path.join(blockstudioDir, '.gitkeep'), '');

      if (fs.existsSync(pageDir)) {
        fs.rmSync(pageDir, { recursive: true });
      }
    }
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

      // Wait for the specific new artboard and its rendered block content.
      await page.waitForFunction(
        () => {
          const artboard = document.querySelector(
            '[data-canvas-slug="no-dir-test-page"]',
          );
          if (!artboard) return false;
          const iframe = artboard.querySelector(
            'iframe',
          ) as HTMLIFrameElement | null;
          if (!iframe) return false;
          const doc = iframe.contentDocument;
          if (!doc || !doc.body) return false;
          return (doc.body.textContent || '').includes('No Dir Block Works');
        },
        null,
        { timeout: 30000 },
      );

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

  test('creating an unused block template does not visibly flash existing artboard', async () => {
    const baseBlockDir = path.join(emptyThemePath, 'blockstudio/flash-base-block');
    const unusedBlockDir = path.join(emptyThemePath, 'blockstudio/unused-flash-block');
    const pageDirA = path.join(emptyThemePath, 'pages/flash-existing-page-a');
    const pageDirB = path.join(emptyThemePath, 'pages/flash-existing-page-b');

    const cleanup = (): void => {
      if (fs.existsSync(baseBlockDir)) {
        fs.rmSync(baseBlockDir, { recursive: true });
      }
      if (fs.existsSync(unusedBlockDir)) {
        fs.rmSync(unusedBlockDir, { recursive: true });
      }
      if (fs.existsSync(pageDirA)) {
        fs.rmSync(pageDirA, { recursive: true });
      }
      if (fs.existsSync(pageDirB)) {
        fs.rmSync(pageDirB, { recursive: true });
      }
    };

    cleanup();

    try {
      fs.mkdirSync(baseBlockDir, { recursive: true });
      fs.writeFileSync(
        path.join(baseBlockDir, 'block.json'),
        JSON.stringify({
          $schema: 'https://app.blockstudio.dev/schema',
          name: 'blockstudio/flash-base-block',
          title: 'Flash Base Block',
          category: 'text',
          icon: 'star-filled',
          blockstudio: true,
        }),
      );
      fs.writeFileSync(
        path.join(baseBlockDir, 'index.php'),
        "<?php\necho '<div class=\"flash-base-marker\">Flash Base Block</div>';",
      );

      fs.mkdirSync(pageDirA, { recursive: true });
      fs.mkdirSync(pageDirB, { recursive: true });
      fs.writeFileSync(
        path.join(pageDirA, 'page.json'),
        JSON.stringify({
          name: 'flash-existing-page-a',
          title: 'Flash Existing Page A',
          slug: 'flash-existing-page-a',
          postType: 'page',
          postStatus: 'publish',
          templateLock: 'all',
        }),
      );
      fs.writeFileSync(
        path.join(pageDirA, 'index.php'),
        '<block name="blockstudio/flash-base-block" />',
      );
      fs.writeFileSync(
        path.join(pageDirB, 'page.json'),
        JSON.stringify({
          name: 'flash-existing-page-b',
          title: 'Flash Existing Page B',
          slug: 'flash-existing-page-b',
          postType: 'page',
          postStatus: 'publish',
          templateLock: 'all',
        }),
      );
      fs.writeFileSync(
        path.join(pageDirB, 'index.php'),
        '<block name="blockstudio/flash-base-block" /><p>Second artboard</p>',
      );

      await page.evaluate(() =>
        localStorage.removeItem('blockstudio-canvas-settings'),
      );
      await page.goto(canvasUrl, { waitUntil: 'domcontentloaded' });

      const root = page.locator('#blockstudio-canvas');
      await expect(root).toBeVisible({ timeout: 15000 });

      await page.waitForFunction(
        () => {
          const el = document.querySelector('[data-canvas-surface]');
          return el && window.getComputedStyle(el).transform !== 'none';
        },
        null,
        { timeout: 15000 },
      );

      await page.waitForFunction(
        () => {
          const slugs = ['flash-existing-page-a', 'flash-existing-page-b'];
          for (const slug of slugs) {
            const artboard = document.querySelector(
              `[data-canvas-slug="${slug}"]`,
            );
            if (!artboard) return false;
            const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;
            if (!iframe) return false;
            const doc = iframe.contentDocument;
            if (!doc || !doc.body) return false;
            const text = doc.body.textContent || '';
            if (!text.includes('Flash Base Block')) return false;
          }
          return true;
        },
        null,
        { timeout: 30000 },
      );

      const menuButton = page
        .locator('.blockstudio-canvas-menu .components-button')
        .first();
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

      // Let the initial live sync settle before starting the probe.
      await page.waitForTimeout(1200);

      await page.evaluate(() => {
        const trackedSlugs = new Set([
          'flash-existing-page-a',
          'flash-existing-page-b',
        ]);

        (window as any).__unusedFlashProbe = {
          done: false,
          frames: 0,
          flashDetected: false,
          emptyBodyDetected: false,
          nullDocDetected: false,
          contentDropDetected: false,
          replacedVisibleIframeDetected: false,
          srcChangedVisibleIframeDetected: false,
          trackedSlugs: Array.from(trackedSlugs),
          maxBodyLengthBySlug: {} as Record<string, number>,
          minBodyLengthBySlug: {} as Record<string, number>,
          iframeBySlug: {} as Record<string, HTMLIFrameElement>,
          srcBySlug: {} as Record<string, string | null>,
        };

        const tick = (): void => {
          const probe = (window as any).__unusedFlashProbe;
          if (!probe || probe.done) return;
          probe.frames += 1;

          const artboards = document.querySelectorAll('[data-canvas-slug]');
          artboards.forEach((artboard) => {
            const slug = artboard.getAttribute('data-canvas-slug') || '';
            if (!trackedSlugs.has(slug)) return;
            const iframe = artboard.querySelector('iframe') as HTMLIFrameElement | null;

            if (!iframe) {
              probe.flashDetected = true;
              probe.nullDocDetected = true;
              return;
            }

            const style = window.getComputedStyle(iframe);
            const isVisible =
              style.visibility !== 'hidden' && style.display !== 'none';
            if (!isVisible) return;

            if (
              probe.iframeBySlug[slug] &&
              probe.iframeBySlug[slug] !== iframe
            ) {
              probe.flashDetected = true;
              probe.replacedVisibleIframeDetected = true;
            }
            probe.iframeBySlug[slug] = iframe;

            const src = iframe.getAttribute('src');
            if (
              probe.srcBySlug[slug] !== undefined &&
              probe.srcBySlug[slug] !== src
            ) {
              probe.flashDetected = true;
              probe.srcChangedVisibleIframeDetected = true;
            }
            probe.srcBySlug[slug] = src;

            const doc = iframe.contentDocument;
            if (!doc || !doc.body) {
              probe.flashDetected = true;
              probe.nullDocDetected = true;
              return;
            }

            const bodyLength = doc.body.innerHTML.length;
            const maxBodyLength = Math.max(
              probe.maxBodyLengthBySlug[slug] || 0,
              bodyLength,
            );
            const minBodyLength = Math.min(
              probe.minBodyLengthBySlug[slug] ?? Number.MAX_SAFE_INTEGER,
              bodyLength,
            );
            probe.maxBodyLengthBySlug[slug] = maxBodyLength;
            probe.minBodyLengthBySlug[slug] = minBodyLength;

            if (doc.body.children.length === 0) {
              probe.flashDetected = true;
              probe.emptyBodyDetected = true;
            }

            const dropThreshold = Math.max(30, Math.floor(maxBodyLength * 0.2));
            if (maxBodyLength > 60 && bodyLength < dropThreshold) {
              probe.flashDetected = true;
              probe.contentDropDetected = true;
            }

            if (!doc.body.textContent?.includes('Flash Base Block')) {
              const overlay = document.querySelector(
                '[data-canvas-freeze-overlay]',
              );
              if (!overlay) {
                probe.flashDetected = true;
              }
            }
          });

          requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
      });

      // Create a brand-new block template on disk, but do not add it to the page.
      fs.mkdirSync(unusedBlockDir, { recursive: true });
      fs.writeFileSync(
        path.join(unusedBlockDir, 'block.json'),
        JSON.stringify({
          $schema: 'https://app.blockstudio.dev/schema',
          name: 'blockstudio/unused-flash-block',
          title: 'Unused Flash Block',
          category: 'text',
          icon: 'star-filled',
          blockstudio: true,
        }),
      );
      fs.writeFileSync(
        path.join(unusedBlockDir, 'index.php'),
        "<?php\necho '<div class=\"unused-flash-block-marker\">Unused Flash Block</div>';",
      );

      await page.waitForFunction(
        () => {
          const getBlockType = (window as any).wp?.blocks?.getBlockType;
          return getBlockType && getBlockType('blockstudio/unused-flash-block');
        },
        null,
        { timeout: 30000 },
      );

      await page.waitForTimeout(2000);

      const probe = await page.evaluate(() => {
        const state = (window as any).__unusedFlashProbe;
        if (state) {
          state.done = true;
        }
        return state;
      });

      expect(probe).toBeTruthy();
      expect((probe as any).frames).toBeGreaterThan(20);
      expect((probe as any).flashDetected).toBe(false);
    } finally {
      cleanup();
    }
  });
});

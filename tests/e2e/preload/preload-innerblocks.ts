import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;

// 3 levels deep: bare-spaceless > preload-simple("Level 0") + bare > preload-simple("Level 1") + bare-spaceless > preload-simple("Level 2")
const BLOCK_CONTENT = `<!-- wp:blockstudio/component-innerblocks-bare-spaceless -->
<!-- wp:blockstudio/type-preload-simple {"blockstudio":{"attributes":{"title":"Level 0","count":10}}} /-->
<!-- wp:blockstudio/component-innerblocks-bare -->
<!-- wp:blockstudio/type-preload-simple {"blockstudio":{"attributes":{"title":"Level 1","count":20}}} /-->
<!-- wp:blockstudio/component-innerblocks-bare-spaceless -->
<!-- wp:blockstudio/type-preload-simple {"blockstudio":{"attributes":{"title":"Level 2","count":30}}} /-->
<!-- /wp:blockstudio/component-innerblocks-bare-spaceless -->
<!-- /wp:blockstudio/component-innerblocks-bare -->
<!-- /wp:blockstudio/component-innerblocks-bare-spaceless -->`;

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser, request }) => {
  const res = await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: BLOCK_CONTENT },
  });
  expect(res.ok()).toBeTruthy();

  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async ({ request }) => {
  await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: '' },
  });
  await page.close();
});

test.describe('Preload InnerBlocks Multi-Depth', () => {
  test('all blocks at all levels preloaded', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`);
    await getEditorCanvas(page);

    await page.waitForFunction(
      () => {
        const bs = (window as any).blockstudio;
        if (!bs?.blockstudioBlocks) return false;
        const blocks = bs.blockstudioBlocks;
        const entries = (Array.isArray(blocks) ? blocks : Object.values(blocks)) as any[];
        const simpleBlocks = entries.filter(
          (e: any) => e.blockName === 'blockstudio/type-preload-simple',
        );
        const containerBlocks = entries.filter(
          (e: any) =>
            e.blockName === 'blockstudio/component-innerblocks-bare-spaceless' ||
            e.blockName === 'blockstudio/component-innerblocks-bare',
        );
        return simpleBlocks.length === 3 && containerBlocks.length === 3;
      },
      { timeout: 30000 },
    );

    const data = await page.evaluate(() => {
      const raw = (window as any).blockstudio.blockstudioBlocks;
      const entries = (Array.isArray(raw) ? raw : Object.values(raw)) as any[];
      return {
        total: entries.length,
        allRendered: entries.every(
          (e: any) => typeof e.rendered === 'string' && e.rendered.length > 0,
        ),
      };
    });

    expect(data.total).toBe(6);
    expect(data.allRendered).toBe(true);
  });

  test('no render API calls', async () => {
    const renderCalls: string[] = [];

    page.on('request', (req) => {
      const url = req.url();
      if (url.includes('/blockstudio/v1/gutenberg/block/render/')) {
        renderCalls.push(url);
      }
    });

    await page.reload();
    await getEditorCanvas(page);
    await page.waitForTimeout(5000);

    expect(renderCalls).toEqual([]);
  });

  test('depth-first FIFO order matches block content', async () => {
    const canvas = await getEditorCanvas(page);

    // Wait for deepest block to confirm all levels rendered
    await canvas.waitForSelector('.preload-simple[data-title="Level 2"]', {
      timeout: 15000,
    });

    const titles = await canvas.locator('.preload-simple .preload-title').allTextContents();
    expect(titles).toEqual(['Level 0', 'Level 1', 'Level 2']);

    const counts = await canvas.locator('.preload-simple .preload-count').allTextContents();
    expect(counts).toEqual(['10', '20', '30']);
  });
});

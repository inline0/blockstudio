import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;

const INSTANCES = [
  { title: 'First', count: 1 },
  { title: 'Second', count: 2 },
  { title: 'Third', count: 3 },
];

function generateBlockContent(): string {
  return INSTANCES.map(
    (inst) =>
      `<!-- wp:blockstudio/type-preload-simple {"blockstudio":{"attributes":{"title":"${inst.title}","count":${inst.count},"active":true}}} /-->`,
  ).join('\n');
}

const BLOCK_CONTENT = generateBlockContent();

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

test.describe('Same Block FIFO Ordering', () => {
  test('all 3 instances preloaded with correct count', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`);
    await getEditorCanvas(page);

    await page.waitForFunction(
      () => {
        const bs = (window as any).blockstudio;
        if (!bs?.blockstudioBlocks) return false;
        const blocks = bs.blockstudioBlocks;
        const entries = Array.isArray(blocks) ? blocks : Object.values(blocks);
        return (entries as any[]).filter(
          (e: any) => e.blockName === 'blockstudio/type-preload-simple',
        ).length === 3;
      },
      { timeout: 30000 },
    );

    const data = await page.evaluate(() => {
      const raw = (window as any).blockstudio.blockstudioBlocks;
      const entries = (Array.isArray(raw) ? raw : Object.values(raw)) as any[];
      return entries
        .filter((e: any) => e.blockName === 'blockstudio/type-preload-simple')
        .map((e: any) => e.rendered);
    });

    expect(data).toHaveLength(3);
    expect(data.every((r: string) => typeof r === 'string' && r.length > 0)).toBe(true);
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

  test('each block renders correct content in FIFO order', async () => {
    const canvas = await getEditorCanvas(page);

    for (const inst of INSTANCES) {
      await canvas.waitForSelector(`.preload-simple[data-title="${inst.title}"]`, {
        timeout: 15000,
      });
    }

    const titles = await canvas.locator('.preload-simple .preload-title').allTextContents();
    expect(titles).toEqual(['First', 'Second', 'Third']);

    const counts = await canvas.locator('.preload-simple .preload-count').allTextContents();
    expect(counts).toEqual(['1', '2', '3']);
  });
});

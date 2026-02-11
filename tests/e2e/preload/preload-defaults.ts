import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;

// Block 1: preload-simple with all defaults (no attrs in markup)
// Block 2: preload-simple with partial override (title only)
// Block 3: preload-no-defaults with explicit values
// Block 4: preload-simple with zero value for count
const BLOCK_CONTENT = [
  '<!-- wp:blockstudio/type-preload-simple /-->',
  '<!-- wp:blockstudio/type-preload-simple {"blockstudio":{"attributes":{"title":"Override"}}} /-->',
  '<!-- wp:blockstudio/type-preload-no-defaults {"blockstudio":{"attributes":{"title":"Explicit","count":42,"active":true}}} /-->',
  '<!-- wp:blockstudio/type-preload-simple {"blockstudio":{"attributes":{"count":0}}} /-->',
].join('\n');

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

test.describe('Preload Defaults and Zero Values', () => {
  test('all 4 blocks preloaded', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`);
    await getEditorCanvas(page);

    await page.waitForFunction(
      () => {
        const bs = (window as any).blockstudio;
        if (!bs?.blockstudioBlocks) return false;
        const blocks = bs.blockstudioBlocks;
        const entries = Array.isArray(blocks) ? blocks : Object.values(blocks);
        return (entries as any[]).length >= 4;
      },
      { timeout: 30000 },
    );

    const count = await page.evaluate(() => {
      const raw = (window as any).blockstudio.blockstudioBlocks;
      const entries = Array.isArray(raw) ? raw : Object.values(raw);
      return (entries as any[]).length;
    });

    expect(count).toBe(4);
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

  test('block with all defaults renders correctly', async () => {
    const canvas = await getEditorCanvas(page);
    const blocks = canvas.locator('.preload-simple');
    await expect(blocks.first()).toBeVisible({ timeout: 15000 });

    // First preload-simple: defaults (title="Hello", count=5)
    const firstTitle = await blocks.nth(0).locator('.preload-title').textContent();
    const firstCount = await blocks.nth(0).locator('.preload-count').textContent();
    expect(firstTitle).toBe('Hello');
    expect(firstCount).toBe('5');
  });

  test('block with partial override renders correctly', async () => {
    const canvas = await getEditorCanvas(page);
    const blocks = canvas.locator('.preload-simple');

    // Second preload-simple: title overridden, count defaults
    const title = await blocks.nth(1).locator('.preload-title').textContent();
    const count = await blocks.nth(1).locator('.preload-count').textContent();
    expect(title).toBe('Override');
    expect(count).toBe('5');
  });

  test('block with no defaults renders explicit values', async () => {
    const canvas = await getEditorCanvas(page);
    const block = canvas.locator('.preload-no-defaults');
    await expect(block).toBeVisible({ timeout: 15000 });

    const title = await block.locator('.preload-title').textContent();
    const count = await block.locator('.preload-count').textContent();
    const active = await block.locator('.preload-active').textContent();
    expect(title).toBe('Explicit');
    expect(count).toBe('42');
    expect(active).toBe('true');
  });

  test('block with zero count renders correctly', async () => {
    const canvas = await getEditorCanvas(page);
    const blocks = canvas.locator('.preload-simple');

    // Third preload-simple (index 2): count=0, title defaults to "Hello"
    const title = await blocks.nth(2).locator('.preload-title').textContent();
    const count = await blocks.nth(2).locator('.preload-count').textContent();
    expect(title).toBe('Hello');
    expect(count).toBe('0');
  });
});

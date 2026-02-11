import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;

const BLOCK_CONTENT = [
  '<!-- wp:blockstudio/type-preload-repeater {"blockstudio":{"attributes":{"heading":"List A","items":[{"text":"Alpha","number":10},{"text":"Beta","number":20}]}}} /-->',
  '<!-- wp:blockstudio/type-preload-repeater {"blockstudio":{"attributes":{"heading":"List B","items":[{"text":"Gamma","number":30}]}}} /-->',
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

test.describe('Preload Repeater Fields', () => {
  test('both repeater blocks preloaded', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`);
    await getEditorCanvas(page);

    await page.waitForFunction(
      () => {
        const bs = (window as any).blockstudio;
        if (!bs?.blockstudioBlocks) return false;
        const blocks = bs.blockstudioBlocks;
        const entries = Array.isArray(blocks) ? blocks : Object.values(blocks);
        return (entries as any[]).filter(
          (e: any) => e.blockName === 'blockstudio/type-preload-repeater',
        ).length === 2;
      },
      { timeout: 30000 },
    );
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

  test('first block renders 2 repeater rows with correct content', async () => {
    const canvas = await getEditorCanvas(page);
    const blocks = canvas.locator('.preload-repeater');
    await expect(blocks.first()).toBeVisible({ timeout: 15000 });

    const firstBlock = blocks.nth(0);
    const heading = await firstBlock.locator('.preload-heading').textContent();
    expect(heading).toBe('List A');

    const items = firstBlock.locator('.preload-item');
    await expect(items).toHaveCount(2);

    const texts = await items.locator('.preload-item-text').allTextContents();
    expect(texts).toEqual(['Alpha', 'Beta']);

    const numbers = await items.locator('.preload-item-number').allTextContents();
    expect(numbers).toEqual(['10', '20']);
  });

  test('second block renders 1 repeater row with correct content', async () => {
    const canvas = await getEditorCanvas(page);
    const blocks = canvas.locator('.preload-repeater');

    const secondBlock = blocks.nth(1);
    const heading = await secondBlock.locator('.preload-heading').textContent();
    expect(heading).toBe('List B');

    const items = secondBlock.locator('.preload-item');
    await expect(items).toHaveCount(1);

    const text = await items.locator('.preload-item-text').textContent();
    expect(text).toBe('Gamma');

    const number = await items.locator('.preload-item-number').textContent();
    expect(number).toBe('30');
  });
});

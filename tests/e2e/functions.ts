import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';

const BASE_URL = 'http://localhost:8888';
const POST_URL = `${BASE_URL}/wp-admin/post.php?post=1483&action=edit`;

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await page.goto(POST_URL);
  await getEditorCanvas(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('Block Functions (RPC)', () => {
  test('calls greet function with params', async () => {
    const result = await page.evaluate(async () => {
      const res = await fetch('/wp-json/blockstudio/v1/fn/blockstudio/type-functions/greet', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': (window as any).blockstudioAdmin.nonceRest },
        body: JSON.stringify({ params: { name: 'Blockstudio' } }),
      });
      return res.json();
    });
    expect(result).toEqual({ message: 'Hello, Blockstudio!' });
  });

  test('calls greet with default params', async () => {
    const result = await page.evaluate(async () => {
      const res = await fetch('/wp-json/blockstudio/v1/fn/blockstudio/type-functions/greet', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': (window as any).blockstudioAdmin.nonceRest },
        body: JSON.stringify({ params: {} }),
      });
      return res.json();
    });
    expect(result).toEqual({ message: 'Hello, World!' });
  });

  test('calls add function', async () => {
    const result = await page.evaluate(async () => {
      const res = await fetch('/wp-json/blockstudio/v1/fn/blockstudio/type-functions/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': (window as any).blockstudioAdmin.nonceRest },
        body: JSON.stringify({ params: { a: 3, b: 7 } }),
      });
      return res.json();
    });
    expect(result).toEqual({ result: 10 });
  });

  test('public function works without nonce', async () => {
    const result = await page.evaluate(async () => {
      const res = await fetch('/wp-json/blockstudio/v1/fn/blockstudio/type-functions/public', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ params: { value: 'test' } }),
      });
      return res.json();
    });
    expect(result).toEqual({ public: true, echo: 'test' });
  });

  test('returns 404 for unknown function', async () => {
    const status = await page.evaluate(async () => {
      const res = await fetch('/wp-json/blockstudio/v1/fn/blockstudio/type-functions/nonexistent', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': (window as any).blockstudioAdmin.nonceRest },
        body: JSON.stringify({ params: {} }),
      });
      return res.status;
    });
    expect(status).toBe(404);
  });

  test('bs.fn is available in editor', async () => {
    const hasBsFn = await page.evaluate(() => typeof (window as any).bs?.fn === 'function');
    expect(hasBsFn).toBe(true);
  });

  test('bs.fn works from editor', async () => {
    const result = await page.evaluate(async () => {
      return (window as any).bs.fn('greet', { name: 'Editor' }, 'blockstudio/type-functions');
    });
    expect(result).toEqual({ message: 'Hello, Editor!' });
  });

  test('bs.fn is available on frontend', async () => {
    await page.goto(`${BASE_URL}/native-single`);
    await page.waitForLoadState('domcontentloaded');
    const hasBsFn = await page.evaluate(() => typeof (window as any).bs?.fn === 'function');
    expect(hasBsFn).toBe(true);
    await page.goto(POST_URL);
    canvas = await getEditorCanvas(page);
  });
});

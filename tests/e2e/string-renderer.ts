import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

const BASE_URL = 'http://localhost:8888';

let page: Page;
const createdPostIds: number[] = [];

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async () => {
  for (const id of createdPostIds) {
    await page.request.delete(
      `${BASE_URL}/wp-json/wp/v2/posts/${id}?force=true`,
    );
  }
  await page.close();
});

async function createPost(title: string, content: string): Promise<string> {
  await page.goto(`${BASE_URL}/wp-admin/`);
  const nonce = await page.evaluate(() => (window as any).wpApiSettings?.nonce ?? '');

  let resolvedNonce = nonce;
  if (!resolvedNonce) {
    await page.goto(`${BASE_URL}/wp-admin/post-new.php`);
    await page.waitForLoadState('domcontentloaded');
    resolvedNonce = await page.evaluate(() => (window as any).wpApiSettings?.nonce ?? '');
  }

  const response = await page.request.post(`${BASE_URL}/wp-json/wp/v2/posts`, {
    data: {
      title,
      content,
      status: 'publish',
    },
    headers: {
      'X-WP-Nonce': resolvedNonce,
    },
  });

  const data = await response.json();
  createdPostIds.push(data.id);
  return data.link;
}

test.describe('String Renderer', () => {
  test('renders a self-closing tag with attributes', async () => {
    const url = await createPost(
      'SR Self-Closing',
      '<bs:type-string-renderer title="Hello String" count=42 />',
    );

    await page.goto(url);
    await page.waitForLoadState('domcontentloaded');

    const block = page.locator('.bs-string-renderer');
    await expect(block).toBeVisible();
    await expect(block.locator('.sr-title')).toHaveText('Hello String');
    await expect(block.locator('.sr-count')).toHaveText('42');
  });

  test('renders a paired tag', async () => {
    const url = await createPost(
      'SR Paired',
      '<bs:type-string-renderer title="Paired Tag" count=7></bs:type-string-renderer>',
    );

    await page.goto(url);
    await page.waitForLoadState('domcontentloaded');

    const block = page.locator('.bs-string-renderer');
    await expect(block).toBeVisible();
    await expect(block.locator('.sr-title')).toHaveText('Paired Tag');
    await expect(block.locator('.sr-count')).toHaveText('7');
  });

  test('renders with default attribute values', async () => {
    const url = await createPost(
      'SR Defaults',
      '<bs:type-string-renderer />',
    );

    await page.goto(url);
    await page.waitForLoadState('domcontentloaded');

    const block = page.locator('.bs-string-renderer');
    await expect(block).toBeVisible();
    await expect(block.locator('.sr-title')).toHaveText('Default Title');
    await expect(block.locator('.sr-count')).toHaveText('0');
  });

  test('renders multiple tags in the same content', async () => {
    const url = await createPost(
      'SR Multiple',
      '<bs:type-string-renderer title="First" count=1 /><bs:type-string-renderer title="Second" count=2 />',
    );

    await page.goto(url);
    await page.waitForLoadState('domcontentloaded');

    const blocks = page.locator('.bs-string-renderer');
    await expect(blocks).toHaveCount(2);
    await expect(blocks.nth(0).locator('.sr-title')).toHaveText('First');
    await expect(blocks.nth(1).locator('.sr-title')).toHaveText('Second');
  });

  test('renders with namespace prefix (double-dash)', async () => {
    const url = await createPost(
      'SR Namespace',
      '<bs:blockstudio--type-string-renderer title="Namespaced" count=99 />',
    );

    await page.goto(url);
    await page.waitForLoadState('domcontentloaded');

    const block = page.locator('.bs-string-renderer');
    await expect(block).toBeVisible();
    await expect(block.locator('.sr-title')).toHaveText('Namespaced');
    await expect(block.locator('.sr-count')).toHaveText('99');
  });

  test('leaves unresolvable tags untouched', async () => {
    const url = await createPost(
      'SR Unknown',
      '<p>Text with an unknown tag.</p>',
    );

    await page.goto(url);
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.bs-string-renderer')).toHaveCount(0);
  });
});

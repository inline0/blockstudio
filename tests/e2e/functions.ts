import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

const BASE_URL = 'http://localhost:8888';

let page: Page;

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

async function callFn(
  block: string,
  fn: string,
  params: Record<string, unknown> = {},
): Promise<unknown> {
  const nonce = await page.evaluate(
    () => (window as any).wpApiSettings?.nonce ?? '',
  );

  const response = await page.request.post(
    `${BASE_URL}/wp-json/blockstudio/v1/fn/${block}/${fn}`,
    {
      data: { params },
      headers: { 'X-WP-Nonce': nonce },
    },
  );

  return response.json();
}

test.describe('Block Functions', () => {
  test.beforeAll(async () => {
    await page.goto(`${BASE_URL}/wp-admin/`);
    await page.waitForLoadState('domcontentloaded');
  });

  test('calls greet function with params', async () => {
    const result = await callFn('blockstudio/type-functions', 'greet', {
      name: 'Blockstudio',
    });
    expect(result).toEqual({ message: 'Hello, Blockstudio!' });
  });

  test('calls greet function with default params', async () => {
    const result = await callFn('blockstudio/type-functions', 'greet');
    expect(result).toEqual({ message: 'Hello, World!' });
  });

  test('calls add function', async () => {
    const result = await callFn('blockstudio/type-functions', 'add', {
      a: 3,
      b: 7,
    });
    expect(result).toEqual({ result: 10 });
  });

  test('calls public function without auth', async () => {
    const response = await page.request.post(
      `${BASE_URL}/wp-json/blockstudio/v1/fn/blockstudio/type-functions/public`,
      {
        data: { params: { value: 'test' } },
      },
    );
    const result = await response.json();
    expect(result).toEqual({ public: true, echo: 'test' });
  });

  test('returns 404 for unknown function', async () => {
    const response = await page.request.post(
      `${BASE_URL}/wp-json/blockstudio/v1/fn/blockstudio/type-functions/nonexistent`,
      {
        data: { params: {} },
        headers: {
          'X-WP-Nonce': await page.evaluate(
            () => (window as any).wpApiSettings?.nonce ?? '',
          ),
        },
      },
    );
    expect(response.status()).toBe(404);
  });

  test('returns 404 for unknown block', async () => {
    const response = await page.request.post(
      `${BASE_URL}/wp-json/blockstudio/v1/fn/blockstudio/nonexistent/greet`,
      {
        data: { params: {} },
        headers: {
          'X-WP-Nonce': await page.evaluate(
            () => (window as any).wpApiSettings?.nonce ?? '',
          ),
        },
      },
    );
    expect(response.status()).toBe(404);
  });

  test('bs.fn client is injected on frontend', async () => {
    const postResponse = await page.request.post(
      `${BASE_URL}/wp-json/wp/v2/posts`,
      {
        data: {
          title: 'Functions Test',
          content:
            '<!-- wp:blockstudio/type-functions {"blockstudio":{"name":"blockstudio/type-functions","attributes":{"message":"Test"}}} /-->',
          status: 'publish',
        },
        headers: {
          'X-WP-Nonce': await page.evaluate(
            () => (window as any).wpApiSettings?.nonce ?? '',
          ),
        },
      },
    );
    const post = await postResponse.json();

    await page.goto(post.link);
    await page.waitForLoadState('domcontentloaded');

    const hasBsFn = await page.evaluate(() => typeof (window as any).bs?.fn === 'function');
    expect(hasBsFn).toBe(true);
  });
});

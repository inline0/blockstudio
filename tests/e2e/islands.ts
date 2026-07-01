import { test, expect, Page, BrowserContext } from '@playwright/test';

const BASE = 'http://localhost:8888';

let context: BrowserContext;
let page: Page;
let islandRequests: Array<{ islands?: unknown[] }> = [];

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1280, height: 800 });
  page.on('request', (request) => {
    if (!request.url().includes('/wp-json/blockstudio/v1/island/render')) {
      return;
    }

    const body = request.postData();
    if (!body) {
      islandRequests.push({});
      return;
    }

    islandRequests.push(JSON.parse(body));
  });
});

test.afterAll(async () => {
  await page.close();
  await context.close();
});

test.beforeEach(() => {
  islandRequests = [];
});

test.describe('Block islands', () => {
  test('server HTML contains dynamic placeholders, hydrated content, and runtime only', async () => {
    const response = await page.request.get(`${BASE}/island-test/`);
    const html = await response.text();

    expect(html).toContain('data-bs-island="blockstudio/island-dynamic"');
    expect(html).toContain('bs-island-placeholder');
    expect(html).not.toContain('bs-island-fragment');
    expect(html).toContain('data-bs-island="blockstudio/island-hydrated"');
    expect(html).toContain('bs-island-hydrated-content');
    expect(html).toContain('data-bs-islands-runtime');
  });

  test('eager dynamic islands swap in one batched request and support all render entry points', async () => {
    await page.goto(`${BASE}/island-test/`, { waitUntil: 'domcontentloaded' });

    await expect(
      page.locator('.bs-island-fragment', { hasText: 'Native island' }).first(),
    ).toBeVisible();
    await expect(
      page.locator('.bs-island-fragment', { hasText: 'Render helper' }),
    ).toBeVisible();
    await expect(
      page.locator('.bs-island-fallback-fragment', {
        hasText: 'Buffered helper',
      }),
    ).toBeVisible();
    await expect(
      page.locator('.bs-island-fragment', { hasText: 'Block tag' }),
    ).toBeVisible();

    await expect.poll(() => islandRequests.length).toBe(1);
    expect(islandRequests[0].islands).toHaveLength(5);

    const nativeMarkers = page.locator(
      '[data-bs-island="blockstudio/island-dynamic"]',
    );
    await expect(nativeMarkers).toHaveCount(4);
    await expect(nativeMarkers.first()).toHaveAttribute(
      'data-bs-island-hydrated',
      '1',
    );
    await expect(page.locator('text=Should not be signed')).toHaveCount(0);
    await expect(page.locator('text=Helper secret')).toHaveCount(0);
    await expect(page.locator('text=Tag secret')).toHaveCount(0);
  });

  test('visible islands wait until they enter the viewport', async () => {
    await expect(page.locator('.bs-island-visible-placeholder')).toHaveCount(1);
    await expect(page.locator('.bs-island-visible-fragment')).toHaveCount(0);

    await page.locator('.bs-island-visible-placeholder').scrollIntoViewIfNeeded();

    await expect(
      page.locator('.bs-island-visible-fragment', { hasText: 'Visible island' }),
    ).toBeVisible();
    await expect.poll(() => islandRequests.length).toBe(1);
    expect(islandRequests[0].islands).toHaveLength(1);
  });

  test('event-driven islands refresh through the same endpoint', async () => {
    const eventIsland = page.locator(
      '[data-bs-island="blockstudio/island-event"] .bs-island-event-fragment',
    );

    await expect(eventIsland).toBeVisible();
    const before = await eventIsland.getAttribute('data-time');

    await page.evaluate(() => {
      window.dispatchEvent(new Event('blockstudio:test-island-refresh'));
    });

    await expect
      .poll(async () => eventIsland.getAttribute('data-time'))
      .not.toBe(before);
    await expect.poll(() => islandRequests.length).toBe(1);
    expect(islandRequests[0].islands).toHaveLength(1);
  });

  test('hydrated islands receive the hydrate event without a fragment request', async () => {
    await expect(
      page.locator('[data-bs-island="blockstudio/island-hydrated"]'),
    ).toHaveAttribute('data-hydrated-fixture', '1');
  });

  test('dynamic placeholders remain useful with JavaScript disabled', async ({ browser }) => {
    const noJsContext = await browser.newContext({ javaScriptEnabled: false });
    const noJsPage = await noJsContext.newPage();

    await noJsPage.goto(`${BASE}/island-test/`, {
      waitUntil: 'domcontentloaded',
    });

    await expect(noJsPage.locator('.bs-island-placeholder').first()).toBeVisible();
    await expect(noJsPage.locator('.bs-island-fragment')).toHaveCount(0);
    await expect(noJsPage.locator('.bs-island-hydrated-content')).toBeVisible();

    await noJsPage.close();
    await noJsContext.close();
  });
});

import { expect, test, Page, BrowserContext, Browser } from '@playwright/test';

const BASE = 'http://localhost:8888';
const ADMIN_PAGE = `${BASE}/wp-admin/tools.php?page=blockstudio-admin`;

let page: Page;
let context: BrowserContext;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }: { browser: Browser }) => {
  context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });

  await page.goto(`${BASE}/wp-login.php`);
  await page.waitForLoadState('networkidle');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('password');
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');
});

test.afterAll(async () => {
  await page.close();
  await context.close();
});

async function getNonce(): Promise<string> {
  return page.evaluate(() => (window as any).blockstudioAdminPage?.nonce ?? '');
}

async function apiPost(path: string, body: Record<string, unknown> = {}) {
  const wpNonce = await getNonce();
  return page.evaluate(
    async ({ url, data, n }: { url: string; data: Record<string, unknown>; n: string }) => {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': n,
        },
        body: JSON.stringify(data),
      });
      return { status: res.status, body: await res.json() };
    },
    { url: `${BASE}/wp-json/${path}`, data: body, n: wpNonce },
  );
}

test.describe('Admin Page - Overview', () => {
  test('admin page loads under Tools', async () => {
    await page.goto(ADMIN_PAGE);
    await page.waitForSelector('#blockstudio-admin');
    await expect(page.locator('#blockstudio-admin')).toBeVisible();
  });

  test('header shows logo', async () => {
    await expect(page.locator('img[alt="Blockstudio"]')).toBeVisible();
  });

  test('header shows Overview and Registry navigation', async () => {
    await page.goto(ADMIN_PAGE);
    await page.waitForSelector('#blockstudio-admin');
    await expect(page.getByRole('button', { name: 'Overview' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Registry' })).toBeVisible();
  });

  test('overview tab shows DataViews with block tabs', async () => {
    await page.getByRole('button', { name: 'Overview' }).click();
    await expect(page.getByRole('tab', { name: /Blocks/ })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Extensions/ })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Fields/ })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Pages/ })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Schemas/ })).toBeVisible();
  });

  test('header shows Docs link', async () => {
    await expect(
      page.getByRole('link', { name: 'Docs' }),
    ).toHaveAttribute('href', 'https://blockstudio.dev/docs');
  });
});

test.describe('Admin Page - Registry Browser', () => {
  test('navigates to registry tab and shows blocks', async () => {
    await page.goto(ADMIN_PAGE);
    await page.waitForSelector('#blockstudio-admin');
    await page.getByRole('button', { name: 'Registry' }).click();
    await expect(page.getByText('E2E Hero')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('E2E Card')).toBeVisible();
  });

  test('shows registry name and descriptions', async () => {
    await expect(page.getByText('test').first()).toBeVisible();
    await expect(page.getByText('A hero block for E2E testing.')).toBeVisible();
  });

  test('shows Available status and Import button', async () => {
    await expect(page.getByText('Available').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Import' }).first()).toBeVisible();
  });
});

test.describe('Admin Page - Registry Import', () => {
  test('REST import endpoint works', async () => {
    await page.goto(ADMIN_PAGE);
    await page.waitForSelector('#blockstudio-admin');

    const result = await apiPost('blockstudio/v1/registry/import', {
      registry: 'test',
      block: 'e2e-hero',
    });

    expect(result.status).toBe(200);
    expect(result.body.success).toBe(true);
    expect(result.body.block).toBe('e2e-hero');
    expect(result.body.files).toBe(2);
  });

  test('import button works from UI', async () => {
    await page.goto(ADMIN_PAGE);
    await page.waitForSelector('#blockstudio-admin');
    await page.getByRole('button', { name: 'Registry' }).click();
    await expect(page.getByText('E2E Card')).toBeVisible({ timeout: 15000 });

    const cardRow = page.locator('tr', { hasText: 'E2E Card' });
    const importBtn = cardRow.getByRole('button', { name: 'Import' });
    await importBtn.click();

    await expect(cardRow.getByText('Installed')).toBeVisible({ timeout: 10000 });
    await expect(cardRow.getByRole('button', { name: 'Reimport' })).toBeVisible();
  });

  test('REST endpoint rejects missing registry', async () => {
    const result = await apiPost('blockstudio/v1/registry/import', {
      registry: 'nonexistent',
      block: 'e2e-hero',
    });
    expect(result.status).toBe(400);
  });

  test('REST endpoint rejects missing block', async () => {
    const result = await apiPost('blockstudio/v1/registry/import', {
      registry: 'test',
      block: 'nonexistent',
    });
    expect(result.status).toBe(404);
  });

  test('REST endpoint rejects directory traversal', async () => {
    const result = await apiPost('blockstudio/v1/registry/import', {
      registry: 'test',
      block: '../../../wp-config',
    });
    expect(result.status).toBe(400);
  });
});

test.describe('Admin Page - Registry Data', () => {
  test('localized data has registryEnabled flag', async () => {
    await page.goto(ADMIN_PAGE);
    await page.waitForSelector('#blockstudio-admin');

    const data = await page.evaluate(
      () => (window as any).blockstudioAdminPage,
    );

    expect(data).toBeDefined();
    expect(data.nonce).toBeTruthy();
    expect(data.restUrl).toContain('blockstudio/v1');
    expect(data.registryEnabled).toBeTruthy();
  });

  test('REST endpoint returns registry blocks', async () => {
    const wpNonce = await getNonce();
    const result = await page.evaluate(
      async (n: string) => {
        const res = await fetch('/wp-json/blockstudio/v1/registry/blocks', {
          headers: { 'X-WP-Nonce': n },
        });
        return { status: res.status, body: await res.json() };
      },
      wpNonce,
    );

    expect(result.status).toBe(200);
    expect(Array.isArray(result.body)).toBe(true);
    expect(result.body.length).toBeGreaterThanOrEqual(2);

    const hero = result.body.find((r: any) => r.name === 'e2e-hero');
    expect(hero).toBeDefined();
    expect(hero.registry).toBe('test');
    expect(hero.title).toBe('E2E Hero');
  });

  test('overview data still present', async () => {
    const data = await page.evaluate(
      () => (window as any).blockstudioAdminPage,
    );

    expect(data.overview).toBeDefined();
    expect(Array.isArray(data.overview.blocks)).toBe(true);
    expect(data.overview.blocks.length).toBeGreaterThan(0);
    expect(data.version).toBeTruthy();
  });
});

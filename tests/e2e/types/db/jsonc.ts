import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const ROUTE = `${BASE}/wp-json/blockstudio/v1/db/blockstudio-type-db-jsonc/default`;

let page: Page;
let nonce: string;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await page.goto(`${BASE}/wp-admin/post.php?post=1483&action=edit`);
  await getEditorCanvas(page);
  nonce = await page.evaluate(() => (window as any).blockstudioAdmin.nonceRest);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('DB JSONC Storage', () => {
  let id: number;

  test('create', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ action: 'signup', details: 'New user' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce }
    );
    expect(r.s).toBe(200);
    expect(r.b.action).toBe('signup');
    expect(r.b.details).toBe('New user');
    expect(r.b.id).toBeDefined();
    expect(r.b.created_at).toBeDefined();
    id = Number(r.b.id);
  });

  test('get by id', async () => {
    const r = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce, id }
    );
    expect(r.s).toBe(200);
    expect(r.b.action).toBe('signup');
  });

  test('list', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce }
    );
    expect(r.s).toBe(200);
    expect(Array.isArray(r.b)).toBe(true);
    expect(r.b.length).toBeGreaterThan(0);
  });

  test('update', async () => {
    const r = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ details: 'Updated details' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce, id }
    );
    expect(r.s).toBe(200);
    expect(r.b.details).toBe('Updated details');
    expect(r.b.action).toBe('signup');
  });

  test('create second record', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ action: 'login', details: 'Returning user' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce }
    );
    expect(r.s).toBe(200);
    expect(Number(r.b.id)).toBeGreaterThan(id);
  });

  test('list with filter', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(`${route}?action=login`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce }
    );
    expect(r.s).toBe(200);
    expect(r.b.length).toBe(1);
    expect(r.b[0].action).toBe('login');
  });

  test('delete', async () => {
    const r = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce, id }
    );
    expect(r.s).toBe(200);
    expect(r.b.deleted).toBe(true);
  });
});

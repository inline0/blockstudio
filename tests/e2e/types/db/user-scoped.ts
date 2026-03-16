import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const ROUTE = `${BASE}/wp-json/blockstudio/v1/db/blockstudio-type-db-user-scoped/default`;

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

test.describe('DB User Scoped', () => {
  let id: number;

  test('create auto-sets user_id', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ title: 'My Todo' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce }
    );
    expect(r.s).toBe(200);
    expect(r.b.title).toBe('My Todo');
    expect(Number(r.b.user_id)).toBeGreaterThan(0);
    id = Number(r.b.id);
  });

  test('list only returns current user records', async () => {
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
    expect(r.b.length).toBeGreaterThan(0);
    const userId = Number(r.b[0].user_id);
    expect(r.b.every((row: any) => Number(row.user_id) === userId)).toBe(true);
  });

  test('get returns own record', async () => {
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
    expect(r.b.title).toBe('My Todo');
  });

  test('update own record works', async () => {
    const r = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ title: 'Updated Todo' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce, id }
    );
    expect(r.s).toBe(200);
    expect(r.b.title).toBe('Updated Todo');
  });

  test('update cannot change user_id', async () => {
    const r = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ user_id: 9999 }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce, id }
    );
    expect(r.s).toBe(200);
    expect(Number(r.b.user_id)).not.toBe(9999);
  });

  test('delete own record works', async () => {
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

  test('get deleted record returns 404', async () => {
    const r = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return res.status;
      },
      { route: ROUTE, nonce, id }
    );
    expect(r).toBe(404);
  });
});

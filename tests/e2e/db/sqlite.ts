import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const ROUTE = `${BASE}/wp-json/blockstudio/v1/db/blockstudio-type-db-sqlite/default`;

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

test.describe('DB SQLite Storage', () => {
  let id: number;

  test('create with all field types', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ title: 'SQLite Row', count: 42, score: 9.99, active: true }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce }
    );
    expect(r.s).toBe(200);
    expect(r.b.title).toBe('SQLite Row');
    expect(Number(r.b.count)).toBe(42);
    expect(Number(r.b.score)).toBeCloseTo(9.99);
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
    expect(r.b.title).toBe('SQLite Row');
  });

  test('list returns array', async () => {
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

  test('list with filter', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(`${route}?title=SQLite%20Row`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce }
    );
    expect(r.s).toBe(200);
    expect(r.b.every((row: any) => row.title === 'SQLite Row')).toBe(true);
  });

  test('update', async () => {
    const r = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ title: 'Updated SQLite' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: ROUTE, nonce, id }
    );
    expect(r.s).toBe(200);
    expect(r.b.title).toBe('Updated SQLite');
    expect(Number(r.b.count)).toBe(42);
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

  test('get deleted returns 404', async () => {
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

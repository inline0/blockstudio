import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';

const BASE_URL = 'http://localhost:8888';
const POST_URL = `${BASE_URL}/wp-admin/post.php?post=1483&action=edit`;
const TABLE_ROUTE = `${BASE_URL}/wp-json/blockstudio/v1/db/blockstudio-type-database/subscribers`;
const JSONC_ROUTE = `${BASE_URL}/wp-json/blockstudio/v1/db/blockstudio-type-database/logs`;

let page: Page;
let nonce: string;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await page.goto(POST_URL);
  await getEditorCanvas(page);
  nonce = await page.evaluate(
    () => (window as any).blockstudioAdmin.nonceRest
  );
});

test.afterAll(async () => {
  await page.close();
});

test.describe('Database (table storage)', () => {
  let createdId: number;

  test('creates a record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ email: 'test@example.com', name: 'Test User', plan: 'pro' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce }
    );

    expect(result.status).toBe(200);
    expect(result.body.email).toBe('test@example.com');
    expect(result.body.plan).toBe('pro');
    createdId = Number(result.body.id);
  });

  test('reads a record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce, id: createdId }
    );
    expect(result.status).toBe(200);
    expect(result.body.email).toBe('test@example.com');
  });

  test('lists records', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, { method: 'GET', headers: { 'X-WP-Nonce': nonce } });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce }
    );
    expect(result.status).toBe(200);
    expect(result.body.length).toBeGreaterThan(0);
  });

  test('updates a record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ plan: 'enterprise' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce, id: createdId }
    );
    expect(result.status).toBe(200);
    expect(result.body.plan).toBe('enterprise');
  });

  test('returns per-field errors for required fields', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ name: 'No Email' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce }
    );
    expect(result.status).toBe(400);
    expect(result.body.data.errors.email).toBeDefined();
    expect(result.body.data.errors.email[0]).toBe('Required.');
  });

  test('returns per-field errors for enum values', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ email: 'a@b.com', plan: 'invalid' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce }
    );
    expect(result.status).toBe(400);
    expect(result.body.data.errors.plan).toBeDefined();
    expect(result.body.data.errors.plan[0]).toContain('Must be one of');
  });

  test('returns per-field errors for custom validate callback', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ email: 'user@banned.com' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce }
    );
    expect(result.status).toBe(400);
    expect(result.body.data.errors.email).toBeDefined();
    expect(result.body.data.errors.email[0]).toBe('This domain is not allowed.');
  });

  test('returns multiple errors per field', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ email: 'not-an-email', plan: 'invalid' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce }
    );
    expect(result.status).toBe(400);
    expect(result.body.data.errors.email).toBeDefined();
    expect(result.body.data.errors.plan).toBeDefined();
  });

  test('deletes a record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { status: res.status, body: await res.json() };
      },
      { route: TABLE_ROUTE, nonce, id: createdId }
    );
    expect(result.status).toBe(200);
    expect(result.body.deleted).toBe(true);
  });
});

test.describe('Database (JSONC storage)', () => {
  let createdId: number;

  test('creates a JSONC record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ action: 'signup', details: 'New user' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: JSONC_ROUTE, nonce }
    );
    expect(result.status).toBe(200);
    expect(result.body.action).toBe('signup');
    createdId = Number(result.body.id);
  });

  test('reads a JSONC record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { status: res.status, body: await res.json() };
      },
      { route: JSONC_ROUTE, nonce, id: createdId }
    );
    expect(result.status).toBe(200);
    expect(result.body.action).toBe('signup');
  });

  test('deletes a JSONC record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { status: res.status, body: await res.json() };
      },
      { route: JSONC_ROUTE, nonce, id: createdId }
    );
    expect(result.status).toBe(200);
    expect(result.body.deleted).toBe(true);
  });
});

test.describe('Database (client)', () => {
  test('bs.db client is available', async () => {
    const hasBsDb = await page.evaluate(
      () => typeof (window as any).bs?.db === 'function'
    );
    expect(hasBsDb).toBe(true);
  });
});

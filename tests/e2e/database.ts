import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';

const BASE_URL = 'http://localhost:8888';
const POST_URL = `${BASE_URL}/wp-admin/post.php?post=1483&action=edit`;
const ROUTE = `${BASE_URL}/wp-json/blockstudio/v1/db/blockstudio-type-database`;

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

test.describe('Database', () => {
  let createdId: number;

  test('creates a record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
          },
          body: JSON.stringify({
            email: 'test@example.com',
            name: 'Test User',
            plan: 'pro',
          }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: ROUTE, nonce }
    );

    expect(result.status).toBe(200);
    expect(result.body.email).toBe('test@example.com');
    expect(result.body.name).toBe('Test User');
    expect(result.body.plan).toBe('pro');
    expect(result.body.id).toBeDefined();
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
      { route: ROUTE, nonce, id: createdId }
    );

    expect(result.status).toBe(200);
    expect(result.body.email).toBe('test@example.com');
  });

  test('lists records', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { status: res.status, body: await res.json() };
      },
      { route: ROUTE, nonce }
    );

    expect(result.status).toBe(200);
    expect(Array.isArray(result.body)).toBe(true);
    expect(result.body.length).toBeGreaterThan(0);
  });

  test('lists with filter', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(`${route}?plan=pro`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return { status: res.status, body: await res.json() };
      },
      { route: ROUTE, nonce }
    );

    expect(result.status).toBe(200);
    expect(result.body.every((r: any) => r.plan === 'pro')).toBe(true);
  });

  test('updates a record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
          },
          body: JSON.stringify({ plan: 'enterprise' }),
        });
        return { status: res.status, body: await res.json() };
      },
      { route: ROUTE, nonce, id: createdId }
    );

    expect(result.status).toBe(200);
    expect(result.body.plan).toBe('enterprise');
    expect(result.body.email).toBe('test@example.com');
  });

  test('validates required fields', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
          },
          body: JSON.stringify({ name: 'No Email' }),
        });
        return res.status;
      },
      { route: ROUTE, nonce }
    );

    expect(result).toBe(400);
  });

  test('validates enum values', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
          },
          body: JSON.stringify({ email: 'a@b.com', plan: 'invalid' }),
        });
        return res.status;
      },
      { route: ROUTE, nonce }
    );

    expect(result).toBe(400);
  });

  test('validates email format', async () => {
    const result = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
          },
          body: JSON.stringify({ email: 'not-an-email' }),
        });
        return res.status;
      },
      { route: ROUTE, nonce }
    );

    expect(result).toBe(400);
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
      { route: ROUTE, nonce, id: createdId }
    );

    expect(result.status).toBe(200);
    expect(result.body.deleted).toBe(true);
  });

  test('returns 404 for deleted record', async () => {
    const result = await page.evaluate(
      async ({ route, nonce, id }) => {
        const res = await fetch(`${route}/${id}`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': nonce },
        });
        return res.status;
      },
      { route: ROUTE, nonce, id: createdId }
    );

    expect(result).toBe(404);
  });

  test('public read works without nonce', async () => {
    const result = await page.evaluate(async ({ route }) => {
      const res = await fetch(route, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
      });
      return res.status;
    }, { route: ROUTE });

    expect(result).toBe(200);
  });

  test('bs.db client is available', async () => {
    const hasBsDb = await page.evaluate(
      () => typeof (window as any).bs?.db === 'function'
    );
    expect(hasBsDb).toBe(true);
  });
});

import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const ROUTE = `${BASE}/wp-json/blockstudio/v1/db/blockstudio-type-db-validation/default`;

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

const post = async (data: any) => {
  return page.evaluate(
    async ({ route, nonce, data }) => {
      const res = await fetch(route, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify(data),
      });
      return { s: res.status, b: await res.json() };
    },
    { route: ROUTE, nonce, data }
  );
};

test.describe('DB Validation', () => {
  test('required field missing', async () => {
    const r = await post({ username: 'test' });
    expect(r.s).toBe(400);
    expect(r.b.data.errors.email).toContain('Required.');
  });

  test('invalid email format', async () => {
    const r = await post({ email: 'not-an-email' });
    expect(r.s).toBe(400);
    expect(r.b.data.errors.email[0]).toBe('Must be a valid email address.');
  });

  test('invalid url format', async () => {
    const r = await post({ email: 'a@b.com', url: 'not-a-url' });
    expect(r.s).toBe(400);
    expect(r.b.data.errors.url[0]).toBe('Must be a valid URL.');
  });

  test('string too short (minLength)', async () => {
    const r = await post({ email: 'a@b.com', username: 'ab' });
    expect(r.s).toBe(400);
    expect(r.b.data.errors.username[0]).toContain('at least 3');
  });

  test('string too long (maxLength)', async () => {
    const r = await post({ email: 'a@b.com', username: 'a'.repeat(21) });
    expect(r.s).toBe(400);
    expect(r.b.data.errors.username[0]).toContain('maximum length');
  });

  test('invalid enum value', async () => {
    const r = await post({ email: 'a@b.com', role: 'superadmin' });
    expect(r.s).toBe(400);
    expect(r.b.data.errors.role[0]).toContain('Must be one of');
  });

  test('custom validate callback', async () => {
    const r = await post({ email: 'user@banned.com' });
    expect(r.s).toBe(400);
    expect(r.b.data.errors.email).toContain('This domain is not allowed.');
  });

  test('multiple errors on different fields', async () => {
    const r = await post({ email: 'bad', role: 'invalid', username: 'x' });
    expect(r.s).toBe(400);
    expect(Object.keys(r.b.data.errors).length).toBeGreaterThanOrEqual(3);
  });

  test('valid data passes', async () => {
    const r = await post({
      email: 'valid@example.com',
      url: 'https://example.com',
      username: 'validuser',
      role: 'editor',
      age: 30,
      score: 95.5,
      verified: true,
    });
    expect(r.s).toBe(200);
    expect(r.b.email).toBe('valid@example.com');
    expect(r.b.username).toBe('validuser');
  });

  test('error response has correct structure', async () => {
    const r = await post({ username: 'test' });
    expect(r.s).toBe(400);
    expect(r.b.code).toBe('blockstudio_db_validation');
    expect(r.b.message).toBe('Validation failed.');
    expect(r.b.data.status).toBe(400);
    expect(typeof r.b.data.errors).toBe('object');
    expect(Array.isArray(r.b.data.errors.email)).toBe(true);
  });
});

import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const CONTACTS = `${BASE}/wp-json/blockstudio/v1/db/blockstudio-type-db-multi/contacts`;
const NOTES = `${BASE}/wp-json/blockstudio/v1/db/blockstudio-type-db-multi/notes`;

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

test.describe('DB Multiple Schemas', () => {
  test('contacts schema (table) works', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ name: 'Alice', email: 'alice@example.com' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: CONTACTS, nonce }
    );
    expect(r.s).toBe(200);
    expect(r.b.name).toBe('Alice');
    expect(r.b.email).toBe('alice@example.com');
  });

  test('notes schema (jsonc) works', async () => {
    const r = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ body: 'First note' }),
        });
        return { s: res.status, b: await res.json() };
      },
      { route: NOTES, nonce }
    );
    expect(r.s).toBe(200);
    expect(r.b.body).toBe('First note');
  });

  test('schemas are independent', async () => {
    const contacts = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, { method: 'GET', headers: { 'X-WP-Nonce': nonce } });
        return res.json();
      },
      { route: CONTACTS, nonce }
    );
    const notes = await page.evaluate(
      async ({ route, nonce }) => {
        const res = await fetch(route, { method: 'GET', headers: { 'X-WP-Nonce': nonce } });
        return res.json();
      },
      { route: NOTES, nonce }
    );
    expect(contacts.some((r: any) => r.name === 'Alice')).toBe(true);
    expect(notes.some((r: any) => r.body === 'First note')).toBe(true);
    expect(contacts.some((r: any) => r.body)).toBe(false);
  });
});

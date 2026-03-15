import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../../utils/playwright-utils';

const BASE = 'http://localhost:8888';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await page.goto(`${BASE}/wp-admin/post.php?post=1483&action=edit`);
  await getEditorCanvas(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('DB Client', () => {
  test('bs.db is available', async () => {
    const has = await page.evaluate(() => typeof (window as any).bs?.db === 'function');
    expect(has).toBe(true);
  });

  test('bs.db returns CRUD methods', async () => {
    const methods = await page.evaluate(() => {
      const db = (window as any).bs.db('blockstudio/type-db-table', 'default');
      return {
        create: typeof db.create,
        list: typeof db.list,
        get: typeof db.get,
        update: typeof db.update,
        delete: typeof db.delete,
      };
    });
    expect(methods.create).toBe('function');
    expect(methods.list).toBe('function');
    expect(methods.get).toBe('function');
    expect(methods.update).toBe('function');
    expect(methods.delete).toBe('function');
  });
});

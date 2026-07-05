import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const BLOCK = 'blockstudio/type-db-storh';

let page: Page;

test.describe.configure({ mode: 'serial' });

const runStorhClientCycle = async (page: Page, label: string) => {
  return page.evaluate(
    async ({ block, label }) => {
      const db = (window as any).bs.db(block, 'default');
      const title = `Storh ${label} ${Date.now()}`;
      const created = await db.create({
        title,
        status: label,
        count: 7,
        score: 4.5,
        active: true,
      });
      const listed = await db.list({ status: label });
      const deleted = await db.delete(created.id);
      const afterDelete = await db.get(created.id);

      return { created, listed, deleted, afterDelete };
    },
    { block: BLOCK, label },
  );
};

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('DB Storh Storage', () => {
  test('bs.db works in the editor', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=1483&action=edit`);
    await getEditorCanvas(page);
    await expect
      .poll(async () => page.evaluate(() => typeof (window as any).bs?.db))
      .toBe('function');

    const result = await runStorhClientCycle(page, 'editor');

    expect(result.created.title).toContain('Storh editor');
    expect(result.created.id).toBeDefined();
    expect(result.listed.some((row: any) => row.id === result.created.id)).toBe(
      true,
    );
    expect(result.deleted.deleted).toBe(true);
    expect(result.afterDelete.code).toBe('blockstudio_db_not_found');
  });

  test('bs.db works on the frontend', async () => {
    await page.goto(`${BASE}/?p=1483`);
    await expect
      .poll(async () => page.evaluate(() => typeof (window as any).bs?.db))
      .toBe('function');

    const result = await runStorhClientCycle(page, 'frontend');

    expect(result.created.title).toContain('Storh frontend');
    expect(result.created.id).toBeDefined();
    expect(result.listed.some((row: any) => row.id === result.created.id)).toBe(
      true,
    );
    expect(result.deleted.deleted).toBe(true);
    expect(result.afterDelete.code).toBe('blockstudio_db_not_found');
  });
});
